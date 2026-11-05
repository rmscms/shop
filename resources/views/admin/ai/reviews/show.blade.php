@extends('cms::admin.layout.index')

@php
  $scoreClass = fn ($s) => $s === null ? 'secondary' : ($s >= 75 ? 'success' : ($s >= 50 ? 'warning' : 'danger'));
@endphp

@section('content')
<div class="content ai-page">
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
      <h5 class="mb-0">
        بررسی AI: {{ $product?->name ?? ('محصول #'.$review->product_id) }}
        <span class="text-muted small">#{{ $review->product_id }}</span>
      </h5>
      <span class="badge bg-{{ $scoreClass($review->score) }} fs-6">امتیاز کل: {{ $review->score ?? '—' }}</span>
      <div class="ms-auto d-flex gap-2">
        @if($product)
          <a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">ویرایش محصول</a>
        @endif
        <a href="{{ route('admin.shop.ai-reviews.index') }}" class="btn btn-sm btn-outline-secondary">بازگشت</a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-2" dir="ltr">
        {{ $review->reviewed_at?->format('Y-m-d H:i') }} · {{ $review->source }}
      </div>
      <p class="mb-0">{{ $review->summary }}</p>
      @if($review->note)
        <div class="small text-muted mt-2">یادداشت درخواست: {{ $review->note }}</div>
      @endif
    </div>
  </div>

  <div class="row g-3 mb-3">
    @foreach((array) $review->result as $key => $block)
      <div class="col-md-6">
        <div class="card h-100 mb-0">
          <div class="card-header d-flex align-items-center">
            <h6 class="mb-0">{{ $aspects[$key] ?? $key }}</h6>
            <span class="badge bg-{{ $scoreClass($block['score'] ?? null) }} ms-auto">{{ $block['score'] ?? '—' }}</span>
          </div>
          <div class="card-body">
            @if(!empty($block['findings']))
              <div class="fw-semibold small mb-1">یافته‌ها</div>
              <ul class="ps-3">
                @foreach($block['findings'] as $f)<li>{{ $f }}</li>@endforeach
              </ul>
            @endif
            @if(!empty($block['recommendations']))
              <div class="fw-semibold small mb-1">پیشنهادها</div>
              <ul class="ps-3 mb-0">
                @foreach($block['recommendations'] as $f)<li>{{ $f }}</li>@endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if($history->count() > 1)
    <div class="card">
      <div class="card-header"><h6 class="mb-0">تاریخچهٔ بررسی‌های این محصول</h6></div>
      <ul class="list-group list-group-flush">
        @foreach($history as $h)
          <li class="list-group-item d-flex align-items-center">
            <a href="{{ route('admin.shop.ai-reviews.show', $h->id) }}" dir="ltr">{{ $h->reviewed_at?->format('Y-m-d H:i') }}</a>
            <span class="badge bg-{{ $scoreClass($h->score) }} ms-auto">{{ $h->score ?? '—' }}</span>
          </li>
        @endforeach
      </ul>
    </div>
  @endif
</div>
@endsection
