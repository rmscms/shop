@extends('cms::admin.layout.index')

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
    <span class="shop-icon shop-icon--lg tone-primary"><i class="ph-squares-four"></i></span>
    <div>
      <h4 class="shop-head__title">طرح کاتالوگ</h4>
      <p class="shop-head__sub">لیست محصولات را وارد کن تا AI دسته‌ها، برندها و تنوع‌ها را بچیند</p>
    </div>
  </div>
  @include('shop::admin.partials.ai-nav', ['active' => 'catalog'])

  @if(!$ready)
    <div class="shop-note tone-warning">جدول‌های طرح کاتالوگ هنوز ساخته نشده‌اند. مهاجرت shop را اجرا کنید.</div>
  @else
    @if(!$configured)
      <div class="shop-note tone-warning mb-3"><i class="ph-warning me-1"></i>کلید API تنظیم نشده است؛ فایل را می‌شود وارد کرد ولی ساخت طرح کار نمی‌کند.</div>
    @endif

    <div class="card mb-3">
      <div class="card-header"><h6 class="shop-card-title"><i class="ph-upload-simple"></i> ۱. وارد کردن لیست محصولات</h6></div>
      <div class="card-body">
        <p class="fs-sm text-muted">فایل Excel یا CSV: ستون اول نام محصول، ستون دوم مشخصات یا مدل. ردیف‌ها فقط در یک جدول موقت ذخیره می‌شوند؛ تا طرح را اعمال نکنید محصولی ساخته نمی‌شود.</p>
        <form method="post" action="{{ route('admin.shop.ai.catalog.import') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
          @csrf
          <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" style="max-width:22rem" required>
          <button type="submit" class="btn btn-primary"><i class="ph-upload-simple me-1"></i> وارد کردن</button>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h6 class="shop-card-title"><i class="ph-list-bullets"></i> ۲. لیست‌های واردشده</h6></div>
      <div class="table-responsive">
        <table class="table table-hover shop-table">
          <thead><tr><th>لیست</th><th>ردیف‌ها</th><th>منتظر طرح</th><th style="width:1%"></th></tr></thead>
          <tbody>
            @forelse($batches as $batch)
              <tr>
                <td dir="ltr" class="text-start">{{ $batch->batch }}</td>
                <td>{{ number_format($batch->total) }}</td>
                <td>{{ number_format($batch->staged) }}</td>
                <td class="text-nowrap">
                  @if($batch->staged > 0)
                    <form method="post" action="{{ route('admin.shop.ai.catalog.plan') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="batch" value="{{ $batch->batch }}">
                      <button type="submit" class="btn btn-primary btn-sm" @disabled(!$configured)><i class="ph-sparkle me-1"></i> ساخت طرح با AI</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4">@include('shop::admin.partials.empty', ['icon' => 'ph-tray', 'title' => 'هنوز لیستی وارد نشده است'])</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer fs-sm text-muted">ساخت طرح در صف <code dir="ltr">{{ $queueName }}</code> اجرا می‌شود (<code dir="ltr">php artisan queue:work --queue={{ $queueName }}</code>).</div>
    </div>

    <div class="card mb-0">
      <div class="card-header"><h6 class="shop-card-title"><i class="ph-git-branch"></i> ۳. طرح‌ها</h6></div>
      <div class="table-responsive">
        <table class="table table-hover shop-table">
          <thead><tr><th>#</th><th>لیست</th><th>وضعیت</th><th>گروه‌ها</th><th>مدل</th><th>زمان</th><th style="width:1%"></th></tr></thead>
          <tbody>
            @forelse($plans as $plan)
              <tr>
                <td class="text-muted">{{ $plan->id }}</td>
                <td dir="ltr" class="text-start">{{ $plan->batch }}</td>
                <td>
                  <span class="shop-dot tone-{{ \RMS\Shop\Support\UiTone::tone('suggestion', $plan->status) }}">{{ \RMS\Shop\Support\UiTone::label('suggestion', $plan->status) }}</span>
                  @if($plan->error)
                    <div class="fs-sm mt-1" style="color:rgb(var(--tone-danger))">{{ \Illuminate\Support\Str::limit($plan->error, 160) }}</div>
                  @endif
                </td>
                <td>{{ count($plan->payload['groups'] ?? []) ?: '—' }}</td>
                <td class="fs-sm text-muted" dir="ltr">{{ $plan->model ?: $plan->driver }}</td>
                <td class="fs-sm text-muted">{{ \RMS\Shop\Support\UiDate::human($plan->updated_at) }}</td>
                <td class="text-nowrap">
                  @if(in_array($plan->status, ['ready', 'applied'], true))
                    <a href="{{ route('admin.shop.ai.catalog.show', $plan) }}" class="btn btn-light btn-sm">{{ $plan->status === 'ready' ? 'بررسی' : 'نمایش' }}</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7">@include('shop::admin.partials.empty', ['icon' => 'ph-git-branch', 'title' => 'هنوز طرحی ساخته نشده است'])</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
