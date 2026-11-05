@extends('cms::admin.layout.index')

@php
  $statusLabels = [
    'pending' => ['در انتظار تأیید', 'warning'],
    'applied' => ['اعمال شد', 'success'],
    'rejected' => ['رد شد', 'dark'],
    'superseded' => ['جایگزین شد', 'secondary'],
    'failed' => ['خطا', 'danger'],
  ];
@endphp

@section('content')
<div class="content ai-page">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول پیشنهادها هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    <div class="row g-3 mb-3">
      @foreach($statusLabels as $key => [$label, $class])
        <div class="col-6 col-md">
          <a href="{{ route('admin.shop.ai-proposals.index', ['status' => $key]) }}" class="card mb-0 text-decoration-none {{ $status === $key ? 'border-'.$class : '' }}">
            <div class="card-body py-2">
              <div class="text-muted small">{{ $label }}</div>
              <div class="fs-4 fw-semibold text-{{ $class }}">{{ $counts[$key] ?? 0 }}</div>
            </div>
          </a>
        </div>
      @endforeach
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h6 class="mb-0"><i class="ph-check-square-offset me-1"></i> تغییرهای پیشنهادی ایجنت</h6>
        <form method="get" class="ms-auto d-flex gap-2">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" @selected($status === 'all')>همه</option>
            @foreach($statusLabels as $k => [$label])
              <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
            @endforeach
          </select>
          <select name="resource" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">همهٔ منبع‌ها</option>
            @foreach($resources as $key => $res)
              <option value="{{ $key }}" @selected($type === $key)>{{ $res->label() }}</option>
            @endforeach
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr><th>#</th><th>منبع</th><th>فیلدها</th><th>وضعیت</th><th>توسط</th><th>تاریخ</th><th></th></tr>
          </thead>
          <tbody>
            @forelse($rows as $p)
              @php
                $res = $resources[$p->resource_type] ?? null;
                [$sLabel, $sClass] = $statusLabels[$p->status] ?? [$p->status, 'secondary'];
              @endphp
              <tr>
                <td dir="ltr">{{ $p->id }}</td>
                <td>{{ $res?->label() ?? $p->resource_type }} <span class="text-muted" dir="ltr">#{{ $p->resource_id }}</span></td>
                <td class="small">{{ collect((array) $p->changes)->keys()->map(fn ($f) => $res?->fields()[$f]['label'] ?? $f)->implode('، ') }}</td>
                <td><span class="badge bg-{{ $sClass }}">{{ $sLabel }}</span></td>
                <td class="small">{{ $p->actor ?? '—' }}</td>
                <td class="small text-nowrap" dir="ltr">{{ $p->created_at?->format('Y-m-d H:i') }}</td>
                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.shop.ai-proposals.show', $p) }}">{{ $p->status === 'pending' ? 'بررسی' : 'نمایش' }}</a></td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">موردی نیست.</td></tr>
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
