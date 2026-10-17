@extends('cms::admin.layout.index')

@section('content')
<div class="content ai-review-page">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <a href="{{ route('admin.shop.ai.index') }}" class="btn btn-sm btn-outline-secondary">بازگشت به لیست</a>
      @if($product)
        <a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">ویرایش محصول</a>
      @endif
    </div>
    <div class="small text-muted" dir="ltr">
      {{ $suggestion->driver }} · {{ $suggestion->model }} · {{ $suggestion->images_sent }} img ·
      in {{ number_format($suggestion->input_tokens) }} / out {{ number_format($suggestion->output_tokens) }}
    </div>
  </div>

  @if(!$product)
    <div class="alert alert-warning">محصول این پیشنهاد حذف شده است.</div>
  @else
    @if(!empty($payload['notes']))
      <div class="alert alert-info"><strong>نکتهٔ AI:</strong> {{ $payload['notes'] }}</div>
    @endif

    <form method="post" action="{{ route('admin.shop.ai.apply', $suggestion) }}" id="ai-apply-form">
      @csrf
      <input type="hidden" name="next" value="1">

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">{{ $product->name }} <span class="text-muted small">#{{ $product->id }}</span></h6>
          @if($product->mainImageUrl())
            <img src="{{ $product->mainImageUrl() }}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:6px">
          @endif
        </div>
        <div class="table-responsive">
          <table class="table align-top mb-0">
            <thead>
              <tr>
                <th style="width:48px"></th>
                <th style="width:120px">فیلد</th>
                <th style="width:40%">فعلی</th>
                <th>پیشنهاد AI</th>
              </tr>
            </thead>
            <tbody>
              @php($editable = $suggestion->status === 'ready')
              @foreach([
                'name' => ['نام', $product->name, $payload['name'] ?? ''],
                'short_desc' => ['توضیح کوتاه', strip_tags((string) $product->short_desc), $payload['short_desc'] ?? ''],
              ] as $field => [$label, $current, $suggested])
                <tr>
                  <td><input type="checkbox" class="form-check-input" name="fields[]" value="{{ $field }}" @checked(filled($suggested)) @disabled(!$editable || blank($suggested))></td>
                  <td class="fw-semibold">{{ $label }}</td>
                  <td class="text-muted">{{ $current ?: '—' }}</td>
                  <td>{{ $suggested ?: '—' }}</td>
                </tr>
              @endforeach
              <tr>
                <td><input type="checkbox" class="form-check-input" name="fields[]" value="description" @checked(filled($payload['description'] ?? '')) @disabled(!$editable || blank($payload['description'] ?? ''))></td>
                <td class="fw-semibold">توضیحات</td>
                <td class="text-muted small"><div class="ai-preview">{!! $product->description ?: '—' !!}</div></td>
                <td class="small"><div class="ai-preview">{!! $payload['description'] ?? '—' !!}</div></td>
              </tr>
              <tr>
                <td><input type="checkbox" class="form-check-input" name="fields[]" value="category_id" @checked($suggestedCategory && $suggestedCategory->id !== $product->category_id) @disabled(!$editable || !$suggestedCategory)></td>
                <td class="fw-semibold">دسته‌بندی</td>
                <td class="text-muted">{{ $product->category?->name ?? '—' }}</td>
                <td>
                  {{ $suggestedCategory?->name ?? '—' }}
                  @if(!empty($payload['new_category_name']))
                    <div class="small text-warning">پیشنهاد دستهٔ تازه: {{ $payload['new_category_name'] }}</div>
                  @endif
                  @if(!empty($payload['category_reason']))
                    <div class="small text-muted">{{ $payload['category_reason'] }}</div>
                  @endif
                </td>
              </tr>
              <tr>
                <td><input type="checkbox" class="form-check-input" name="fields[]" value="brand_id" @checked($suggestedBrand && $suggestedBrand->id !== $product->brand_id) @disabled(!$editable || !$suggestedBrand)></td>
                <td class="fw-semibold">برند</td>
                <td class="text-muted">{{ $product->brand?->name ?? '—' }}</td>
                <td>
                  {{ $suggestedBrand?->name ?? '—' }}
                  @if(!$suggestedBrand && !empty($payload['detected_brand']))
                    <div class="small text-warning">برند دیده‌شده که در لیست نیست: {{ $payload['detected_brand'] }}</div>
                  @endif
                </td>
              </tr>
              <tr>
                <td><input type="checkbox" class="form-check-input" name="fields[]" value="features" @checked(!empty($payload['feature_groups'])) @disabled(!$editable || empty($payload['feature_groups']))></td>
                <td class="fw-semibold">ویژگی‌ها</td>
                <td class="text-muted small">
                  @forelse($currentFeatures as $feature)
                    <div>{{ $feature->name }}: {{ $feature->value }}</div>
                  @empty
                    —
                  @endforelse
                </td>
                <td class="small">
                  @forelse($payload['feature_groups'] ?? [] as $group)
                    <div class="fw-semibold mt-1">{{ $group['group'] ?? '' }}</div>
                    @foreach($group['features'] ?? [] as $feature)
                      <div>{{ $feature['name'] ?? '' }}: {{ $feature['value'] ?? '' }}</div>
                    @endforeach
                  @empty
                    —
                  @endforelse
                  <div class="text-muted mt-1">ویژگی‌های فعلی حفظ می‌شوند و فقط موارد تازه اضافه می‌شوند.</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </form>

    @if(!empty($payload['sources']))
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">منابع</h6></div>
        <div class="card-body small">
          <div class="text-muted mb-2">صفحه‌هایی که AI برای بررسی مشخصات جستجو کرد. قبل از اعمال، مشخصات را با منبع چک کنید.</div>
          <ol class="mb-0">
            @foreach($payload['sources'] as $source)
              <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] ?: $source['url'] }}</a></li>
            @endforeach
          </ol>
        </div>
      </div>
    @endif

    @php($seoTitle = $payload['meta_title'] ?? ($payload['seo_title'] ?? ''))
    @php($seoDescription = $payload['meta_description'] ?? ($payload['seo_description'] ?? ''))
    @php($hasSeo = filled($seoTitle) || filled($seoDescription) || filled($payload['focus_keyword'] ?? '') || filled($payload['image_alt'] ?? ''))
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <input type="checkbox" class="form-check-input mt-0" form="ai-apply-form" name="fields[]" value="seo" id="ai-apply-seo"
               @checked($hasSeo) @disabled(!$editable || !$hasSeo)>
        <label for="ai-apply-seo" class="mb-0"><h6 class="mb-0">سئو</h6></label>
        <span class="small text-muted">عنوان، توضیح متا، کلمهٔ کلیدی و alt عکس اصلی روی محصول ذخیره می‌شوند.</span>
      </div>
      <div class="card-body">
        @foreach([
          ['عنوان سئو', $seoTitle, (int) config('shop.seo.title_max', 60)],
          ['توضیح متا', $seoDescription, (int) config('shop.seo.description_max', 155)],
          ['کلمهٔ کلیدی اصلی', $payload['focus_keyword'] ?? '', null],
          ['متن alt عکس اصلی', $payload['image_alt'] ?? '', null],
          ['عبارت‌های مرتبط (داخلی)', implode('، ', $payload['keywords'] ?? []), null],
        ] as [$label, $value, $max])
          <div class="mb-2">
            <label class="form-label small text-muted mb-1 d-flex justify-content-between">
              <span>{{ $label }}</span>
              @if($max)
                <span @class(['text-danger' => mb_strlen($value) > $max])>{{ mb_strlen($value) }} / {{ $max }}</span>
              @endif
            </label>
            <div class="input-group">
              <input type="text" class="form-control" readonly value="{{ $value }}">
              <button type="button" class="btn btn-outline-secondary" data-copy="{{ $value }}">کپی</button>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    @if($suggestion->status === 'ready')
      <div class="d-flex flex-wrap gap-2">
        <button type="submit" form="ai-apply-form" class="btn btn-success">اعمال موارد انتخاب‌شده و بعدی</button>
        <form method="post" action="{{ route('admin.shop.ai.reject', $suggestion) }}">
          @csrf
          <input type="hidden" name="next" value="1">
          <button type="submit" class="btn btn-outline-danger">رد و بعدی</button>
        </form>
        <form method="post" action="{{ route('admin.shop.ai.retry', $suggestion) }}">
          @csrf
          <button type="submit" class="btn btn-outline-secondary">دوباره بنویس</button>
        </form>
      </div>
    @else
      <div class="alert alert-secondary mb-0">وضعیت این پیشنهاد: {{ $suggestion->status }}</div>
    @endif
  @endif
</div>

<style>
  .ai-review-page .ai-preview { max-height: 320px; overflow: auto; }
  .ai-review-page .ai-preview p:last-child { margin-bottom: 0; }
</style>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('.ai-review-page [data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var text = button.getAttribute('data-copy') || '';
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
          var original = button.textContent;
          button.textContent = 'کپی شد';
          setTimeout(function () { button.textContent = original; }, 1200);
        });
      }
    });
  });
</script>
@endpush
