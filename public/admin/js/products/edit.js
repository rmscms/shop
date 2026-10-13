// git-trigger
(function(){
    document.addEventListener('DOMContentLoaded', function(){
      // Auto-generate slug from name (only in create mode)
      (function initSlugAutoGeneration(){
        var nameInput = document.querySelector('input[name="name"][data-auto-slug]');
        var slugInput = document.querySelector('input[name="slug"][data-slug-target]');
        if (!nameInput || !slugInput) return;
        
        var isCreateMode = nameInput.getAttribute('data-auto-slug') === 'true';
        if (!isCreateMode) return; // Don't auto-generate in edit mode
        
        var slugManuallyEdited = false;
        
        // Track if user manually edits slug
        slugInput.addEventListener('input', function(){
          slugManuallyEdited = true;
        });
        
        // Generate slug from name
        nameInput.addEventListener('input', function(){
          if (slugManuallyEdited) return; // Don't overwrite if user manually edited
          
          var name = this.value || '';
          // Convert to slug: lowercase, replace spaces with hyphens, remove special chars
          var slug = name
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')           // Replace spaces with hyphens
            .replace(/[^\w\-]+/g, '')      // Remove special characters except hyphens
            .replace(/\-\-+/g, '-')        // Replace multiple hyphens with single
            .replace(/^-+/, '')            // Remove leading hyphens
            .replace(/-+$/, '');           // Remove trailing hyphens
          
          slugInput.value = slug;
        });
      })();
      
      // Persist active tab across reloads and submits (non-AJAX)
    try{
      var tabHidden = document.getElementById('active_tab');
      // Activate tab from location.hash if present
      var initialHash = location.hash || '';
      if (initialHash && (initialHash.startsWith('#tab_') || initialHash === '#videos')){
        var link = document.querySelector('a[data-bs-toggle="tab"][href="'+initialHash+'"]');
        if (link && window.bootstrap){ new bootstrap.Tab(link).show(); }
        if (tabHidden){ tabHidden.value = initialHash.substring(1); }
      }
      // Update hidden and hash on tab change
      document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(function(a){
        a.addEventListener('shown.bs.tab', function(ev){
          var href = ev.target.getAttribute('href') || '';
          if (href.startsWith('#tab_') || href === '#videos'){
            var id = href.substring(1);
            if (tabHidden) tabHidden.value = id;
            if (history && history.replaceState){ history.replaceState(null, '', href); }
          }
        });
      });
    }catch(e){}
    // Enhance editors inside the Basic tab (short_desc + description)
    (function enhanceEditors(){
      var shortDesc = document.querySelector('textarea[name="short_desc"]');
      var desc = document.querySelector('textarea[name="description"]');
      if (shortDesc) { shortDesc.classList.add('js-ckeditor'); shortDesc.setAttribute('data-editor','ckeditor'); shortDesc.setAttribute('data-min-height','200px'); if (!shortDesc.getAttribute('rows')) shortDesc.setAttribute('rows','5'); }
      if (desc) { desc.classList.add('js-ckeditor'); desc.setAttribute('data-editor','ckeditor'); desc.setAttribute('data-min-height','360px'); if (!desc.getAttribute('rows')) desc.setAttribute('rows','18'); }
      if (window.rmsInitCkEditors) window.rmsInitCkEditors();
    })();
    // init select2 if available
    if (window.jQuery && jQuery().select2) {
      jQuery('.select2').select2({width:'100%'});
    }
    // pricing در CNY است؛ تبدیل خودکار نیاز نیست
    // Pricing tab handlers (extracted from Blade)
    (function initPricingTab(){
      try {
        var sel = document.querySelector('select[name="discount_type"]');
        var unit = document.getElementById('discount-unit');
        if (sel && unit) {
          sel.addEventListener('change', function(){ unit.textContent = (sel.value==='percent') ? '%' : 'CNY'; });
        }

        var pricingBtn = document.getElementById('btn-save-pricing-ajax');
        if (pricingBtn) {
          pricingBtn.addEventListener('click', async function(){
            var btn = this;
            var toggle = function(dis){
              btn.disabled = dis;
              var lbl = btn.querySelector('.label');
              var ld = btn.querySelector('.loading');
              if (lbl) lbl.classList.toggle('d-none', dis);
              if (ld) ld.classList.toggle('d-none', !dis);
            };
            toggle(true);
            try {
              var token = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';
              var payload = {
                cost_cny: (document.querySelector('input[name="cost_cny"]')||{}).value || '',
                sale_price_cny: (document.querySelector('input[name="sale_price_cny"]')||{}).value || '',
                discount_type: (document.querySelector('select[name="discount_type"]')||{}).value || '',
                discount_value: (document.querySelector('input[name="discount_value"]').value)||'',
                stock_qty: (document.querySelector('input[name="stock_qty"]')||{}).value || '',
                point_per_unit: (document.querySelector('input[name="point_per_unit"]')||{}).value || ''
              };
              var endpoint = (window.RMS && RMS.apiEndpoints && RMS.apiEndpoints.savePricing) || 
                             (window.App && App.apiEndpoints && App.apiEndpoints.savePricing);
              if (!endpoint) { throw new Error('Pricing endpoint not found'); }
              var resp = await fetch(endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
              });
              if (resp.status === 422) {
                var err = await resp.json().catch(function(){ return {}; });
                var msgs = [];
                if (err && err.errors){
                  Object.keys(err.errors).forEach(function(k){
                    (Array.isArray(err.errors[k])? err.errors[k] : [String(err.errors[k]||'')]).forEach(function(m){ if (m) msgs.push(m); });
                  });
                }
                showErrorToast(msgs.length? msgs : 'اعتبارسنجی ناموفق');
                return;
              }
              var data = await resp.json().catch(function(){ return {}; });
              if (!resp.ok) {
                var msg = (data && (data.message||data.error)) ? (data.message||data.error) : ('خطای سرور ('+resp.status+')');
                showErrorToast(msg);
                return;
              }
              if (data && (data.ok || data.success)) {
                if (!triggerToast('success', 'موفق', (data && data.message) ? data.message : 'قیمت‌ها ذخیره شد', 2200) && typeof showToast === 'function') {
                  showToast('ذخیره شد', 'success');
                }
              } else {
                var m = (data && data.message) ? data.message : 'ذخیره ناموفق';
                showErrorToast(m);
              }
            } catch(e) {
              showErrorToast(e && e.message ? e.message : 'خطای ارتباط');
            } finally { toggle(false); }
          });
        }
      } catch(_) {}
    })();

    // Common pick fields and helpers
    var SAVE_ENDPOINT = (window.RMS && RMS.apiEndpoints && RMS.apiEndpoints.saveBasic)
      ? RMS.apiEndpoints.saveBasic
      : ((window.App && App.apiEndpoints && App.apiEndpoints.saveBasic) ? App.apiEndpoints.saveBasic : null);
    var PICK_FIELDS = ['name','slug','sku','category_id','brand_id','active','point_per_unit','cost_cny','sale_price_cny','discount_type','discount_value','stock_qty','short_desc','description'];
    function collectParams(){
      var fd = new URLSearchParams();
      PICK_FIELDS.forEach(function(n){ var el = document.querySelector('[name="'+n+'"]'); if (!el) return; if (el.type==='checkbox') fd.set(n, el.checked? '1':'0'); else fd.set(n, el.value||''); });
      return fd;
    }
    function snapshot(){
      var obj = {};
      PICK_FIELDS.forEach(function(n){ var el = document.querySelector('[name="'+n+'"]'); if (!el) return; obj[n] = (el.type==='checkbox') ? (el.checked? '1':'0') : (el.value||''); });
      try { return JSON.stringify(obj); } catch(e){ return ''+Date.now(); }
    }
    function clearFieldErrors(){
      PICK_FIELDS.forEach(function(n){
        var el = document.querySelector('[name="'+n+'"]'); if (!el) return;
        el.classList.remove('is-invalid');
        var next = el.nextElementSibling;
        if (next && next.classList.contains('invalid-feedback') && next.classList.contains('js-inline')) { next.remove(); }
      });
    }
    function applyFieldError(name, messages){
      var el = document.querySelector('[name="'+name+'"]'); if (!el) return;
      el.classList.add('is-invalid');
      var msg = Array.isArray(messages) ? messages[0] : String(messages||'خطای اعتبارسنجی');
      var fb = document.createElement('div'); fb.className = 'invalid-feedback js-inline'; fb.textContent = msg;
      if (el.parentElement && el.parentElement.classList.contains('input-group')){
        el.parentElement.appendChild(fb);
      } else {
        el.insertAdjacentElement('afterend', fb);
      }
    }
    function triggerToast(type, title, message, duration){
      if (window.showToastMessage){
        window.showToastMessage(type || 'info', title || '', message || '', duration || 3500);
        return true;
      }
      if (typeof showToast === 'function'){
        showToast(message || title || '', type || 'info');
        return true;
      }
      return false;
    }
    function showErrorToast(lines){
      var msgArray = Array.isArray(lines) ? lines.filter(Boolean) : [];
      var message = msgArray.length ? msgArray.join(' - ') : (typeof lines === 'string' ? lines : 'خطا در ذخیره');
      if (triggerToast('danger', 'خطا', message, 4500)) return;
      var t2 = document.createElement('div'); t2.className='alert alert-danger position-fixed start-0 top-0 m-3'; t2.style.zIndex = '2000';
      if (msgArray.length){
        var ul = document.createElement('ul'); ul.className='mb-0 ps-3';
        msgArray.forEach(function(l){ var li=document.createElement('li'); li.textContent=l; ul.appendChild(li); });
        t2.appendChild(ul);
      } else {
        t2.textContent = message;
      }
      document.body.appendChild(t2); setTimeout(function(){ t2.remove(); }, 4000);
    }

    // Save & Stay (basic tab) - only on edit
    var saveStayBtn = document.getElementById('btn-save-stay');
    var isSaving = false;
    
    // Delete product button with confirm modal 🗑️
    document.querySelectorAll('[data-action="delete-product"]').forEach(function(btn) {
      btn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        var productName = btn.getAttribute('data-product-name') || 'این محصول';
        var deleteUrl = btn.getAttribute('data-delete-url') || btn.href;
        
        if (!deleteUrl) {
          console.error('❌ Delete URL not found for product');
          return;
        }
        
        try {
          const confirmed = await confirmDelete(productName);
          if (!confirmed) return;
          
          // نمایش loading
          btn.disabled = true;
          var originalText = btn.innerHTML;
          btn.innerHTML = '<i class="ph-spinner ph-spin me-2"></i>در حال حذف...';
          
          // ارسال درخواست حذف
          var formData = new FormData();
          formData.append('_method', 'DELETE');
          formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
          
          var response = await fetch(deleteUrl, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          
          if (response.ok) {
            // موفقیت - هدایت به لیست
            if (window.showToastMessage) {
              window.showToastMessage('success', '✅ حذف موفق', 'محصول با موفقیت حذف شد', 2000);
            }
            setTimeout(() => {
              window.location.href = '/admin/shop/products';
            }, 500);
          } else {
            throw new Error('خطا در حذف محصول');
          }
          
        } catch (error) {
          console.error('❌ Delete error:', error);
          btn.disabled = false;
          btn.innerHTML = originalText;
          
          if (window.showToastMessage) {
            window.showToastMessage('danger', '❌ خطا', 'خطا در حذف محصول', 4000);
          } else {
            var errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger position-fixed start-0 top-0 m-3'; errorDiv.style.zIndex='2000';
            errorDiv.textContent = 'خطا در حذف محصول';
            document.body.appendChild(errorDiv);
            setTimeout(() => errorDiv.remove(), 4000);
          }
        }
      });
    });
    
    // Bulk operations with confirm modal 📦
    document.querySelectorAll('[data-action="bulk-operation"]').forEach(function(btn) {
      btn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        var operation = btn.getAttribute('data-operation');
        var title = btn.getAttribute('data-confirm-title') || '⚠️ تایید عملیات';
        var message = btn.getAttribute('data-confirm-message') || 'آیا مطمئن هستید؟';
        var actionUrl = btn.getAttribute('data-url') || btn.href;
        
        if (!actionUrl) {
          console.error('❌ Action URL not found');
          return;
        }
        
        try {
          // استفاده از confirm modal سفارشی
          let confirmOptions = {
            icon: 'ph-gear',
            confirmText: 'بله، انجام بده',
            confirmClass: 'btn-primary'
          };
          
          // سفارشی کردن بر اساس نوع عملیات
          if (operation === 'delete-all') {
            confirmOptions.icon = 'ph-trash';
            confirmOptions.confirmClass = 'btn-danger';
            confirmOptions.confirmText = 'بله، حذف کن';
          } else if (operation === 'activate-all') {
            confirmOptions.icon = 'ph-check-circle';
            confirmOptions.confirmClass = 'btn-success';
            confirmOptions.confirmText = 'بله، فعال کن';
          } else if (operation === 'deactivate-all') {
            confirmOptions.icon = 'ph-x-circle';
            confirmOptions.confirmClass = 'btn-warning';
            confirmOptions.confirmText = 'بله، غیرفعال کن';
          }
          
          const confirmed = await confirmAction(title, message, confirmOptions);
          if (!confirmed) return;
          
          // نمایش loading
          btn.disabled = true;
          var originalText = btn.innerHTML;
          btn.innerHTML = '<i class="ph-spinner ph-spin me-2"></i>در حال پردازش...';
          
          // ارسال درخواست
          var response = await fetch(actionUrl, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });
          
          if (response.ok) {
            var data = await response.json();
            if (window.showToastMessage) {
              window.showToastMessage('success', '✅ موفق', data.message || 'عملیات با موفقیت انجام شد', 3000);
            }
            
            // بازخوانی صفحه اگر نیاز باشد
            if (btn.getAttribute('data-reload') === 'true') {
              setTimeout(() => window.location.reload(), 1000);
            }
          } else {
            throw new Error('خطا در انجام عملیات');
          }
          
        } catch (error) {
          console.error('❌ Bulk operation error:', error);
          
          if (window.showToastMessage) {
            window.showToastMessage('danger', '❌ خطا', error.message || 'خطا در انجام عملیات', 4000);
          }
        } finally {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      });
    });
    
    // Active/Inactive toggle with confirm 🔄
    document.querySelectorAll('[data-action="toggle-active"]').forEach(function(toggle) {
      toggle.addEventListener('change', async function(e) {
        var productId = toggle.getAttribute('data-product-id');
        var productName = toggle.getAttribute('data-product-name') || 'محصول';
        var currentState = toggle.checked;
        var actionText = currentState ? 'فعال' : 'غیرفعال';
        
        try {
          const confirmed = await confirmAction(
            `🔄 ${actionText} کردن محصول`,
            `آیا می‌خواهید محصول «${productName}» را ${actionText} کنید؟`,
            {
              icon: currentState ? 'ph-check-circle' : 'ph-x-circle',
              confirmClass: currentState ? 'btn-success' : 'btn-warning',
              confirmText: `بله، ${actionText} کن`
            }
          );
          
          if (!confirmed) {
            // برگرداندن حالت قبلی
            toggle.checked = !currentState;
            return;
          }
          
          // ارسال درخواست تغییر وضعیت
          var response = await fetch(`/admin/shop/products/${productId}/toggle-active`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({ active: currentState })
          });
          
          if (response.ok) {
            var data = await response.json();
            if (window.showToastMessage) {
              window.showToastMessage('success', '✅ تغییر وضعیت', `محصول ${actionText} شد`, 2000);
            }
          } else {
            throw new Error(`خطا در ${actionText} کردن محصول`);
          }
          
        } catch (error) {
          console.error('❌ Toggle error:', error);
          // برگرداندن حالت قبلی
          toggle.checked = !currentState;
          
          if (window.showToastMessage) {
            window.showToastMessage('danger', '❌ خطا', error.message, 3000);
          }
        }
      });
    });

    if (saveStayBtn && SAVE_ENDPOINT){
      saveStayBtn.addEventListener('click', async function(){
        var label = saveStayBtn.querySelector('.label');
        var loading = saveStayBtn.querySelector('.loading');
        try{
          isSaving = true;
          saveStayBtn.disabled = true; label.classList.add('d-none'); loading.classList.remove('d-none');
          var fd = collectParams();
          var resp = await fetch(SAVE_ENDPOINT, { method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString()});
          if (resp.status === 422){
            var err = await resp.json().catch(function(){ return {}; });
            clearFieldErrors();
            var allMsgs = [];
            if (err && err.errors){
              Object.keys(err.errors).forEach(function(k){ applyFieldError(k, err.errors[k]); allMsgs = allMsgs.concat(err.errors[k]); });
            }
            showErrorToast(allMsgs);
            return; // don't proceed
          }
          var data = await resp.json();
          if (!resp.ok || !data.ok) throw new Error(data.message||'خطا در ذخیره');
          lastSnap = snapshot();
          // toast
          var t = document.createElement('div'); t.className='alert alert-success position-fixed start-0 top-0 m-3'; t.style.zIndex='2000'; t.textContent='ذخیره شد'; document.body.appendChild(t); setTimeout(function(){ t.remove(); }, 2000);
        }catch(e){
          showErrorToast(e && e.message ? e.message : 'خطا در ذخیره');
        } finally {
          isSaving = false;
          saveStayBtn.disabled = false; label.classList.remove('d-none'); loading.classList.add('d-none');
        }
      });
    }

    // Flash messages via JS variable injected from server
    try {
      var flash = (window.RMS && RMS.flash) ? RMS.flash : null;
      if (flash) {
        if (flash.success) {
          if (window.showToastMessage) { window.showToastMessage('success', 'موفقیت', String(flash.success), 3500); }
          else { var s = document.createElement('div'); s.className='alert alert-success position-fixed start-0 top-0 m-3'; s.style.zIndex='2000'; s.textContent=String(flash.success); document.body.appendChild(s); setTimeout(function(){ s.remove(); }, 3500);}        
        }
        if (flash.error) {
          if (window.showToastMessage) { window.showToastMessage('danger', 'خطا', String(flash.error), 6000); }
          else { var e = document.createElement('div'); e.className='alert alert-danger position-fixed start-0 top-0 m-3'; e.style.zIndex='2000'; e.textContent=String(flash.error); document.body.appendChild(e); setTimeout(function(){ e.remove(); }, 6000);}        
        }
      }
    } catch(_) {}

    // Autosave every 20s if dirty (only when endpoint exists)
    var lastSnap = snapshot();
    if (SAVE_ENDPOINT){
      setInterval(async function(){
        try{
          if (isSaving) return;
          var nowSnap = snapshot();
          if (nowSnap === lastSnap) return;
          isSaving = true;
          var fd = collectParams();
          var resp = await fetch(SAVE_ENDPOINT, { method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString()});
          if (resp.status === 422){
            var err = await resp.json().catch(function(){ return {}; });
            var msgs = [];
            if (err && err.errors){
              Object.keys(err.errors).forEach(function(k){
                (Array.isArray(err.errors[k])? err.errors[k] : [String(err.errors[k]||'')]).forEach(function(m){ if (m) msgs.push(m); });
              });
            }
            throw new Error((msgs && msgs.length)? msgs.join(' | ') : 'اعتبارسنجی ناموفق');
          }
          var data = await resp.json().catch(function(){ return {}; });
          if (!resp.ok || !data.ok) throw new Error(data.message||('خطا در ذخیره خودکار ('+resp.status+')'));
          lastSnap = nowSnap;
          var t = document.createElement('div'); t.className='alert alert-success position-fixed start-0 top-0 m-3 py-1 px-2 small'; t.style.zIndex='2000'; t.textContent='ذخیره خودکار شد'; document.body.appendChild(t); setTimeout(function(){ t.remove(); }, 1500);
        }catch(e){
          var t2 = document.createElement('div'); t2.className='alert alert-danger position-fixed start-0 top-0 m-3 py-1 px-2 small'; t2.style.zIndex='2000'; t2.textContent='خطا در ذخیره خودکار: '+ (e && e.message ? e.message : 'نامشخص'); document.body.appendChild(t2); setTimeout(function(){ t2.remove(); }, 3000);
        } finally {
          isSaving = false;
        }
      }, 20000);
    }

    // Images tab: AVIF regeneration handler (extracted from Blade)
    (function initAvifRegeneration(){
      var avifBtn = document.getElementById('btn-regenerate-avif');
      if (!avifBtn) return;
      avifBtn.addEventListener('click', async function() {
        var productId = this.getAttribute('data-product-id');
        if (!productId) return;
        var confirmed = false;
        try {
          if (typeof confirmAction === 'function') {
            confirmed = await confirmAction('بازسازی AVIF', 'آیا می‌خواهید تمام تصاویر این محصول را دوباره به AVIF تبدیل کنید؟', { 
              icon: 'ph-magic-wand', 
              confirmClass: 'btn-success', 
              confirmText: 'بازسازی' 
            });
          } else {
            confirmed = window.confirm('بازسازی تصاویر AVIF؟');
          }
        } catch(_) {}
        if (!confirmed) return;

        // Loading UI
        this.disabled = true;
        var iconEl = document.getElementById('avif-icon');
        var spinnerEl = document.getElementById('avif-spinner');
        var statusEl = document.getElementById('avif-status');
        if (iconEl) iconEl.classList.add('d-none');
        if (spinnerEl) spinnerEl.classList.remove('d-none');
        if (statusEl) statusEl.innerHTML = '<i class="ph-clock me-1"></i>در حال پردازش...';
        try {
          var token = (document.querySelector('meta[name="csrf-token"]')||{}).content || '';
          var response = await fetch('/admin/shop_products/'+productId+'/regenerate-avif', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': token,
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          var data = await response.json().catch(function(){ return {}; });
          if (data && data.success) {
            if (statusEl) statusEl.innerHTML = '<i class="ph-check-circle me-1 text-success"></i>'+ (data.message||'موفق');
            if (typeof showToast === 'function') { showToast('success', data.message||'موفق'); }
          } else {
            if (statusEl) statusEl.innerHTML = '<i class="ph-x-circle me-1 text-danger"></i>خطا'+ (data && data.message? (': '+data.message):'');
          }
        } catch (error) {
          if (statusEl) statusEl.innerHTML = '<i class="ph-x-circle me-1 text-danger"></i>خطای ارتباط';
        } finally {
          this.disabled = false;
          if (spinnerEl) spinnerEl.classList.add('d-none');
          if (iconEl) iconEl.classList.remove('d-none');
        }
      });
    })();
  });
})();
