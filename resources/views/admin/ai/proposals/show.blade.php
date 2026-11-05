@extends('cms::admin.layout.index')

@php
  $pending = $proposal->status === 'pending';
  $tone = \RMS\Shop\Support\UiTone::tone('proposal', $proposal->status);
  $spec = $resource?->fields() ?? [];
@endphp

@section('content')
@include('shop::admin.partials.ui')
<div class="content shop-ui">
  @if($errors->any())
    <div class="shop-note tone-danger mb-3"><i class="ph-warning-circle me-1"></i>{{ $errors->first() }}</div>
  @endif

  <div class="shop-head">
    <span class="shop-icon shop-icon--lg tone-{{ $tone }}"><i class="ph-git-diff"></i></span>
    <div>
      <h4 class="shop-head__title">{{ $resource?->label() ?? $proposal->resource_type }}: {{ $modelTitle ?? ('#'.$proposal->resource_id) }}</h4>
      <p class="shop-head__sub">
        <span class="shop-dot tone-{{ $tone }}">{{ \RMS\Shop\Support\UiTone::label('proposal', $proposal->status) }}</span>
        · <span dir="ltr">{{ $proposal->created_at?->format('Y-m-d H:i') }}</span> · {{ $proposal->actor ?? '—' }}
      </p>
    </div>
    <div class="shop-head__actions">
      @if($adminUrl)<a href="{{ $adminUrl }}" class="btn btn-light"><i class="ph-pencil-simple me-1"></i> ویرایش</a>@endif
      <a href="{{ route('admin.shop.ai-proposals.index') }}" class="btn btn-light"><i class="ph-arrow-right me-1"></i> بازگشت</a>
    </div>
  </div>

  @if($proposal->note)
    <div class="shop-note tone-info mb-3"><i class="ph-note me-1"></i> یادداشت ایجنت: {{ $proposal->note }}</div>
  @endif
  @if($proposal->error)
    <div class="shop-note tone-danger mb-3">{{ $proposal->error }}</div>
  @endif
  @if(!$model)
    <div class="shop-note tone-warning mb-3"><i class="ph-warning me-1"></i> این مورد دیگر وجود ندارد.</div>
  @endif

  <form method="post" action="{{ route('admin.shop.ai-proposals.apply', $proposal) }}">
    @csrf
    <div class="card mb-3">
      <div class="table-responsive">
        <table class="table shop-table align-top">
          <thead><tr><th style="width:2.5rem"></th><th style="width:9rem">فیلد</th><th>فعلی</th><th>پیشنهاد ایجنت</th></tr></thead>
          <tbody>
            @foreach((array) $proposal->changes as $field => $value)
              @php
                $now = (string) ($live[$field] ?? '');
                $was = (string) data_get($proposal->before, $field, '');
                $drift = $pending && $now !== $was;
              @endphp
              <tr>
                <td><input type="checkbox" class="form-check-input" name="fields[]" value="{{ $field }}" @checked($pending) @disabled(!$pending || !$model)></td>
                <td>
                  <div class="fw-semibold">{{ $spec[$field]['label'] ?? $field }}</div>
                  @if($drift)<div class="fs-xs tone-warning" style="color:rgb(var(--tone-warning))"><i class="ph-warning me-1"></i>از زمان پیشنهاد عوض شده</div>@endif
                </td>
                <td><div class="shop-diff shop-diff--old">{{ \Illuminate\Support\Str::limit(strip_tags($now), 600) ?: '—' }}</div></td>
                <td><div class="shop-diff shop-diff--new">{{ \Illuminate\Support\Str::limit(strip_tags((string) $value), 600) }}</div></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    @if($pending && $model)
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success"
                data-confirm="فیلدهای تیک‌خورده روی {{ $resource?->label() ?? 'این مورد' }} اعمال شوند؟"
                data-confirm-title="اعمال تغییرها"
                data-confirm-desc="مقدارهای فعلی با پیشنهاد ایجنت جایگزین می‌شوند و در تاریخچه ثبت می‌گردند."
                data-confirm-type="success"
                data-confirm-ok="اعمال کن"><i class="ph-check me-1"></i> اعمال فیلدهای انتخاب‌شده</button>
        <button type="submit" class="btn btn-light" formaction="{{ route('admin.shop.ai-proposals.reject', $proposal) }}" formnovalidate
                data-confirm="کل این پیشنهاد رد شود؟"
                data-confirm-title="رد پیشنهاد"
                data-confirm-desc="هیچ تغییری اعمال نمی‌شود و پیشنهاد بسته می‌شود."
                data-confirm-type="danger"
                data-confirm-ok="رد کن"
                data-confirm-icon="ph-x-circle"><i class="ph-x me-1"></i> رد کل پیشنهاد</button>
      </div>
    @endif
  </form>
</div>
@include('shop::admin.partials.confirm')
@endsection
