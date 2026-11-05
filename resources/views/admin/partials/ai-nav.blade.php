{{--
  Section links shared by every AI page.  @include('shop::admin.partials.ai-nav', ['active' => 'reviews'])
  active: suggestions | proposals | catalog | images | reviews
--}}
@php
  $aiNavItems = [
    'suggestions' => ['پیشنهادهای محصول', 'ph-sparkle', 'admin.shop.ai.index'],
    'proposals' => ['تأیید تغییرها', 'ph-check-square-offset', 'admin.shop.ai-proposals.index'],
    'catalog' => ['طرح کاتالوگ', 'ph-squares-four', 'admin.shop.ai.catalog.index'],
    'images' => ['عکس‌های پیشنهادی', 'ph-image', 'admin.shop.ai.images.index'],
    'reviews' => ['آمار بررسی', 'ph-chart-bar', 'admin.shop.ai-reviews.index'],
  ];
@endphp
<nav class="shop-nav">
  @foreach($aiNavItems as $key => [$label, $icon, $route])
    <a href="{{ route($route) }}" class="{{ ($active ?? '') === $key ? 'is-active' : '' }}"><i class="{{ $icon }}"></i>{{ $label }}</a>
  @endforeach
</nav>
