@extends('cms::admin.layout.index')

@php
  $statusLabels = [
    'requested' => ['در انتظار بررسی', 'warning'],
    'done' => ['انجام شد', 'success'],
    'dismissed' => ['لغو شد', 'dark'],
  ];
  $scoreClass = fn ($s) => $s === null ? 'secondary' : ($s >= 75 ? 'success' : ($s >= 50 ? 'warning' : 'danger'));
@endphp

@section('content')
<div class="content ai-page">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="mb-3">
    <a href="{{ route('admin.shop.ai.index') }}" class="btn btn-sm btn-outline-primary">پیشنهادهای AI</a>
    <a href="{{ route('admin.shop.ai.catalog.index') }}" class="btn btn-sm btn-outline-primary">طرح کاتالوگ</a>
    <a href="{{ route('admin.shop.ai.images.index') }}" class="btn btn-sm btn-outline-primary">عکس‌های پیشنهادی</a>
    <a href="{{ route('admin.shop.ai-reviews.index') }}" class="btn btn-sm btn-primary">آمار بررسی AI</a>
  </div>

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول بررسی‌های AI هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    @php($pct = $stats['total'] > 0 ? (int) round($stats['reviewed'] / $stats['total'] * 100) : 0)
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card h-100 mb-0"><div class="card-body">
          <div class="text-muted small mb-1">پوشش بررسی</div>
          <div class="fs-3 fw-semibold">{{ $stats['reviewed'] }} <span class="fs-6 text-muted">از {{ $stats['total'] }} محصول</span></div>
          <div class="progress mt-2" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
          <div class="small text-muted mt-1">{{ $pct }}٪ بررسی شده · {{ $stats['unreviewed'] }} مانده</div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100 mb-0"><div class="card-body">
          <div class="text-muted small mb-1">میانگین امتیاز</div>
          <div class="fs-3 fw-semibold"><span class="badge bg-{{ $scoreClass($stats['avg_score']) }}">{{ $stats['avg_score'] ?? '—' }}</span></div>
          <div class="small text-muted mt-2">{{ $stats['low'] }} محصول زیر ۵۰</div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100 mb-0"><div class="card-body">
          <div class="text-muted small mb-1">در انتظار ایجنت</div>
          <div class="fs-3 fw-semibold">{{ $stats['requested'] }}</div>
          <div class="small text-muted mt-2">۷ روز اخیر: {{ $stats['last_7_days'] }} بررسی انجام شد</div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card h-100 mb-0"><div class="card-body">
          <div class="text-muted small mb-1">میانگین هر جنبه</div>
          @foreach($aspects as $key => $label)
            <div class="d-flex justify-content-between small"><span>{{ $label }}</span>
              <span class="badge bg-{{ $scoreClass($stats['aspect_avg'][$key] ?? null) }}">{{ $stats['aspect_avg'][$key] ?? '—' }}</span></div>
          @endforeach
        </div></div>
      </div>
    </div>

    @if($stats['unreviewed'] > 0)
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
          <h6 class="mb-0">بررسی‌نشده‌ها ({{ $stats['unreviewed'] }})</h6>
          <form method="post" action="{{ route('admin.shop.ai-reviews.request-all') }}" class="ms-auto"
                onsubmit="return confirm('برای همهٔ محصولات بررسی‌نشده درخواست ثبت شود؟')">
            @csrf
            <button class="btn btn-sm btn-primary" type="submit">درخواست بررسی برای همهٔ بررسی‌نشده‌ها</button>
          </form>
        </div>
        <div class="card-body">
          @foreach($unreviewed as $p)
            <a href="{{ route('admin.shop.products.edit', $p->id) }}" class="badge bg-light text-dark border me-1 mb-1">#{{ $p->id }} {{ \Illuminate\Support\Str::limit($p->name, 40) }}</a>
          @endforeach
          @if($stats['unreviewed'] > $unreviewed->count())
            <span class="text-muted small">… و {{ $stats['unreviewed'] - $unreviewed->count() }} محصول دیگر</span>
          @endif
        </div>
      </div>
    @endif

    @if($actions->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">تغییرهای اعمال‌شدهٔ مستقیم توسط ایجنت (با دستور ادمین)</h6></div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>محصول</th><th>فیلدها</th><th>دستور ادمین</th><th>قبل ← بعد</th><th>تاریخ</th></tr></thead>
            <tbody>
              @foreach($actions as $a)
                <tr>
                  <td><a href="{{ route('admin.shop.products.edit', $a->product_id) }}">{{ $a->product?->name ?? ('#'.$a->product_id) }}</a></td>
                  <td class="small">{{ implode('، ', (array) $a->fields) }}</td>
                  <td class="small">{{ \Illuminate\Support\Str::limit($a->operator_instruction, 80) }}</td>
                  <td class="small">
                    @foreach((array) $a->fields as $f)
                      <div><span class="text-muted">{{ $f }}:</span>
                        {{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($a->before, $f)), 40) ?: '—' }}
                        ← {{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($a->after, $f)), 40) ?: '—' }}</div>
                    @endforeach
                  </td>
                  <td class="small text-nowrap" dir="ltr">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    <div class="card">
      <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h6 class="mb-0">لاگ بررسی‌ها</h6>
        <form method="get" class="ms-auto d-flex gap-2">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">همه</option>
            @foreach($statusLabels as $k => [$label])
              <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr><th>#</th><th>محصول</th><th>جنبه‌ها</th><th>وضعیت</th><th>امتیاز</th><th>خلاصه</th><th>تاریخ</th><th></th></tr>
          </thead>
          <tbody>
            @forelse($reviews as $r)
              @php([$sLabel, $sClass] = $statusLabels[$r->status] ?? [$r->status, 'secondary'])
              <tr>
                <td dir="ltr">{{ $r->id }}</td>
                <td><a href="{{ route('admin.shop.products.edit', $r->product_id) }}">{{ $r->product?->name ?? ('#'.$r->product_id) }}</a></td>
                <td class="small">{{ collect($r->aspects ?? [])->map(fn ($a) => $aspects[$a] ?? $a)->implode('، ') }}</td>
                <td><span class="badge bg-{{ $sClass }}">{{ $sLabel }}</span></td>
                <td>@if($r->score !== null)<span class="badge bg-{{ $scoreClass($r->score) }}">{{ $r->score }}</span>@else — @endif</td>
                <td class="small">{{ \Illuminate\Support\Str::limit((string) $r->summary, 90) ?: '—' }}</td>
                <td class="small text-nowrap" dir="ltr">{{ ($r->reviewed_at ?? $r->requested_at)?->format('Y-m-d H:i') }}</td>
                <td class="text-nowrap">
                  @if($r->status === 'done')
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.shop.ai-reviews.show', $r) }}">گزارش</a>
                  @elseif($r->status === 'requested')
                    <form method="post" action="{{ route('admin.shop.ai-reviews.dismiss', $r) }}" class="d-inline">@csrf
                      <button class="btn btn-sm btn-outline-secondary" type="submit">لغو</button></form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center text-muted py-4">هنوز بررسی‌ای ثبت نشده است.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($reviews instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="card-footer">{{ $reviews->links() }}</div>
      @endif
    </div>
  @endif
</div>
@endsection
