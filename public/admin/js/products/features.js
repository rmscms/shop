(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var G = (window.RMS || window.App || {});
    var root = document.getElementById('features-root');
    var addBtn = document.getElementById('btn-add-feature');
    var addCategoryBtn = document.getElementById('btn-add-category');
    var saveBtn = document.getElementById('btn-save-features');
    if (!root || !saveBtn) return;
    
    var featureCategories = G.data?.featureCategories || [];
    var featuresData = G.data?.features || [];

    function rowTpl(item){
      item = item || { name:'', value:'', sort:'' };
      return '<tr>'+
        '<td><input type="text" class="form-control form-control-sm feat-name" placeholder="عنوان" value="'+(item.name||'').replace(/"/g,'&quot;')+'"></td>'+
        '<td><input type="text" class="form-control form-control-sm feat-value" placeholder="مقدار" value="'+(item.value||'').replace(/"/g,'&quot;')+'"></td>'+
        '<td style="width:100px"><input type="number" class="form-control form-control-sm feat-sort" min="0" value="'+(item.sort ?? '')+'"></td>'+
        '<td style="width:60px"><button type="button" class="btn btn-link text-danger p-0 btn-remove" title="حذف"><i class="ph-trash"></i></button></td>'+
      '</tr>';
    }

    function bindRowEvents(tr){
      var del = tr.querySelector('.btn-remove');
      del && del.addEventListener('click', function(){ tr.remove(); });
      
      // Auto-save on input change 🚀
      var nameInput = tr.querySelector('.feat-name');
      var valueInput = tr.querySelector('.feat-value');
      var sortInput = tr.querySelector('.feat-sort');
      
      [nameInput, valueInput, sortInput].forEach(function(inp){
        if (!inp) return;
        inp.addEventListener('input', debounce(function(){
          autoSave();
        }, 800)); // 800ms تاخیر برای جلوگیری از ارسال مکرر
      });
    }

    function addRow(item){
      var temp = document.createElement('tbody');
      temp.innerHTML = rowTpl(item);
      var tr = temp.firstElementChild;
      root.appendChild(tr);
      bindRowEvents(tr); // این event binding شامل auto-save هم میشه
    }

    // init with existing features
    var items = (G.data && G.data.features) || (G.features || []);
    (items||[]).forEach(addRow);
    if (!items || items.length===0) addRow({});

    addBtn && addBtn.addEventListener('click', function(){ addRow({}); });

    async function save(){
      var url = saveBtn.getAttribute('data-save-url');
      var rows = Array.from(root.querySelectorAll('tr'));
      var features = rows.map(function(tr){
        return {
          name: (tr.querySelector('.feat-name')?.value||'').trim(),
          value: (tr.querySelector('.feat-value')?.value||'').trim(),
          sort: parseInt(tr.querySelector('.feat-sort')?.value||'0',10) || 0,
        };
      }).filter(function(x){ return x.name!=='' || x.value!==''; });
      // UI state
      saveBtn.disabled = true; saveBtn.classList.add('disabled');
      var lab = saveBtn.querySelector('.save-label'); var ld = saveBtn.querySelector('.save-loading');
      lab && lab.classList.add('d-none'); ld && ld.classList.remove('d-none');
      try{
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var res = await fetch(url, { method:'POST', headers:{'X-CSRF-TOKEN':token,'Accept':'application/json','Content-Type':'application/json'}, body: JSON.stringify({ features: features }) });
        var data = await res.json().catch(function(){ return {}; });
        if (res.ok && data.ok){
          var t = document.createElement('div'); t.className='alert alert-success position-fixed end-0 top-0 m-3'; t.textContent='ذخیره شد'; document.body.appendChild(t); setTimeout(function(){ t.remove(); }, 1800);
        } else {
          var t2 = document.createElement('div'); t2.className='alert alert-danger position-fixed end-0 top-0 m-3'; t2.textContent='خطا در ذخیره'; document.body.appendChild(t2); setTimeout(function(){ t2.remove(); }, 2200);
        }
      } finally {
        saveBtn.disabled = false; saveBtn.classList.remove('disabled');
        lab && lab.classList.remove('d-none'); ld && ld.classList.add('d-none');
      }
    }

    saveBtn.addEventListener('click', save);
    
    // Auto-save function 💾
    async function autoSave(){
      var url = saveBtn.getAttribute('data-save-url');
      if (!url) return;
      
      var rows = Array.from(root.querySelectorAll('tr'));
      var features = rows.map(function(tr){
        return {
          name: (tr.querySelector('.feat-name')?.value||'').trim(),
          value: (tr.querySelector('.feat-value')?.value||'').trim(),
          sort: parseInt(tr.querySelector('.feat-sort')?.value||'0',10) || 0,
        };
      }).filter(function(x){ return x.name!=='' || x.value!==''; });
      
      try{
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var res = await fetch(url, { method:'POST', headers:{'X-CSRF-TOKEN':token,'Accept':'application/json','Content-Type':'application/json'}, body: JSON.stringify({ features: features }) });
        var data = await res.json().catch(function(){ return {}; });
        
        // نمایش پیام کوچک موفقیت
        if (res.ok && data.ok){
          showAutoSaveToast('ذخیره شد ✅');
        }
      } catch(e) {
        console.log('Auto-save error:', e);
      }
    }
    
    // Debounce helper
    function debounce(func, wait) {
      var timeout;
      return function executedFunction() {
        var args = arguments;
        var later = function() {
          clearTimeout(timeout);
          func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
    
    // Toast notification for auto-save
    function showAutoSaveToast(message) {
      var existingToast = document.querySelector('.auto-save-toast');
      if (existingToast) existingToast.remove();
      
      var toast = document.createElement('div');
      toast.className = 'auto-save-toast alert alert-success position-fixed';
      toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 8px 12px; font-size: 12px; opacity: 0.9; transition: opacity 0.3s;';
      toast.textContent = message;
      
      document.body.appendChild(toast);
      setTimeout(function(){
        toast.style.opacity = '0';
        setTimeout(function(){ toast.remove(); }, 300);
      }, 1500);
    }
    
  });
})();
