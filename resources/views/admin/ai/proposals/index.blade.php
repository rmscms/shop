@extends('cms::admin.layout.index')

@php
  $statusTabs = ['pending' => 'در انتظار تأیید', 'applied' => 'اعمال شد', 'rejected' => 'رد شد', 'superseded' => 'جایگزین شد', 'failed' => 'خطا', 'all' => 'همه'];
  $resourceIcons = ['product' => 'ph-package', 'category' => 'ph-folders', 'brand' => 'ph-tag'];
@endphp

@section('content')
@include('shop::admin.partials.ui')
<div class="content shop-ui">
  @if(session('success'))
    <div class="shop-note tone-success mb-3"><i class="ph-check-circle me-1"></i>{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="shop-note tone-danger mb-3"><i class="ph-warning-circle me-1"></i>{{ $errors->first() }}</div>
  @endif

  <div class="shop-head">
    <span class="shop-icon shop-icon--lg tone-success"><i class="ph-check-square-offset"></i></span>
    <div>
      <h4 class="shop-head__title">تأیید تغییرهای پیشنهادی AI</h4>
      <p class="shop-head__sub">ایجنت پیشنهاد می‌دهد، تو تأیید می‌کنی؛ تا تأیید نشود چیزی روی فروشگاه عوض نمی‌شود</p>
    </div>
  </div>
  @include('shop::admin.partials.ai-nav', ['active' => 'proposals'])

  @if(!$ready)
    <div class="shop-note tone-warning">جدول پیشنهادها هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    <div class="card">
      <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <nav class="shop-nav mb-0">
          @foreach($statusTabs as $key => $label)
            @php
              $count = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
            @endphp
            <a href="{{ route('admin.shop.ai-proposals.index', array_filter(['status' => $key, 'resource' => $type])) }}" class="{{ $status === $key ? 'is-active' : '' }}">
              {{ $label }} <span class="opacity-75">{{ $count }}</span>
            </a>
          @endforeach
        </nav>
        <form method="get" class="ms-auto">
          <input type="hidden" name="status" value="{{ $status }}">
          <select name="resource" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">همهٔ منبع‌ها</option>
            @foreach($resources as $key => $res)
              <option value="{{ $key }}" @selected($type === $key)>{{ $res->label() }}</option>
            @endforeach
          </select>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover shop-table">
          <thead><tr><th>منبع</th><th>فیلدها</th><th>وضعیت</th><th>توسط</th><th>تاریخ</th><th style="width:1%"></th></tr></thead>
          <tbody>
            @forelse($rows as $p)
              @php
                $res = $resources[$p->resource_type] ?? null;
                $tone = \RMS\Shop\Support\UiTone::tone('proposal', $p->status);
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="shop-icon shop-icon--sm tone-muted"><i class="{{ $resourceIcons[$p->resource_type] ?? 'ph-circle' }}"></i></span>
                    <div>
                      <div class="fw-semibold">{{ $res?->label() ?? $p->resource_type }} <span class="text-muted fw-normal" dir="ltr">#{{ $p->resource_id }}</span></div>
                    </div>
                  </div>
                </td>
                <td class="fs-sm">{{ collect((array) $p->changes)->keys()->map(fn ($f) => $res?->fields()[$f]['label'] ?? $f)->implode('، ') }}</td>
                <td><span class="shop-dot tone-{{ $tone }}">{{ \RMS\Shop\Support\UiTone::label('proposal', $p->status) }}</span></td>
                <td class="fs-sm text-muted">{{ $p->actor ?? '—' }}</td>
                <td class="fs-sm text-nowrap text-muted">{{ \RMS\Shop\Support\UiDate::html($p->created_at) }}</td>
                <td><a class="btn btn-light btn-sm" href="{{ route('admin.shop.ai-proposals.show', $p) }}">{{ $p->status === 'pending' ? 'بررسی' : 'نمایش' }}</a></td>
              </tr>
            @empty
              <tr><td colspan="6">@include('shop::admin.partials.empty', ['icon' => 'ph-check-square-offset', 'title' => 'موردی نیست', 'hint' => 'وقتی ایجنت تغییری پیشنهاد بدهد اینجا می‌آید.'])</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="card-footer">{{ $rows->links() }}</div>
      @endif
    </div>
  @endif
</div>
@endsection
