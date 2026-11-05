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
    <span class="shop-icon shop-icon--lg tone-info"><i class="ph-image"></i></span>
    <div>
      <h4 class="shop-head__title">عکس‌های پیشنهادی</h4>
      <p class="shop-head__sub">عکس درست را انتخاب کن؛ بقیه کنار گذاشته می‌شوند</p>
    </div>
  </div>
  @include('shop::admin.partials.ai-nav', ['active' => 'images'])

  @if(!$ready)
    <div class="shop-note tone-warning">جدول عکس‌های پیشنهادی هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    <div class="shop-note tone-info mb-3">
      <i class="ph-info me-1"></i>
      عکس‌هایی را که درست هستند تیک بزنید؛ فقط همان‌ها دانلود و به کتابخانهٔ تصاویر محصول اضافه می‌شوند (اولی عکس اصلی می‌شود اگر محصول عکسی ندارد) و بقیه کنار گذاشته می‌شوند.
      عکس سایت‌های دیگر ممکن است حق نشر داشته باشد؛ منبع هر عکس زیرش آمده است.
    </div>

    @if($waitingForProducts > 0)
      <div class="shop-note tone-muted mb-3">{{ number_format($waitingForProducts) }} عکس پیشنهادی برای ردیف‌هایی است که هنوز محصول نشده‌اند؛ بعد از اعمال طرح کاتالوگ اینجا می‌آیند.</div>
    @endif

    @forelse($products as $product)
      <form method="post" action="{{ route('admin.shop.ai.images.apply', $product) }}" class="card mb-3">
        @csrf
        <input type="hidden" name="page" value="{{ request('page') }}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 class="shop-card-title">{{ $product->name }} <span class="text-muted fw-normal fs-sm">#{{ $product->id }}</span></h6>
          <a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-light btn-sm" target="_blank" rel="noopener"><i class="ph-pencil-simple me-1"></i> ویرایش محصول</a>
        </div>
        <div class="card-body">
          <div class="row g-3">
            @foreach($candidates[$product->id] ?? [] as $candidate)
              <div class="col-6 col-md-4 col-xl-3">
                <label class="d-block rounded p-2 h-100 shop-pick">
                  <div class="ratio ratio-1x1 rounded mb-2 overflow-hidden shop-pick__img">
                    <img src="{{ $candidate->url }}" alt="" loading="lazy" referrerpolicy="no-referrer" style="object-fit:contain">
                  </div>
                  <div class="form-check mb-1">
                    <input type="checkbox" class="form-check-input" name="candidates[]" value="{{ $candidate->id }}">
                    <span class="form-check-label fs-sm">انتخاب</span>
                  </div>
                  <div class="fs-sm text-muted text-truncate" dir="ltr" title="{{ $candidate->title }}">
                    @if($candidate->source_url)
                      <a href="{{ $candidate->source_url }}" target="_blank" rel="noopener noreferrer">{{ $candidate->sourceHost() }}</a>
                    @else
                      {{ $candidate->sourceHost() }}
                    @endif
                  </div>
                  @if($candidate->error)
                    <div class="fs-sm mt-1 tone-danger" style="color:rgb(var(--tone-danger))">{{ $candidate->error }}</div>
                  @endif
                </label>
              </div>
            @endforeach
          </div>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><i class="ph-check me-1"></i> اضافه کردن عکس‌های انتخاب‌شده</button>
          <span class="fs-sm text-muted">بدون تیک = همهٔ پیشنهادهای این محصول رد می‌شوند.</span>
        </div>
      </form>
    @empty
      <div class="card">@include('shop::admin.partials.empty', ['icon' => 'ph-image', 'title' => 'عکس پیشنهادی منتظر بررسی نیست'])</div>
    @endforelse

    @if(method_exists($products, 'links'))
      {{ $products->links('shop::admin.partials.pagination') }}
    @endif
  @endif
</div>
@endsection
