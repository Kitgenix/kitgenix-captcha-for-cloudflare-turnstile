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
  $(function () {
    // Guard all other enhancements so one failure doesn't kill the rest.
    try {
    /* ------------------------------------------------------------------
       Scroll-spy navigation for sidebar
       Highlights active link based on intersection
    ------------------------------------------------------------------ */
    const $links = $('.kitgenix-nav-link');
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

    /* ------------------------------------------------------------------
       Progressive enhancement: convert checkboxes to switches
       (Non-destructive: original value preserved.)
    ------------------------------------------------------------------ */
    const enhanceSelectors = 'input[type=checkbox]';
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

    /* settings filter removed per user request */

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
      $input.attr('type', isPassword ? 'text' : 'password');
      $btn.attr('aria-pressed', isPassword ? 'true' : 'false');
      const newLabel = isPassword ? ($btn.data('label-hide') || 'Hide secret key') : ($btn.data('label-show') || 'Reveal secret key');
      const newText  = isPassword ? ($btn.data('text-hide') || 'Hide') : ($btn.data('text-show') || 'Show');
      $btn.attr('aria-label', newLabel);
      const $textSpan = $btn.find('.kitgenix-reveal-secret-text');
      if($textSpan.length){ $textSpan.text(newText); } else { $btn.text(newText); }
    });

    $(document).on('click', '.kitgenix-copy-secret', function(){
      const $btn = $(this);
      const targetId = $btn.data('target');
      if(!targetId) return;
      const $input = $('#' + targetId);
      if (!$input.length) return;
      const val = $input.val();
      if (!val) return;
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
       Collapsible <details> state persistence + toggle all
    ------------------------------------------------------------------ */
    const STORAGE_KEY = 'kgx_turnstile_details_state';
    function loadDetailsState(){
      try { return JSON.parse(localStorage.getItem(STORAGE_KEY)||'{}'); } catch(e){ return {}; }
    }
    function saveDetailsState(state){
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch(e){}
    }
    const state = loadDetailsState();
    $('details[data-kgx-group]').each(function(){
      const id = $(this).attr('id') || $(this).data('kgx-group');
      if(state[id] === false){ this.open = false; }
    }).on('toggle', function(){
      const id = $(this).attr('id') || $(this).data('kgx-group');
      state[id] = this.open;
      saveDetailsState(state);
    });
    // Global expand/collapse
    const $globalToggle = $('#kgx-toggle-all');
    $globalToggle.on('click', function(){
      const allOpen = $('details[data-kgx-group]').filter(function(){return this.open;}).length === $('details[data-kgx-group]').length;
      const targetOpen = !allOpen;
      $('details[data-kgx-group]').each(function(){ this.open = targetOpen; const id = $(this).attr('id') || $(this).data('kgx-group'); state[id] = targetOpen; });
      saveDetailsState(state);
      $(this).text(targetOpen ? $(this).data('label-collapse') : $(this).data('label-expand'));
    });
    if($globalToggle.length){
      const allOpen = $('details[data-kgx-group]').filter(function(){return this.open;}).length === $('details[data-kgx-group]').length;
      $globalToggle.text(allOpen ? $globalToggle.data('label-collapse') : $globalToggle.data('label-expand'));
    }

    /* ------------------------------------------------------------------
       Unsaved changes bar
    ------------------------------------------------------------------ */
    const $form = $('#kitgenix-settings-content form');
    const $unsaved = $('#kgx-unsaved-bar');
    const initialSerialized = $form.serialize();
    function checkUnsaved(){
      const current = $form.serialize();
      const changed = current !== initialSerialized;
      $('body').toggleClass('kgx-unsaved', changed);
      if($unsaved.length){ $unsaved.attr('aria-hidden', changed ? 'false':'true').toggle(changed); }
    }
    $form.on('input change', 'input, select, textarea', function(){ checkUnsaved(); });
    $('#kgx-unsaved-save').on('click', function(){ $form.trigger('submit'); });
    checkUnsaved();

    /* ------------------------------------------------------------------
       Copy shortcode button
    ------------------------------------------------------------------ */
    $(document).on('click', '.kgx-copy-shortcode', function(){
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

/* ------------------------------------------------------------------
   Whitelist preview
------------------------------------------------------------------ */
(function($){
  $(function(){
    try{
      // Simple client-side parsing used for the whitelist preview
      function parseLines(text){
        var lines = (text||'').split(/[\r\n,]+/).map(function(l){ return l.trim(); }).filter(Boolean);
        var out = [];
        lines.forEach(function(line){
          var clean = line.replace(/[^\w\.\:\*\/\-]/g,'');
          if(!clean) return;
          if(clean.indexOf('/') !== -1){
            var parts = clean.split('/');
            if(parts.length===2 && /^\d+$/.test(parts[1])){ out.push(clean); return; }
          }
          out.push(clean);
        });
        return out;
      }

      $('#kgx-whitelist-ips-preview-btn').on('click', function(){
        var v = $('#whitelist_ips').val() || '';
        var parsed = parseLines(v);
        var $p = $('#kgx-whitelist-ips-preview');
        if(!parsed.length){ $p.hide(); return; }
        $p.text(parsed.join('\n')).show();
      });

      $('#kgx-whitelist-uas-preview-btn').on('click', function(){
        var v = $('#whitelist_user_agents').val() || '';
        var lines = (v||'').split(/\r?\n/).map(function(l){ return l.trim(); }).filter(Boolean);
        var $p = $('#kgx-whitelist-uas-preview');
        if(!lines.length){ $p.hide(); return; }
        $p.text(lines.join('\n')).show();
      });

    } catch(e){ if(window.console) console.error('[Kitgenix Admin UI]', e); }
  });
})(jQuery);
