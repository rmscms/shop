@extends('cms::admin.layout.index')

@php
  $typeClass = ['review' => 'info', 'suggestion' => 'primary', 'apply' => 'danger', 'write' => 'warning', 'note' => 'secondary'];
@endphp

@section('content')
<div class="content ai-page">
  <div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
      <h5 class="mb-0">تاریخچهٔ کار AI: {{ $product->name }} <span class="text-muted small">#{{ $product->id }}</span></h5>
      <div class="ms-auto d-flex gap-2">
        <a href="{{ route('admin.shop.products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">ویرایش محصول</a>
        <a href="{{ route('admin.shop.ai-reviews.index') }}" class="btn btn-sm btn-outline-secondary">آمار بررسی AI</a>
      </div>
    </div>
    <ul class="list-group list-group-flush">
      @forelse($items as $a)
        @php($d = (array) $a->details)
        <li class="list-group-item">
          <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-{{ $typeClass[$a->type] ?? 'secondary' }}">{{ $types[$a->type] ?? $a->type }}</span>
            <span class="fw-semibold">{{ $a->title }}</span>
            <span class="text-muted small ms-auto" dir="ltr">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
          </div>
          <div class="small text-muted mt-1">توسط: {{ $a->actor ?? '—' }}</div>

          @if($a->type === 'review')
            <div class="small mt-1">امتیاز {{ $d['score'] ?? '—' }} · {{ $d['summary'] ?? '' }}
              @if(!empty($d['review_id']))<a href="{{ route('admin.shop.ai-reviews.show', $d['review_id']) }}">گزارش</a>@endif</div>
          @elseif($a->type === 'suggestion')
            <div class="small mt-1" dir="auto">{{ $d['from'] ?? '' }} ← {{ $d['to'] ?? '' }}</div>
          @elseif($a->type === 'apply')
            @if(!empty($d['operator_instruction']))<div class="small mt-1">دستور ادمین: «{{ $d['operator_instruction'] }}»</div>@endif
            @foreach((array) ($d['after'] ?? []) as $f => $after)
              <div class="small"><span class="text-muted">{{ $f }}:</span>
                {{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($d, 'before.'.$f)), 50) ?: '—' }}
                ← {{ \Illuminate\Support\Str::limit(strip_tags((string) $after), 50) ?: '—' }}</div>
            @endforeach
          @elseif($a->type === 'write')
            <div class="small mt-1 text-muted" dir="ltr">{{ collect((array) ($d['sent'] ?? []))->map(fn ($v, $k) => $k.'='.$v)->implode(' · ') }}</div>
          @elseif($a->type === 'note' && !empty($d['text']))
            <div class="small mt-1">{{ $d['text'] }}</div>
          @endif
        </li>
      @empty
        <li class="list-group-item text-center text-muted py-4">هنوز کاری از ایجنت روی این محصول ثبت نشده است.</li>
      @endforelse
    </ul>
    @if($items instanceof \Illuminate\Contracts\Pagination\Paginator)
      <div class="card-footer">{{ $items->links() }}</div>
    @endif
  </div>
</div>
@endsection
