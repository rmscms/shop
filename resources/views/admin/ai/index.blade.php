@extends('cms::admin.layout.index')

@php
  $statusLabels = [
    'pending' => ['در صف', 'secondary'],
    'processing' => ['در حال پردازش', 'info'],
    'ready' => ['آمادهٔ بررسی', 'primary'],
    'failed' => ['خطا', 'danger'],
    'applied' => ['اعمال شد', 'success'],
    'rejected' => ['رد شد', 'dark'],
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

  <div class="mb-3">
    <a href="{{ route('admin.shop.ai.catalog.index') }}" class="btn btn-sm btn-outline-primary">طرح کاتالوگ: وارد کردن لیست و چیدن دسته‌ها، برندها و تنوع‌ها</a>
    <a href="{{ route('admin.shop.ai.images.index') }}" class="btn btn-sm btn-outline-primary">عکس‌های پیشنهادی</a>
  </div>

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول پیشنهادهای AI هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    @if(!$configured)
      <div class="alert alert-warning">
        کلید API تنظیم نشده است. در <code>.env</code> مقدار <code dir="ltr">SHOP_AI_DRIVER</code> و کلید همان درایور
        (<code dir="ltr">SHOP_AI_ANTHROPIC_KEY</code> یا <code dir="ltr">SHOP_AI_OPENAI_KEY</code>) را بگذارید.
      </div>
    @endif

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card h-100 mb-0">
          <div class="card-body">
            <div class="text-muted small mb-1">سرویس فعال</div>
            <div class="fw-semibold" dir="ltr">{{ $driver }} · {{ $model }}</div>
            <div class="small text-muted mt-2">صف: <code dir="ltr">{{ $queueName }}</code></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 mb-0">
          <div class="card-body">
            <div class="text-muted small mb-1">وضعیت</div>
            <div class="d-flex flex-wrap gap-2">
              @foreach($statusLabels as $key => [$label, $color])
                <a href="{{ route('admin.shop.ai.index', $status === $key ? [] : ['status' => $key]) }}"
                   class="badge bg-{{ $color }} text-decoration-none {{ $status !== '' && $status !== $key ? 'opacity-50' : '' }}">
                  {{ $label }}: {{ number_format($counts[$key] ?? 0) }}
                </a>
              @endforeach
            </div>
            <div class="small text-muted mt-2">از {{ number_format($productsTotal) }} محصول</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 mb-0">
          <div class="card-body">
            <div class="text-muted small mb-1">مصرف توکن</div>
            <div class="fw-semibold" dir="ltr">in {{ number_format((int) ($usage->input ?? 0)) }} · out {{ number_format((int) ($usage->output ?? 0)) }}</div>
            @if(($counts['ready'] ?? 0) > 0)
              <a href="{{ route('admin.shop.ai.next') }}" class="btn btn-sm btn-primary mt-2">بررسی پیشنهادها یکی‌یکی</a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">ارسال محصولات به هوش مصنوعی</h6></div>
      <div class="card-body">
        <p class="text-muted small">
          AI عکس‌ها و اطلاعات هر محصول را می‌خواند و نام، توضیح، متن سئو، دسته‌بندی، برند و ویژگی‌ها را به فارسی پیشنهاد می‌دهد.
          چیزی روی محصول عوض نمی‌شود تا خودتان پیشنهاد را تأیید کنید. محصولاتی که قبلاً پیشنهاد گرفته‌اند دوباره ارسال نمی‌شوند.
        </p>
        <div class="d-flex flex-wrap gap-2 align-items-start">
          <form method="post" action="{{ route('admin.shop.ai.enqueue') }}">
            @csrf
            <input type="hidden" name="scope" value="all">
            <button type="submit" class="btn btn-primary" @disabled(!$configured)>همهٔ محصولات</button>
          </form>
          <form method="post" action="{{ route('admin.shop.ai.enqueue') }}">
            @csrf
            <input type="hidden" name="scope" value="missing">
            <button type="submit" class="btn btn-outline-primary" @disabled(!$configured)>فقط محصولات بدون توضیح</button>
          </form>
          <form method="post" action="{{ route('admin.shop.ai.enqueue') }}" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="scope" value="product">
            <input type="text" name="product_id" class="form-control" style="max-width:140px" inputmode="numeric" placeholder="شناسهٔ محصول" required>
            <button type="submit" class="btn btn-outline-secondary text-nowrap" @disabled(!$configured)>یک محصول</button>
          </form>
        </div>
        <div class="form-text mt-2">
          برای پردازش صف باید worker اجرا باشد: <code dir="ltr">php artisan queue:work --queue={{ $queueName }}</code>
        </div>
      </div>
    </div>

    <div class="card mb-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:70px">#</th>
              <th>محصول</th>
              <th>نام پیشنهادی</th>
              <th style="width:130px">وضعیت</th>
              <th style="width:140px">زمان</th>
              <th style="width:170px"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($suggestions as $row)
              @php([$label, $color] = $statusLabels[$row->status] ?? [$row->status, 'secondary'])
              <tr>
                <td>{{ $row->product_id }}</td>
                <td>
                  <div>{{ $row->product?->name ?? '—' }}</div>
                  @if($row->product?->sku)<div class="small text-muted" dir="ltr">{{ $row->product->sku }}</div>@endif
                </td>
                <td>
                  {{ $row->payload['name'] ?? '' }}
                  @if($row->status === 'failed' && $row->error)
                    <div class="small text-danger text-break">{{ \Illuminate\Support\Str::limit($row->error, 200) }}</div>
                  @endif
                </td>
                <td><span class="badge bg-{{ $color }}">{{ $label }}</span></td>
                <td class="small text-muted">{{ $row->updated_at?->format('Y-m-d H:i') }}</td>
                <td class="text-end">
                  <div class="d-flex gap-1 justify-content-end">
                    @if($row->payload)
                      <a href="{{ route('admin.shop.ai.show', $row) }}" class="btn btn-sm btn-outline-primary">مشاهده</a>
                    @endif
                    @if(in_array($row->status, ['failed', 'rejected', 'applied', 'ready'], true))
                      <form method="post" action="{{ route('admin.shop.ai.retry', $row) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">دوباره</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">هنوز پیشنهادی ثبت نشده است.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($suggestions instanceof \Illuminate\Contracts\Pagination\Paginator && $suggestions->hasPages())
        <div class="card-footer">{{ $suggestions->links() }}</div>
      @endif
    </div>
  @endif
</div>
@endsection
