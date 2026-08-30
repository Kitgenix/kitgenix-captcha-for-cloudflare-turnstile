/**
 * Kitgenix CAPTCHA for Cloudflare Turnstile – Admin UI
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
    var kitgenixNoticeIcons = {
      success: '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 18.333A8.333 8.333 0 1 0 10 1.667a8.333 8.333 0 0 0 0 16.666Z" stroke="currentColor" stroke-width="1.4"/><path d="M6.667 10.417 8.75 12.5l4.583-4.583" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
      warning: '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8.65 3.25c.6-1 2.1-1 2.7 0l6.6 11.05c.6 1.03-.14 2.33-1.35 2.33H3.4c-1.21 0-1.95-1.3-1.35-2.33L8.65 3.25Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M10 8v3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>',
      info: '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 18.333A8.333 8.333 0 1 0 10 1.667a8.333 8.333 0 0 0 0 16.666Z" stroke="currentColor" stroke-width="1.4"/><path d="M10 9v4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="10" cy="6.5" r=".9" fill="currentColor"/></svg>'
    };

    function setKitgenixSetupStatus($status, variant, bodyHtml){
      if(!$status.length){ return; }
      $status.removeClass('kitgenix-notice-success kitgenix-notice-warning kitgenix-notice-info').addClass('kitgenix-notice-' + variant);
      var icon = kitgenixNoticeIcons[variant] || kitgenixNoticeIcons.info;
      $status.html(
        '<span class="kitgenix-notice-icon" aria-hidden="true">' + icon + '</span>' +
        '<div class="kitgenix-notice-body">' + bodyHtml + '</div>'
      );
    }

    window.KitgenixCaptchaForCloudflareTurnstileAdminHandleToken = function(token){
      const cfg = window.KitgenixTurnstileAdmin || {};
      const $status = $('#kitgenix-captcha-for-cloudflare-turnstile-setup-status');
      const $success = $('#kitgenix-captcha-for-cloudflare-turnstile-test-success');

      if($success.length){
        $success.text('Widget challenge completed. Verifying setup with Cloudflare now...').show().attr('aria-hidden', 'false');
      }

      if(!cfg.ajax_url || !cfg.verify_setup_action || !cfg.verify_setup_nonce || !token){
        setKitgenixSetupStatus($status, 'warning', '<p class="kitgenix-notice-text"><strong>Setup verification:</strong> Unable to run the server-side setup test from this screen.</p>');
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
          setKitgenixSetupStatus($status, 'warning', '<p class="kitgenix-notice-text"><strong>Setup verification:</strong> The server response was incomplete.</p>');
          return;
        }

        let html = '<p class="kitgenix-notice-text"><strong>Setup verification:</strong> ' + $('<div/>').text(setupStatus.message).html() + '</p>';
        if(setupStatus.checked_at){
          const checkedAt = new Date(Number(setupStatus.checked_at) * 1000);
          if(!Number.isNaN(checkedAt.getTime())){
            html += '<p class="description">Last checked: ' + $('<div/>').text(checkedAt.toLocaleString()).html() + '</p>';
          }
        }
        setKitgenixSetupStatus($status, setupStatus.verified ? 'success' : 'warning', html);

        if($success.length){
          $success.text(setupStatus.verified ? 'Widget challenge completed and the server-side setup verification passed.' : 'Widget challenge completed, but the server-side setup verification did not pass.').show().attr('aria-hidden', 'false');
        }
      }).fail(function(){
        setKitgenixSetupStatus($status, 'warning', '<p class="kitgenix-notice-text"><strong>Setup verification:</strong> Could not reach the WordPress AJAX endpoint to complete the server-side test.</p>');
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

    /* ------------------------------------------------------------------
       Settings import preview
       Reads the chosen JSON file client-side (it never leaves the browser
       until the admin actually submits) and shows what it contains before
       Replace/Merge is committed server-side. This is a preview only –
       Settings_Transfer::handle_import() remains the authoritative
       validator/sanitizer, unchanged by anything shown here.
    ------------------------------------------------------------------ */
    const $importFile = $('#kitgenix_turnstile_import_file');
    const $importPreview = $('#kitgenix-turnstile-import-preview');
    const $importSubmit = $('#kitgenix-turnstile-import-submit');
    if ($importFile.length && $importPreview.length) {
      if ($importSubmit.length) $importSubmit.prop('disabled', true);

      function renderImportError(message) {
        $importPreview.attr('hidden', false).html(
          '<div class="kitgenix-notice kitgenix-notice-error"><div class="kitgenix-notice-body"><p class="kitgenix-notice-text"></p></div></div>'
        );
        $importPreview.find('.kitgenix-notice-text').text(message);
        if ($importSubmit.length) $importSubmit.prop('disabled', true);
      }

      function renderImportPreview(data) {
        const pluginId = (data && typeof data.plugin === 'string') ? data.plugin : '';
        const expectedId = 'kitgenix-captcha-for-cloudflare-turnstile';
        const exportedAt = (data && typeof data.exported_at === 'string') ? data.exported_at : '';
        const settings = (data && typeof data.settings === 'object' && data.settings) ? data.settings : (typeof data === 'object' ? data : {});
        const keys = Object.keys(settings || {});
        const hasSiteKey = Object.prototype.hasOwnProperty.call(settings, 'site_key');
        const hasSecretKey = Object.prototype.hasOwnProperty.call(settings, 'secret_key');

        const groups = [];
        const groupChecks = [
          { label: 'Integration toggles', test: function (k) { return k.indexOf('enable_') === 0; } },
          { label: 'Per-integration modes', test: function (k) { return k.indexOf('mode_') === 0; } },
          { label: 'Test Mode per Integration', test: function (k) { return k === 'test_mode_integrations'; } },
          { label: 'IP / User-Agent whitelist', test: function (k) { return k.indexOf('whitelist') === 0; } },
          { label: 'Trusted proxy configuration', test: function (k) { return k.indexOf('trusted_prox') === 0 || k === 'trust_proxy' || k === 'respect_proxy_headers'; } },
          { label: 'Custom messages', test: function (k) { return k.indexOf('message') !== -1; } },
          { label: 'Widget display settings', test: function (k) { return k === 'theme' || k === 'widget_size' || k === 'appearance' || k.indexOf('_override_') !== -1; } }
        ];
        groupChecks.forEach(function (g) {
          if (keys.some(g.test)) groups.push(g.label);
        });

        let html = '<div class="kitgenix-notice ' + (pluginId && pluginId !== expectedId ? 'kitgenix-notice-warning' : 'kitgenix-notice-info') + '">';
        html += '<div class="kitgenix-notice-body">';
        html += '<p class="kitgenix-notice-title">Import preview</p>';
        if (pluginId && pluginId !== expectedId) {
          html += '<p class="kitgenix-notice-text">This file was exported from a different plugin/slug (<code>' + escapeHtml(pluginId) + '</code>). The import will likely be rejected on submit.</p>';
        }
        html += '<ul style="margin:6px 0 0 18px;list-style:disc;">';
        html += '<li>' + keys.length + ' setting key(s) found' + (exportedAt ? ', exported ' + escapeHtml(exportedAt) : '') + '</li>';
        html += '<li>Site Key / Secret Key in file: ' + (hasSiteKey || hasSecretKey ? 'yes – current keys on this site will be affected per the chosen mode' : 'no – this site’s current keys are kept either way') + '</li>';
        html += '<li>Contains: ' + (groups.length ? escapeHtml(groups.join(', ')) : 'no recognizable settings groups') + '</li>';
        html += '</ul>';
        html += '</div></div>';

        $importPreview.attr('hidden', false).html(html);
        if ($importSubmit.length) $importSubmit.prop('disabled', false);
      }

      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
      }

      $importFile.on('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
          $importPreview.attr('hidden', true).empty();
          if ($importSubmit.length) $importSubmit.prop('disabled', true);
          return;
        }
        if (!window.FileReader) {
          // No client-side preview available; let the server validate on submit.
          if ($importSubmit.length) $importSubmit.prop('disabled', false);
          return;
        }
        const reader = new FileReader();
        reader.onload = function () {
          try {
            const parsed = JSON.parse(String(reader.result || ''));
            renderImportPreview(parsed);
          } catch (e) {
            renderImportError('This file is not valid JSON, so it cannot be imported.');
          }
        };
        reader.onerror = function () {
          renderImportError('Could not read this file.');
        };
        reader.readAsText(file);
      });
    }

    } catch(err){ if(window.console) console.error('[Kitgenix Admin UI]', err); }
  });
})(jQuery);
