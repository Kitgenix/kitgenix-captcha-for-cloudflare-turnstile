/**
 * Kitgenix CAPTCHA for Cloudflare Turnstile — Admin UI
 *
 * GUIDE: What this file does
 * -----------------------------------------
 * - Powers the accordion UI on the plugin settings screen:
 *   • Adds proper ARIA roles/attributes.
 *   • Keyboard accessible (Enter/Space).
 *   • Remembers the last opened section in localStorage.
 * - Shows a small “settings saved” toast if present in the DOM.
 *
 * GUIDE: What this file does NOT do
 * -----------------------------------------
 * - It does NOT render the Cloudflare Turnstile test widget shown on the
 *   settings page. That widget is rendered by an inline onload callback
 *   that Settings_UI registers when it enqueues the Turnstile API for
 *   that page (see: includes/admin/class-settings-ui.php).
 *
 * Scope
 * -----------------------------------------
 * - This script is only enqueued on the plugin’s admin pages by
 *   Script_Handler::enqueue_admin_assets().
 */

(function ($) {
  $(document).ready(function () {

    /* -----------------------------------------
     * Accordion: ARIA wiring
     * ----------------------------------------- */
    $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').each(function (i) {
      $(this).attr({
        'aria-expanded': 'false',
        'aria-controls': 'kitgenix-captcha-for-cloudflare-turnstile-accordion-body-' + i,
        'id':            'kitgenix-captcha-for-cloudflare-turnstile-accordion-header-' + i,
        'tabindex':      0,
        'role':          'button'
      });
      $(this).next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').attr({
        'id':             'kitgenix-captcha-for-cloudflare-turnstile-accordion-body-' + i,
        'aria-labelledby':'kitgenix-captcha-for-cloudflare-turnstile-accordion-header-' + i,
        'role':           'region',
        'tabindex':       -1
      });
    });

    /* -----------------------------------------
     * Accordion: restore last open (or open first)
     * ----------------------------------------- */
    var lastOpen = localStorage.getItem('KitgenixCaptchaForCloudflareTurnstileAccordionOpen');
    var $header  = lastOpen ? $('#' + lastOpen) : $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').first();
    if ($header.length) {
      $header.addClass('active').attr('aria-expanded', 'true');
      $header.next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').show();
    }

    /* -----------------------------------------
     * Accordion: toggle logic + a11y (click/Enter/Space)
     * ----------------------------------------- */
    let accordionDebounce = false;
    $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').on('click keydown', function (e) {
      // Keyboard activation
      if (e.type === 'keydown') {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault(); // prevent page scroll on Space
      }

      // Debounce rapid toggles
      if (accordionDebounce) return;
      accordionDebounce = true;
      setTimeout(function () { accordionDebounce = false; }, 250);

      const $hdr  = $(this);
      const $body = $hdr.next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body');
      const isOpen = $hdr.hasClass('active');

      // Close all
      $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header')
        .removeClass('active')
        .attr('aria-expanded', 'false');
      $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').slideUp(220);

      // Open requested
      if (!isOpen) {
        $hdr.addClass('active').attr('aria-expanded', 'true');
        $body.slideDown(220, function () {
          $body.attr('tabindex', -1).focus();
        });
        localStorage.setItem('KitgenixCaptchaForCloudflareTurnstileAccordionOpen', $hdr.attr('id'));
      } else {
        localStorage.removeItem('KitgenixCaptchaForCloudflareTurnstileAccordionOpen');
      }
    });

    /* -----------------------------------------
     * Toast: show “settings saved” if present
     * (Rendered server-side on successful save)
     * ----------------------------------------- */
    var $toast = $('#kitgenix-captcha-for-cloudflare-turnstile-settings-saved-toast');
    if ($toast.length) {
      $toast.fadeIn(200).delay(2000).fadeOut(400);
    }

    /* -----------------------------------------
     * NOTE: We intentionally do NOT render the admin test widget here.
     * That is handled by the inline onload function registered in
     * Settings_UI when it enqueues the Cloudflare Turnstile API.
     * (Avoid double-rendering / undefined globals.)
     * ----------------------------------------- */
  });
})(jQuery);
