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
  // Signals that the full admin UI script loaded (used to disable inline fallbacks).
  window.KitgenixTurnstileAdminJsReady = true;

  $(function () {
    // Guard all other enhancements so one failure doesn't kill the rest.
    try {
    /* ------------------------------------------------------------------
       Scroll-spy navigation for sidebar
       Highlights active link based on intersection
    ------------------------------------------------------------------ */
    // Sidebar is hidden in top-tab layout, keep code but guard when present
    const $links = $('.kitgenix-nav-link');
    if($links.length){
      const sectionMap = {};
      $links.each(function(){
        const href = $(this).attr('href');
        if (href && href.startsWith('#')) {
          const $sec = $(href);
          if ($sec.length) sectionMap[href] = $sec[0];
        }
      });
      const observer = new IntersectionObserver((entries)=>{
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const id = '#' + entry.target.id;
            $links.removeClass('active');
            $links.filter('[href="'+id+'"]').addClass('active');
          }
        });
      }, { rootMargin: '-40% 0px -55% 0px', threshold: [0, 0.2, 0.4, 0.6, 0.8, 1] });
      Object.values(sectionMap).forEach(sec => observer.observe(sec));

      // Smooth scroll enhancement
      $links.on('click', function(e){
        const href = $(this).attr('href');
        if (href && href.startsWith('#')) {
          const $target = $(href);
          if ($target.length) {
            e.preventDefault();
            window.scrollTo({ top: $target.offset().top - 80, behavior: 'smooth' });
          }
        }
      });
    }

     /* ------------------------------------------------------------------
       Progressive enhancement: convert checkboxes to switches
       (Disabled by default so checkboxes render as vanilla WP checkboxes
       unless explicitly opted-in with a dedicated class.)
     ------------------------------------------------------------------ */
     const enhanceSelectors = '#kitgenix-captcha-for-cloudflare-turnstile-admin-app input[type=checkbox].kitgenix-switch-toggle';
     $(enhanceSelectors).each(function(){
      const $cb = $(this);
      if ($cb.closest('.kitgenix-switch-wrapper').length) return; // already wrapped
      if ($cb.attr('type') !== 'checkbox') return;
      $cb.addClass('kitgenix-switch-hidden');
      const describedBy = $cb.closest('label').text().trim() || $cb.attr('id') || 'toggle option';
      const $switch = $('<input />', {
        type: 'checkbox',
        class: 'kitgenix-switch',
        role: 'switch',
        'aria-checked': $cb.prop('checked') ? 'true' : 'false',
        'aria-label': describedBy
      }).prop('checked', $cb.prop('checked'));
      $switch.on('change', ()=> {
        const state = $switch.prop('checked');
        $switch.attr('aria-checked', state ? 'true' : 'false');
        $cb.prop('checked', state).trigger('change');
      });
      $cb.after($('<span class="kitgenix-switch-wrapper"></span>').append($switch));
    });

    /* -----------------------------------------
       Toast: show “settings saved” if present
    ----------------------------------------- */
    var $toast = $('#kitgenix-captcha-for-cloudflare-turnstile-settings-saved-toast');
    if ($toast.length) {
      $toast.fadeIn(200).delay(2200).fadeOut(400);
    }

    /* ------------------------------------------------------------------
       Secret key reveal & copy
    ------------------------------------------------------------------ */
    // Delegate for robustness (works if buttons injected later).
    $(document).on('click', '.kitgenix-reveal-secret', function(){
      const $btn = $(this);
      const targetId = $btn.data('target');
      if(!targetId) return;
      const $input = $('#' + targetId);
      if (!$input.length) return;
      const isPassword = $input.attr('type') === 'password';

      function setButtonState(showing){
        $btn.attr('aria-pressed', showing ? 'true' : 'false');
        const newLabel = showing ? ($btn.data('label-hide') || 'Hide secret key') : ($btn.data('label-show') || 'Reveal secret key');
        const newText  = showing ? ($btn.data('text-hide') || 'Hide') : ($btn.data('text-show') || 'Show');
        $btn.attr('aria-label', newLabel);
        const $textSpan = $btn.find('.kitgenix-reveal-secret-text');
        if($textSpan.length){ $textSpan.text(newText); } else { $btn.text(newText); }
      }

      function revealNow(){
        $input.attr('type', 'text');
        setButtonState(true);
      }

      function hideNow(){
        $input.attr('type', 'password');
        setButtonState(false);
      }

      // If we're about to reveal and the secret is stored-but-hidden, fetch it on demand.
      const needsFetch = isPassword && !$input.val() && ($input.data('kitgenix-captcha-for-cloudflare-turnstile-saved-secret') || $input.attr('data-kitgenix-captcha-for-cloudflare-turnstile-saved-secret'));
      if (needsFetch) {
        const cfg = window.KitgenixTurnstileAdmin || {};
        if (!cfg.ajax_url || !cfg.reveal_secret_action || !cfg.reveal_secret_nonce) {
          // Can't fetch; still toggle type so user can paste a new value.
          revealNow();
          return;
        }

        $btn.prop('disabled', true);
        const originalText = $btn.text();
        $btn.text('Loading…');

        $.post(cfg.ajax_url, {
          action: cfg.reveal_secret_action,
          nonce: cfg.reveal_secret_nonce
        }).done(function(resp){
          try {
            if (resp && resp.success && resp.data && resp.data.secret_key) {
              $input.val(resp.data.secret_key);
            }
          } catch(_e) {}
          revealNow();
        }).fail(function(){
          // If fetch fails, still reveal empty field so user can paste.
          revealNow();
        }).always(function(){
          $btn.prop('disabled', false);
          // Restore label state; revealNow() sets to Hide already, so restore only if still showing original.
          if ($btn.text() === 'Loading…') $btn.text(originalText);
        });

        return;
      }

      // Normal toggle behavior (works for newly entered secrets).
      if (isPassword) {
        revealNow();
      } else {
        hideNow();
      }
    });

    $(document).on('click', '.kitgenix-copy-secret', function(){
      const $btn = $(this);
      const targetId = $btn.data('target');
      if(!targetId) return;
      const $input = $('#' + targetId);
      if (!$input.length) return;
      const val = $input.val();
      if (!val) {
        const original = $btn.text();
        $btn.text('Enter secret').attr('aria-label','Enter a secret key to copy');
        setTimeout(()=>{ $btn.text(original).attr('aria-label','Copy secret key'); }, 1400);
        return;
      }
      function feedback(){
        const original = $btn.html();
        $btn.html('✓').attr('aria-label','Copied');
        setTimeout(()=>{ $btn.html(original).attr('aria-label','Copy secret key'); },1200);
      }
      function fallback(){
        try {
          const origType = $input.attr('type');
            $input.attr('type','text');
            $input[0].select();
            document.execCommand('copy');
            $input.attr('type', origType);
            feedback();
        } catch(e){ /* swallow */ }
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(feedback).catch(fallback);
      } else {
        fallback();
      }
    });

    /* ------------------------------------------------------------------
       End-to-end setup verification
    ------------------------------------------------------------------ */
    window.KitgenixCaptchaForCloudflareTurnstileAdminHandleToken = function(token){
      const cfg = window.KitgenixTurnstileAdmin || {};
      const $status = $('#kitgenix-captcha-for-cloudflare-turnstile-setup-status');
      const $success = $('#kitgenix-captcha-for-cloudflare-turnstile-test-success');

      if($success.length){
        $success.text('Widget challenge completed. Verifying setup with Cloudflare now...').show().attr('aria-hidden', 'false');
      }

      if(!cfg.ajax_url || !cfg.verify_setup_action || !cfg.verify_setup_nonce || !token){
        if($status.length){
          $status.removeClass('notice-success notice-info').addClass('notice-warning');
          $status.html('<p><strong>Setup verification:</strong> Unable to run the server-side setup test from this screen.</p>');
        }
        return;
      }

      $.post(cfg.ajax_url, {
        action: cfg.verify_setup_action,
        nonce: cfg.verify_setup_nonce,
        token: token
      }).done(function(resp){
        const payload = resp && resp.success && resp.data ? resp.data : null;
        const setupStatus = payload && payload.status ? payload.status : null;

        if(!setupStatus || !setupStatus.message){
          if($status.length){
            $status.removeClass('notice-success notice-info').addClass('notice-warning');
            $status.html('<p><strong>Setup verification:</strong> The server response was incomplete.</p>');
          }
          return;
        }

        if($status.length){
          $status.removeClass('notice-success notice-warning notice-info').addClass(setupStatus.verified ? 'notice-success' : 'notice-warning');
          let html = '<p><strong>Setup verification:</strong> ' + $('<div/>').text(setupStatus.message).html() + '</p>';
          if(setupStatus.checked_at){
            const checkedAt = new Date(Number(setupStatus.checked_at) * 1000);
            if(!Number.isNaN(checkedAt.getTime())){
              html += '<p class="description">Last checked: ' + $('<div/>').text(checkedAt.toLocaleString()).html() + '</p>';
            }
          }
          $status.html(html);
        }

        if($success.length){
          $success.text(setupStatus.verified ? 'Widget challenge completed and the server-side setup verification passed.' : 'Widget challenge completed, but the server-side setup verification did not pass.').show().attr('aria-hidden', 'false');
        }
      }).fail(function(){
        if($status.length){
          $status.removeClass('notice-success notice-info').addClass('notice-warning');
          $status.html('<p><strong>Setup verification:</strong> Could not reach the WordPress AJAX endpoint to complete the server-side test.</p>');
        }
      });
    };

    /* ------------------------------------------------------------------
       Copy recent diagnostic log
    ------------------------------------------------------------------ */
    $(document).on('click', '#kitgenix-turnstile-copy-log', function(){
      const $btn = $(this);
      const targetId = $btn.data('target');
      const $target = targetId ? $('#' + targetId) : $();
      const text = $target.length ? String($target.val() || '') : '';
      if(!text){
        return;
      }

      const original = $btn.text();
      function feedback(label){
        $btn.text(label).prop('disabled', true);
        setTimeout(function(){
          $btn.text(original).prop('disabled', false);
        }, 1400);
      }

      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(function(){
          feedback('Copied!');
        }).catch(function(){
          fallback();
        });
      } else {
        fallback();
      }

      function fallback(){
        try {
          $target.trigger('focus').trigger('select');
          document.execCommand('copy');
          feedback('Copied!');
        } catch(_e){
          feedback('Copy failed');
        }
      }
    });
    /* ------------------------------------------------------------------
       Unsaved changes bar
    ------------------------------------------------------------------ */
    const $form = $('#kitgenix-settings-content form[action="options.php"]').first();
    const $unsaved = $('#kitgenix-captcha-for-cloudflare-turnstile-unsaved-bar');
    const initialSerialized = $form.length ? $form.serialize() : '';
    function checkUnsaved(){
      if(!$form.length) return;
      const current = $form.serialize();
      const changed = current !== initialSerialized;
      $('body').toggleClass('kitgenix-captcha-for-cloudflare-turnstile-unsaved', changed);
      if($unsaved.length){ $unsaved.attr('aria-hidden', changed ? 'false':'true').toggle(changed); }
    }
    $form.on('input change', 'input, select, textarea', function(){ checkUnsaved(); });
    $('#kitgenix-captcha-for-cloudflare-turnstile-unsaved-save').on('click', function(){
      try {
        if (!$form.length) return;
        const formEl = $form.get(0);
        // Prefer requestSubmit (runs native validation and submit handlers), fall back to submit.
        if (formEl && typeof formEl.requestSubmit === 'function') {
          formEl.requestSubmit();
        } else if (formEl && typeof formEl.submit === 'function') {
          formEl.submit();
        } else {
          // Last resort: click the first submit control.
          const $submit = $form.find('button[type=submit], input[type=submit]').first();
          if ($submit.length) $submit.trigger('click');
        }
      } catch(_e) {}
    });
    checkUnsaved();

    /* ------------------------------------------------------------------
       Copy shortcode button
    ------------------------------------------------------------------ */
    $(document).on('click', '.kitgenix-captcha-for-cloudflare-turnstile-copy-shortcode', function(){
      const code = '[kitgenix_turnstile]';
      const $btn = $(this);
      function feedback(){
        const original = $btn.text();
        $btn.text('Copied ✓');
        setTimeout(()=>{ $btn.text(original); }, 1200);
      }
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(code).then(feedback).catch(()=>fallback());
      } else { fallback(); }
      function fallback(){
        const temp = $('<textarea style="position:absolute;left:-9999px;top:-9999px;"></textarea>').val(code).appendTo('body');
        temp[0].select();
        document.execCommand('copy');
        temp.remove();
        feedback();
      }
    });

    } catch(err){ if(window.console) console.error('[Kitgenix Admin UI]', err); }
  });
})(jQuery);
