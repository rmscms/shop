(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const G = window.RMS || window.App || {};
    const root = document.getElementById('features-root');
    const addBtn = document.getElementById('btn-add-feature');
    const addCategoryBtn = document.getElementById('btn-add-category');
    const saveBtn = document.getElementById('btn-save-features');
    if (!root || !saveBtn) {
      return;
    }

    const endpoints = G.endpoints || {};
    const MIN_TERM = 3;
    const initialCategories = Array.isArray(G.data?.features) && G.data.features.length
      ? G.data.features
      : [{
        id: null,
        name: '',
        icon: 'ph-info',
        features: [{
          name: '',
          value: '',
          sort: 0,
          feature_definition_id: null,
          feature_value_id: null,
        }],
      }];

    function escapeHtml(str) {
      return (str || '').replace(/[&<>"']/g, function (c) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;',
        })[c] || c;
      });
    }

    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func.apply(null, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    function showAutoSaveToast(message) {
      let toast = document.querySelector('.auto-save-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.className = 'auto-save-toast alert alert-success position-fixed';
        toast.style.cssText = 'bottom:20px;left:20px;z-index:9999;padding:8px 12px;font-size:12px;';
        document.body.appendChild(toast);
      }
      toast.textContent = message;
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 1500);
    }

    function showSuccess(message) {
      const el = document.createElement('div');
      el.className = 'alert alert-success position-fixed end-0 bottom-0 m-3';
      el.textContent = message;
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 2000);
    }

    function showError(message, lines) {
      const el = document.createElement('div');
      el.className = 'alert alert-danger position-fixed end-0 bottom-0 m-3';
      if (Array.isArray(lines) && lines.length) {
        el.innerHTML = `<strong>${escapeHtml(message || 'خطا')}</strong>`;
        const list = document.createElement('ul');
        list.className = 'mb-0 ps-3';
        lines.forEach((line) => {
          const li = document.createElement('li');
          li.textContent = line;
          list.appendChild(li);
        });
        el.appendChild(list);
      } else {
        el.textContent = message || 'خطا';
      }
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 3500);
    }

    function setupAutocomplete({ input, results, fetcher, formatter, onSelect, onClear, minChars = MIN_TERM }) {
      if (!input || !results) return;
      const wrapper = input.closest('.feature-autocomplete');
      if (wrapper) wrapper.classList.add('position-relative');

      const hide = () => {
        results.classList.add('d-none');
        results.innerHTML = '';
      };

      const render = (items) => {
        if (!items.length) {
          hide();
          return;
        }
        results.innerHTML = items.map((item) => `
          <button type="button" class="list-group-item list-group-item-action" data-payload='${JSON.stringify(item)}'>
            ${formatter ? formatter(item) : escapeHtml(item.name || item.value || '')}
          </button>
        `).join('');
        results.classList.remove('d-none');
      };

      const load = debounce(async (term) => {
        try {
          const items = await fetcher(term);
          render(items || []);
        } catch (err) {
          console.error(err);
          hide();
        }
      }, 300);

      input.addEventListener('input', function () {
        if (typeof onClear === 'function') onClear();
        const term = input.value.trim();
        if (term.length < minChars) {
          hide();
          return;
        }
        load(term);
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

    function collectData() {
      const categories = [];
      root.querySelectorAll('.feature-category').forEach((card) => {
        const categoryId = card.dataset.categoryId ? parseInt(card.dataset.categoryId, 10) : null;
        const categoryName = card.querySelector('.category-search')?.value?.trim() || '';
        const rows = card.querySelectorAll('.features-tbody tr');
        const features = [];
        rows.forEach((row) => {
          const nameInput = row.querySelector('.feat-name');
          const valueInput = row.querySelector('.feat-value');
          const sortInput = row.querySelector('.feat-sort');
          const name = nameInput?.value?.trim() || '';
          const value = valueInput?.value?.trim() || '';
          if (!name && !value) return;
          const featureId = nameInput?.dataset.featureId ? parseInt(nameInput.dataset.featureId, 10) : null;
          const valueId = valueInput?.dataset.valueId ? parseInt(valueInput.dataset.valueId, 10) : null;

          features.push({
            feature_id: featureId,
            value_id: valueId,
            name,
            value,
            sort: sortInput?.value ? parseInt(sortInput.value, 10) || 0 : 0,
          });
        });
        if (features.length) {
          categories.push({
            category_id: categoryId,
            category_name: categoryName,
            features,
          });
        }
      });
      return { categories };
    }

    function toggleSaveState(isSaving) {
      saveBtn.disabled = isSaving;
      const label = saveBtn.querySelector('.save-label');
      const loading = saveBtn.querySelector('.save-loading');
      if (label && loading) {
        label.classList.toggle('d-none', isSaving);
        loading.classList.toggle('d-none', !isSaving);
      }
    }

    // Save Success Animation Function
    function showSaveSuccess(inputElement) {
      if (!inputElement) return;

      // Add success class
      inputElement.classList.add('save-success');

      // Remove class after 2 seconds
      setTimeout(() => {
        inputElement.classList.remove('save-success');
      }, 2000);
    }

    let isSaving = false;
    const autoSave = async () => {
      if (isSaving) return; // Prevent multiple simultaneous saves
      isSaving = true;
      const url = saveBtn.getAttribute('data-save-url');
      if (!url) {
        isSaving = false;
        return;
      }
      try {
        await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify(collectData()),
        });
        showAutoSaveToast('ذخیره خودکار انجام شد');
      } catch (err) {
        console.warn('Auto-save failed', err);
      } finally {
        isSaving = false;
      }
    };

    async function saveFeatures() {
      const url = saveBtn.getAttribute('data-save-url');
      if (!url) return;
      toggleSaveState(true);
      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify(collectData()),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.ok) {
          showSuccess('ویژگی‌ها ذخیره شد');
        } else if (res.status === 422 && data?.errors) {
          const messages = Object.values(data.errors).flat();
          showError('اعتبارسنجی ناموفق', messages);
        } else {
          throw new Error(data?.message || 'خطای ناشناخته');
        }
      } catch (err) {
        console.error(err);
        showError(err.message || 'خطا در ذخیره');
      } finally {
        toggleSaveState(false);
      }
    }

    function appendFeatureRow(tbody, feature) {
      const rowData = Object.assign({
        name: '',
        value: '',
        sort: '',
        feature_definition_id: null,
        feature_value_id: null,
      }, feature || {});

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="position-relative feature-autocomplete">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control feat-name" placeholder="نام ویژگی" value="${escapeHtml(rowData.name || '')}" data-feature-id="${rowData.feature_definition_id || ''}">
              <button type="button" class="btn btn-outline-secondary btn-create-feature" title="ایجاد ویژگی"><i class="ph-plus"></i></button>
            </div>
            <div class="autocomplete-results list-group d-none"></div>
          </div>
        </td>
        <td>
          <div class="position-relative feature-autocomplete">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control feat-value" placeholder="مقدار ویژگی" value="${escapeHtml(rowData.value || '')}" data-value-id="${rowData.feature_value_id || ''}">
              <button type="button" class="btn btn-outline-secondary btn-create-value" title="ایجاد مقدار"><i class="ph-plus"></i></button>
            </div>
            <div class="autocomplete-results list-group d-none"></div>
          </div>
        </td>
        <td><input type="number" class="form-control form-control-sm feat-sort" min="0" value="${rowData.sort ?? ''}"></td>
        <td><button type="button" class="btn btn-link text-danger p-0 btn-remove-feature" title="حذف"><i class="ph-trash"></i></button></td>
      `;

      tbody.appendChild(tr);
      attachFeatureEvents(tr);
    }

    function attachFeatureEvents(row) {
      const nameInput = row.querySelector('.feat-name');
      const valueInput = row.querySelector('.feat-value');
      const sortInput = row.querySelector('.feat-sort');
      const removeBtn = row.querySelector('.btn-remove-feature');
      const createFeatureBtn = row.querySelector('.btn-create-feature');
      const createValueBtn = row.querySelector('.btn-create-value');
      const featureList = nameInput.closest('.feature-autocomplete').querySelector('.autocomplete-results');
      const valueList = valueInput.closest('.feature-autocomplete').querySelector('.autocomplete-results');

      setupAutocomplete({
        input: nameInput,
        results: featureList,
        fetcher: async (term) => {
          if (!endpoints.searchFeatures) return [];
          const url = new URL(endpoints.searchFeatures, window.location.origin);
          url.searchParams.set('q', term);
          const categoryId = row.closest('.feature-category')?.dataset.categoryId;
          if (categoryId) url.searchParams.set('category_id', categoryId);
          const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
          const json = await res.json();
          return json.data || [];
        },
        formatter: (item) => `<div>${escapeHtml(item.name)}</div>`,
        onSelect: (item) => {
          // Check if this feature name already exists in the current category (excluding current input)
          const categoryCard = nameInput.closest('.feature-category');
          const existingFeatures = Array.from(categoryCard.querySelectorAll('.feat-name'))
            .filter(input => input !== nameInput) // Exclude current input
            .map(input => input.value.trim())
            .filter(val => val);

          if (existingFeatures.includes(item.name)) {
            // Show error - feature already exists in this category
            nameInput.classList.add('save-error');
            showAutoSaveToast(`ویژگی "${item.name}" قبلاً در این دسته انتخاب شده است`, 'error');
            setTimeout(() => {
              nameInput.classList.remove('save-error');
              nameInput.value = '';
              delete nameInput.dataset.featureId;
            }, 2000);
          } else {
            // Success - feature is available
            nameInput.dataset.featureId = item.id;
            nameInput.value = item.name;
            nameInput.classList.remove('save-warning');
            showSaveSuccess(nameInput);
          }
        },
        onClear: () => {
          delete nameInput.dataset.featureId;
        },
      });

      setupAutocomplete({
        input: valueInput,
        results: valueList,
        fetcher: async (term) => {
          const featureId = nameInput.dataset.featureId;
          if (!featureId || !endpoints.searchValues) return [];
          const url = new URL(endpoints.searchValues, window.location.origin);
          url.searchParams.set('q', term);
          url.searchParams.set('feature_id', featureId);
          const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
          const json = await res.json();
          return json.data || [];
        },
        formatter: (item) => `<div>${escapeHtml(item.value)}</div>`,
        onSelect: (item) => {
          valueInput.dataset.valueId = item.id;
          valueInput.value = item.value;
          valueInput.classList.remove('save-warning');
          showSaveSuccess(valueInput);
        },
        onClear: () => {
          delete valueInput.dataset.valueId;
        },
      });

      nameInput?.addEventListener('input', (e) => {
        // Clear feature ID when user types (indicating they might be creating a new one)
        if (e.target.value.trim() !== (e.target.dataset.originalValue || '')) {
          delete e.target.dataset.featureId;
          nameInput.classList.remove('save-success', 'save-warning');
          // Also clear value ID since feature changed
          const valueInput = row.querySelector('.feat-value');
          if (valueInput) {
            delete valueInput.dataset.valueId;
          }
        }
      });

      nameInput?.addEventListener('blur', () => {
        const value = nameInput.value.trim();
        if (value && !nameInput.dataset.featureId) {
          // New feature that needs to be created with + button
          nameInput.classList.add('save-warning');
        }
      });

      valueInput?.addEventListener('input', (e) => {
        // Clear value ID when user types (indicating they might be creating a new one)
        if (e.target.value.trim() !== (e.target.dataset.originalValue || '')) {
          delete e.target.dataset.valueId;
          valueInput.classList.remove('save-success', 'save-warning');
        }
      });

      valueInput?.addEventListener('blur', () => {
        const value = valueInput.value.trim();
        if (value && !valueInput.dataset.valueId) {
          // New value that needs to be created with + button
          valueInput.classList.add('save-warning');
        }
      });

      // sortInput no longer auto-saves

      removeBtn?.addEventListener('click', function () {
        row.remove();
        // No autoSave here
      });

      createFeatureBtn?.addEventListener('click', async function () {
        const term = nameInput.value.trim();
        if (term.length < MIN_TERM) {
          alert('حداقل ۳ کاراکتر وارد کنید');
          return;
        }
        if (!endpoints.createFeature) return;
        const categoryId = row.closest('.feature-category')?.dataset.categoryId || null;
        try {
          const res = await fetch(endpoints.createFeature, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ name: term, category_id: categoryId }),
          });
          const data = await res.json();
          if (res.ok && data?.feature) {
            nameInput.dataset.featureId = data.feature.id;
            nameInput.value = data.feature.name;
            nameInput.classList.remove('save-warning');
            showSaveSuccess(nameInput);
            showAutoSaveToast(`ویژگی "${data.feature.name}" ایجاد شد`);
          } else {
            throw new Error(data?.message || 'خطا در ایجاد ویژگی');
          }
        } catch (err) {
          console.error(err);
          showAutoSaveToast(err.message || 'خطا');
        }
      });

      createValueBtn?.addEventListener('click', async function () {
        const term = valueInput.value.trim();
        const featureId = nameInput.dataset.featureId;
        if (!featureId) {
          alert('ابتدا ویژگی را انتخاب کنید');
          return;
        }
        if (term.length < MIN_TERM) {
          alert('حداقل ۳ کاراکتر وارد کنید');
          return;
        }
        if (!endpoints.createValue) return;
        try {
          const res = await fetch(endpoints.createValue, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ feature_id: featureId, value: term }),
          });
          const data = await res.json();
          if (res.ok && data?.value) {
            valueInput.dataset.valueId = data.value.id;
            valueInput.value = data.value.value;
            valueInput.classList.remove('save-warning');
            showSaveSuccess(valueInput);
            showAutoSaveToast('مقدار جدید ثبت شد');
          } else {
            throw new Error(data?.message || 'خطا در ایجاد مقدار');
          }
        } catch (err) {
          console.error(err);
          showAutoSaveToast(err.message || 'خطا');
        }
      });
    }

    function attachCategoryEvents(card) {
      const searchInput = card.querySelector('.category-search');
      const hiddenInput = card.querySelector('.category-id');
      const iconHolder = card.querySelector('.input-group-text i');
      const results = card.querySelector('.autocomplete-results');
      const addFeatureBtn = card.querySelector('.btn-add-feature-to-category');
      const removeBtn = card.querySelector('.btn-remove-category');
      const createBtn = card.querySelector('.btn-create-category');
      const tbody = card.querySelector('.features-tbody');

      setupAutocomplete({
        input: searchInput,
        results,
        fetcher: async (term) => {
          if (!endpoints.searchCategories) return [];
          const url = new URL(endpoints.searchCategories, window.location.origin);
          url.searchParams.set('q', term);
          const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
          const json = await res.json();
          return json.data || [];
        },
        formatter: (item) => `<div>${escapeHtml(item.name)}</div>`,
        onSelect: (item) => {
          hiddenInput.value = item.id;
          card.dataset.categoryId = item.id;
          searchInput.value = item.name;
          if (iconHolder) iconHolder.className = item.icon || 'ph-info';
        },
        onClear: () => {
          hiddenInput.value = '';
          delete card.dataset.categoryId;
        },
      });

      createBtn?.addEventListener('click', async function () {
        const term = searchInput.value.trim();
        if (term.length < MIN_TERM) {
          alert('حداقل ۳ کاراکتر وارد کنید');
          return;
        }
        const createCategoryUrl = endpoints.createCategory || '/admin/shop/products/features/create-category';
        console.log('Using createCategory URL:', createCategoryUrl);
        try {
          const res = await fetch(endpoints.createCategory, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ name: term, icon: 'ph-tag' }),
          });
          const data = await res.json();
          if (res.ok && data?.category) {
            hiddenInput.value = data.category.id;
            card.dataset.categoryId = data.category.id;
            searchInput.value = data.category.name;
            if (iconHolder) iconHolder.className = data.category.icon || 'ph-tag';
            showAutoSaveToast(`دسته "${data.category.name}" ساخته شد`);
          } else {
            throw new Error(data?.message || 'خطا در ایجاد دسته');
          }
        } catch (err) {
          console.error(err);
          showAutoSaveToast(err.message || 'خطا');
        }
      });

      addFeatureBtn?.addEventListener('click', function () {
        appendFeatureRow(tbody);
        // No autoSave here
      });

      removeBtn?.addEventListener('click', function () {
        if (confirm('این دسته و ویژگی‌هایش حذف شوند؟')) {
          card.remove();
          // No autoSave here
        }
      });
    }

    function buildCategoryCard(data) {
      const card = document.createElement('div');
      card.className = 'card mb-3 feature-category';
      if (data?.id) {
        card.dataset.categoryId = data.id;
      }
      card.innerHTML = `
        <div class="card-header bg-primary bg-opacity-10 border-primary border-opacity-25">
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="flex-grow-1 position-relative feature-autocomplete">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="${escapeHtml(data?.icon || 'ph-info')}"></i></span>
                <input type="text" class="form-control category-search" placeholder="جستجوی دسته" value="${escapeHtml(data?.name || '')}">
                <button type="button" class="btn btn-outline-secondary btn-create-category" title="ایجاد دسته"><i class="ph-plus"></i></button>
              </div>
              <input type="hidden" class="category-id" value="${data?.id || ''}">
              <div class="autocomplete-results list-group d-none"></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-sm btn-outline-primary btn-add-feature-to-category">+ ویژگی</button>
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-category">حذف دسته</button>
            </div>
          </div>
        </div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th style="width:30%">نام ویژگی</th>
                <th style="width:45%">مقدار</th>
                <th style="width:15%">ترتیب</th>
                <th style="width:10%">عملیات</th>
              </tr>
            </thead>
            <tbody class="features-tbody"></tbody>
          </table>
        </div>
      `;
      root.appendChild(card);
      const tbody = card.querySelector('.features-tbody');
      (data?.features || []).forEach((feature) => appendFeatureRow(tbody, feature));
      if (!tbody.children.length) {
        appendFeatureRow(tbody);
      }
      attachCategoryEvents(card);
      return card;
    }

    // init
    initialCategories.forEach((category) => buildCategoryCard(category));

    addCategoryBtn?.addEventListener('click', function () {
      buildCategoryCard({
        id: null,
        name: '',
        icon: 'ph-info',
        features: [{
          name: '',
          value: '',
          sort: 0,
        }],
      });
    });

    addBtn?.addEventListener('click', function () {
      const lastCard = root.querySelector('.feature-category:last-of-type');
      if (lastCard) {
        appendFeatureRow(lastCard.querySelector('.features-tbody'));
      } else {
        buildCategoryCard();
      }
    });

    saveBtn.addEventListener('click', saveFeatures);
  });
})();
