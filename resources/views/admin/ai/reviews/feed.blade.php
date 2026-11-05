@extends('cms::admin.layout.index')

@php
  $typeFilters = ['' => 'همه'] + $types;
@endphp

@section('content')
@include('shop::admin.partials.ui')
<div class="content shop-ui">
  <div class="shop-head">
    <span class="shop-icon shop-icon--lg tone-primary"><i class="ph-clock-counter-clockwise"></i></span>
    <div>
      <h4 class="shop-head__title">فعالیت AI</h4>
      <p class="shop-head__sub">هر کاری که ایجنت‌ها روی فروشگاه کرده‌اند، از جدید به قدیم</p>
    </div>
  </div>
  @include('shop::admin.partials.ai-nav', ['active' => 'activity'])

  @if(!$ready)
    <div class="shop-note tone-warning">جدول فعالیت‌ها هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    <div class="card">
      <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <nav class="shop-nav mb-0">
          @foreach($typeFilters as $key => $label)
            @php
              $count = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0);
            @endphp
            <a href="{{ route('admin.shop.ai-activity.index', array_filter(['type' => $key, 'resource' => $resource])) }}" class="{{ $type === (string) $key ? 'is-active' : '' }}">
              {{ $label }} <span class="opacity-75">{{ $count }}</span>
            </a>
          @endforeach
        </nav>
        <form method="get" class="ms-auto">
          <input type="hidden" name="type" value="{{ $type }}">
          <select name="resource" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">همهٔ منبع‌ها</option>
            @foreach($resources as $key => $res)
              <option value="{{ $key }}" @selected($resource === $key)>{{ $res->label() }}</option>
            @endforeach
          </select>
        </form>
      </div>

      @if($items->isEmpty())
        @include('shop::admin.partials.empty', ['icon' => 'ph-clock-counter-clockwise', 'title' => 'هنوز فعالیتی ثبت نشده است', 'hint' => 'بررسی‌ها، پیشنهادها، تأییدها و تغییرهای ایجنت اینجا می‌آیند.'])
      @else
        <ul class="shop-tl">
          @foreach($items as $a)
            @php
              $d = (array) $a->details;
              $tone = \RMS\Shop\Support\UiTone::tone('activity', $a->type);
              $icon = \RMS\Shop\Support\UiTone::ACTIVITY_ICONS[$a->type] ?? 'ph-circle';
              $rType = $a->resource_type ?: ($a->product_id ? 'product' : null);
              $rId = $a->resource_id ?: $a->product_id;
              $named = $rType && $rId ? ($names[$rType.':'.$rId] ?? null) : null;
            @endphp
            <li>
              <span class="shop-icon shop-icon--round tone-{{ $tone }}"><i class="{{ $icon }}"></i></span>
              <div class="shop-tl__body">
                <div class="d-flex align-items-center flex-wrap gap-2">
                  <span class="shop-tl__title">{{ $a->title }}</span>
                  <span class="shop-soft tone-{{ $tone }}">{{ \RMS\Shop\Support\UiTone::label('activity', $a->type) }}</span>
                  <span class="shop-tl__meta ms-auto">{{ \RMS\Shop\Support\UiDate::html($a->created_at) }}</span>
                </div>
                <div class="shop-tl__meta">
                  @if($named)
                    {{ $named[2] }}:
                    @if($named[1])<a href="{{ $named[1] }}">{{ \Illuminate\Support\Str::limit($named[0], 50) }}</a>@else {{ \Illuminate\Support\Str::limit($named[0], 50) }} @endif
                    ·
                  @endif
                  توسط {{ $a->actor ?? '—' }}
                  @if($a->product_id)
                    · <a href="{{ route('admin.shop.products.ai-activity', $a->product_id) }}">تاریخچهٔ این محصول</a>
                  @endif
                </div>

                @if($a->type === 'review')
                  <div class="shop-tl__detail">امتیاز <span class="shop-score tone-{{ \RMS\Shop\Support\UiTone::score(isset($d['score']) ? (int) $d['score'] : null) }}">{{ $d['score'] ?? '—' }}</span> · {{ \Illuminate\Support\Str::limit((string) ($d['summary'] ?? ''), 160) }}
                    @if(!empty($d['review_id']))<a href="{{ route('admin.shop.ai-reviews.show', $d['review_id']) }}">گزارش کامل</a>@endif</div>
                @elseif($a->type === 'suggestion' && !empty($d['proposal_id']))
                  <div class="shop-tl__detail"><a href="{{ route('admin.shop.ai-proposals.show', $d['proposal_id']) }}">دیدن پیشنهاد #{{ $d['proposal_id'] }}</a>@if(!empty($d['note'])) · {{ $d['note'] }}@endif</div>
                @elseif($a->type === 'suggestion' && isset($d['from']))
                  <div class="shop-tl__detail d-flex flex-wrap align-items-center gap-2" dir="auto">
                    <span class="shop-diff shop-diff--old py-0">{{ $d['from'] }}</span><i class="ph-arrow-left text-muted"></i><span class="shop-diff shop-diff--new py-0">{{ $d['to'] ?? '' }}</span>
                  </div>
                @elseif($a->type === 'apply')
                  <button type="button" class="btn btn-light btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#act-{{ $a->id }}"><i class="ph-eye me-1"></i> جزئیات کامل</button>
                  @include('shop::admin.partials.change-modal', [
                    'modalId' => 'act-'.$a->id,
                    'title' => $a->title,
                    'meta' => \RMS\Shop\Support\UiDate::both($a->created_at).' · '.($a->actor ?? '—'),
                    'instruction' => $d['operator_instruction'] ?? null,
                    'before' => (array) ($d['before'] ?? []),
                    'after' => (array) ($d['after'] ?? []),
                    'link' => $named[1] ?? null,
                  ])
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
        @if($items instanceof \Illuminate\Contracts\Pagination\Paginator && $items->hasPages())
          <div class="card-footer">{{ $items->links() }}</div>
        @endif
      @endif
    </div>
  @endif
</div>
@endsection
