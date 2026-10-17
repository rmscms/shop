@php
  $seoService = app(\RMS\Shop\Services\SeoService::class);
  $seoUrl = !empty($product) ? $seoService->productUrl($product) : $seoService->url('product', 'slug');
  $mainImage = !empty($product) ? $product->assignedImages()->first() : null;
  $titleMax = (int) config('shop.seo.title_max', 60);
  $descMax = (int) config('shop.seo.description_max', 155);
@endphp
<div id="tab_seo" class="tab-pane fade p-3 shop-seo-tab"
     data-save-url="{{ !empty($product) ? route('admin.shop.products.seo.update', $product->id) : '' }}"
     data-title-max="{{ $titleMax }}" data-desc-max="{{ $descMax }}">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="mb-3">
        <label class="form-label d-flex justify-content-between">
          <span>عنوان سئو (meta title)</span>
          <small class="text-muted"><span data-count="meta_title">0</span> / {{ $titleMax }}</small>
        </label>
        <input type="text" name="seo[meta_title]" class="form-control" maxlength="120" data-seo="meta_title"
               value="{{ old('seo.meta_title', $product->meta_title ?? '') }}" placeholder="{{ $product->name ?? '' }}">
        <div class="form-text">کلمهٔ کلیدی اصلی را اول عنوان بیاورید. خالی بماند، نام محصول استفاده می‌شود.</div>
      </div>

      <div class="mb-3">
        <label class="form-label d-flex justify-content-between">
          <span>توضیح متا (meta description)</span>
          <small class="text-muted"><span data-count="meta_description">0</span> / {{ $descMax }}</small>
        </label>
        <textarea name="seo[meta_description]" class="form-control" rows="3" maxlength="320" data-seo="meta_description"
                  placeholder="{{ $seoService->description(null, $product->short_desc ?? null, $product->description ?? null) }}">{{ old('seo.meta_description', $product->meta_description ?? '') }}</textarea>
        <div class="form-text">۱۲۰ تا ۱۵۵ کاراکتر، با یک مزیت مشخص. خالی بماند، از توضیح کوتاه ساخته می‌شود.</div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">کلمهٔ کلیدی اصلی</label>
          <input type="text" name="seo[focus_keyword]" class="form-control" maxlength="100" data-seo="focus_keyword"
                 value="{{ old('seo.focus_keyword', $product->focus_keyword ?? '') }}">
          <div class="form-text" data-keyword-hint></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">متن جایگزین عکس اصلی (alt)</label>
          <input type="text" name="seo[image_alt]" class="form-control" maxlength="255"
                 value="{{ old('seo.image_alt', $mainImage->alt ?? '') }}" @disabled(!$mainImage)
                 placeholder="{{ $mainImage ? ($product->name ?? '') : 'اول یک عکس به محصول بدهید' }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">GTIN / بارکد (اختیاری)</label>
          <input type="text" name="seo[gtin]" class="form-control" maxlength="32" dir="ltr"
                 value="{{ old('seo.gtin', $product->gtin ?? '') }}" placeholder="EAN / UPC">
        </div>
        <div class="col-md-6">
          <label class="form-label">MPN / پارت‌نامبر سازنده (اختیاری)</label>
          <input type="text" name="seo[mpn]" class="form-control" maxlength="64" dir="ltr"
                 value="{{ old('seo.mpn', $product->mpn ?? '') }}">
        </div>
        <div class="col-md-8">
          <label class="form-label">آدرس canonical (اختیاری)</label>
          <input type="url" name="seo[canonical_url]" class="form-control" dir="ltr"
                 value="{{ old('seo.canonical_url', $product->canonical_url ?? '') }}" placeholder="{{ $seoUrl }}">
          <div class="form-text">فقط وقتی این محصول نسخهٔ تکراری صفحهٔ دیگری است پر کنید.</div>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input type="hidden" name="seo[noindex]" value="0">
            <input class="form-check-input" type="checkbox" name="seo[noindex]" value="1" id="seo-noindex"
                   @checked(old('seo.noindex', $product->noindex ?? false))>
            <label class="form-check-label" for="seo-noindex">در گوگل نمایش داده نشود (noindex)</label>
          </div>
        </div>
      </div>

      @if(!empty($product))
        <div class="d-flex justify-content-end mt-3">
          <button type="button" class="btn btn-success" data-seo-save>
            <i class="ph-floppy-disk me-1"></i> ذخیرهٔ سئو
          </button>
        </div>
      @endif
    </div>

    <div class="col-lg-5">
      <label class="form-label">پیش‌نمایش در نتایج گوگل</label>
      <div class="border rounded p-3 bg-white" dir="rtl" style="font-family: arial, sans-serif">
        <div class="small text-muted text-truncate" dir="ltr" style="text-align:left">{{ $seoUrl }}</div>
        <div data-preview="title" style="color:#1a0dab;font-size:19px;line-height:1.3;margin:2px 0"></div>
        <div data-preview="description" style="color:#4d5156;font-size:14px;line-height:1.5"></div>
      </div>
      <ul class="small text-muted mt-3 mb-0 ps-3" data-seo-checks></ul>
      @if(!$product || !$product->active)
        <div class="alert alert-warning small mt-3 mb-0">محصول غیرفعال است؛ تا فعال نشود با noindex منتشر می‌شود.</div>
      @endif
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var tab = document.getElementById('tab_seo');
  if (!tab) { return; }
  var titleMax = parseInt(tab.dataset.titleMax, 10), descMax = parseInt(tab.dataset.descMax, 10);
  var field = function (name) { return tab.querySelector('[data-seo="' + name + '"]'); };
  var title = field('meta_title'), desc = field('meta_description'), keyword = field('focus_keyword');
  var nameInput = document.querySelector('input[name="name"]');

  function cut(text, max) { return text.length > max ? text.slice(0, max - 1).replace(/\s+\S*$/, '') + '…' : text; }

  function render() {
    var t = (title.value || '').trim() || (nameInput ? nameInput.value : '') || title.placeholder;
    var d = (desc.value || '').trim() || desc.placeholder;
    tab.querySelector('[data-count="meta_title"]').textContent = (title.value || '').length;
    tab.querySelector('[data-count="meta_description"]').textContent = (desc.value || '').length;
    tab.querySelector('[data-count="meta_title"]').parentNode.className = (title.value || '').length > titleMax ? 'text-danger' : 'text-muted';
    tab.querySelector('[data-count="meta_description"]').parentNode.className = (desc.value || '').length > descMax ? 'text-danger' : 'text-muted';
    tab.querySelector('[data-preview="title"]').textContent = cut(t, titleMax);
    tab.querySelector('[data-preview="description"]').textContent = cut(d, descMax);

    var checks = [], k = (keyword.value || '').trim().toLowerCase();
    var tl = (title.value || '').length, dl = (desc.value || '').length;
    checks.push((tl >= 30 && tl <= titleMax ? '✅ ' : '⚠️ ') + 'طول عنوان: ' + tl + ' (بهتر ۳۰ تا ' + titleMax + ')');
    checks.push((dl >= 120 && dl <= descMax ? '✅ ' : '⚠️ ') + 'طول توضیح: ' + dl + ' (بهتر ۱۲۰ تا ' + descMax + ')');
    if (k) {
      var inTitle = t.toLowerCase().indexOf(k);
      checks.push((inTitle > -1 && inTitle < 20 ? '✅ ' : '⚠️ ') + 'کلمهٔ کلیدی ' + (inTitle > -1 ? (inTitle < 20 ? 'اول عنوان است' : 'در عنوان هست ولی دیر آمده') : 'در عنوان نیست'));
      checks.push((d.toLowerCase().indexOf(k) > -1 ? '✅ ' : '⚠️ ') + 'کلمهٔ کلیدی ' + (d.toLowerCase().indexOf(k) > -1 ? 'در توضیح هست' : 'در توضیح نیست'));
    }
    tab.querySelector('[data-seo-checks]').innerHTML = checks.map(function (c) { return '<li>' + c.replace(/</g, '&lt;') + '</li>'; }).join('');
  }

  [title, desc, keyword, nameInput].forEach(function (el) { el && el.addEventListener('input', render); });
  render();

  var save = tab.querySelector('[data-seo-save]');
  if (save && tab.dataset.saveUrl) {
    save.addEventListener('click', async function () {
      var body = new URLSearchParams();
      tab.querySelectorAll('[name^="seo["]').forEach(function (el) {
        if (el.disabled || (el.type === 'checkbox' && !el.checked)) { return; }
        body.set(el.name, el.value);
      });
      save.disabled = true;
      try {
        var resp = await fetch(tab.dataset.saveUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
        var data = await resp.json().catch(function () { return {}; });
        if (!resp.ok || !data.ok) {
          var msgs = data.errors ? Object.values(data.errors).flat().join(' | ') : (data.message || 'خطا در ذخیره');
          throw new Error(msgs);
        }
        var t = document.createElement('div'); t.className = 'alert alert-success position-fixed start-0 top-0 m-3'; t.style.zIndex = '2000'; t.textContent = 'سئو ذخیره شد'; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 2000);
      } catch (e) {
        var t2 = document.createElement('div'); t2.className = 'alert alert-danger position-fixed start-0 top-0 m-3'; t2.style.zIndex = '2000'; t2.textContent = e.message; document.body.appendChild(t2); setTimeout(function () { t2.remove(); }, 4000);
      } finally {
        save.disabled = false;
      }
    });
  }
})();
</script>
@endpush
