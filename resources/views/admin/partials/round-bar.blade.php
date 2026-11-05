{{--
  AI work round: which round the page shows, switch to an archived one, and (optionally) start a new round.
  @include('shop::admin.partials.round-bar', ['round' => $round, 'withStart' => true])
  $round comes from the ResolvesAiRound trait. Nothing renders until the rounds migration ran.
--}}
@php
  $rounds = $round['rounds'] ?? collect();
  $selected = $round['selected'] ?? null;
  $keep = collect(request()->query())->except(['round', 'page'])->all();
@endphp
@if($rounds->isNotEmpty())
<div class="shop-round d-flex align-items-center flex-wrap gap-2 mb-3">
  <span class="shop-soft {{ ($round['archived'] ?? false) ? 'tone-muted' : 'tone-primary' }}">
    <i class="{{ ($round['archived'] ?? false) ? 'ph-archive' : 'ph-arrows-clockwise' }}"></i>
    {{ ($round['all'] ?? false) ? 'همهٔ دورها' : ($selected?->label().(($round['archived'] ?? false) ? ' (آرشیو)' : ' (جاری)')) }}
  </span>
  @if($selected)
    <span class="fs-sm text-muted">از {{ \RMS\Shop\Support\UiDate::jalali($selected->started_at) }}@if($selected->ended_at) تا {{ \RMS\Shop\Support\UiDate::jalali($selected->ended_at) }}@endif</span>
  @endif

  <form method="get" class="ms-auto d-flex align-items-center gap-2">
    @foreach($keep as $k => $v)
      @if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <select name="round" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:12rem">
      @foreach($rounds as $r)
        <option value="{{ $r->id }}" @selected(!($round['all'] ?? false) && $selected && $selected->id === $r->id)>
          {{ $r->label() }}{{ $r->isCurrent() ? ' · جاری' : ' · آرشیو' }}
        </option>
      @endforeach
      <option value="all" @selected($round['all'] ?? false)>همهٔ دورها</option>
    </select>
  </form>

  @if(!empty($withStart) && !($round['archived'] ?? false) && !($round['all'] ?? false))
    <form method="post" action="{{ route('admin.shop.ai-rounds.start') }}" class="d-flex align-items-center gap-2"
          data-confirm="دور تازه شروع شود؟"
          data-confirm-title="شروع دور تازه"
          data-confirm-desc="آمار و بررسی‌های این دور به آرشیو می‌رود (در «فعالیت AI» قابل مراجعه است) و پوشش بررسی از صفر شروع می‌شود. درخواست‌های بازِ بررسی و پیشنهادهای منتظر تأیید همراه می‌آیند."
          data-confirm-type="warning"
          data-confirm-ok="شروع دور تازه"
          data-confirm-icon="ph-arrows-clockwise">
      @csrf
      <input type="text" name="name" class="form-control form-control-sm" maxlength="120" placeholder="نام دور (اختیاری)" style="width:11rem">
      <button type="submit" class="btn btn-light btn-sm text-nowrap"><i class="ph-arrows-clockwise me-1"></i> شروع دور تازه</button>
    </form>
  @endif
</div>
@endif
