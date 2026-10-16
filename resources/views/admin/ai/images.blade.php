@extends('cms::admin.layout.index')

@section('content')
<div class="content ai-images-page">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.shop.ai.index') }}" class="btn btn-sm btn-outline-secondary">دستیار AI محصولات</a>
    <a href="{{ route('admin.shop.ai.catalog.index') }}" class="btn btn-sm btn-outline-secondary">طرح کاتالوگ</a>
  </div>

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول عکس‌های پیشنهادی هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    <div class="alert alert-info small">
      عکس‌هایی را که درست هستند تیک بزنید؛ فقط همان‌ها دانلود و به کتابخانهٔ تصاویر محصول اضافه می‌شوند (اولی عکس اصلی می‌شود اگر محصول عکسی ندارد) و بقیه کنار گذاشته می‌شوند.
      عکس سایت‌های دیگر ممکن است حق نشر داشته باشد؛ منبع هر عکس زیرش آمده است.
    </div>

    @if($waitingForProducts > 0)
      <div class="alert alert-secondary small">{{ number_format($waitingForProducts) }} عکس پیشنهادی برای ردیف‌هایی است که هنوز محصول نشده‌اند؛ بعد از اعمال طرح کاتالوگ اینجا می‌آیند.</div>
    @endif

    @forelse($products as $product)
      <form method="post" action="{{ route('admin.shop.ai.images.apply', $product) }}" class="card mb-3">
        @csrf
        <input type="hidden" name="page" value="{{ request('page') }}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 class="mb-0">{{ $product->name }} <span class="text-muted small">#{{ $product->id }}</span></h6>
          <a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">ویرایش محصول</a>
        </div>
        <div class="card-body">
          <div class="row g-3">
            @foreach($candidates[$product->id] ?? [] as $candidate)
              <div class="col-6 col-md-4 col-xl-3">
                <label class="d-block border rounded p-2 h-100 ai-image-option">
                  <div class="ratio ratio-1x1 bg-light rounded mb-2 overflow-hidden">
                    <img src="{{ $candidate->url }}" alt="" loading="lazy" referrerpolicy="no-referrer" style="object-fit:contain">
                  </div>
                  <div class="form-check mb-1">
                    <input type="checkbox" class="form-check-input" name="candidates[]" value="{{ $candidate->id }}">
                    <span class="form-check-label small">انتخاب</span>
                  </div>
                  <div class="small text-muted text-truncate" dir="ltr" title="{{ $candidate->title }}">
                    @if($candidate->source_url)
                      <a href="{{ $candidate->source_url }}" target="_blank" rel="noopener noreferrer">{{ $candidate->sourceHost() }}</a>
                    @else
                      {{ $candidate->sourceHost() }}
                    @endif
                  </div>
                  @if($candidate->error)
                    <div class="small text-danger mt-1">{{ $candidate->error }}</div>
                  @endif
                </label>
              </div>
            @endforeach
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-success btn-sm">اضافه کردن عکس‌های انتخاب‌شده</button>
          <span class="small text-muted ms-2">بدون تیک = همهٔ پیشنهادهای این محصول رد می‌شوند.</span>
        </div>
      </form>
    @empty
      <div class="alert alert-light border mb-0">عکس پیشنهادی منتظر بررسی نیست.</div>
    @endforelse

    @if(method_exists($products, 'links'))
      {{ $products->links() }}
    @endif
  @endif
</div>

<style>
  .ai-images-page .ai-image-option { cursor: pointer; }
  .ai-images-page .ai-image-option:has(input:checked) { border-color: var(--bs-success) !important; box-shadow: 0 0 0 2px rgba(25, 135, 84, .25); }
</style>
@endsection
