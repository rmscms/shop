(function(){
  console.log('🧩 orders-show.js loaded');
  document.addEventListener('DOMContentLoaded', function(){
    console.log('🧩 orders-show DOM ready');

    // Copy tracking code button
    var copyBtn = document.getElementById('copy-tracking-code');
    var trackInput = document.getElementById('tracking-code-input');
    if (copyBtn && trackInput) {
      copyBtn.addEventListener('click', function(){
        try { navigator.clipboard.writeText(trackInput.value || ''); } catch(e) {}
      });
    }

    // Refund confirm (cancel-refund-form)
    var refundTriggers = document.querySelectorAll('[data-refund-trigger]');
    if (refundTriggers.length) {
      console.log('🧩 refund triggers found:', refundTriggers.length);
      refundTriggers.forEach(function(trigger){
        var form = trigger.closest('form');
        if (!form) { return; }
        trigger.addEventListener('click', function(e){
          e.preventDefault();
          var title = 'لغو سفارش و بازگشت مبلغ';
          var msg = 'با تأیید این عملیات، سفارش لغو می‌شود و مبلغ پرداختی به کاربر بازگردانده خواهد شد.';
          if (typeof window.confirmAction !== 'function') {
            console.warn('confirmAction plugin is not available; submitting without confirmation.');
            form.submit();
            return;
          }
          console.log('🔔 opening confirm-modal for refund');
          Promise.resolve(window.confirmAction(title, msg, {
            icon: 'ph-warning',
            confirmClass: 'btn-danger',
            confirmText: 'لغو سفارش',
            cancelText: 'انصراف',
          })).then(function(ok){
            if (ok) { form.submit(); }
          });
        });
      });
    }

    // Image modal: open large image on thumb click
    var links = document.querySelectorAll('.order-item-thumb');
    var modalRoot = document.getElementById('orderItemImageModal');
    var imgEl = document.getElementById('order-item-image');
    var avifSource = document.getElementById('order-item-source-avif');
    var bsModal = (modalRoot && window.bootstrap) ? window.bootstrap.Modal.getOrCreateInstance(modalRoot) : null;
    links.forEach(function(a){
      a.addEventListener('click', function(e){
        e.preventDefault();
        if (!imgEl || !bsModal) return;
        var avif = a.getAttribute('data-img-avif') || '';
        var url = a.getAttribute('data-img-url') || '';
        if (avifSource) { avifSource.srcset = avif || ''; }
        // Always set fallback <img> to non-AVIF for browsers without AVIF support
        imgEl.src = url || avif;
        imgEl.alt = 'product-image';
        try { bsModal.show(); } catch(e) {}
      });
    });
  });
})();


