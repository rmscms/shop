{{--
  Modern confirmation dialog. Never use the browser's confirm()/alert(); use this instead.

  Put once on a page:   @include('shop::admin.partials.confirm')
  Then add attributes to a <form> (asks on submit) or to a button/link (asks on click):

    data-confirm="Main question"                    (required; this is what turns it on)
    data-confirm-title="Heading"
    data-confirm-desc="Smaller explanation"
    data-confirm-type="danger | warning | info | success"   (colour + icon, default warning)
    data-confirm-ok="Confirm button text"
    data-confirm-cancel="Cancel button text"
    data-confirm-icon="ph-trash"                    (overrides the type's icon)
    data-confirm-type-to-confirm="exact text"       (user must type it, for irreversible actions)

  From JS: rmsConfirm({title, message, desc, type, ok, cancel, icon, typeToConfirm}) -> Promise<boolean>
--}}
@once
<style>
  .rms-cm-backdrop {
    position: fixed; inset: 0; z-index: 10050; display: flex; align-items: center; justify-content: center;
    padding: 1rem; background: rgba(8, 10, 16, .62); backdrop-filter: blur(3px);
    opacity: 0; transition: opacity .16s ease;
  }
  .rms-cm-backdrop.is-open { opacity: 1; }
  .rms-cm {
    width: 100%; max-width: 26rem; border-radius: 1rem; padding: 1.5rem 1.5rem 1.25rem; text-align: center;
    background: var(--card-bg, var(--body-bg, #fff)); color: var(--body-color, #212529);
    border: 1px solid var(--border-color, rgba(128, 128, 128, .25));
    box-shadow: 0 1.5rem 3rem rgba(0, 0, 0, .35);
    transform: translateY(.75rem) scale(.97); transition: transform .18s cubic-bezier(.2, .9, .3, 1.2);
  }
  .rms-cm-backdrop.is-open .rms-cm { transform: none; }
  .rms-cm-icon {
    width: 4rem; height: 4rem; margin: 0 auto 1rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 1.9rem;
    color: var(--rms-cm-color); background: color-mix(in srgb, var(--rms-cm-color) 15%, transparent);
    box-shadow: 0 0 0 .5rem color-mix(in srgb, var(--rms-cm-color) 8%, transparent);
  }
  .rms-cm h5 { margin: 0 0 .5rem; font-weight: 700; }
  .rms-cm-msg { margin: 0; line-height: 1.8; }
  .rms-cm-desc { margin: .6rem 0 0; font-size: .8125rem; opacity: .7; line-height: 1.7; }
  .rms-cm-type { margin-top: 1rem; text-align: start; }
  .rms-cm-type label { display: block; margin-bottom: .35rem; font-size: .8125rem; opacity: .8; }
  .rms-cm-type code { padding: .05rem .4rem; border-radius: .35rem; background: color-mix(in srgb, var(--rms-cm-color) 14%, transparent); color: var(--rms-cm-color); }
  .rms-cm-actions { display: flex; gap: .6rem; justify-content: center; margin-top: 1.4rem; }
  .rms-cm-actions .btn { min-width: 7.5rem; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; }
  @media (prefers-reduced-motion: reduce) { .rms-cm-backdrop, .rms-cm { transition: none; } }
</style>
<script>
(function () {
  if (window.rmsConfirm) { return; }

  var TYPES = {
    danger:  { icon: 'ph-trash',          color: '#e5484d', btn: 'btn-danger',  ok: 'بله، حذف کن' },
    warning: { icon: 'ph-warning',        color: '#f59e0b', btn: 'btn-warning', ok: 'بله، ادامه بده' },
    info:    { icon: 'ph-info',           color: '#3b82f6', btn: 'btn-primary', ok: 'تأیید' },
    success: { icon: 'ph-check-circle',   color: '#22a06b', btn: 'btn-success', ok: 'بله، اعمال کن' }
  };

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) { node.className = cls; }
    if (text != null) { node.textContent = text; }
    return node;
  }

  // Everything is written with textContent, so names that come from the database can't inject markup.
  window.rmsConfirm = function (opts) {
    opts = opts || {};
    var t = TYPES[opts.type] || TYPES.warning;
    var previousFocus = document.activeElement;

    return new Promise(function (resolve) {
      var backdrop = el('div', 'rms-cm-backdrop');
      var box = el('div', 'rms-cm');
      box.setAttribute('role', 'alertdialog');
      box.setAttribute('aria-modal', 'true');
      box.style.setProperty('--rms-cm-color', t.color);

      var icon = el('div', 'rms-cm-icon');
      icon.appendChild(el('i', opts.icon || t.icon));
      box.appendChild(icon);
      box.appendChild(el('h5', '', opts.title || 'تأیید عملیات'));
      box.appendChild(el('p', 'rms-cm-msg', opts.message || 'آیا مطمئن هستید؟'));
      if (opts.desc) { box.appendChild(el('p', 'rms-cm-desc', opts.desc)); }

      var input = null;
      if (opts.typeToConfirm) {
        var wrap = el('div', 'rms-cm-type');
        var label = el('label');
        label.appendChild(document.createTextNode('برای تأیید این را دقیق بنویس: '));
        label.appendChild(el('code', '', opts.typeToConfirm));
        input = el('input', 'form-control');
        input.type = 'text';
        input.autocomplete = 'off';
        input.setAttribute('dir', 'auto');
        wrap.appendChild(label);
        wrap.appendChild(input);
        box.appendChild(wrap);
      }

      var actions = el('div', 'rms-cm-actions');
      var cancel = el('button', 'btn btn-outline-secondary');
      cancel.type = 'button';
      cancel.appendChild(el('i', 'ph-x'));
      cancel.appendChild(document.createTextNode(opts.cancel || 'انصراف'));
      var ok = el('button', 'btn ' + t.btn);
      ok.type = 'button';
      ok.appendChild(el('i', 'ph-check'));
      ok.appendChild(document.createTextNode(opts.ok || t.ok));
      actions.appendChild(cancel);
      actions.appendChild(ok);
      box.appendChild(actions);

      backdrop.appendChild(box);
      document.body.appendChild(backdrop);
      requestAnimationFrame(function () { backdrop.classList.add('is-open'); });

      function allowed() { return !input || input.value.trim() === String(opts.typeToConfirm).trim(); }
      function sync() { ok.disabled = !allowed(); }
      function close(result) {
        document.removeEventListener('keydown', onKey, true);
        backdrop.classList.remove('is-open');
        setTimeout(function () { backdrop.remove(); }, 160);
        if (previousFocus && previousFocus.focus) { previousFocus.focus(); }
        resolve(result);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); close(false); }
        if (e.key === 'Enter' && allowed() && e.target !== cancel) { e.preventDefault(); close(true); }
        if (e.key === 'Tab') {
          var items = [input, cancel, ok].filter(function (n) { return n && !n.disabled; });
          var i = items.indexOf(document.activeElement);
          e.preventDefault();
          items[(i + (e.shiftKey ? items.length - 1 : 1)) % items.length].focus();
        }
      }

      cancel.addEventListener('click', function () { close(false); });
      ok.addEventListener('click', function () { if (allowed()) { close(true); } });
      backdrop.addEventListener('mousedown', function (e) { if (e.target === backdrop) { close(false); } });
      if (input) { input.addEventListener('input', sync); }
      sync();
      document.addEventListener('keydown', onKey, true);
      (input || ok).focus();
    });
  };

  function optionsFrom(node) {
    var d = node.dataset;
    return {
      message: d.confirm, title: d.confirmTitle, desc: d.confirmDesc, type: d.confirmType,
      ok: d.confirmOk, cancel: d.confirmCancel, icon: d.confirmIcon, typeToConfirm: d.confirmTypeToConfirm
    };
  }

  // Buttons and links: ask on click, then continue what the click would have done.
  document.addEventListener('click', function (e) {
    var node = e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (!node || node.tagName === 'FORM') { return; }
    e.preventDefault();
    e.stopPropagation();
    var form = node.closest('form');
    window.rmsConfirm(optionsFrom(node)).then(function (yes) {
      if (!yes) { return; }
      if (node.tagName === 'A' && node.href) { window.location.href = node.href; return; }
      if (form) { form.__rmsOk = true; form.requestSubmit ? form.requestSubmit(node.type === 'submit' ? node : undefined) : form.submit(); }
    });
  }, true);

  // Forms: ask on submit.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.__rmsOk) { form.__rmsOk = false; return; }
    if (!form.matches || !form.matches('form[data-confirm]')) { return; }
    e.preventDefault();
    window.rmsConfirm(optionsFrom(form)).then(function (yes) {
      if (!yes) { return; }
      form.__rmsOk = true;
      form.requestSubmit ? form.requestSubmit(e.submitter || undefined) : form.submit();
    });
  }, true);
})();
</script>
@endonce
