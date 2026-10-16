@extends('cms::admin.layout.index')

@php
  $statusLabels = [
    'pending' => ['در صف', 'secondary'],
    'processing' => ['در حال ساخت', 'info'],
    'ready' => ['آمادهٔ بررسی', 'primary'],
    'failed' => ['خطا', 'danger'],
    'applied' => ['اعمال شد', 'success'],
    'rejected' => ['رد شد', 'dark'],
  ];
@endphp

@section('content')
<div class="content ai-catalog-page">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="mb-3">
    <a href="{{ route('admin.shop.ai.index') }}" class="btn btn-sm btn-outline-secondary">دستیار AI محصولات</a>
  </div>

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول‌های طرح کاتالوگ هنوز ساخته نشده‌اند. مهاجرت shop را اجرا کنید.</div>
  @else
    @if(!$configured)
      <div class="alert alert-warning">کلید API تنظیم نشده است؛ فایل را می‌شود وارد کرد ولی ساخت طرح کار نمی‌کند.</div>
    @endif

    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">۱. وارد کردن لیست محصولات</h6></div>
      <div class="card-body">
        <p class="small text-muted">فایل Excel یا CSV: ستون اول نام محصول، ستون دوم مشخصات یا مدل. ردیف‌ها فقط در یک جدول موقت ذخیره می‌شوند؛ تا طرح را اعمال نکنید محصولی ساخته نمی‌شود.</p>
        <form method="post" action="{{ route('admin.shop.ai.catalog.import') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
          @csrf
          <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" style="max-width:360px" required>
          <button type="submit" class="btn btn-primary">وارد کردن</button>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">۲. لیست‌های واردشده</h6></div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead><tr><th>لیست</th><th>ردیف‌ها</th><th>منتظر طرح</th><th></th></tr></thead>
          <tbody>
            @forelse($batches as $batch)
              <tr>
                <td dir="ltr" class="text-start">{{ $batch->batch }}</td>
                <td>{{ number_format($batch->total) }}</td>
                <td>{{ number_format($batch->staged) }}</td>
                <td class="text-end">
                  @if($batch->staged > 0)
                    <form method="post" action="{{ route('admin.shop.ai.catalog.plan') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="batch" value="{{ $batch->batch }}">
                      <button type="submit" class="btn btn-sm btn-success" @disabled(!$configured)>ساخت طرح با AI</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-muted">هنوز لیستی وارد نشده است.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer small text-muted">ساخت طرح در صف <code dir="ltr">{{ $queueName }}</code> اجرا می‌شود (<code dir="ltr">php artisan queue:work --queue={{ $queueName }}</code>).</div>
    </div>

    <div class="card mb-0">
      <div class="card-header"><h6 class="mb-0">۳. طرح‌ها</h6></div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead><tr><th>#</th><th>لیست</th><th>وضعیت</th><th>گروه‌ها</th><th>مدل</th><th>زمان</th><th></th></tr></thead>
          <tbody>
            @forelse($plans as $plan)
              @php([$label, $color] = $statusLabels[$plan->status] ?? [$plan->status, 'secondary'])
              <tr>
                <td>{{ $plan->id }}</td>
                <td dir="ltr" class="text-start">{{ $plan->batch }}</td>
                <td>
                  <span class="badge bg-{{ $color }}">{{ $label }}</span>
                  @if($plan->error)
                    <div class="small text-danger mt-1">{{ \Illuminate\Support\Str::limit($plan->error, 160) }}</div>
                  @endif
                </td>
                <td>{{ count($plan->payload['groups'] ?? []) ?: '—' }}</td>
                <td class="small text-muted" dir="ltr">{{ $plan->model ?: $plan->driver }}</td>
                <td class="small text-muted">{{ $plan->updated_at?->diffForHumans() }}</td>
                <td class="text-end">
                  @if(in_array($plan->status, ['ready', 'applied'], true))
                    <a href="{{ route('admin.shop.ai.catalog.show', $plan) }}" class="btn btn-sm btn-outline-primary">{{ $plan->status === 'ready' ? 'بررسی' : 'نمایش' }}</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-muted">هنوز طرحی ساخته نشده است.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
