@extends('cms::admin.layout.index')

@section('content')
@include('shop::admin.partials.ui')
<div class="content shop-ui">
  <div class="shop-head">
    <span class="shop-icon shop-icon--lg tone-primary"><i class="ph-clock-counter-clockwise"></i></span>
    <div>
      <h4 class="shop-head__title">{{ $product->name }}</h4>
      <p class="shop-head__sub">تاریخچهٔ کارهای AI روی محصول #{{ $product->id }}</p>
    </div>
    <div class="shop-head__actions">
      <a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-light"><i class="ph-pencil-simple me-1"></i> ویرایش محصول</a>
      <a href="{{ route('admin.shop.ai-reviews.index') }}" class="btn btn-light"><i class="ph-chart-bar me-1"></i> آمار بررسی</a>
    </div>
  </div>

  <div class="card">
    @if($items->isEmpty())
      @include('shop::admin.partials.empty', ['icon' => 'ph-clock-counter-clockwise', 'title' => 'هنوز کاری از ایجنت روی این محصول ثبت نشده است', 'hint' => 'بررسی‌ها، پیشنهادها و تغییرها اینجا به‌ترتیب زمان می‌آیند.'])
    @else
      <ul class="shop-tl">
        @foreach($items as $a)
          @php
            $d = (array) $a->details;
            $tone = \RMS\Shop\Support\UiTone::tone('activity', $a->type);
            $icon = \RMS\Shop\Support\UiTone::ACTIVITY_ICONS[$a->type] ?? 'ph-circle';
          @endphp
          <li>
            <span class="shop-icon shop-icon--round tone-{{ $tone }}"><i class="{{ $icon }}"></i></span>
            <div class="shop-tl__body">
              <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="shop-tl__title">{{ $a->title }}</span>
                <span class="shop-soft tone-{{ $tone }}">{{ \RMS\Shop\Support\UiTone::label('activity', $a->type) }}</span>
                <span class="shop-tl__meta ms-auto" dir="ltr">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
              </div>
              <div class="shop-tl__meta">توسط {{ $a->actor ?? '—' }}</div>

              @if($a->type === 'review')
                <div class="shop-tl__detail">امتیاز <span class="shop-score tone-{{ \RMS\Shop\Support\UiTone::score(isset($d['score']) ? (int) $d['score'] : null) }}">{{ $d['score'] ?? '—' }}</span> · {{ $d['summary'] ?? '' }}
                  @if(!empty($d['review_id']))<a href="{{ route('admin.shop.ai-reviews.show', $d['review_id']) }}">گزارش کامل</a>@endif</div>
              @elseif($a->type === 'suggestion' && isset($d['from']))
                <div class="shop-tl__detail d-flex flex-wrap align-items-center gap-2" dir="auto">
                  <span class="shop-diff shop-diff--old py-0">{{ $d['from'] }}</span><i class="ph-arrow-left text-muted"></i><span class="shop-diff shop-diff--new py-0">{{ $d['to'] ?? '' }}</span>
                </div>
              @elseif($a->type === 'apply')
                @if(!empty($d['operator_instruction']))<div class="shop-tl__detail text-muted">دستور ادمین: «{{ $d['operator_instruction'] }}»</div>@endif
                @foreach((array) ($d['after'] ?? []) as $f => $after)
                  <div class="shop-tl__detail d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted">{{ $f }}</span>
                    <span class="shop-diff shop-diff--old py-0">{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($d, 'before.'.$f)), 50) ?: '—' }}</span>
                    <i class="ph-arrow-left text-muted"></i>
                    <span class="shop-diff shop-diff--new py-0">{{ \Illuminate\Support\Str::limit(strip_tags((string) $after), 50) ?: '—' }}</span>
                  </div>
                @endforeach
              @elseif($a->type === 'write')
                <div class="shop-tl__detail"><code class="shop-code" dir="ltr">{{ collect((array) ($d['sent'] ?? []))->map(fn ($v, $k) => $k.'='.$v)->implode(' · ') }}</code></div>
              @elseif($a->type === 'note' && !empty($d['text']))
                <div class="shop-tl__detail">{{ $d['text'] }}</div>
              @endif
            </div>
          </li>
        @endforeach
      </ul>
      @if($items instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="card-footer">{{ $items->links() }}</div>
      @endif
    @endif
  </div>
</div>
@endsection
