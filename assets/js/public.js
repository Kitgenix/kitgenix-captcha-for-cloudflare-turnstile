/**
 * Kitgenix CAPTCHA for Cloudflare Turnstile — Public script
 *
 * WHAT THIS DOES (high level)
 * ---------------------------------------------------------
 * - Renders Cloudflare Turnstile inside any container with `.cf-turnstile`.
 * - Writes the token to a hidden input: <input type="hidden" name="cf-turnstile-response" />
 * - Optionally disables the submit button until Turnstile succeeds.
 * - Auto-resets/refreshes the widget after a configurable freshness window.
 * - Shows a small inline message when a token expires or an error occurs.
 * - Hooks into popular form plugins to render, reset on errors, and re-render after AJAX.
 *
 * WHERE IT RENDERS (GUIDES)
 * ---------------------------------------------------------
 * - WordPress core forms (login/register/lost password/comment) — via `.cf-turnstile` containers output by PHP.
 * - Elementor forms/popups — see `renderElementorWidgets()` and elementor event hooks below.
 * - Gravity Forms — `renderGravityFormsWidgets()` + `gform_post_render`.
 * - Formidable Forms — `renderFormidableFormsWidgets()` + frm events.
 * - Forminator — `renderForminatorWidgets()` + forminator events.
 * - Jetpack Forms — `renderJetpackFormsWidgets()` after their form HTML loads.
 * - Kadence Blocks Forms — `renderKadenceFormsWidgets()` when Kadence is present.
 *
 * IMPORTANT INTEGRATION HOOK
 * ---------------------------------------------------------
 * This module is exposed on `window.KitgenixCaptchaForCloudflareTurnstile`.
 * The WooCommerce Blocks fetch bridge (added via PHP) optionally calls
 *   window.KitgenixCaptchaForCloudflareTurnstile.getLastToken()
 * to attach the token to Store API requests.
 */

