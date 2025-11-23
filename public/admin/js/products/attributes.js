(function(){
  const G = (window.RMS || window.App || {});
  const state = {
    attributes: [],
    combinations: [],
    rate: (G && Number(G.currencyRate)) || null,
    imageCounts: (G && G.images && G.images.counts) || {},
  };
  const AUTOCOMPLETE_MIN_TERM = 3;
  const attrEndpoints = {
    searchDefinitions: G.endpoints?.searchAttributeDefinitions || null,
    searchValues: G.endpoints?.searchAttributeValues || null,
    createDefinition: G.endpoints?.createAttributeDefinition || null,
    createValue: G.endpoints?.createAttributeValue || null,
  };

function debounce(fn, wait = 250) {
  let timeout;
  return function (...args) {
    const context = this;
    clearTimeout(timeout);
    timeout = setTimeout(() => fn.apply(context, args), wait);
  };
}

  function setupAutocomplete({ input, results, minChars = AUTOCOMPLETE_MIN_TERM, fetcher, formatter, onSelect, onClear }) {
    // Check if already setup
    if (input.hasAttribute('data-autocomplete-setup')) {
      return;
    }

    if (!input || !results || typeof fetcher !== 'function') {
      return;
    }
    const wrapper = input.closest('.position-relative') || input.parentElement;
    if (wrapper) {
      wrapper.classList.add('position-relative');
    }

    // Mark as setup
    input.setAttribute('data-autocomplete-setup', 'true');
    const hide = () => {
      results.classList.add('d-none');
      results.innerHTML = '';
    };
    const render = (items) => {
      if (!items || !items.length) {
        hide();
        return;
      }
      results.innerHTML = items.map((item) => `
        <button type="button" class="list-group-item list-group-item-action" data-payload='${JSON.stringify(item)}'>
          ${formatter ? formatter(item) : escapeHtml(item.name || item.value || '')}
        </button>
      `).join('');
      results.classList.remove('d-none');
      results.style.display = 'block';
    };
    const debouncedFetch = debounce(async (term) => {
      try {
        const list = await fetcher(term);
        render(list || []);
      } catch (err) {
        console.error(err);
        hide();
      }
    }, 250);
    input.addEventListener('input', function () {
      if (typeof onClear === 'function') onClear();
      const term = input.value.trim();
      if (term.length < minChars) {
        hide();
        return;
      }
      debouncedFetch(term);
    });
    results.addEventListener('click', function (event) {
      const btn = event.target.closest('button[data-payload]');
      if (!btn) return;
      const payload = JSON.parse(btn.dataset.payload || '{}');
      if (typeof onSelect === 'function') onSelect(payload);
      hide();
    });
    document.addEventListener('click', function (event) {
      if (!results.contains(event.target) && event.target !== input) {
        hide();
      }
    });
  }

  async function showConfirmModal(options){
    const defaults = {
      title: 'تایید عملیات',
      message: 'آیا مطمئن هستید؟',
      description: null,
      icon: 'ph-question',
      confirmText: 'بله، تایید کن',
      confirmClass: 'btn-primary',
      confirmIcon: 'ph-check',
      cancelText: 'انصراف',
      cancelClass: 'btn-outline-secondary',
      cancelIcon: 'ph-x'
    };
    const cfg = Object.assign({}, defaults, options || {});
    if (typeof window.RMSConfirmModal === 'function') {
      const modal = new window.RMSConfirmModal({
        title: cfg.title,
        message: cfg.message,
        description: cfg.description,
        icon: cfg.icon,
        confirmText: cfg.confirmText,
        confirmClass: cfg.confirmClass,
        confirmIcon: cfg.confirmIcon || cfg.icon || 'ph-check',
        cancelText: cfg.cancelText,
        cancelClass: cfg.cancelClass,
        cancelIcon: cfg.cancelIcon || 'ph-x'
      });
      return await modal.show();
    }
    if (typeof window.confirmAction === 'function') {
      return await window.confirmAction(cfg.title, cfg.message, cfg);
    }
    return window.confirm(cfg.message);
  }

  window.addEventListener('rms:combination-image-assigned', (event)=>{
    if (!event || !event.detail) return;
    const detail = event.detail;
    const rawId = typeof detail.combinationId !== 'undefined' ? detail.combinationId : detail.combination_id;
    if (rawId === undefined || rawId === null || rawId === '') return;
    const numericId = Number(rawId);
    const key = Number.isNaN(numericId) ? rawId : numericId;
    const serverCount = (detail && typeof detail.count !== 'undefined' && detail.count !== null) ? Number(detail.count) : null;
    const current = Number(state.imageCounts[key] || 0);
    const nextCount = (serverCount !== null && !Number.isNaN(serverCount)) ? serverCount : current + 1;
    if (Number.isNaN(nextCount)) return;
    state.imageCounts[key] = nextCount;
    try { renderCombinations(); } catch (_e) {}
  });

  function uid(prefix){ return prefix + '_' + Math.random().toString(36).slice(2,9); }
  function qs(s,root=document){ return root.querySelector(s); }
  function qsa(s,root=document){ return Array.from(root.querySelectorAll(s)); }

  function loadInitial(){
    const data = (G && G.data) || {};
    if (Array.isArray(data.attributes)) state.attributes = normalizeAttributes(data.attributes);
    if (Array.isArray(data.combinations)) state.combinations = normalizeCombinations(data.combinations);
    renderAttributes();
    renderCombinations();
    hydrateCombinationSelector();
  }

  function normalizeAttributes(items){
    return (items||[]).map((a,i)=>({
      id: a.id||undefined,
      tmpId: uid('attr'),
      name: a.name||'',
      type: a.type||'text', // text|color|image
      ui: a.ui||'pill',     // pill|radio|select
      sort: a.sort||i,
      attribute_definition_id: a.attribute_definition_id ?? a.definition_id ?? null,
      values: (a.values||[]).map((v,j)=>({
        id: v.id||undefined,
        tmpId: uid('val'),
        value: v.value||'',
        image_path: v.image_path||null,
        color: v.color||null,
        sort: v.sort||j,
        definition_value_id: v.definition_value_id ?? null,
      }))
    }));
  }
  function normalizeCombinations(items){
    return (items||[]).map((c)=>({
      id: c.id||undefined,
      tmpId: uid('cmb'),
      sku: c.sku||'',
      attribute_value_ids: c.attribute_value_ids || [],
      price: (c.price !== undefined ? c.price : (c.sale_price !== undefined ? c.sale_price : null)),
      price_cny: (c.price_cny !== undefined ? c.price_cny : (c.sale_price_cny !== undefined ? c.sale_price_cny : null)),
      stock: (c.stock !== undefined ? c.stock : (c.stock_qty !== undefined ? c.stock_qty : 0)),
      active: typeof c.active==='boolean'? c.active : true
    }));
  }

  function renderAttributes(){
    const root = qs('#attributes-root');
    if (!root) return;
    root.innerHTML = '';
    if (state.attributes.length===0){
      const empty = document.createElement('div');
      empty.className='col-12';
      empty.innerHTML = '<div class="text-center py-4 text-muted"><i class="ph-sliders display-4 mb-2"></i><div>هیچ ویژگی‌ای تعریف نشده است. روی دکمه "+" کلیک کنید.</div></div>';
      root.appendChild(empty);
    }
    state.attributes.sort((a,b)=>a.sort-b.sort).forEach((attr)=>{
      if (!Array.isArray(attr.values)) attr.values = [];
      const hasDefinition = !!attr.attribute_definition_id;
      const col = document.createElement('div');
      col.className='col-xl-4 col-lg-6 col-md-12 mb-3';
      col.innerHTML = `
        <div class="card h-100 border attribute-card">
          <div class="card-header bg-light">
            <div class="position-relative">
              <div class="d-flex align-items-center gap-2 mb-2">
                <input type="text" class="form-control form-control-sm attr-name flex-grow-1" value="${escapeHtml(attr.name)}" placeholder="${tr('shop.attributes.name_placeholder','نام ویژگی')}" data-definition-id="${hasDefinition ? attr.attribute_definition_id : ''}" style="min-width: 60px; height: 30px;">
                <div class="d-flex gap-1">
                  <button type="button" class="btn btn-outline-secondary btn-sm btn-create-definition" title="ایجاد تعریف جدید" style="width: 30px; height: 30px; padding: 0;"><i class="ph-plus"></i></button>
                  <button class="btn btn-danger btn-sm btn-del-attr" title="حذف ویژگی" style="width: 30px; height: 30px; padding: 0;"><i class="ph-trash"></i></button>
                </div>
              </div>
              <div class="autocomplete-results list-group d-none" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; max-height: 200px; overflow-y: auto; background: white; border: 1px solid #dee2e6; border-radius: 0.375rem;"></div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
              <select class="form-select form-select-sm attr-type" style="width: 80px;">
                <option value="text" ${attr.type==='text'?'selected':''}>Text</option>
                <option value="color" ${attr.type==='color'?'selected':''}>Color</option>
                <option value="image" ${attr.type==='image'?'selected':''}>Image</option>
              </select>
              <select class="form-select form-select-sm attr-ui" style="width: 90px;">
                <option value="pill" ${attr.ui==='pill'?'selected':''}>Pill</option>
                <option value="radio" ${attr.ui==='radio'?'selected':''}>Radio</option>
                <option value="select" ${attr.ui==='select'?'selected':''}>Select</option>
              </select>
              ${hasDefinition ? `<small class="text-success"><i class="ph-check me-1"></i>شناسه: #${attr.attribute_definition_id}</small>` : '<small class="text-muted">برای اتصال به تعریف‌های موجود، حداقل ۳ کاراکتر تایپ کنید.</small>'}
            </div>
            <div class="autocomplete-results list-group d-none mt-1" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
          </div>
          <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-1 values-wrap"></div>
            <div class="mt-2">
              <button class="btn btn-outline-primary btn-sm btn-add-value w-100"><i class="ph-plus me-1"></i>${tr('shop.actions.add_value','افزودن مقدار')}</button>
            </div>
          </div>
        </div>
      `;
      const card = col.querySelector('.card');
      card.innerHTML = `
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-2 position-relative">
            <div class="feature-autocomplete flex-grow-1">
              <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm attr-name flex-grow-1" value="${escapeHtml(attr.name)}" placeholder="${tr('shop.attributes.name_placeholder','نام ویژگی')}" data-definition-id="${hasDefinition ? attr.attribute_definition_id : ''}" style="min-width: 60px; height: 30px;">
                <div class="d-flex gap-1">
                  <button type="button" class="btn btn-outline-secondary btn-sm btn-create-definition" title="ایجاد تعریف جدید" style="width: 30px; height: 30px; padding: 0;"><i class="ph-plus"></i></button>
                  <button class="btn btn-danger btn-sm btn-del-attr" title="حذف ویژگی" style="width: 30px; height: 30px; padding: 0;"><i class="ph-trash"></i></button>
                </div>
              </div>
              <div class="autocomplete-results list-group d-none"></div>
            </div>
          </div>
          <div class="form-text small ${hasDefinition ? 'text-success' : 'text-muted'} mb-2">
            ${hasDefinition ? `شناسه متصل: #${attr.attribute_definition_id}` : 'برای اتصال به تعریف‌های موجود، حداقل ۳ کاراکتر تایپ کنید.'}
          </div>
          <div class="row g-2 align-items-center mt-2">
            <div class="col-md-4">
              <label class="form-label mb-0 small">نوع مقدار</label>
              <select class="form-select form-select-sm attr-type">
                <option value="text" ${attr.type==='text'?'selected':''}>متنی</option>
                <option value="color" ${attr.type==='color'?'selected':''}>رنگ</option>
                <option value="image" ${attr.type==='image'?'selected':''}>تصویر</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-0 small">نحوه نمایش</label>
              <select class="form-select form-select-sm attr-ui">
                <option value="pill" ${attr.ui==='pill'?'selected':''}>چیپ</option>
                <option value="radio" ${attr.ui==='radio'?'selected':''}>radio</option>
                <option value="select" ${attr.ui==='select'?'selected':''}>select</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-0 small">ترتیب</label>
              <input type="number" class="form-control form-control-sm attr-sort" value="${Number(attr.sort||0)}">
            </div>
          </div>
        </div>
        <div class="values-wrap vstack gap-2"></div>
        <div class="mt-2">
          <button type="button" class="btn btn-light btn-sm btn-add-value"><i class="ph-plus me-1"></i>${tr('shop.actions.add_value','افزودن مقدار')}</button>
        </div>
      `;
      const attrNameInput = qs('.attr-name', card);
      const attrNameResults = card.querySelector('.autocomplete-results');
      const valuesWrap = qs('.values-wrap', card);
      attr.values.sort((a,b)=>a.sort-b.sort).forEach((v)=>{
        const chip = document.createElement('div');
        chip.className='d-flex align-items-center gap-1 border rounded px-2 py-1 value-row';
        chip.setAttribute('data-value-tmp-id', v.tmpId);
        chip.innerHTML = `
          <div class="feature-autocomplete flex-grow-1">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control form-control-sm value-input" placeholder="مقدار" value="${escapeHtml(v.value)}" data-value-definition-id="${v.definition_value_id || ''}" style="min-width: 80px;">
              <button type="button" class="btn btn-outline-secondary btn-sm btn-create-value" title="${hasDefinition ? 'ایجاد مقدار ثابت' : 'ابتدا نام ویژگی را انتخاب کنید'}" ${!hasDefinition ? 'disabled' : ''}><i class="ph-plus"></i></button>
            </div>
            <div class="autocomplete-results list-group d-none"></div>
            <div class="invalid-feedback"></div>
          </div>
          ${attr.type==='color' ? '<div class="color-picker-wrapper position-relative"><input type="text" class="form-control form-control-sm value-color-text" value="'+(v.color || '#000000')+'" placeholder="#000000" style="width: 80px;"><div class="color-preview" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; border-radius: 3px; border: 1px solid #ccc; background: '+(v.color || '#000000')+'; cursor: pointer;" title="انتخاب رنگ"></div></div>' : ''}
          <button type="button" class="btn btn-outline-danger btn-sm btn-del-value" title="حذف مقدار"><i class="ph-x"></i></button>
        `;
        const valueInput = chip.querySelector('.value-input');
        const valueResults = chip.querySelector('.autocomplete-results');
        const createValueBtn = chip.querySelector('.btn-create-value');
        const colorTextEl = chip.querySelector('.value-color-text');
        const colorPreviewEl = chip.querySelector('.color-preview');
        createValueBtn.disabled = !hasDefinition || !!v.definition_value_id;

        // Color picker functionality
        if (colorTextEl && colorPreviewEl) {
          // Update preview when text changes
          colorTextEl.addEventListener('input', (e)=>{
            const color = e.target.value;
            if (/^#[0-9A-F]{6}$/i.test(color)) {
              colorPreviewEl.style.background = color;
              v.color = color;
            }
          });

          // Show color picker on preview click
          colorPreviewEl.addEventListener('click', (e)=>{
            e.stopPropagation();
            showColorPicker(colorPreviewEl, (selectedColor) => {
              colorTextEl.value = selectedColor;
              colorPreviewEl.style.background = selectedColor;
              v.color = selectedColor;
            });
          });

          // Initialize
          if (v.color) {
            colorTextEl.value = v.color;
            colorPreviewEl.style.background = v.color;
          }
        }
        valueInput?.addEventListener('input', (e)=>{
          v.value = e.target.value;
          v.definition_value_id = null;
          // Re-enable create button when user types (clears selection)
          createValueBtn.disabled = !hasDefinition || !!v.definition_value_id;
          createValueBtn.title = hasDefinition ? 'ایجاد مقدار ثابت' : 'ابتدا نام ویژگی را انتخاب کنید';
        });
        chip.querySelector('.btn-del-value')?.addEventListener('click', (e)=>{
          e.preventDefault();
          attr.values = attr.values.filter(x=>x!==v);
          renderAttributes();
        });
        setupAutocomplete({
          input: valueInput,
          results: valueResults,
          minChars: AUTOCOMPLETE_MIN_TERM,
          fetcher: async (term) => {
            if (!attr.attribute_definition_id || !attrEndpoints.searchValues) return [];
            if (term.length < AUTOCOMPLETE_MIN_TERM) return [];
            const url = new URL(attrEndpoints.searchValues, window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('definition_id', attr.attribute_definition_id);
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(()=> ({}));
            return data?.data || [];
          },
          formatter: (item) => `<div class="d-flex justify-content-between align-items-center"><span>${escapeHtml(item.value || '')}</span>${item.color ? '<span class="badge bg-secondary" style="background:'+item.color+'!important;">&nbsp;</span>' : ''}</div>`,
          onSelect: (item) => {
            console.log('Value onSelect triggered:', item, 'createValueBtn:', createValueBtn);
            // Check if this value already exists in current attribute values
            const existingValue = attr.values.find(val =>
              val !== v && // Not the current value
              (val.definition_value_id === item.id || val.value === item.value)
            );
            if (existingValue) {
              // Show error on input instead of toast
              const valueInput = chip.querySelector('.value-input');
              valueInput.classList.add('is-invalid');
              let errorDiv = chip.querySelector('.invalid-feedback');
              if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                valueInput.parentNode.insertBefore(errorDiv, valueInput.nextSibling);
              }
              errorDiv.textContent = `مقدار "${item.value}" در این ویژگی تکراری است`;
              // Remove error after 3 seconds
              setTimeout(() => {
                valueInput.classList.remove('is-invalid');
                if (errorDiv) errorDiv.remove();
              }, 3000);
              return;
            }

            v.value = item.value || '';
            v.definition_value_id = item.id || null;
            if (attr.type === 'color' && item.color) { v.color = item.color; }
            renderAttributes();
            // Disable create button after render
            setTimeout(() => {
              const valueRow = document.querySelector(`[data-value-tmp-id="${v.tmpId}"]`);
              const updatedBtn = valueRow?.querySelector('.btn-create-value');
              if (updatedBtn) {
                console.log('Disabling createValueBtn after render for tmpId:', v.tmpId);
                updatedBtn.disabled = true;
                updatedBtn.title = 'مقدار متصل است';
              } else {
                console.log('Could not find updatedBtn for tmpId:', v.tmpId);
              }
            }, 0);
          },
          onClear: () => {
            v.definition_value_id = null;
            // Re-enable create button when value is cleared
            createValueBtn.disabled = !hasDefinition;
            createValueBtn.title = hasDefinition ? 'ایجاد مقدار ثابت' : 'ابتدا نام ویژگی را انتخاب کنید';
          },
        });
        createValueBtn?.addEventListener('click', async (e)=>{
          e.preventDefault();
          if (!attr.attribute_definition_id) { toast('ابتدا نام ویژگی را انتخاب کنید', 'warning'); return; }
          if (!attrEndpoints.createValue) { toast('مسیر ایجاد مقدار تنظیم نشده است', 'danger'); return; }
          const term = (v.value || '').trim();
          if (term.length < AUTOCOMPLETE_MIN_TERM) { toast('حداقل ۳ کاراکتر وارد کنید', 'warning'); return; }
          try{
            createValueBtn.disabled = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch(attrEndpoints.createValue, {
              method: 'POST',
              headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': token },
              body: JSON.stringify({
                definition_id: attr.attribute_definition_id,
                value: term,
                color: attr.type==='color' ? (v.color || null) : null,
              }),
            });
            const data = await res.json().catch(()=> ({}));
            if (!res.ok || !data?.value) throw new Error(data?.message || 'خطا در ایجاد مقدار');
            v.definition_value_id = data.value.id;
            v.value = data.value.value || term;
            if (attr.type==='color' && data.value.color) v.color = data.value.color;
            toast(data.message || `مقدار "${v.value}" ذخیره شد`, 'success');
            showSaveSuccess(valueInput);
            renderAttributes();
            // Disable button after successful save
            setTimeout(() => {
              const valueRow = document.querySelector(`[data-value-tmp-id="${v.tmpId}"]`);
              const updatedBtn = valueRow?.querySelector('.btn-create-value');
              if (updatedBtn) {
                updatedBtn.disabled = true;
                updatedBtn.title = 'مقدار ذخیره شده';
              }
            }, 0);
          } catch(err){
            console.error(err);
            toast(err.message || 'خطا در ایجاد مقدار', 'danger');
          } finally {
            createValueBtn.disabled = !attr.attribute_definition_id || !!v.definition_value_id;
          }
        });
        valuesWrap.appendChild(chip);
      });
      qs('.attr-type', card)?.addEventListener('change', (e)=>{
        attr.type = e.target.value;
        updateDefinitionButtonState();
        renderAttributes();
      });
      qs('.attr-ui', card)?.addEventListener('change', (e)=>{
        attr.ui = e.target.value;
        updateDefinitionButtonState();
      });
      qs('.btn-del-attr', card)?.addEventListener('click', async (e)=>{
        e.preventDefault();
        const ok = await showConfirmModal({
          title: 'حذف ویژگی',
          message: 'این ویژگی و مقادیر آن حذف شود؟',
          icon: 'ph-warning',
          confirmClass: 'btn-danger',
          confirmText: 'حذف',
          confirmIcon: 'ph-trash',
        });
        if (!ok) return;
        state.attributes = state.attributes.filter(x=>x!==attr);
        renderAttributes();
        renderCombinations();
        hydrateCombinationSelector();
      });
      qs('.btn-add-value', card)?.addEventListener('click', (e)=>{
        e.preventDefault();
        attr.values.push({
          tmpId: uid('val'),
          value: '',
          image_path: null,
          color: attr.type==='color' ? '#000000' : null,
          sort: (attr.values?.length||0),
          definition_value_id: null,
        });
        renderAttributes();
      });
      // Setup autocomplete immediately
      const finalAttrNameInput = qs('.attr-name', card);
      const finalAttrNameResults = qs('.autocomplete-results', card);
      if (finalAttrNameInput && finalAttrNameResults) {
        // Check if already setup
        if (finalAttrNameInput.hasAttribute('data-autocomplete-setup')) {
          return;
        }
        setupAutocomplete({
          input: finalAttrNameInput,
          results: finalAttrNameResults,
          minChars: AUTOCOMPLETE_MIN_TERM,
        fetcher: async (term) => {
          if (!attrEndpoints.searchDefinitions) return [];
          if (term.length < AUTOCOMPLETE_MIN_TERM) return [];
          const url = new URL(attrEndpoints.searchDefinitions, window.location.origin);
          url.searchParams.set('q', term);
          const res = await fetch(url.toString(), { headers: { 'Accept':'application/json' } });
          const data = await res.json().catch(()=> ({}));
          return data?.data || [];
        },
        formatter: (item) => {
          const type = escapeHtml(item.type || '');
          const ui = escapeHtml(item.ui || '');
          return `<div class="d-flex justify-content-between align-items-center"><span>${escapeHtml(item.name || '')}</span><span class="badge bg-secondary-subtle text-body-secondary">${type}/${ui}</span></div>`;
        },
        onSelect: (item) => {
          attr.name = item.name || '';
          attr.attribute_definition_id = item.id || null;
          attr.type = item.type || attr.type;
          attr.ui = item.ui || attr.ui;
          attr.values.forEach(val => { val.definition_value_id = null; });
          updateDefinitionButtonState();
          renderAttributes();
        },
        onClear: () => {
          attr.attribute_definition_id = null;
          attr.values.forEach(val => { val.definition_value_id = null; });
          updateDefinitionButtonState();
        },
          });
        } else {
          console.log('Autocomplete elements not found!');
        }
      const createDefinitionBtn = qs('.btn-create-definition', card);

      // Function to check if definition exists and update button state
      const updateDefinitionButtonState = async () => {
        const term = (attr.name || '').trim();
        if (hasDefinition) {
          createDefinitionBtn.disabled = true;
          createDefinitionBtn.title = 'تعریف متصل است';
          return;
        }
        // Always enable the button for new definitions - disable only when selected from autocomplete
        createDefinitionBtn.disabled = false;
        createDefinitionBtn.title = term.length < AUTOCOMPLETE_MIN_TERM ? 'حداقل ۳ کاراکتر وارد کنید' : 'ایجاد تعریف جدید';
      };

      // Initial check
      updateDefinitionButtonState();

      // Update button state when user types in attribute name
      attrNameInput?.addEventListener('input', () => {
        attr.name = attrNameInput.value; // Update attribute name
        attr.attribute_definition_id = null; // Clear selection when user types
        attr.values.forEach(val => { val.definition_value_id = null; });
        updateDefinitionButtonState();
      });

      createDefinitionBtn?.addEventListener('click', async (e)=>{
        e.preventDefault();
        if (!attrEndpoints.createDefinition) { toast('مسیر ایجاد تعریف تنظیم نشده است', 'danger'); return; }
        const term = (attr.name || '').trim();
        if (term.length < AUTOCOMPLETE_MIN_TERM) { toast('حداقل ۳ کاراکتر برای نام وارد کنید', 'warning'); return; }
        try{
          createDefinitionBtn.disabled = true;
          const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
          const res = await fetch(attrEndpoints.createDefinition, {
            method: 'POST',
            headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': token },
            body: JSON.stringify({ name: term, type: attr.type || 'text', ui: attr.ui || 'pill' }),
          });
          const data = await res.json().catch(()=> ({}));
          if (!res.ok || !data?.definition) throw new Error(data?.message || 'خطا در ایجاد تعریف');
          attr.attribute_definition_id = data.definition.id;
          attr.name = data.definition.name || term;
          attr.type = data.definition.type || attr.type;
          attr.ui = data.definition.ui || attr.ui;
          attr.values.forEach(val => { val.definition_value_id = null; });
          toast(data.message || `تعریف "${attr.name}" ذخیره شد`, 'success');
          showSaveSuccess(attrNameInput);
          renderAttributes();
        } catch(err){
          console.error(err);
          toast(err.message || 'خطا در ایجاد تعریف', 'danger');
        } finally {
          createDefinitionBtn.disabled = false;
        }
      });
      root.appendChild(col);
    });
    persistHiddenFields();
  }

  function cartesian(arr){
    return arr.reduce((a,b)=> a.flatMap(d => b.map(e => [...d, e])), [[]]);
  }
  function generateCombinations(){
    const sets = state.attributes.map(a=> a.values.filter(v=> (v.value||'').trim()!=='').map(v=> ({attr:a, val:v})) ).filter(s=>s.length>0);
    if (sets.length===0){ state.combinations = []; renderCombinations(); hydrateCombinationSelector(); return; }
    const combos = cartesian(sets);
    const prevMap = new Map(state.combinations.map(c=>[keyOf(c.attribute_value_ids), c]));
    state.combinations = combos.map(items=>{
      const ids = items.map(it=> it.val.id || it.val.tmpId);
      const k = keyOf(ids);
      const old = prevMap.get(k);
      return old || { tmpId: uid('cmb'), sku:'', attribute_value_ids: ids, price:null, price_cny:null, stock:0, active:true };
    });
    renderCombinations();
    hydrateCombinationSelector();
  }
  function keyOf(ids){ return (ids||[]).slice().sort().join('-'); }

  function renderCombinations(){
    const list = qs('#combinations-list');
    if (list) {
      list.innerHTML = '';
      if (state.combinations.length === 0) {
        list.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">هیچ ترکیب‌ای وجود ندارد. ویژگی‌ها را تعریف کنید و سپس ترکیب‌ها را تولید کنید.</td></tr>';
        persistHiddenFields();
        return;
      }

      state.combinations.forEach((c)=>{
        const names = c.attribute_value_ids.map(id=> findValueLabel(id)).filter(Boolean).join(' / ');
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <input type="text" class="form-control form-control-sm cmb-sku" value="${escapeHtml(c.sku||'')}" placeholder="SKU">
          </td>
          <td>
            <div class="text-truncate" title="${escapeHtml(names)}" style="max-width: 200px;">${escapeHtml(names)}</div>
          </td>
          <td>
            <input type="text" class="form-control form-control-sm cmb-price text-center" value="${valOrEmpty(c.price)}" placeholder="0">
          </td>
          <td>
            <input type="text" class="form-control form-control-sm cmb-price-cny text-center" value="${valOrEmpty(c.price_cny)}" placeholder="0">
          </td>
          <td>
            <input type="text" class="form-control form-control-sm cmb-stock text-center" value="${c.stock === 0 ? '' : (c.stock || '')}" placeholder="0">
          </td>
          <td class="text-center">
            <input type="checkbox" class="form-check-input cmb-active" ${c.active? 'checked':''}>
          </td>
          <td class="text-center">
            <button class="btn btn-outline-secondary btn-sm btn-images" title="تصاویر">
              <i class="ph-image"></i> <span class="badge bg-secondary">${Number(state.imageCounts[c.id]||0)}</span>
            </button>
          </td>
          <td class="text-center">
            <button class="btn btn-outline-danger btn-sm btn-del-comb" title="حذف ترکیب">
              <i class="ph-trash"></i>
            </button>
          </td>
        `;

        qs('.cmb-sku', tr)?.addEventListener('input', (e)=>{ c.sku = e.target.value; });
        const priceEl = qs('.cmb-price', tr);
        const priceCnyEl = qs('.cmb-price-cny', tr);
        priceEl?.addEventListener('input', (e)=>{ c.price = toNum(e.target.value); });
        priceCnyEl?.addEventListener('input', (e)=>{ c.price_cny = toNum(e.target.value); });
        const stockEl = qs('.cmb-stock', tr);
        if (stockEl) {
          stockEl.addEventListener('input', (e)=>{
            // Allow only numbers
            const val = e.target.value.replace(/[^\d]/g, '');
            e.target.value = val;
            c.stock = val ? parseInt(val, 10) : 0;
          });
          stockEl.addEventListener('blur', (e)=>{
            if (!e.target.value.trim()) {
              e.target.value = '';
              c.stock = 0;
            } else {
              const num = parseInt(e.target.value, 10);
              e.target.value = num.toString();
              c.stock = num;
            }
          });
        }
        qs('.cmb-active', tr)?.addEventListener('change', (e)=>{ c.active = !!e.target.checked; });
        qs('.btn-images', tr)?.addEventListener('click', (e)=>{ e.preventDefault(); openImagesForCombination(c); });
        qs('.btn-del-comb', tr)?.addEventListener('click', async function(e){
          e.preventDefault();
          const ok = await showConfirmModal({
            title: 'حذف ترکیب',
            message: 'این ترکیب حذف شود؟ تصاویر متصل هم جدا می‌شوند.',
            icon: 'ph-warning',
            confirmClass: 'btn-danger',
            confirmText: 'بله، حذف کن',
            confirmIcon: 'ph-trash'
          });
          if (!ok) return;
          state.combinations = state.combinations.filter(function(x){ return x!==c; });
          persistHiddenFields();
          renderCombinations();
          toast('ترکیب حذف شد. برای اعمال نهایی ذخیره کنید.', 'warning');
        });

        list.appendChild(tr);
      });
      persistHiddenFields();
      return;
    }

    // No fallback needed since we're using a proper table layout
  }

  function hydrateCombinationSelector(){
    const sel = qs('#combination-selector');
    const uploaderInput = document.querySelector('#images-root .image-uploader input[type="file"]');
    if (!sel) return;
    sel.innerHTML = '';
    const optNone = document.createElement('option');
    optNone.value=''; optNone.textContent= tr('shop.common.select','انتخاب');
    sel.appendChild(optNone);
    state.combinations.forEach((c,i)=>{
      const o = document.createElement('option');
      o.value = (c.id || c.tmpId);
      o.textContent = `${c.sku||('#'+(i+1))} — ${c.attribute_value_ids.map(id=> findValueLabel(id)).join(' / ')}`;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function(){
      if (uploaderInput){
        const comb = this.value ? String(this.value) : '';
        const fieldName = comb ? `gallery__comb_${comb}` : 'gallery';
        uploaderInput.setAttribute('data-field-name', fieldName);
        uploaderInput.name = fieldName; // let plugin build array keys itself
      }
      const container = qs('#combination-uploader');
      if (!container) return;
      container.setAttribute('data-combination', this.value);
      // wire upload/list/delete endpoints based on selection
      const imagesApi = (G && G.images) || null;
      if (imagesApi){
        const listUrl = imagesApi.list + (this.value ? ('?combination_id='+encodeURIComponent(this.value)) : '');
        const uploadUrl = imagesApi.upload + (this.value ? ('/'+encodeURIComponent(this.value)) : '');
        container.setAttribute('data-iu-list-url', listUrl);
        container.setAttribute('data-iu-upload-url', uploadUrl);
        container.setAttribute('data-iu-delete-template', imagesApi.delete || '');
      }
      // trigger plugin refresh if available
      if (window.ImageUploader && container._iuInstance && container._iuInstance.refresh){
        container._iuInstance.refresh();
      }
    });

    // initialize endpoints for default (product-level) on load
    sel.dispatchEvent(new Event('change'));
  }

  async function openImagesForCombination(c){
    const sel = qs('#combination-selector');
    if (sel){ sel.value = (c.id || c.tmpId); sel.dispatchEvent(new Event('change')); }

    const cid = c.id;
    const hasImgs = !!(cid && state.imageCounts[cid] && Number(state.imageCounts[cid])>0);
    if (!hasImgs) {
      // No images yet: navigate user to upload tab
      const imagesTabLink = qsa('a[data-bs-toggle="tab"]').find(a=> a.getAttribute('href')==='#tab_images');
      if (imagesTabLink){ new bootstrap.Tab(imagesTabLink).show(); }
      return;
    }

    try {
      const imagesApi = (G && G.images) || {};
      const listUrl = imagesApi.list + '?combination_id=' + encodeURIComponent(cid);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

      // Use axios if present; fallback to fetch
      let data;
      if (window.axios) {
        const res = await axios.get(listUrl, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
        data = res.data;
      } else {
        const res = await fetch(listUrl, { headers: { 'Accept':'application/json','X-CSRF-TOKEN': token } });
        data = await res.json();
      }
      const rows = Array.isArray(data?.data) ? data.data : [];
      const grid = qs('#combo-image-grid');
      if (!grid) return;
      grid.innerHTML = '';
      // If no images, close modal (nothing to preview)
      if (!rows.length) {
        try {
          const modalEl = document.getElementById('comboImageModal');
          if (modalEl && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }
        } catch(_){}
        return;
      }
      rows.forEach(r => {
        const rel = String(r.path||'');
        const urlAvif = String(r.avif_url||'');
        const urlOrig = String(r.url||'');
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';
        col.innerHTML = `<div class="ratio ratio-1x1 border rounded overflow-hidden position-relative bg-body">
            <img src="${urlOrig}" data-avif="${urlAvif}" alt="preview" class="w-100 h-100" style="object-fit: contain;"/>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle position-absolute top-0 end-0 m-1 js-detach"
              style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;" title="جدا کردن">
              <i class="ph-link-break"></i>
            </button>
          </div>`;
        // Try upgrade to AVIF only if it exists (preload and swap)
        try {
          if (urlAvif) {
            var test = new Image();
            test.onload = function(){ try { col.querySelector('img').src = urlAvif; } catch(_){} };
            test.onerror = function(){};
            test.src = urlAvif;
          }
        } catch(_){ }
        const btn = col.querySelector('.js-detach');
        if (btn) {
          btn.addEventListener('click', async function(e){
            e.preventDefault();
            try{
              const ok = await showConfirmModal({
                title: 'جدا کردن تصویر',
                message: 'این تصویر از ترکیب جدا شود؟',
                icon: 'ph-link-break',
                confirmClass: 'btn-danger',
                confirmText: 'جدا کردن',
                confirmIcon: 'ph-link-break'
              });
              if (!ok) return;
              const imagesApi = (G && G.images) || {};
              const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
              const res = await fetch(imagesApi.detach, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                  combination_id: cid, 
                  combination_image_id: Number(r.id||0) || undefined, 
                  image_id: Number(r.image_id||0) || undefined,
                  file_path: rel 
                })
              });
              const data = await res.json().catch(function(){ return {}; });
              if (res.ok && data && data.ok) {
                col.remove();
                // decrement imageCounts for this combination and refresh UI
                try { if (typeof cid !== 'undefined') { state.imageCounts[cid] = Math.max(0, Number(state.imageCounts[cid]||0) - 1); } } catch(_){}
                try { renderCombinations(); } catch(_){}
                // Close modal if empty
                if (!grid.children.length) {
                  try { const modalEl = document.getElementById('comboImageModal'); if (modalEl && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } } catch(_){ }
                }
                if (window.showToastMessage) { window.showToastMessage('success', 'جدا شد', 'تصویر از ترکیب جدا شد', 2000); }
              } else {
                throw new Error(data && data.message ? data.message : 'خطا در جدا کردن');
              }
            } catch(err) {
              console.error(err);
              if (window.showToastMessage) { window.showToastMessage('danger', 'خطا', 'جدا کردن انجام نشد', 3000); }
            }
          });
        }
        grid.appendChild(col);
      });
      const modalEl = document.getElementById('comboImageModal');
      if (modalEl) { new bootstrap.Modal(modalEl).show(); }
    } catch (e) {
      console.error(e);
      // fallback: go to upload tab
      const imagesTabLink = qsa('a[data-bs-toggle="tab"]').find(a=> a.getAttribute('href')==='#tab_images');
      if (imagesTabLink){ new bootstrap.Tab(imagesTabLink).show(); }
    }
  }

  function findValueLabel(id){
    for (const a of state.attributes){
      for (const v of a.values){
        if ((v.id && id===v.id) || (v.tmpId && id===v.tmpId)) return `${a.name}: ${v.value}`;
      }
    }
    return '';
  }

  function persistHiddenFields(){
    const aEl = qs('#attributes_json');
    const cEl = qs('#combinations_json');
    if (aEl) aEl.value = JSON.stringify(exportAttributes());
    if (cEl) cEl.value = JSON.stringify(state.combinations);
  }
  function exportAttributes(){
    return state.attributes.map(a=> ({
      id: a.id,
      tmpId: a.tmpId,
      name: a.name,
      type: a.type,
      ui: (a.ui||'pill'),
      sort: a.sort,
      attribute_definition_id: a.attribute_definition_id ?? null,
      values: a.values.map(v=> ({
        id: v.id,
        tmpId: v.tmpId,
        value: v.value,
        image_path: v.image_path,
        color: v.color,
        sort: v.sort,
        definition_value_id: v.definition_value_id ?? null,
      }))
    }));
  }

  function wireActions(){
    const addAttr = qs('#btn-add-attribute');
    const genBtn = qs('#btn-generate-combinations');
    addAttr?.addEventListener('click', function(e){
      e.preventDefault();
      state.attributes.push({
        tmpId: uid('attr'),
        name:'',
        type:'text',
        ui:'pill',
        sort: state.attributes.length,
        attribute_definition_id: null,
        values: []
      });
      renderAttributes();
    });
    genBtn?.addEventListener('click', function(e){ e.preventDefault(); generateCombinations(); });

    // AJAX save combinations
    const saveBtn = qs('#btn-save-combinations-ajax');
    if (saveBtn) {
      const saveLabel = saveBtn.querySelector('.save-label');
      const saveLoading = saveBtn.querySelector('.save-loading');
      saveBtn.addEventListener('click', async function(e){
        e.preventDefault();
        persistHiddenFields();
        const api = (G && G.apiEndpoints) || {};
        if (!api.saveCombinations) { alert('ابتدا محصول را ذخیره کنید.'); return; }
        try {
          saveBtn.disabled = true; saveLabel.classList.add('d-none'); saveLoading.classList.remove('d-none');
          const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
          const body = new URLSearchParams();
          body.set('attributes_json', document.querySelector('#attributes_json')?.value || '[]');
          body.set('combinations_json', document.querySelector('#combinations_json')?.value || '[]');
          const res = await fetch(api.saveCombinations, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded' }, body: body.toString()
          });
          const data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || 'خطا در ذخیره');
          // hydrate state from server ids
          if (Array.isArray(data.attributes)) {
            state.attributes = normalizeAttributes(data.attributes);
          }
          if (Array.isArray(data.combinations)) {
            state.combinations = normalizeCombinations(data.combinations);
          }
          renderAttributes(); renderCombinations(); hydrateCombinationSelector();
          toast('ترکیب‌ها ذخیره شد', 'success');
        } catch(err){
          console.error(err);
          toast('خطا در ذخیره ترکیب‌ها', 'danger');
        } finally {
          saveBtn.disabled = false; saveLabel.classList.remove('d-none'); saveLoading.classList.add('d-none');
        }
      });
    }

    // form submit persist
    const form = qs('#productForm');
    form?.addEventListener('submit', function(){
      persistHiddenFields();
    });
  }

  function tr(key,fallback){
    try{
      const parts = key.split('.');
      let cur = window.trans; for (const p of parts){ cur = cur?.[p]; if (!cur) return fallback; }
      return cur || fallback;
    }catch(e){ return fallback; }
  }
  function escapeHtml(s){ return (s||'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
  function toNum(x){ const n = Number(x); return isFinite(n)? n : null; }
  function round2(x){ return Math.round((x + Number.EPSILON)*100)/100; }
  function valOrEmpty(v){ return (v===null || v===undefined || (typeof v==='number' && !isFinite(v)) || v==='') ? '' : String(v); }

  function toast(message, type){
    try{
      const el = document.createElement('div');
      el.className = 'position-fixed end-0 bottom-0 m-3 alert alert-'+(type||'info');
      el.style.zIndex = 1060; el.textContent = message;
      document.body.appendChild(el);
      setTimeout(()=>{ el.classList.add('show'); }, 10);
      setTimeout(()=>{ el.remove(); }, 3000);
    } catch(e) { alert(message); }
  }

  // Save Success Animation Function (Using is-valid for visual feedback)
  function showSaveSuccess(inputElement) {
    if (!inputElement) return;

    // Add valid class (Bootstrap standard for success state)
    inputElement.classList.add('is-valid');

    // Remove class after 2 seconds
    setTimeout(() => {
      inputElement.classList.remove('is-valid');
    }, 2000);
  }

  // Color Picker Function
  function showColorPicker(triggerEl, onSelect) {
    // Remove existing picker
    const existing = document.querySelector('.color-picker-popover');
    if (existing) existing.remove();

    // Create popover
    const popover = document.createElement('div');
    popover.className = 'color-picker-popover position-absolute bg-white border rounded shadow-sm p-2';
    popover.style.cssText = 'z-index: 1050; width: 200px; top: 100%; left: 0; margin-top: 5px;';

    // Predefined colors
    const predefinedColors = [
      '#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
      '#800000', '#008000', '#000080', '#808000', '#800080', '#008080', '#C0C0C0', '#808080',
      '#FFA500', '#A52A2A', '#DC143C', '#FF69B4', '#4B0082', '#7CFC00', '#DDA0DD', '#98FB98'
    ];

    let html = '<div class="mb-2"><small class="text-muted">رنگ‌های از پیش تعریف شده:</small></div>';
    html += '<div class="d-flex flex-wrap gap-1 mb-2">';

    predefinedColors.forEach(color => {
      html += `<div class="color-swatch" style="width: 20px; height: 20px; background: ${color}; border: 1px solid #ccc; border-radius: 3px; cursor: pointer;" data-color="${color}" title="${color}"></div>`;
    });

    html += '</div>';
    html += '<div class="mb-1"><small class="text-muted">یا رنگ دلخواه:</small></div>';
    html += '<input type="color" class="form-control form-control-sm" value="#000000" id="custom-color-picker">';

    popover.innerHTML = html;
    triggerEl.parentNode.appendChild(popover);

    // Handle predefined colors
    popover.querySelectorAll('.color-swatch').forEach(swatch => {
      swatch.addEventListener('click', () => {
        const color = swatch.dataset.color;
        onSelect(color);
        popover.remove();
      });
    });

    // Handle custom color picker
    const customPicker = popover.querySelector('#custom-color-picker');
    customPicker.addEventListener('change', (e) => {
      onSelect(e.target.value);
      popover.remove();
    });

    // Close on outside click
    setTimeout(() => {
      document.addEventListener('click', function closePicker(e) {
        if (!popover.contains(e.target) && e.target !== triggerEl) {
          popover.remove();
          document.removeEventListener('click', closePicker);
        }
      });
    }, 1);
  }

  document.addEventListener('DOMContentLoaded', function(){
    loadInitial();
    wireActions();
  });
})();
