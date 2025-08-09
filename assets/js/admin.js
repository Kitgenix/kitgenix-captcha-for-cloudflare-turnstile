(function ($) {
  $(document).ready(function () {
    // Accordion ARIA only, remove chevron icon logic
    $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').each(function(i) {
      // Set ARIA attributes only
      $(this).attr({
        'aria-expanded': 'false',
        'aria-controls': 'kitgenix-captcha-for-cloudflare-turnstile-accordion-body-'+i,
        'id': 'kitgenix-captcha-for-cloudflare-turnstile-accordion-header-'+i,
        'tabindex': 0,
        'role': 'button'
      });
      $(this).next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').attr({
        'id': 'kitgenix-captcha-for-cloudflare-turnstile-accordion-body-'+i,
        'aria-labelledby': 'kitgenix-captcha-for-cloudflare-turnstile-accordion-header-'+i,
        'role': 'region',
        'tabindex': -1
      });
    });

    // Open Site Keys accordion by default if nothing is set
    var lastOpen = localStorage.getItem('KitgenixCaptchaForCloudflareTurnstileAccordionOpen');
    if (!lastOpen) {
      var $firstHeader = $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').first();
      $firstHeader.addClass('active').attr('aria-expanded', 'true');
      $firstHeader.next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').show();
    } else {
      var $header = $('#' + lastOpen);
      $header.addClass('active').attr('aria-expanded', 'true');
      $header.next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').show();
    }

    // Accordion toggle with smooth animation and accessibility
    let accordionDebounce = false;
    $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').on('click keydown', function (e) {
      if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
      if (accordionDebounce) return;
      accordionDebounce = true;
      setTimeout(function() { accordionDebounce = false; }, 250);
      const $header = $(this);
      const $body = $header.next('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body');
      const isOpen = $header.hasClass('active');
      // Toggle current item
      $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-header').removeClass('active').attr('aria-expanded', 'false');
      $('.kitgenix-captcha-for-cloudflare-turnstile-accordion-body').slideUp(220);
      if (!isOpen) {
        $header.addClass('active').attr('aria-expanded', 'true');
        $body.slideDown(220, function() {
          $body.attr('tabindex', -1).focus();
        });
        localStorage.setItem('KitgenixCaptchaForCloudflareTurnstileAccordionOpen', $header.attr('id'));
      } else {
        localStorage.removeItem('KitgenixCaptchaForCloudflareTurnstileAccordionOpen');
      }
    });

    // Settings saved toast
    if ($('#kitgenix-captcha-for-cloudflare-turnstile-settings-saved-toast').length) {
      $('#kitgenix-captcha-for-cloudflare-turnstile-settings-saved-toast').fadeIn(200).delay(2000).fadeOut(400);
    }

    // Render Turnstile widget for API test (settings page)
    if ($('#kitgenix-captcha-for-cloudflare-turnstile-test-widget').length && window.turnstile && window.KitgenixCaptchaForCloudflareTurnstileTestSiteKey) {
      window.KitgenixCaptchaForCloudflareTurnstileTurnstileWidgetId = turnstile.render('kitgenix-captcha-for-cloudflare-turnstile-test-widget', {
        sitekey: window.KitgenixCaptchaForCloudflareTurnstileTestSiteKey,
        theme: window.KitgenixCaptchaForCloudflareTurnstileTestTheme || 'auto',
        callback: function(token) {
          // Hide widget and show success message
          $('#kitgenix-captcha-for-cloudflare-turnstile-test-widget').hide();
          $('#kitgenix-captcha-for-cloudflare-turnstile-test-success').fadeIn(200);
        },
        'expired-callback': function() {
          $('#kitgenix-captcha-for-cloudflare-turnstile-test-success').fadeOut(100);
        },
        'error-callback': function() {
          $('#kitgenix-captcha-for-cloudflare-turnstile-test-success').fadeOut(100);
        }
      });
    }

    // Test Turnstile keys button
    $('#kitgenix-captcha-for-cloudflare-turnstile-test-turnstile').on('click', function(e) {
      e.preventDefault();
      var $btn = $(this);
      $btn.prop('disabled', true).text('Testing...');
      var siteKey = $('#site_key').val();
      var secretKey = $('#secret_key').val();
      $.post(ajaxurl, {
        action: 'kitgenix_captcha_for_cloudflare_turnstile_test_turnstile',
        site_key: siteKey,
        secret_key: secretKey,
        _ajax_nonce: window.KitgenixCaptchaForCloudflareTurnstileTestNonce
      }, function(resp) {
        alert(resp.data ? resp.data : 'Test failed.');
        $btn.prop('disabled', false).text('Test Turnstile Keys');
      }).fail(function(jqXHR, textStatus) {
        alert('AJAX error: ' + textStatus);
        $btn.prop('disabled', false).text('Test Turnstile Keys');
      });
    });
  });
})(jQuery);