(function ($) {
  const KitgenixCaptchaForCloudflareTurnstile = {
    widgets: [],
    config: window.KitgenixCaptchaForCloudflareTurnstileConfig || {},
    observer: null,
    retryLimit: 20,

    // Remember the most recently set token so other scripts (e.g., Woo Blocks bridge)
    // can access it without DOM lookups.
    _lastToken: '',
    getLastToken() {
      return this._lastToken || '';
    },

    // Timers per widget (idle freshness + token age)
    _timers: typeof WeakMap !== 'undefined' ? new WeakMap() : null,
    _getTimers(el) {
      if (this._timers) {
        if (!this._timers.get(el)) this._timers.set(el, { idle: null, age: null });
        return this._timers.get(el);
      }
      // Fallback without WeakMap
      let bag = el.__kitgenixcaptchaforcloudflareturnstileTimers;
      if (!bag) { bag = { idle: null, age: null }; el.__kitgenixcaptchaforcloudflareturnstileTimers = bag; }
      return bag;
    },
    _clearTimers(el) {
      const t = this._getTimers(el);
      if (t.idle)  { clearTimeout(t.idle);  t.idle  = null; }
      if (t.age)   { clearTimeout(t.age);   t.age   = null; }
    },

    // Small helper to show/hide inline messages under a widget container
    _showInlineMsg(el, text, type) {
      if (!el) return;
      this._clearInlineMsg(el);
      const msg = document.createElement('div');
      msg.className = 'kitgenix-captcha-for-cloudflare-turnstile-ts-inline-msg kitgenix-captcha-for-cloudflare-turnstile-type-' + (type || 'expired');
      msg.setAttribute('role', 'alert');
      msg.setAttribute('aria-live', 'polite');
      msg.textContent = text || 'Expired — please verify again.';
      if (el.parentNode) {
        el.parentNode.insertBefore(msg, el.nextSibling);
      }
    },
    _clearInlineMsg(el) {
      if (!el || !el.parentNode) return;
      const next = el.nextSibling;
      if (next && next.classList && next.classList.contains('kitgenix-captcha-for-cloudflare-turnstile-ts-inline-msg')) {
        try { next.remove(); } catch (e) { if (window.console) console.warn(e); }
      }
    },

    // Freshness: if user never solves, reset after X ms
    _scheduleIdleReset(el) {
      const t = this._getTimers(el);
      if (t.idle) clearTimeout(t.idle);
      const ms = parseInt(this.config.freshness_ms || 150000, 10);
      if (!ms || ms < 30000) return; // min 30s
      t.idle = setTimeout(() => {
        const $form = $(el).closest('form');
        const token = $form.find('input[name="cf-turnstile-response"]').val() || '';
        if (!token) {
          this.resetWidget(el, 'expired');
        }
      }, ms);
    },

    // Freshness: if token was obtained, reset after X ms
    _scheduleTokenAgeReset(el) {
      const t = this._getTimers(el);
      if (t.age) clearTimeout(t.age);
      const ms = parseInt(this.config.freshness_ms || 150000, 10);
      if (!ms || ms < 30000) return;
      t.age = setTimeout(() => {
        this.resetWidget(el, 'expired');
      }, ms);
    },

    sizeMap: {
      small: 'compact',
      medium: 'normal',
      large: 'normal',
      standard: 'normal',
      normal: 'normal'
    },

    /**
     * GUIDE: Render all unrendered Turnstile containers across the page.
     * - Core & most plugin forms output a `.cf-turnstile` placeholder in PHP.
     * - We explicitly call `turnstile.render()` here and wire callbacks.
     */
    renderWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      var found = $('.cf-turnstile').length;
      if (this.config.debug && window.console) {
        if (found) {
          console.log('[KitgenixTurnstile] Found ' + found + ' .cf-turnstile widgets on page');
        } else {
          console.log('[KitgenixTurnstile] No .cf-turnstile widgets found on page');
        }
      }

      $('.cf-turnstile').each((_, el) => {
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();

        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || this.config.theme || 'auto',
          size: this.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || this.config.appearance || 'always',
          callback: (token) => {
            this._clearInlineMsg(el);
            this.setResponseInput(el, token);
            this.enableSubmit(el);
            this._scheduleTokenAgeReset(el); // token age window
          },
          'expired-callback': () => {
            this.setResponseInput(el, '');
            this.disableSubmit(el);
            this.resetWidget(el, 'expired');
          },
          'error-callback': () => {
            this.setResponseInput(el, '');
            this.disableSubmit(el);
            this.resetWidget(el, 'error');
          }
        };

        turnstile.render(el, params);
        el.dataset.rendered = 'true';

        // If admin enabled "Disable submit until solved", enforce that up-front
        if (this.config.disable_submit) {
          this.disableSubmit(el);
        }

        // Start idle freshness timer and bump it on user activity
        this._scheduleIdleReset(el);
        const bump = () => this._scheduleIdleReset(el);
        el.addEventListener('mousemove', bump);
        el.addEventListener('keydown', bump);
        el.addEventListener('touchstart', bump, { passive: true });
      });

      // GUIDE: Contact Form 7 — ensure submit is re-enabled after CF7 finalizes
      if (typeof wpcf7 !== 'undefined') {
        document.querySelectorAll('.wpcf7 form').forEach(function(form) {
          form.addEventListener('wpcf7submit', function() {
            form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function(btn) {
              btn.disabled = false;
              btn.classList.remove('kitgenix-captcha-for-cloudflare-turnstile-disabled');
            });
          });
        });
      }
    },

    // Reset a widget, clear token, disable submit, and show a small inline message.
    resetWidget(el, reason) {
      try {
        if (typeof turnstile !== 'undefined' && el.dataset.rendered) {
          turnstile.reset(el);
        }
      } catch (e) { if (window.console) console.error(e); }
      this._clearTimers(el);
      this.setResponseInput(el, '');
      this.disableSubmit(el);

      const msg = reason === 'error'
        ? 'Verification error — please verify again.'
        : (this.config.replay_message || 'Expired — please verify again.');
      this._showInlineMsg(el, msg, reason === 'error' ? 'error' : 'expired');

      // After reset, restart idle timer so it won’t sit forever if the user pauses
      this._scheduleIdleReset(el);
    },

    /**
     * GUIDE: Elementor — render inside Elementor forms & popups (AJAX and dynamic UIs).
     */
    renderElementorWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.elementor-form .cf-turnstile, .elementor-popup-modal .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || this.config.theme || 'auto',
          size: this.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || this.config.appearance || 'always',
          callback: (token) => {
            this._clearInlineMsg(el);
            this.setResponseInput(el, token);
            this.enableSubmit(el);
            this._scheduleTokenAgeReset(el);
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); }
        };
        turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (this.config.disable_submit) this.disableSubmit(el);
        this._scheduleIdleReset(el);
      });
    },

    /**
     * GUIDE: Gravity Forms — render on `.gform_wrapper` markup and refresh after GF re-renders.
     */
    renderGravityFormsWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.gform_wrapper .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || this.config.theme || 'auto',
          size: this.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || this.config.appearance || 'always',
          callback: (token) => {
            this._clearInlineMsg(el);
            this.setResponseInput(el, token);
            this.enableSubmit(el);
            this._scheduleTokenAgeReset(el);
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); }
        };
        turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (this.config.disable_submit) this.disableSubmit(el);
        this._scheduleIdleReset(el);
      });
    },

    /**
     * GUIDE: Formidable Forms — render near `.frm_form_fields`.
     */
    renderFormidableFormsWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.frm_form_fields .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || this.config.theme || 'auto',
          size: this.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || this.config.appearance || 'always',
          callback: (token) => {
            this._clearInlineMsg(el);
            this.setResponseInput(el, token);
            this.enableSubmit(el);
            this._scheduleTokenAgeReset(el);
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); }
        };
        turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (this.config.disable_submit) this.disableSubmit(el);
        this._scheduleIdleReset(el);
      });
    },

    /**
     * GUIDE: Forminator — render inside `.forminator-custom-form`.
     */
    renderForminatorWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.forminator-custom-form .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || this.config.theme || 'auto',
          size: this.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || this.config.appearance || 'always',
          callback: (token) => {
            this._clearInlineMsg(el);
            this.setResponseInput(el, token);
            this.enableSubmit(el);
            this._scheduleTokenAgeReset(el);
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); }
        };
        turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (this.config.disable_submit) this.disableSubmit(el);
        this._scheduleIdleReset(el);
      });
    },

    /**
     * GUIDE: Jetpack Forms — render inside `.contact-form`.
     */
    renderJetpackFormsWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.contact-form .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || this.config.theme || 'auto',
          size: this.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || this.config.appearance || 'always',
          callback: (token) => {
            this._clearInlineMsg(el);
            this.setResponseInput(el, token);
            this.enableSubmit(el);
            this._scheduleTokenAgeReset(el);
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); }
        };
        turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (this.config.disable_submit) this.disableSubmit(el);
        this._scheduleIdleReset(el);
      });
    },

    // Kadence — defined only when Kadence Forms likely present
    renderKadenceFormsWidgets: null,

    /**
     * GUIDE: Boot the system
     * - If Turnstile API isn't ready yet, show a spinner and retry a few times.
     * - Once ready, render all widgets on the page.
     */
    init: function retry(attempt = 0) {
      if (typeof turnstile === 'undefined') {
        if (attempt >= this.retryLimit) {
          if (window.console && window.console.error) {
            console.error('Cloudflare Turnstile failed to load after ' + this.retryLimit + ' attempts.');
          }
          return;
        }
        $('.cf-turnstile').each(function () {
          if (!$(this).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').length) {
            $(this).html('<div class="kitgenix-captcha-for-cloudflare-turnstile-spinner" aria-label="Loading Turnstile..." role="status"></div>');
          }
        });
        return setTimeout(() => {
          try { KitgenixCaptchaForCloudflareTurnstile.init(attempt + 1); } catch (e) { if (window.console) console.error(e); }
        }, 300);
      }
      this.renderWidgets();
    },

    // Keep the hidden input up to date and remember last token globally.
    setResponseInput: function (el, token) {
      const $form = $(el).closest('form');
      let $input = $form.find('input[name="cf-turnstile-response"]');
      if (!$input.length) {
        $input = $('<input type="hidden" name="cf-turnstile-response" />').appendTo($form);
      }
      token = token || '';
      $input.val(token);
      if (token) {
        this._lastToken = token;
      }
    },

    disableSubmit: function (el) {
      const $form = $(el).closest('form');
      $form.find('button[type=submit], input[type=submit]').prop('disabled', true).addClass('kitgenix-captcha-for-cloudflare-turnstile-disabled');
    },

    enableSubmit: function (el) {
      const $form = $(el).closest('form');
      $form.find('button[type=submit], input[type=submit]').prop('disabled', false).removeClass('kitgenix-captcha-for-cloudflare-turnstile-disabled');
    },

    /**
     * GUIDE: Watch the DOM for newly added `.cf-turnstile` containers (AJAX UIs, popups, etc.)
     * and render them shortly after they're inserted.
     */
    observeDOM: function () {
      if (this.observer) return;
      let debounceTimer = null;
      this.observer = new MutationObserver((mutations) => {
        let needsInit = false;
        mutations.forEach(function (mutation) {
          mutation.addedNodes && $(mutation.addedNodes).find('.cf-turnstile').length && (needsInit = true);
        });
        if (needsInit) {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            try { KitgenixCaptchaForCloudflareTurnstile.init(); } catch (e) { if (window.console) console.error(e); }
          }, 100);
        }
      });
      this.observer.observe(document.body, { childList: true, subtree: true });
    },

    /**
     * GUIDE: Elementor-specific integrations (attach token to AJAX body, reset on errors, support dynamic popups).
     */
    elementorIntegration: function () {
      $(document).on('elementor-pro/forms/ajax:beforeSend', (e, jqXHR, data) => {
        const $form = $(e.target).closest('form');
        const token = $form.find('input[name="cf-turnstile-response"]').val() || '';
        if (token) {
          data.data['cf-turnstile-response'] = token;
        }
      });

      // On common error events, reset with inline message
      $(document).on('elementor-pro/forms/submit/error elementor-pro/forms/ajax:error', function (e) {
        const $form = $(e.target).closest('form');
        $form.find('.cf-turnstile').each(function () {
          KitgenixCaptchaForCloudflareTurnstile.resetWidget(this, 'error');
        });
      });

      $(document).on('elementor-pro/forms/new elementor/forms/new', function () {
        setTimeout(() => KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(), 100);
      });
      $(window).on('elementor/popup/show', function () {
        setTimeout(() => KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(), 100);
      });
      $(document).on('submit', '.elementor-form', function () {
        $(this).find('.cf-turnstile').each(function () {
          if (typeof turnstile !== 'undefined' && this.dataset.rendered) {
            turnstile.reset(this);
          }
        });
      });
    },

    /**
     * GUIDE: Fluent Forms — render and reset on their AJAX lifecycle events.
     */
    fluentFormsIntegration: function () {
      function renderFluentTurnstile() {
        $('.fluentform-wrap .cf-turnstile, .fluentform .cf-turnstile').each(function () {
          const el = this;
          if (el.dataset.rendered) return;
          $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
          const params = {
            sitekey: el.getAttribute('data-sitekey'),
            theme: el.getAttribute('data-theme') || KitgenixCaptchaForCloudflareTurnstile.config.theme || 'auto',
            size: KitgenixCaptchaForCloudflareTurnstile.sizeMap[el.getAttribute('data-size')] || 'normal',
            appearance: el.getAttribute('data-appearance') || KitgenixCaptchaForCloudflareTurnstile.config.appearance || 'always',
            callback: function (token) { 
              KitgenixCaptchaForCloudflareTurnstile._clearInlineMsg(el);
              KitgenixCaptchaForCloudflareTurnstile.setResponseInput(el, token); 
              KitgenixCaptchaForCloudflareTurnstile.enableSubmit(el);
              KitgenixCaptchaForCloudflareTurnstile._scheduleTokenAgeReset(el);
            },
            'expired-callback': function () { KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'expired'); },
            'error-callback': function () { KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'error'); }
          };
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
          KitgenixCaptchaForCloudflareTurnstile._scheduleIdleReset(el);
        });
      }

      $(document).ready(renderFluentTurnstile);
      $(document).on('fluentform_rendering_field_after fluentform_init_form fluentform_rendering_form_fields fluentform_after_form_render', function () {
        setTimeout(renderFluentTurnstile, 100);
      });

      // Reset on known Fluent failure
      $(document).on('fluentform_submission_failed', function (e) {
        const $form = $(e.target).closest('form');
        $form.find('.cf-turnstile').each(function () {
          KitgenixCaptchaForCloudflareTurnstile.resetWidget(this, 'error');
        });
      });

      document.querySelectorAll('.fluentform-wrap').forEach(function (wrap) {
        new MutationObserver(() => {
          setTimeout(renderFluentTurnstile, 100);
        }).observe(wrap, { childList: true, subtree: true });
      });
    }
  };

  // Kadence — keep parity, add freshness
  var kadenceEnabled = typeof Kadence_Blocks_Form !== 'undefined' || (window.KitgenixCaptchaForCloudflareTurnstileConfig && window.KitgenixCaptchaForCloudflareTurnstileConfig.enable_kadenceforms);
  if (kadenceEnabled) {
    KitgenixCaptchaForCloudflareTurnstile.renderKadenceFormsWidgets = function () {
      if (typeof turnstile === 'undefined') return;
      $('.kb-form .cf-turnstile').each(function () {
        const el = this;
        if (el.dataset.rendered) return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        const params = {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || KitgenixCaptchaForCloudflareTurnstile.config.theme || 'auto',
          size: KitgenixCaptchaForCloudflareTurnstile.sizeMap[el.getAttribute('data-size')] || 'normal',
          appearance: el.getAttribute('data-appearance') || KitgenixCaptchaForCloudflareTurnstile.config.appearance || 'always',
          callback: function (token) { 
            KitgenixCaptchaForCloudflareTurnstile._clearInlineMsg(el);
            KitgenixCaptchaForCloudflareTurnstile.setResponseInput(el, token); 
            KitgenixCaptchaForCloudflareTurnstile.enableSubmit(el);
            KitgenixCaptchaForCloudflareTurnstile._scheduleTokenAgeReset(el);
          },
          'expired-callback': function () { KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'expired'); },
          'error-callback': function () { KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'error'); }
        };
        turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
        KitgenixCaptchaForCloudflareTurnstile._scheduleIdleReset(el);
      });
    };
  }

  // Global: reset on common AJAX submit errors for popular plugins
  // GUIDE: CF7
  document.addEventListener('wpcf7invalid', handleCF7Error, true);
  document.addEventListener('wpcf7spam', handleCF7Error, true);
  document.addEventListener('wpcf7mailfailed', handleCF7Error, true);
  function handleCF7Error(e) {
    const form = e.target && e.target.closest ? e.target.closest('form') : null;
    if (!form) return;
    $(form).find('.cf-turnstile').each(function () {
      KitgenixCaptchaForCloudflareTurnstile.resetWidget(this, 'error');
    });
  }

  // GUIDE: WPForms ajax error (if using AJAX)
  $(document).on('wpformsAjaxSubmitError', function(e, details){
    const $form = $(details && details.form || e.target).closest('form');
    $form.find('.cf-turnstile').each(function () {
      KitgenixCaptchaForCloudflareTurnstile.resetWidget(this, 'error');
    });
  });

  // GUIDE: Gravity Forms — after re-render (which happens on validation errors), reset & re-render
  $(document).on('gform_post_render', function () {
    setTimeout(() => { 
      try { 
        $('.gform_wrapper form').each(function(){
          $(this).find('.cf-turnstile').each(function(){
            KitgenixCaptchaForCloudflareTurnstile.resetWidget(this, 'expired');
          });
        });
        KitgenixCaptchaForCloudflareTurnstile.renderGravityFormsWidgets(); 
      } catch (e) { if (window.console) console.error(e); } 
    }, 100);
  });

  // GUIDE: Forminator — reset on error hooks
  $(document).on('forminator:form:submit:error forminator:form:submit:failed', function(e){
    const $form = $(e.target).closest('form');
    $form.find('.cf-turnstile').each(function () {
      KitgenixCaptchaForCloudflareTurnstile.resetWidget(this, 'error');
    });
  });

  // Boot
  $(document).ready(function () {
    KitgenixCaptchaForCloudflareTurnstile.init();
    KitgenixCaptchaForCloudflareTurnstile.observeDOM();
    KitgenixCaptchaForCloudflareTurnstile.elementorIntegration();
    KitgenixCaptchaForCloudflareTurnstile.fluentFormsIntegration();
    KitgenixCaptchaForCloudflareTurnstile.renderGravityFormsWidgets();
    KitgenixCaptchaForCloudflareTurnstile.renderFormidableFormsWidgets();
    KitgenixCaptchaForCloudflareTurnstile.renderForminatorWidgets();
    KitgenixCaptchaForCloudflareTurnstile.renderJetpackFormsWidgets();
    if (KitgenixCaptchaForCloudflareTurnstile.renderKadenceFormsWidgets) KitgenixCaptchaForCloudflareTurnstile.renderKadenceFormsWidgets();

    // GUIDE: WordPress core forms (login/register/lostpassword/comment) — if a widget exists, ensure render pass
    ['login', 'register', 'lostpassword', 'comment'].forEach(function (context) {
      const el = document.getElementById('cf-turnstile-' + context);
      if (el && typeof turnstile !== 'undefined') {
        try { KitgenixCaptchaForCloudflareTurnstile.renderWidgets(); } catch (e) { if (window.console) console.error(e); }
      }
    });

    // Elementor can render slightly after ready; nudge once more
    setTimeout(() => {
      try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); }
    }, 100);
  });

  // Additional Elementor hooks for popups/dynamic forms
  $(window).on('elementor/frontend/init', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
  });
  $(window).on('elementor/popup/show', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
  });
  $(document).on('elementor/forms/new', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
  });
  $(document).on('elementor-pro/forms/new elementor/forms/new', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); } }, 200);
  });
  $(document).on('elementor/popup/show', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); } }, 200);
  });
  $(document).on('gform_post_render', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderGravityFormsWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
  });
  $(document).on('frmFormComplete frmAfterFormRendered', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderFormidableFormsWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
  });
  $(document).on('forminator:form:rendered forminator:form:ajax:rendered', function() {
    setTimeout(function() {
      if (typeof KitgenixCaptchaForCloudflareTurnstile !== 'undefined' && KitgenixCaptchaForCloudflareTurnstile.renderForminatorWidgets) {
        KitgenixCaptchaForCloudflareTurnstile.renderForminatorWidgets();
      }
    }, 100);
  });
  $(document).on('kb-form-rendered', function () {
    if (KitgenixCaptchaForCloudflareTurnstile.renderKadenceFormsWidgets) {
      setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderKadenceFormsWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
    }
  });

  // EXPOSE THE MODULE GLOBALLY (GUIDE: used by Woo Blocks fetch bridge)
  window.KitgenixCaptchaForCloudflareTurnstile = KitgenixCaptchaForCloudflareTurnstile;

})(jQuery);
