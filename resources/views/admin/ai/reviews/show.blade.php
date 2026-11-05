@extends('cms::admin.layout.index')

@php
  $aspectIcons = ['seo' => 'ph-magnifying-glass', 'content' => 'ph-text-align-right', 'images' => 'ph-image', 'price' => 'ph-currency-circle-dollar', 'competition' => 'ph-chart-line-up'];
  $overallTone = \RMS\Shop\Support\UiTone::score($review->score);
@endphp

@section('content')
@include('shop::admin.partials.ui')
<div class="content shop-ui">
  <div class="shop-head">
    <span class="shop-icon shop-icon--lg tone-{{ $overallTone }}"><i class="ph-clipboard-text"></i></span>
    <div>
      <h4 class="shop-head__title">{{ $product?->name ?? ('محصول #'.$review->product_id) }}</h4>
      <p class="shop-head__sub">گزارش بررسی AI · <span dir="ltr">{{ $review->reviewed_at?->format('Y-m-d H:i') }}</span> · {{ $review->source }}</p>
    </div>
    <div class="shop-head__actions">
      @if($product)<a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-light"><i class="ph-pencil-simple me-1"></i> ویرایش محصول</a>@endif
      <a href="{{ route('admin.shop.ai-reviews.index') }}" class="btn btn-light"><i class="ph-arrow-right me-1"></i> بازگشت</a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-4">
      <div class="text-center px-3 tone-{{ $overallTone }}">
        <div class="shop-score" style="font-size:2.4rem;line-height:1">{{ $review->score ?? '—' }}</div>
        <div class="fs-sm text-muted mt-1">امتیاز کل</div>
      </div>
      <div class="flex-grow-1" style="min-width:16rem">
        <p class="mb-0" style="line-height:1.9">{{ $review->summary }}</p>
        @if($review->note)
          <div class="shop-note tone-info mt-2"><i class="ph-note me-1"></i> یادداشت درخواست: {{ $review->note }}</div>
        @endif
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    @foreach((array) $review->result as $key => $block)
      @php($tone = \RMS\Shop\Support\UiTone::score(isset($block['score']) ? (int) $block['score'] : null))
      <div class="col-lg-6">
        <div class="card mb-0 h-100">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3 tone-{{ $tone }}">
              <span class="shop-icon shop-icon--sm"><i class="{{ $aspectIcons[$key] ?? 'ph-circle' }}"></i></span>
              <div class="fw-semibold">{{ $aspects[$key] ?? $key }}</div>
              <div class="ms-auto shop-score">{{ $block['score'] ?? '—' }}</div>
            </div>
            <div class="shop-meter mb-3 tone-{{ $tone }}"><span style="width: {{ (int) ($block['score'] ?? 0) }}%"></span></div>

            @if(!empty($block['findings']))
              <div class="fs-sm fw-semibold text-muted mb-1">یافته‌ها</div>
              <ul class="list-unstyled mb-3">
                @foreach($block['findings'] as $f)
                  <li class="d-flex gap-2 mb-1"><span class="shop-bullet"></span><span>{{ $f }}</span></li>
                @endforeach
              </ul>
            @endif
            @if(!empty($block['recommendations']))
              <div class="fs-sm fw-semibold text-muted mb-1">پیشنهادها</div>
              <ul class="list-unstyled mb-0">
                @foreach($block['recommendations'] as $f)
                  <li class="d-flex gap-2 mb-1"><i class="ph-arrow-bend-down-left tone-primary mt-1" style="color:rgb(var(--tone-primary))"></i><span>{{ $f }}</span></li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if($history->count() > 1)
    <div class="card">
      <div class="card-header"><h6 class="shop-card-title"><i class="ph-clock-counter-clockwise"></i> تاریخچهٔ بررسی‌های این محصول</h6></div>
      <ul class="shop-tl">
        @foreach($history as $h)
          <li>
            <span class="shop-icon shop-icon--sm shop-icon--round tone-{{ \RMS\Shop\Support\UiTone::score($h->score) }}"><i class="ph-chart-bar"></i></span>
            <div class="shop-tl__body d-flex align-items-center gap-2">
              <a href="{{ route('admin.shop.ai-reviews.show', $h->id) }}" dir="ltr">{{ $h->reviewed_at?->format('Y-m-d H:i') }}</a>
              @if($h->id === $review->id)<span class="shop-soft tone-primary">همین گزارش</span>@endif
              <span class="ms-auto shop-score tone-{{ \RMS\Shop\Support\UiTone::score($h->score) }}">{{ $h->score ?? '—' }}</span>
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  @endif
</div>
@endsection
