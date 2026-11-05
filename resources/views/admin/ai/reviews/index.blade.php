@extends('cms::admin.layout.index')

@php
  $statusFilters = ['' => 'همه', 'requested' => 'در انتظار', 'done' => 'انجام شد', 'dismissed' => 'لغو شد'];
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
    <span class="shop-icon shop-icon--lg tone-primary"><i class="ph-chart-bar"></i></span>
    <div>
      <h4 class="shop-head__title">آمار بررسی AI</h4>
      <p class="shop-head__sub">پوشش و کیفیت محصول‌ها بر اساس بررسی‌های ایجنت</p>
    </div>
  </div>
  @include('shop::admin.partials.ai-nav', ['active' => 'reviews'])

  @if(!$ready)
    <div class="shop-note tone-warning">جدول بررسی‌های AI هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    @php
      $pct = $stats['total'] > 0 ? (int) round($stats['reviewed'] / $stats['total'] * 100) : 0;
      $avgTone = \RMS\Shop\Support\UiTone::score($stats['avg_score']);
    @endphp

    <div class="row g-3 mb-3">
      <div class="col-6 col-xl-3">
        @include('shop::admin.partials.stat', ['label' => 'پوشش بررسی', 'value' => $stats['reviewed'], 'suffix' => 'از '.$stats['total'].' محصول', 'icon' => 'ph-chart-pie', 'tone' => 'primary', 'meter' => $pct, 'hint' => $pct.'٪ بررسی شده · '.$stats['unreviewed'].' مانده'])
      </div>
      <div class="col-6 col-xl-3">
        @include('shop::admin.partials.stat', ['label' => 'میانگین امتیاز', 'value' => $stats['avg_score'] ?? '—', 'suffix' => 'از ۱۰۰', 'icon' => 'ph-gauge', 'tone' => $avgTone, 'hint' => $stats['low'].' محصول زیر ۵۰'])
      </div>
      <div class="col-6 col-xl-3">
        @include('shop::admin.partials.stat', ['label' => 'در انتظار ایجنت', 'value' => $stats['requested'], 'icon' => 'ph-hourglass', 'tone' => $stats['requested'] > 0 ? 'warning' : 'muted', 'hint' => 'در ۷ روز اخیر '.$stats['last_7_days'].' بررسی انجام شد'])
      </div>
      <div class="col-6 col-xl-3">
        <div class="card mb-0 h-100">
          <div class="p-3">
            <div class="shop-stat__label mb-2">میانگین هر جنبه</div>
            <div class="d-flex flex-column gap-2">
              @foreach($aspects as $key => $label)
                @php
                  $avg = $stats['aspect_avg'][$key] ?? null;
                @endphp
                <div class="shop-meter-row tone-{{ \RMS\Shop\Support\UiTone::score($avg) }}">
                  <span class="shop-meter-row__label">{{ $label }}</span>
                  <div class="shop-meter"><span style="width: {{ $avg ?? 0 }}%"></span></div>
                  <span class="shop-meter-row__value shop-score">{{ $avg ?? '—' }}</span>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>

    @if($stats['unreviewed'] > 0)
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
          <h6 class="shop-card-title"><i class="ph-tray"></i> بررسی‌نشده‌ها <span class="shop-soft tone-muted">{{ $stats['unreviewed'] }}</span></h6>
          <form method="post" action="{{ route('admin.shop.ai-reviews.request-all') }}" class="ms-auto"
                data-confirm="برای همهٔ محصولات بررسی‌نشده درخواست بررسی ثبت شود؟"
                data-confirm-title="درخواست بررسی گروهی"
                data-confirm-desc="برای هر محصولی که هنوز بررسی نشده یک درخواست در صف ایجنت می‌رود. چیزی روی محصول‌ها عوض نمی‌شود."
                data-confirm-type="info"
                data-confirm-ok="ثبت درخواست‌ها"
                data-confirm-icon="ph-paper-plane-tilt">
            @csrf
            <button class="btn btn-light btn-sm" type="submit"><i class="ph-paper-plane-tilt me-1"></i> درخواست بررسی برای همه</button>
          </form>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
          @foreach($unreviewed as $p)
            <a href="{{ route('admin.shop.products.edit', $p->id) }}" class="shop-soft tone-muted text-decoration-none" title="{{ $p->name }}">
              <span class="opacity-75">#{{ $p->id }}</span> {{ \Illuminate\Support\Str::limit($p->name, 34) }}
            </a>
          @endforeach
          @if($stats['unreviewed'] > $unreviewed->count())
            <span class="text-muted fs-sm align-self-center">و {{ $stats['unreviewed'] - $unreviewed->count() }} محصول دیگر</span>
          @endif
        </div>
      </div>
    @endif

    @if($actions->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
          <h6 class="shop-card-title"><i class="ph-pencil-simple"></i> تغییرهای مستقیم ایجنت (با دستور ادمین)</h6>
          <a href="{{ route('admin.shop.ai-activity.index', ['type' => 'apply']) }}" class="ms-auto fs-sm">همهٔ تغییرهای مستقیم</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover shop-table">
            <thead><tr><th>محصول</th><th>فیلدها</th><th>دستور ادمین</th><th>تاریخ</th><th style="width:1%"></th></tr></thead>
            <tbody>
              @foreach($actions as $a)
                <tr>
                  <td><a href="{{ route('admin.shop.products.edit', $a->product_id) }}">{{ \Illuminate\Support\Str::limit($a->product?->name ?? ('#'.$a->product_id), 40) }}</a></td>
                  <td class="fs-sm">{{ implode('، ', (array) $a->fields) }}</td>
                  <td class="fs-sm" style="max-width:22rem">{{ \Illuminate\Support\Str::limit($a->operator_instruction, 70) }}</td>
                  <td class="fs-sm text-nowrap text-muted">{{ \RMS\Shop\Support\UiDate::html($a->created_at) }}</td>
                  <td class="text-nowrap"><button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#action-{{ $a->id }}"><i class="ph-eye me-1"></i> جزئیات</button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @foreach($actions as $a)
        @include('shop::admin.partials.change-modal', [
          'modalId' => 'action-'.$a->id,
          'title' => $a->product?->name ?? ('محصول #'.$a->product_id),
          'meta' => \RMS\Shop\Support\UiDate::both($a->created_at).' · '.($a->source ?: 'ایجنت'),
          'instruction' => $a->operator_instruction,
          'before' => (array) $a->before,
          'after' => (array) $a->after,
          'link' => route('admin.shop.products.ai-activity', $a->product_id),
        ])
      @endforeach
    @endif

    <div class="card">
      <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h6 class="shop-card-title"><i class="ph-list-checks"></i> لاگ بررسی‌ها</h6>
        <nav class="shop-nav mb-0 ms-auto">
          @foreach($statusFilters as $k => $label)
            <a href="{{ route('admin.shop.ai-reviews.index', $k === '' ? [] : ['status' => $k]) }}" class="{{ $status === (string) $k ? 'is-active' : '' }}">{{ $label }}</a>
          @endforeach
        </nav>
      </div>
      <div class="table-responsive">
        <table class="table table-hover shop-table">
          <thead>
            <tr><th>محصول</th><th>وضعیت</th><th>امتیاز</th><th>خلاصه</th><th>تاریخ</th><th style="width:1%"></th></tr>
          </thead>
          <tbody>
            @forelse($reviews as $r)
              <tr>
                <td>
                  <a href="{{ route('admin.shop.products.edit', $r->product_id) }}" class="fw-semibold">{{ $r->product?->name ?? ('#'.$r->product_id) }}</a>
                  <span class="shop-cell-sub">{{ collect($r->aspects ?? [])->map(fn ($a) => $aspects[$a] ?? $a)->implode('، ') }}</span>
                </td>
                <td><span class="shop-dot tone-{{ \RMS\Shop\Support\UiTone::tone('review', $r->status) }}">{{ \RMS\Shop\Support\UiTone::label('review', $r->status) }}</span></td>
                <td>
                  @if($r->score !== null)
                    <span class="shop-score tone-{{ \RMS\Shop\Support\UiTone::score($r->score) }}">{{ $r->score }}</span>
                  @else <span class="text-muted">—</span> @endif
                </td>
                <td class="fs-sm" style="max-width:26rem">{{ \Illuminate\Support\Str::limit((string) $r->summary, 110) ?: '—' }}</td>
                <td class="fs-sm text-nowrap text-muted">{{ \RMS\Shop\Support\UiDate::html($r->reviewed_at ?? $r->requested_at) }}</td>
                <td class="text-nowrap">
                  @if($r->status === 'done')
                    <a class="btn btn-light btn-sm" href="{{ route('admin.shop.ai-reviews.show', $r) }}"><i class="ph-file-text me-1"></i> گزارش</a>
                  @elseif($r->status === 'requested')
                    <form method="post" action="{{ route('admin.shop.ai-reviews.dismiss', $r) }}" class="d-inline"
                          data-confirm="درخواست بررسی این محصول لغو شود؟"
                          data-confirm-title="لغو درخواست"
                          data-confirm-type="warning"
                          data-confirm-ok="لغو کن"
                          data-confirm-cancel="نه، بماند">@csrf
                      <button class="btn btn-light btn-sm" type="submit"><i class="ph-x me-1"></i> لغو</button></form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6">@include('shop::admin.partials.empty', ['icon' => 'ph-clipboard-text', 'title' => 'هنوز بررسی‌ای ثبت نشده است', 'hint' => 'از دکمهٔ «بررسی AI» در صفحهٔ هر محصول درخواست بده.'])</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($reviews instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="card-footer">{{ $reviews->links('shop::admin.partials.pagination') }}</div>
      @endif
    </div>
  @endif
</div>
@include('shop::admin.partials.confirm')
@endsection
