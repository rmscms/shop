{{--
  Empty state.  @include('shop::admin.partials.empty', ['icon' => 'ph-tray', 'title' => '...', 'hint' => '...'])
--}}
<div class="shop-empty">
  <i class="{{ $icon ?? 'ph-tray' }}"></i>
  <div class="shop-empty__title">{{ $title ?? 'موردی نیست' }}</div>
  @isset($hint)<div class="fs-sm">{{ $hint }}</div>@endisset
</div>
