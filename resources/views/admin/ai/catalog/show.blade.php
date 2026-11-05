@extends('cms::admin.layout.index')

@php
  $editable = $plan->status === 'ready';
  $groups = $payload['groups'] ?? [];

  // Category paths as a nested tree for display.
  $tree = [];
  foreach ($payload['categories'] ?? [] as $path) {
      $node = &$tree;
      foreach (explode(' > ', $path) as $part) {
          $node[$part] ??= [];
          $node = &$node[$part];
      }
      unset($node);
  }
  $productCount = collect($groups)->sum(fn ($g) => $g['mode'] === 'variants' ? 1 : count($g['items']));
@endphp

@section('content')
@include('shop::admin.partials.ui')
<div class="content shop-ui ai-catalog-show">
  @if(session('success'))
    <div class="shop-note tone-success mb-3"><i class="ph-check-circle me-1"></i>{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="shop-note tone-danger mb-3"><i class="ph-warning-circle me-1"></i>{{ $errors->first() }}</div>
  @endif

  <div class="shop-head">
    <span class="shop-icon shop-icon--lg tone-primary"><i class="ph-git-branch"></i></span>
    <div>
      <h4 class="shop-head__title">طرح کاتالوگ #{{ $plan->id }}</h4>
      <p class="shop-head__sub">پیش از ساخت محصولات، گروه‌ها را ببین و تیک بزن</p>
    </div>
  </div>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="{{ route('admin.shop.ai.catalog.index') }}" class="btn btn-light btn-sm">بازگشت</a>
    <div class="small text-muted" dir="ltr">
      {{ $plan->batch }} · {{ $plan->driver }} · {{ $plan->model }} · in {{ number_format($plan->input_tokens) }} / out {{ number_format($plan->output_tokens) }}
    </div>
  </div>

  @if(!empty($payload['notes']))
    <div class="shop-note tone-info mb-3"><strong>نکتهٔ AI:</strong> {{ $payload['notes'] }}</div>
  @endif

  @if($plan->status === 'applied')
    <div class="shop-note tone-success mb-3">
      این طرح {{ \RMS\Shop\Support\UiDate::human($plan->applied_at) }} اعمال شد: {{ count($plan->result['products'] ?? []) }} محصول،
      {{ count($plan->result['categories_created'] ?? []) }} دستهٔ تازه، {{ count($plan->result['brands_created'] ?? []) }} برند تازه.
    </div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="card h-100 mb-0">
        <div class="card-header"><h6 class="shop-card-title">درخت دسته‌بندی ({{ count($payload['categories'] ?? []) }})</h6></div>
        <div class="card-body small">
          @php
            $render = function (array $nodes) use (&$render) {
                $html = '<ul class="mb-0">';
                foreach ($nodes as $name => $children) {
                    $html .= '<li>'.e($name).($children ? $render($children) : '').'</li>';
                }
                return $html.'</ul>';
            };
          @endphp
          {!! $tree ? $render($tree) : '—' !!}
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100 mb-0">
        <div class="card-header"><h6 class="shop-card-title">برندها ({{ count($payload['brands'] ?? []) }})</h6></div>
        <div class="card-body d-flex flex-wrap gap-2 align-content-start">
          @forelse($payload['brands'] ?? [] as $brand)
            <span class="shop-soft tone-muted" dir="ltr">{{ $brand }}</span>
          @empty
            —
          @endforelse
        </div>
      </div>
    </div>
  </div>

  @if(!empty($payload['suspicious']))
    <div class="card mb-3 shop-card-accent tone-warning">
      <div class="card-header"><h6 class="shop-card-title">ردیف‌های مشکوک ({{ count($payload['suspicious']) }})</h6></div>
      <div class="card-body small">
        <p class="text-muted">این ردیف‌ها همان‌طور که در فایل هستند ساخته می‌شوند. اگر AI درست می‌گوید، اول فایل را اصلاح و دوباره وارد کنید، یا بعد از ساخت محصول را ویرایش کنید.</p>
        <ul class="mb-0">
          @foreach($payload['suspicious'] as $row)
            <li class="mb-1">
              <strong>{{ $items[$row['id']]?->label() ?? '#'.$row['id'] }}</strong>
              <span class="text-muted">(ردیف {{ $items[$row['id']]?->row }})</span>:
              {{ $row['reason'] }}
              @if($row['suggestion'] !== '')
                <span class="text-success">← {{ $row['suggestion'] }}</span>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  <form method="post" action="{{ route('admin.shop.ai.catalog.apply', $plan) }}">
    @csrf
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="shop-card-title">گروه‌های محصول ({{ count($groups) }} گروه، حدود {{ $productCount }} صفحهٔ محصول)</h6>
        @if($editable)
          <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="toggle-all" checked>
            <label class="form-check-label small" for="toggle-all">همه</label>
          </div>
        @endif
      </div>
      <div class="table-responsive">
        <table class="table align-top mb-0">
          <thead>
            <tr>
              <th style="width:40px"></th>
              <th>محصول</th>
              <th style="width:170px">حالت</th>
              <th>ردیف‌ها</th>
            </tr>
          </thead>
          <tbody>
            @foreach($groups as $index => $group)
              <tr @class(['shop-row-warn' => collect($group['items'])->contains(fn ($i) => $suspicious->has($i['id'])) || !empty($group['unplanned'])])>
                <td>
                  <input type="checkbox" class="form-check-input group-check" name="groups[]" value="{{ $index }}" checked @disabled(!$editable)>
                </td>
                <td>
                  <div class="fw-semibold">{{ $group['name'] }}</div>
                  <div class="small text-muted">
                    {{ $group['category'] ?: 'بدون دسته' }}
                    @if($group['brand']) · <span dir="ltr">{{ $group['brand'] }}</span> @endif
                  </div>
                  @if(!empty($group['unplanned']))
                    <div class="small text-warning">AI این ردیف را در هیچ گروهی نگذاشته بود.</div>
                  @endif
                </td>
                <td>
                  @if(count($group['items']) > 1)
                    <select name="mode[{{ $index }}]" class="form-select form-select-sm" @disabled(!$editable)>
                      <option value="variants" @selected($group['mode'] === 'variants')>یک محصول با تنوع «{{ $group['attribute'] }}»</option>
                      <option value="separate" @selected($group['mode'] === 'separate')>محصول جدا برای هر ردیف</option>
                    </select>
                  @else
                    <span class="small text-muted">تک محصول</span>
                  @endif
                </td>
                <td class="small">
                  @foreach($group['items'] as $item)
                    <div @class(['text-danger' => $suspicious->has($item['id'])])>
                      <span class="shop-soft tone-muted">{{ $item['value'] ?: '—' }}</span>
                      <span class="text-muted">{{ $items[$item['id']]?->label() }}</span>
                      @if($group['mode'] === 'separate' && count($group['items']) > 1 && $item['name'])
                        <div class="ms-3">← {{ $item['name'] }}</div>
                      @endif
                    </div>
                  @endforeach
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    @if($editable)
      <div class="shop-note tone-muted mb-3">
        با اعمال، دسته‌ها و برندهای لازم ساخته می‌شوند و محصولات <strong>غیرفعال و بدون قیمت</strong> ساخته می‌شوند. گروه‌هایی که تیکشان را بردارید در لیست می‌مانند تا بعداً دوباره طرح بگیرند.
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="ph-check me-1"></i> ساخت محصولات انتخاب‌شده</button>
      </div>
    @endif
  </form>

  @if($editable)
    <form method="post" action="{{ route('admin.shop.ai.catalog.reject', $plan) }}" class="mt-2"
        data-confirm="کل این طرح رد شود؟" data-confirm-title="رد طرح" data-confirm-desc="هیچ محصول، دسته یا برندی ساخته نمی‌شود." data-confirm-type="danger" data-confirm-ok="رد کن" data-confirm-icon="ph-x-circle">
      @csrf
      <button type="submit" class="btn btn-light"><i class="ph-x me-1"></i> رد طرح</button>
    </form>
  @endif
</div>
@include('shop::admin.partials.confirm')
@endsection

@push('scripts')
<script>
  (function () {
    var all = document.getElementById('toggle-all');
    if (!all) { return; }
    all.addEventListener('change', function () {
      document.querySelectorAll('.ai-catalog-show .group-check').forEach(function (box) { box.checked = all.checked; });
    });
  })();
</script>
@endpush
