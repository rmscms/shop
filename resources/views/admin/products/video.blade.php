@extends('cms::admin.layout.index')
@section('title', 'ویدئو محصول: ' . ($product->name ?? ''))
@section('content')
<div class="container py-3">
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">مدیریت ویدئو محصول</div>
      <a href="{{ route('admin.shop.products.edit', ['product' => (int)($product->id ?? 0)]) }}" class="btn btn-sm btn-outline-secondary">بازگشت به محصول</a>
    </div>
    <div class="card-body">
      @if(!empty($video))
        <div class="mb-3">
          <div class="small text-muted mb-2">وضعیت فعلی:</div>
          @if(!empty($video->hls_master_path))
            <div class="alert alert-success py-2">ویدئو پردازش شده و آماده پخش است.</div>
            @if(!empty($video->poster_path))
              <img src="{{ Storage::disk('public')->url($video->poster_path) }}" alt="poster" style="max-width: 360px; border-radius: 8px; border: 1px solid var(--bs-border-color);">
            @endif
          @else
            <div class="alert alert-warning py-2">ویدئو در صف پردازش است. لطفاً چند دقیقه بعد صفحه را رفرش کنید.</div>
          @endif
        </div>
        <form method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm('حذف ویدئو قطعی است. مطمئنید؟');">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger">حذف ویدئو</button>
        </form>
        <hr>
      @endif

      <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">آپلود ویدئو (MP4/WEBM/MOV، حداکثر 500MB)</label>
          <input type="file" name="video" class="form-control" accept="video/*" required>
          <div class="form-text">پس از آپلود، ویدئو به صورت خودکار به HLS تبدیل می‌شود و تصویر پوستر تولید می‌گردد.</div>
        </div>
        <button class="btn btn-primary">آپلود</button>
      </form>
    </div>
  </div>
</div>
@endsection
