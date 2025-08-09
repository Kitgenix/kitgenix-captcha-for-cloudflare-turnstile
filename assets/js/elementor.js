// assets/js/elementor.js
// This script ensures Turnstile renders on Elementor forms and popups after they are loaded.
(function($) {
  var turnstileLoaded = false;
  var renderQueued = false;

  function renderTurnstileWidgets(context, attempt = 0) {
    var $widgets = $(context || document).find('.cf-turnstile');
    if (typeof turnstile === 'undefined') {
      if (attempt < 40) {
        setTimeout(function() {
          try { renderTurnstileWidgets(context, attempt + 1); } catch (e) { if (window.console) console.error(e); }
        }, 150);
      } else {
        if (window.console && window.console.error) {
          console.error('Cloudflare Turnstile failed to load after 40 attempts.');
        }
      }
      return;
    }
    $widgets.each(function() {
      var el = this;
      if (el.dataset.rendered) return;
      try {
        turnstile.render(el, {
          sitekey: el.getAttribute('data-sitekey'),
          theme: el.getAttribute('data-theme') || 'auto',
          size: el.getAttribute('data-size') || 'normal',
          appearance: el.getAttribute('data-appearance') || 'always',
          callback: function(token) {
            var $form = $(el).closest('form');
            var $input = $form.find('input[name="cf-turnstile-response"]');
            if (!$input.length) {
              $input = $('<input type="hidden" name="cf-turnstile-response" />').appendTo($form);
            }
            $input.val(token || '');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', false).removeClass('kitgenix-captcha-for-cloudflare-turnstile-disabled');
          },
          'expired-callback': function() {
            var $form = $(el).closest('form');
            $form.find('input[name="cf-turnstile-response"]').val('');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', true).addClass('kitgenix-captcha-for-cloudflare-turnstile-disabled');
          },
          'error-callback': function() {
            var $form = $(el).closest('form');
            $form.find('input[name="cf-turnstile-response"]').val('');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', true).addClass('kitgenix-captcha-for-cloudflare-turnstile-disabled');
          }
        });
        el.dataset.rendered = 'true';
      } catch (e) { if (window.console) console.error(e); }
    });
  }

  // Fallback: Inject Turnstile widget before the submit button in Elementor forms if missing
  function fallbackInjectTurnstileWidget() {
    var settings = window.KitgenixCaptchaForCloudflareTurnstileConfig || {};
    var siteKey = settings.site_key || '';
    var theme = settings.theme || 'auto';
    var size = settings.size || 'normal';
    var appearance = settings.appearance || 'always';
    if (!siteKey) return;
    document.querySelectorAll('.elementor-form-fields-wrapper').forEach(function(wrapper) {
      var submitGroup = wrapper.querySelector('.elementor-field-type-submit');
      if (!submitGroup) return;
      if (!wrapper.querySelector('.cf-turnstile')) {
        var container = document.createElement('div');
        container.className = 'cf-turnstile';
        container.setAttribute('data-sitekey', siteKey);
        container.setAttribute('data-theme', theme);
        container.setAttribute('data-size', size);
        container.setAttribute('data-appearance', appearance);
        submitGroup.parentNode.insertBefore(container, submitGroup);
      }
    });
  }

  // Helper: Render widgets when Turnstile API is loaded
  function onTurnstileReady() {
    turnstileLoaded = true;
    renderTurnstileWidgets();
    if (renderQueued) {
      renderTurnstileWidgets();
      renderQueued = false;
    }
  }

  // Listen for Turnstile API onload (if present)
  if (typeof window.turnstile === 'undefined') {
    window.onTurnstileLoad = onTurnstileReady;
  } else {
    onTurnstileReady();
  }

  // Render on page load (if API is ready)
  $(document).ready(function() {
    fallbackInjectTurnstileWidget();
    if (turnstileLoaded || typeof turnstile !== 'undefined') {
      renderTurnstileWidgets();
    } else {
      renderQueued = true;
    }
  });

  // Elementor frontend events
  $(window).on('elementor/frontend/init', function() {
    fallbackInjectTurnstileWidget();
    if (turnstileLoaded || typeof turnstile !== 'undefined') {
      renderTurnstileWidgets();
    } else {
      renderQueued = true;
    }
  });
  $(window).on('elementor/frontend/dom_ready', function() {
    fallbackInjectTurnstileWidget();
    if (turnstileLoaded || typeof turnstile !== 'undefined') {
      renderTurnstileWidgets();
    } else {
      renderQueued = true;
    }
  });

  // Elementor popup show
  $(document).on('elementor/popup/show', function(e) {
    fallbackInjectTurnstileWidget();
    if (turnstileLoaded || typeof turnstile !== 'undefined') {
      renderTurnstileWidgets($('.elementor-popup-modal'));
    } else {
      renderQueued = true;
    }
  });

  // Elementor form render/new
  $(document).on('elementor-pro/forms/new elementor/forms/new', function(e) {
    setTimeout(function() {
      fallbackInjectTurnstileWidget();
      if (turnstileLoaded || typeof turnstile !== 'undefined') {
        renderTurnstileWidgets();
      } else {
        renderQueued = true;
      }
    }, 100);
  });

  // Reset Turnstile on form submit
  $(document).on('submit', '.elementor-form', function() {
    $(this).find('.cf-turnstile').each(function() {
      if (typeof turnstile !== 'undefined' && this.dataset.rendered) {
        turnstile.reset(this);
      }
    });
  });

  // Fallback: MutationObserver to catch any missed .cf-turnstile widgets
  var debounceTimer = null;
  var observer = new MutationObserver(function(mutations) {
    let needsRender = false;
    mutations.forEach(function(mutation) {
      mutation.addedNodes.forEach(function(node) {
        if (node.nodeType === 1) { // Element
          if (node.classList && node.classList.contains('cf-turnstile')) {
            needsRender = true;
          } else {
            var widgets = node.querySelectorAll && node.querySelectorAll('.cf-turnstile');
            if (widgets && widgets.length) {
              needsRender = true;
            }
          }
        }
      });
    });
    if (needsRender) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function() {
        try { renderTurnstileWidgets(); } catch (e) { if (window.console) console.error(e); }
      }, 100);
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });

})(jQuery);
