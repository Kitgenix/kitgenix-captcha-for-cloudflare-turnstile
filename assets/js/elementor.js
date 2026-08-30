/* global jQuery, elementorFrontend */
(function ($) {
  'use strict';

  /**
   * Kitgenix Turnstile â€” Elementor bridge
   *
   * GUIDE: What this file does
   * -----------------------------------------
   * - Ensures a hidden input exists in Elementor forms:
   *     <input type="hidden" name="cf-turnstile-response" />
   * - Ensures a `.cf-turnstile` container exists just before the submit group
   *   if one isnâ€™t already present in the form.
   * - Triggers the global/public renderer (from assets/js/public.js) to render
   *   Cloudflare Turnstile into that container.
   *
   * GUIDE: What this file does NOT do
   * -----------------------------------------
   * - It does NOT render the widget itself (no turnstile.render here).
   *   Rendering is centralized in assets/js/public.js.
   */

  function ensureHiddenInput($form) {
    var $input = $form.find('input[name="cf-turnstile-response"]');
    if (!$input.length) {
      $input = $('<input>', { type: 'hidden', name: 'cf-turnstile-response', value: '' }).appendTo($form);
    }
    return $input;
  }

  /**
   * GUIDE: Ensure a Turnstile container exists in an Elementor form.
   * - wrapper: `.elementor-form-fields-wrapper` DOM node
   * - If a container already exists anywhere in the form, we only ensure the hidden input.
   * - If not, we inject a `.cf-turnstile` before the submit field group.
   */
  function ensureContainer(wrapper) {
    if (!wrapper) return;

    // Tighter scope than generic <form>
    var form = wrapper.closest ? wrapper.closest('.elementor-form') : null;
    if (!form) return;

    // If ANY Turnstile container already exists, just ensure hidden input and bail
    if (form.querySelector('.cf-turnstile')) {
      ensureHiddenInput($(form));
      return;
    }

    // Need a site key to inject a usable container. If missing, do nothing.
    var cfg = window.KitgenixCaptchaForCloudflareTurnstileConfig || {};
    var siteKey = cfg.site_key ? String(cfg.site_key) : '';
    if (!siteKey) {
      // Hidden input is still useful if another integration renders later
      ensureHiddenInput($(form));
      return;
    }

    // Hidden input for token
    ensureHiddenInput($(form));

    // Container before submit group (fallback: append to wrapper)
    var submitGroup = wrapper.querySelector('.elementor-field-type-submit');
    var container = document.createElement('div');
    container.className = 'cf-turnstile';
    container.setAttribute('data-sitekey', siteKey);
    if (cfg.theme)      container.setAttribute('data-theme', String(cfg.theme));
    if (cfg.size)       container.setAttribute('data-size', String(cfg.size));
    if (cfg.appearance) container.setAttribute('data-appearance', String(cfg.appearance));
    container.setAttribute('data-KitgenixCaptchaForCloudflareTurnstile-owner', 'elementor');

    if (submitGroup && submitGroup.parentNode) {
      submitGroup.parentNode.insertBefore(container, submitGroup);
    } else if (wrapper && wrapper.appendChild) {
      wrapper.appendChild(container);
    }

    // Hint global renderer that new containers exist (public.js will render them)
    try {
      document.dispatchEvent(new CustomEvent('KitgenixCaptchaForCloudflareTurnstile:turnstile-containers-added', { detail: { source: 'elementor' } }));
    } catch (e) {}
  }

  // GUIDE: Initial pass on page load
  $(function () {
    document.querySelectorAll('.elementor-form-fields-wrapper').forEach(ensureContainer);
  });

  // GUIDE: Elementor hooks for new forms & popups
  if (window.elementorFrontend && elementorFrontend.hooks) {
    elementorFrontend.hooks.addAction('frontend/element_ready/form.default', function ($scope) {
      $scope.find('.elementor-form-fields-wrapper').each(function () { ensureContainer(this); });
    });
    elementorFrontend.hooks.addAction('frontend/element_ready/popup.default', function ($scope) {
      $scope.find('.elementor-form-fields-wrapper').each(function () { ensureContainer(this); });
    });
  }

  // GUIDE: When Elementor shows a popup after page load
  $(document).on('elementor/popup/show', function () {
    document.querySelectorAll('.elementor-popup-modal .elementor-form-fields-wrapper').forEach(ensureContainer);
  });

  // GUIDE: After a successful Elementor AJAX submit, clear the hidden token and
  // collapse the widget for that form only (layout tidy-up). This must NEVER run
  // on a failed/validation-error response – doing so would hide the widget right
  // after public.js's error handler resets it for a retry, leaving the visitor
  // with an error message and no way to re-verify. success is read from the
  // response body itself (Elementor's send_form action uses wp_send_json_*),
  // never inferred from "the request merely completed".
  $(document).on('ajaxComplete', function (_e, xhr, settings) {
    var data = settings && settings.data ? String(settings.data) : '';
    if (data.indexOf('action=elementor_pro_forms_send_form') === -1) return;

    var succeeded = false;
    try {
      var json = xhr && xhr.responseJSON ? xhr.responseJSON : JSON.parse(xhr && xhr.responseText);
      succeeded = !!(json && json.success);
    } catch (e) {
      succeeded = false;
    }
    if (!succeeded) return;

    // ajaxComplete does not tell us which form issued the request, and a page
    // (or a popup) can have more than one Elementor form. Scope by state instead
    // of guessing the DOM node: only a form that actually held a solved token
    // could have produced this successful submission, so only those are touched.
    // A second, untouched form on the same page still has an empty token and is
    // left alone.
    $('.elementor-form').each(function () {
      var $form = $(this);
      var $input = $form.find('input[name="cf-turnstile-response"]');
      if (!$input.length || !$input.val()) return;
      $input.val('');
      // Collapse and hide containers to eliminate any layout gap after success
      $form.find('.cf-turnstile').each(function(){
        this.classList.add('kitgenix-ts-collapsed');
        this.classList.add('kitgenix-ts-hide');
      });
    });
  });

  // Optional: also react when Elementor initializes frontend (covers some lazy loads)
  $(window).on('elementor/frontend/init', function () {
    document.querySelectorAll('.elementor-form-fields-wrapper').forEach(ensureContainer);
  });

})(jQuery);