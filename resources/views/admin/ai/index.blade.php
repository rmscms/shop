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
    <span class="shop-icon shop-icon--lg tone-warning"><i class="ph-sparkle"></i></span>
    <div>
      <h4 class="shop-head__title">دستیار هوش مصنوعی محصولات</h4>
      <p class="shop-head__sub">پیشنهاد نام، توضیح، سئو، دسته و ویژگی‌ها؛ تا تأیید نکنی چیزی عوض نمی‌شود</p>
    </div>
  </div>
  @include('shop::admin.partials.ai-nav', ['active' => 'suggestions'])

  @if(!$ready)
    <div class="shop-note tone-warning">جدول پیشنهادهای AI هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    @if(!$configured)
      <div class="shop-note tone-warning mb-3">
        <i class="ph-warning me-1"></i>
        کلید API تنظیم نشده است. در <code dir="ltr">.env</code> مقدار <code dir="ltr">SHOP_AI_DRIVER</code> و کلید همان درایور
        (<code dir="ltr">SHOP_AI_ANTHROPIC_KEY</code> یا <code dir="ltr">SHOP_AI_OPENAI_KEY</code>) را بگذارید.
      </div>
    @endif

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        @include('shop::admin.partials.stat', ['label' => 'سرویس فعال', 'value' => $driver, 'icon' => 'ph-cpu', 'tone' => $configured ? 'success' : 'muted', 'hint' => $model.' · صف '.$queueName])
      </div>
      <div class="col-md-4">
        <div class="card mb-0 h-100">
          <div class="p-3">
            <div class="shop-stat__label mb-2">وضعیت پیشنهادها <span class="opacity-75">· از {{ number_format($productsTotal) }} محصول</span></div>
            <div class="shop-nav mb-0">
              @foreach(['pending', 'processing', 'ready', 'failed', 'applied', 'rejected'] as $key)
                <a href="{{ route('admin.shop.ai.index', $status === $key ? [] : ['status' => $key]) }}" class="{{ $status === $key ? 'is-active' : '' }}">
                  <span class="shop-dot tone-{{ \RMS\Shop\Support\UiTone::tone('suggestion', $key) }}">{{ \RMS\Shop\Support\UiTone::label('suggestion', $key) }}</span>
                  <span class="opacity-75">{{ number_format($counts[$key] ?? 0) }}</span>
                </a>
              @endforeach
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card mb-0 h-100">
          <div class="p-3 d-flex flex-column h-100">
            <div class="shop-stat__label mb-1">مصرف توکن</div>
            <div class="fw-semibold" dir="ltr">in {{ number_format((int) ($usage->input ?? 0)) }} · out {{ number_format((int) ($usage->output ?? 0)) }}</div>
            @if(($counts['ready'] ?? 0) > 0)
              <a href="{{ route('admin.shop.ai.next') }}" class="btn btn-primary mt-auto align-self-start"><i class="ph-list-checks me-1"></i> بررسی پیشنهادها یکی‌یکی</a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h6 class="shop-card-title"><i class="ph-paper-plane-tilt"></i> ارسال محصولات به هوش مصنوعی</h6></div>
      <div class="card-body">
        <p class="text-muted fs-sm">
          AI عکس‌ها و اطلاعات هر محصول را می‌خواند و نام، توضیح، متن سئو، دسته‌بندی، برند و ویژگی‌ها را به فارسی پیشنهاد می‌دهد.
          چیزی روی محصول عوض نمی‌شود تا خودتان پیشنهاد را تأیید کنید. محصولاتی که قبلاً پیشنهاد گرفته‌اند دوباره ارسال نمی‌شوند.
        </p>
        <div class="d-flex flex-wrap gap-2 align-items-start">
          <form method="post" action="{{ route('admin.shop.ai.enqueue') }}"
                data-confirm="همهٔ محصولات برای دریافت پیشنهاد به صف AI بروند؟"
                data-confirm-title="ارسال همهٔ محصولات"
                data-confirm-desc="برای هر محصول که پیشنهاد ندارد یک کار در صف می‌رود و از اعتبار سرویس AI مصرف می‌شود."
                data-confirm-type="info" data-confirm-ok="ارسال به صف" data-confirm-icon="ph-paper-plane-tilt">
            @csrf
            <input type="hidden" name="scope" value="all">
            <button type="submit" class="btn btn-primary" @disabled(!$configured)>همهٔ محصولات</button>
          </form>
          <form method="post" action="{{ route('admin.shop.ai.enqueue') }}">
            @csrf
            <input type="hidden" name="scope" value="missing">
            <button type="submit" class="btn btn-light" @disabled(!$configured)>فقط محصولات بدون توضیح</button>
          </form>
          <form method="post" action="{{ route('admin.shop.ai.enqueue') }}" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="scope" value="product">
            <input type="text" name="product_id" class="form-control" style="max-width:9rem" inputmode="numeric" placeholder="شناسهٔ محصول" required>
            <button type="submit" class="btn btn-light text-nowrap" @disabled(!$configured)>یک محصول</button>
          </form>
        </div>
        <div class="form-text mt-2">
          برای پردازش صف باید worker اجرا باشد: <code dir="ltr">php artisan queue:work --queue={{ $queueName }}</code>
        </div>
      </div>
    </div>

    <div class="card mb-0">
      <div class="table-responsive">
        <table class="table table-hover shop-table">
          <thead>
            <tr><th style="width:4.5rem">#</th><th>محصول</th><th>نام پیشنهادی</th><th>وضعیت</th><th>زمان</th><th style="width:1%"></th></tr>
          </thead>
          <tbody>
            @forelse($suggestions as $row)
              <tr>
                <td class="text-muted">{{ $row->product_id }}</td>
                <td>
                  <div class="fw-semibold">{{ $row->product?->name ?? '—' }}</div>
                  @if($row->product?->sku)<span class="shop-cell-sub" dir="ltr">{{ $row->product->sku }}</span>@endif
                </td>
                <td>
                  {{ $row->payload['name'] ?? '' }}
                  @if($row->status === 'failed' && $row->error)
                    <div class="fs-sm text-break mt-1 tone-danger" style="color:rgb(var(--tone-danger))">{{ \Illuminate\Support\Str::limit($row->error, 200) }}</div>
                  @endif
                </td>
                <td><span class="shop-dot tone-{{ \RMS\Shop\Support\UiTone::tone('suggestion', $row->status) }}">{{ \RMS\Shop\Support\UiTone::label('suggestion', $row->status) }}</span></td>
                <td class="fs-sm text-muted text-nowrap">{{ \RMS\Shop\Support\UiDate::html($row->updated_at) }}</td>
                <td>
                  <div class="d-flex gap-1">
                    @if($row->payload)
                      <a href="{{ route('admin.shop.ai.show', $row) }}" class="btn btn-light btn-sm">مشاهده</a>
                    @endif
                    @if(in_array($row->status, ['failed', 'rejected', 'applied', 'ready'], true))
                      <form method="post" action="{{ route('admin.shop.ai.retry', $row) }}">
                        @csrf
                        <button type="submit" class="btn btn-light btn-sm" title="دوباره به صف بفرست"><i class="ph-arrows-clockwise"></i></button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="6">@include('shop::admin.partials.empty', ['icon' => 'ph-sparkle', 'title' => 'هنوز پیشنهادی ثبت نشده است', 'hint' => 'محصولات را از کارت بالا به صف AI بفرست.'])</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($suggestions instanceof \Illuminate\Contracts\Pagination\Paginator && $suggestions->hasPages())
        <div class="card-footer">{{ $suggestions->links('shop::admin.partials.pagination') }}</div>
      @endif
    </div>
  @endif
</div>
@include('shop::admin.partials.confirm')
@endsection
