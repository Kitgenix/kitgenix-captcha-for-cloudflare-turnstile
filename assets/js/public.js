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
        if (!this._timers.get(el)) this._timers.set(el, { idle: null, age: null, reveal: null });
        return this._timers.get(el);
      }
      // Fallback without WeakMap
      let bag = el.__kitgenixcaptchaforcloudflareturnstileTimers;
      if (!bag) { bag = { idle: null, age: null, reveal: null }; el.__kitgenixcaptchaforcloudflareturnstileTimers = bag; }
      return bag;
    },
    _clearTimers(el) {
      const t = this._getTimers(el);
      if (t.idle)  { clearTimeout(t.idle);  t.idle  = null; }
      if (t.age)   { clearTimeout(t.age);   t.age   = null; }
      if (t.reveal){ clearTimeout(t.reveal);t.reveal= null; }
    },
    // After a short delay, if still no token in Interaction Only, surface the UI and optionally disable submit
    _scheduleRevealIfNoToken(el) {
      try {
        const t = this._getTimers(el);
        if (t.reveal) clearTimeout(t.reveal);
        const ms = parseInt(this.config.reveal_delay_ms || 5000, 10);
        if (!ms || ms < 1000) return;
        t.reveal = setTimeout(() => {
          try {
            const $form = $(el).closest('form');
            const token = $form.find('input[name="cf-turnstile-response"]').val() || '';
            if (token) return; // already verified invisibly
            if (el.getAttribute('data-appearance') === 'interaction-only') {
              if (el.classList.contains('kt-ts-collapsed')) {
                el.classList.remove('kt-ts-collapsed');
              }
              if (this.config.disable_submit) {
                this.disableSubmit(el);
              }
              try { if (typeof turnstile !== 'undefined') turnstile.reset(el); } catch (e) {}
              // Rely on Cloudflare's own UI; do not show our inline prompt here.
              try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
            }
          } catch (e) { /* ignore */ }
        }, ms);
      } catch (e) { /* noop */ }
    },

    // Small helper to show/hide inline messages under a widget container
    _showInlineMsg(el, text, type) {
      if (!el) return;
      const cfg = (this.config && this.config.messages) || {};
      if (cfg.suppress === true) return; // allow suppression of inline notices via config
      this._clearInlineMsg(el);
      const msg = document.createElement('div');
      msg.className = 'kitgenix-captcha-for-cloudflare-turnstile-ts-inline-msg kitgenix-captcha-for-cloudflare-turnstile-type-' + (type || 'expired');
      msg.setAttribute('role', 'alert');
      msg.setAttribute('aria-live', 'polite');
      const messages = (this.config && this.config.messages) || {};
      const fallback = messages.prompt || 'Please complete the verification to continue.';
      msg.textContent = text || fallback;
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
      normal: 'normal',
      flexible: 'flexible' // Cloudflare will stretch to 100% width
    },

    // Ensure a hidden input exists for the closest form so we can store the token.
    ensureHiddenInput: function (el) {
      try {
        const $form = jQuery(el).closest('form');
        if (!$form.length) return;
        let $input = $form.find('input[name="cf-turnstile-response"]');
        if (!$input.length) {
          $input = jQuery('<input type="hidden" name="cf-turnstile-response" />').appendTo($form);
        }
      } catch (e) { /* ignore */ }
    },

    // Deduplicate extra containers in Elementor popups: keep only the first per form
    _dedupeElementorContainers(scope) {
      try {
        const root = scope || document;
        const forms = root.querySelectorAll('.elementor-popup-modal .elementor-form');
        forms.forEach(function(form){
          const list = form.querySelectorAll('.cf-turnstile');
          if (list.length > 1) {
            for (let i = 1; i < list.length; i++) {
              try { list[i].remove(); } catch (e) { /* ignore */ }
            }
          }
        });
      } catch (e) { /* noop */ }
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
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';

        // Always ensure hidden input exists for this form before rendering
        this.ensureHiddenInput(el);
        // If previously hidden after success, unhide now for a fresh render
        try { el.classList.remove('kt-ts-hide'); } catch (e) {}

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
            // Cancel any pending reveal timer since we have a token now
            try { const t = this._getTimers(el); if (t.reveal) { clearTimeout(t.reveal); t.reveal = null; } } catch (e) {}
          },
          'expired-callback': () => {
            this.resetWidget(el, 'expired');
          },
          'error-callback': () => {
            this.resetWidget(el, 'error');
            if (el.getAttribute('data-appearance') === 'interaction-only') {
              el.classList.remove('kt-ts-collapsed');
              if (this.config.disable_submit) this.disableSubmit(el);
            }
          },
          'unsupported-callback': () => {
            if (el.getAttribute('data-appearance') === 'interaction-only') {
              el.classList.remove('kt-ts-collapsed');
              if (this.config.disable_submit) this.disableSubmit(el);
            }
          },
          'timeout-callback': () => {
            if (el.getAttribute('data-appearance') === 'interaction-only') {
              el.classList.remove('kt-ts-collapsed');
              if (this.config.disable_submit) this.disableSubmit(el);
            }
          }
        };

        // Collapse visual gap for interaction-only until it actually renders/expands
        if (params.appearance === 'interaction-only') {
          el.classList.add('kt-ts-collapsed');
        }
        const renderWhenVisible = () => {
          const style = window.getComputedStyle(el);
          const visible = style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
          if (!visible) { setTimeout(renderWhenVisible, 120); return; }

          // Clean any stale children (defensive, in case of prior duplicate render attempts)
          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          // Mark on attribute too so CSS selectors may rely on it
          try { el.setAttribute('data-rendered', 'true'); } catch (e) {}
          delete el.dataset.kgxRendering;

          // If the UI becomes visible later (rare), uncollapse once it actually has height
          if (params.appearance === 'interaction-only') {
            try {
              const ro = new ResizeObserver(() => {
                if (el.classList.contains('kt-ts-collapsed') && el.offsetHeight > 0) {
                  el.classList.remove('kt-ts-collapsed');
                  ro.disconnect();
                }
              });
              ro.observe(el);
            } catch (e) {}
            // If no token after a short delay, surface the UI proactively
            this._scheduleRevealIfNoToken(el);
          }
        };
  renderWhenVisible();

        // If admin enabled "Disable submit until solved", enforce that up-front
        if (this.config.disable_submit && (params.appearance !== 'interaction-only')) {
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
      // Disable submit if:
      // - disable_submit is on AND
      //   - not interaction-only OR
      //   - interaction-only but UI is visible (container uncollapsed)
      var isInteractionOnly = (el.getAttribute('data-appearance') === 'interaction-only');
      var uiVisible = !el.classList.contains('kt-ts-collapsed');
      if (this.config.disable_submit && (!isInteractionOnly || (isInteractionOnly && uiVisible))) {
        this.disableSubmit(el);
      }

      const messages = (this.config && this.config.messages) || {};
      const msg = reason === 'error'
        ? (messages.error || 'Verification failed. Please try again.')
        : (messages.expired || this.config.replay_message || 'Verification expired. Please verify to continue.');
      this._showInlineMsg(el, msg, reason === 'error' ? 'error' : 'expired');

      // After reset, restart idle timer so it won’t sit forever if the user pauses
      this._scheduleIdleReset(el);
    },

    /**
     * GUIDE: Elementor — render inside Elementor forms & popups (AJAX and dynamic UIs).
     */
    renderElementorWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      // Ensure we don't have duplicate containers per form in popups
      try { this._dedupeElementorContainers(document); } catch (e) {}
      $('.elementor-form .cf-turnstile, .elementor-popup-modal .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';

        // Ensure hidden input exists even before first token
        this.ensureHiddenInput(el);
        // If previously hidden after success, unhide prior to rendering
        try { el.classList.remove('kt-ts-hide'); } catch (e) {}
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
            // Keep collapsed; watcher will uncollapse only if visible
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } },
          'unsupported-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } },
          'timeout-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } }
        };
        if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); }
        const renderWhenVisible = () => {
          const style = window.getComputedStyle(el);
          const visible = style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
          if (!visible) { setTimeout(renderWhenVisible, 120); return; }

          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          try { el.setAttribute('data-rendered', 'true'); } catch (e) {}
          if (this.config.disable_submit && (params.appearance !== 'interaction-only')) this.disableSubmit(el);
          this._scheduleIdleReset(el);
          delete el.dataset.kgxRendering;

          if (params.appearance === 'interaction-only') {
            try {
              const ro = new ResizeObserver(() => {
                if (el.classList.contains('kt-ts-collapsed') && el.offsetHeight > 0) {
                  el.classList.remove('kt-ts-collapsed');
                  ro.disconnect();
                }
              });
              ro.observe(el);
            } catch (e) {}
            KitgenixCaptchaForCloudflareTurnstile._scheduleRevealIfNoToken(el);
          }
        };
        renderWhenVisible();
      });
    },

    /**
     * GUIDE: Gravity Forms — render on `.gform_wrapper` markup and refresh after GF re-renders.
     */
    renderGravityFormsWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.gform_wrapper .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';
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
            // Keep collapsed; watcher handles visibility
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } },
          'unsupported-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } },
          'timeout-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } }
        };
        if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); }
        const renderWhenVisibleF = () => {
          const style = window.getComputedStyle(el);
          const visible = style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
          if (!visible) { setTimeout(renderWhenVisibleF, 120); return; }

          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          if (this.config.disable_submit && (params.appearance !== 'interaction-only')) this.disableSubmit(el);
          this._scheduleIdleReset(el);
          delete el.dataset.kgxRendering;

          if (params.appearance === 'interaction-only') {
            try {
              const ro = new ResizeObserver(() => {
                if (el.classList.contains('kt-ts-collapsed') && el.offsetHeight > 0) {
                  el.classList.remove('kt-ts-collapsed');
                  ro.disconnect();
                }
              });
              ro.observe(el);
            } catch (e) {}
            KitgenixCaptchaForCloudflareTurnstile._scheduleRevealIfNoToken(el);
          }
        };
        renderWhenVisibleF();
      });
    },

    /**
     * GUIDE: Formidable Forms — render near `.frm_form_fields`.
     */
    renderFormidableFormsWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.frm_form_fields .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';
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
            // Keep collapsed; watcher handles visibility
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); } },
          'unsupported-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); } },
          'timeout-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); } }
        };
        if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); }
        const renderWhenVisibleJ = () => {
          const style = window.getComputedStyle(el);
          const visible = style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
          if (!visible) { setTimeout(renderWhenVisibleJ, 120); return; }

          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          if (this.config.disable_submit && (params.appearance !== 'interaction-only')) this.disableSubmit(el);
          this._scheduleIdleReset(el);
          delete el.dataset.kgxRendering;

          if (params.appearance === 'interaction-only') {
            try {
              const ro = new ResizeObserver(() => {
                if (el.classList.contains('kt-ts-collapsed') && el.offsetHeight > 0) {
                  el.classList.remove('kt-ts-collapsed');
                  ro.disconnect();
                }
              });
              ro.observe(el);
            } catch (e) {}
            KitgenixCaptchaForCloudflareTurnstile._scheduleRevealIfNoToken(el);
          }
        };
        renderWhenVisibleJ();
      });
    },

    /**
     * GUIDE: Forminator — render inside `.forminator-custom-form`.
     */
    renderForminatorWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.forminator-custom-form .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';
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
            // Keep collapsed; watcher handles visibility
          },
          'expired-callback': () => { this.resetWidget(el, 'expired'); },
          'error-callback': () => { this.resetWidget(el, 'error'); if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } }
        };
  if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); this._ensureInteractionOnlyCollapseWatcher(el); }
          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
        el.dataset.rendered = 'true';
  if (this.config.disable_submit && (params.appearance !== 'interaction-only')) this.disableSubmit(el);
        this._scheduleIdleReset(el);
          delete el.dataset.kgxRendering;
          if (params.appearance === 'interaction-only') {
            KitgenixCaptchaForCloudflareTurnstile._scheduleRevealIfNoToken(el);
          }
      });
    },

    /**
     * GUIDE: Jetpack Forms — render inside `.contact-form`.
     */
    renderJetpackFormsWidgets: function () {
      if (typeof turnstile === 'undefined') return;
      $('.contact-form .cf-turnstile').each((_, el) => {
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';
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
          'error-callback': () => { this.resetWidget(el, 'error'); if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } },
          'unsupported-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } },
          'timeout-callback': () => { if (el.getAttribute('data-appearance') === 'interaction-only') { el.classList.remove('kt-ts-collapsed'); if (this.config.disable_submit) this.disableSubmit(el); } }
        };
        if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); }
        const renderWhenVisible = () => {
          const style = window.getComputedStyle(el);
          const visible = style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
          if (!visible) { setTimeout(renderWhenVisible, 120); return; }

          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          if (this.config.disable_submit && (params.appearance !== 'interaction-only')) this.disableSubmit(el);
          this._scheduleIdleReset(el);
          delete el.dataset.kgxRendering;

          if (params.appearance === 'interaction-only') {
            try {
              const ro = new ResizeObserver(() => {
                if (el.classList.contains('kt-ts-collapsed') && el.offsetHeight > 0) {
                  el.classList.remove('kt-ts-collapsed');
                  ro.disconnect();
                }
              });
              ro.observe(el);
            } catch (e) {}
            KitgenixCaptchaForCloudflareTurnstile._scheduleRevealIfNoToken(el);
          }
        };
        renderWhenVisible();
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
          var $c = $(this);
          // Do not inject a visible spinner for interaction-only placeholders; keep them collapsed/empty.
          var isInteractionOnly = ($c.attr('data-appearance') === 'interaction-only');
          if (isInteractionOnly) { return; }
          if (!$c.find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').length) {
            $c.html('<div class="kitgenix-captcha-for-cloudflare-turnstile-spinner" aria-label="Loading Turnstile..." role="status"></div>');
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
        } else {
          // No token yet. If Interaction Only, surface the UI now and cancel this submit.
          const $inter = $form.find('.cf-turnstile[data-appearance="interaction-only"]');
          if ($inter.length) {
            const el = $inter.get(0);
            if (el.classList.contains('kt-ts-collapsed')) {
              el.classList.remove('kt-ts-collapsed');
            }
            if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) {
              KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
            }
            try { if (typeof turnstile !== 'undefined') turnstile.reset(el); } catch (err) {}
            // Rely on Cloudflare's own UI; do not show our inline prompt here.
            try { if (jqXHR && jqXHR.abort) jqXHR.abort(); } catch (err) {}
            return false;
          }
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
      $(window).on('elementor/popup/show', function (_e, id) {
        const reRenderInPopup = () => {
          try {
            const popup = document.querySelector('.elementor-popup-modal[data-id="' + id + '"]') || document.querySelector('.elementor-popup-modal:last-of-type');
            // Dedupe any extra containers before we render
            try { KitgenixCaptchaForCloudflareTurnstile._dedupeElementorContainers(popup || document); } catch (e) {}
            const list = popup ? popup.querySelectorAll('.cf-turnstile') : document.querySelectorAll('.elementor-popup-modal .cf-turnstile');
            list.forEach(function (el) {
              const already = !!el.dataset.rendered;
              const iframe = el.querySelector('iframe');
              const visible = iframe && iframe.offsetHeight > 0 && iframe.offsetWidth > 0;
              // Always clear a stale "rendering" flag so the renderer can retry
              if (el.dataset.kgxRendering === '1') { try { delete el.dataset.kgxRendering; } catch (e) {} }
              if (!already) {
                // Fresh pass, ensure hidden input exists pre-render
                try { KitgenixCaptchaForCloudflareTurnstile.ensureHiddenInput(el); } catch (e) {}
              } else if (!visible) {
                // Was rendered while hidden; force a reset + allow re-render
                try { if (typeof turnstile !== 'undefined') turnstile.reset(el); } catch (e) {}
                try { el.removeAttribute('data-rendered'); } catch (e) {}
                try { delete el.dataset.rendered; } catch (e) {}
              }
            });
            KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets();
          } catch (e) { if (window.console) console.error(e); }
        };
        requestAnimationFrame(() => setTimeout(reRenderInPopup, 60));
      });
      $(document).on('submit', '.elementor-form', function () {
        $(this).find('.cf-turnstile').each(function () {
          if (typeof turnstile !== 'undefined' && this.dataset.rendered) {
            turnstile.reset(this);
          }
        });
      });
    },
    
    // Global form guard: if Interaction Only has no token yet, surface UI immediately and block submit once.
    attachGlobalSubmitGuard: function () {
      if (this._globalSubmitGuardAttached) return;
      this._globalSubmitGuardAttached = true;
      $(document).on('submit', 'form', function (e) {
        try {
          const $form = $(this);
          if (!$form.find('.cf-turnstile').length) return;
          const token = $form.find('input[name="cf-turnstile-response"]').val() || '';
          if (token) return;
          const $inter = $form.find('.cf-turnstile[data-appearance="interaction-only"]');
          if (!$inter.length) return;
          const el = $inter.get(0);
          if (el.classList.contains('kt-ts-collapsed')) {
            el.classList.remove('kt-ts-collapsed');
          }
          if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) {
            KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
          }
          try { if (typeof turnstile !== 'undefined') turnstile.reset(el); } catch (err) {}
          // Rely on Cloudflare's own UI; do not show our inline prompt here.
          try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (err) {}
          e.preventDefault();
          e.stopImmediatePropagation();
          return false;
        } catch (err) { /* ignore guard errors */ }
      });
    },

    /**
     * GUIDE: Fluent Forms — render and reset on their AJAX lifecycle events.
     */
    fluentFormsIntegration: function () {
      function renderFluentTurnstile() {
        $('.fluentform-wrap .cf-turnstile, .fluentform .cf-turnstile').each(function () {
          const el = this;
          if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
          $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
          el.dataset.kgxRendering = '1';
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
            'error-callback': function () { 
              KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'error'); 
              if (el.getAttribute('data-appearance') === 'interaction-only') { 
                el.classList.remove('kt-ts-collapsed'); 
                if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
              }
            },
            'unsupported-callback': function () { 
              if (el.getAttribute('data-appearance') === 'interaction-only') { 
                el.classList.remove('kt-ts-collapsed'); 
                if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
              }
            },
            'timeout-callback': function () { 
              if (el.getAttribute('data-appearance') === 'interaction-only') { 
                el.classList.remove('kt-ts-collapsed'); 
                if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
              }
            }
          };
          if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); }
          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
          el.dataset.rendered = 'true';
          if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit && (params.appearance !== 'interaction-only')) {
            KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
          }
          KitgenixCaptchaForCloudflareTurnstile._scheduleIdleReset(el);
          delete el.dataset.kgxRendering;
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
        if (el.dataset.rendered || el.dataset.kgxRendering === '1') return;
        $(el).find('.kitgenix-captcha-for-cloudflare-turnstile-spinner').remove();
        el.dataset.kgxRendering = '1';
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
              // Keep collapsed unless UI becomes visible via unsupported/timeout or actual size
          },
          'expired-callback': function () { KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'expired'); },
          'error-callback': function () { 
            KitgenixCaptchaForCloudflareTurnstile.resetWidget(el, 'error'); 
            if (el.getAttribute('data-appearance') === 'interaction-only') { 
              el.classList.remove('kt-ts-collapsed'); 
              if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
            }
          },
          'unsupported-callback': function () { 
            if (el.getAttribute('data-appearance') === 'interaction-only') { 
              el.classList.remove('kt-ts-collapsed'); 
              if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
            }
          },
          'timeout-callback': function () { 
            if (el.getAttribute('data-appearance') === 'interaction-only') { 
              el.classList.remove('kt-ts-collapsed'); 
              if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
            }
          }
        };
          if (params.appearance === 'interaction-only') { el.classList.add('kt-ts-collapsed'); }
          // Render without visibility guard here (Kadence blocks are typically visible), but keep logic consistent if needed
          try { el.innerHTML = ''; } catch (e) {}
          turnstile.render(el, params);
        el.dataset.rendered = 'true';
        if (KitgenixCaptchaForCloudflareTurnstile.config.disable_submit && (params.appearance !== 'interaction-only')) KitgenixCaptchaForCloudflareTurnstile.disableSubmit(el);
        KitgenixCaptchaForCloudflareTurnstile._scheduleIdleReset(el);
        delete el.dataset.kgxRendering;
        if (params.appearance === 'interaction-only') {
          KitgenixCaptchaForCloudflareTurnstile._scheduleRevealIfNoToken(el);
        }
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
    KitgenixCaptchaForCloudflareTurnstile.attachGlobalSubmitGuard();
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

  // Collapse gaps after successful Elementor AJAX submit to avoid empty space
  // Detection via generic ajaxSuccess to avoid brittle event name coupling
  $(document).ajaxSuccess(function (_e, _xhr, settings) {
    try {
      var data = settings && settings.data ? String(settings.data) : '';
      if (data.indexOf('action=elementor_pro_forms_send_form') !== -1) {
        // Re-collapse interaction-only containers in all Elementor forms
        jQuery('.elementor-form .cf-turnstile[data-appearance="interaction-only"]').each(function () {
          this.classList.add('kt-ts-collapsed');
        });
      }
    } catch (err) { /* ignore */ }
  });

  // Additional Elementor hooks for popups/dynamic forms
  $(window).on('elementor/frontend/init', function () {
    setTimeout(() => { try { KitgenixCaptchaForCloudflareTurnstile.renderElementorWidgets(); } catch (e) { if (window.console) console.error(e); } }, 100);
  });

  // Render when external scripts announce new containers
  try {
    document.addEventListener('kgx:turnstile-containers-added', function () {
      try { KitgenixCaptchaForCloudflareTurnstile.init(); } catch (e) { if (window.console) console.error(e); }
    });
  } catch (e) {}
  // Note: popup/form events are handled inside elementorIntegration() to avoid duplicate bindings
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
