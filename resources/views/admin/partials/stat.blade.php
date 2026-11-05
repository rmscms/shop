{{--
  Quiet stat tile.  @include('shop::admin.partials.stat', ['label' => '...', 'value' => 12, 'icon' => 'ph-key', 'tone' => 'primary', 'hint' => '...', 'suffix' => 'of 77', 'meter' => 40])
  tone: success | warning | danger | info | primary | muted   (colours only the icon chip and the optional meter)
--}}
@php($tone = $tone ?? 'muted')
<div class="card mb-0 h-100">
  <div class="shop-stat tone-{{ $tone }}">
    @isset($icon)
      <span class="shop-icon"><i class="{{ $icon }}"></i></span>
    @endisset
    <div class="shop-stat__body">
      <div class="shop-stat__label">{{ $label }}</div>
      <div class="shop-stat__value">{{ $value }}@isset($suffix) <small>{{ $suffix }}</small>@endisset</div>
      @isset($meter)
        <div class="shop-meter mt-2"><span style="width: {{ max(0, min(100, (int) $meter)) }}%"></span></div>
      @endisset
      @isset($hint)
        <div class="shop-stat__hint">{{ $hint }}</div>
      @endisset
    </div>
  </div>
</div>
