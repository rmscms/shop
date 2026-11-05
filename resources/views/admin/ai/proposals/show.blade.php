@extends('cms::admin.layout.index')

@php
  $pending = $proposal->status === 'pending';
  $labels = ['pending' => ['در انتظار تأیید', 'warning'], 'applied' => ['اعمال شد', 'success'], 'rejected' => ['رد شد', 'dark'], 'superseded' => ['جایگزین شد', 'secondary'], 'failed' => ['خطا', 'danger']];
  [$sLabel, $sClass] = $labels[$proposal->status] ?? [$proposal->status, 'secondary'];
  $spec = $resource?->fields() ?? [];
@endphp

@section('content')
<div class="content ai-page">
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
      <h5 class="mb-0">
        {{ $resource?->label() ?? $proposal->resource_type }}:
        {{ $modelTitle ?? ('#'.$proposal->resource_id) }}
        <span class="text-muted small">#{{ $proposal->resource_id }}</span>
      </h5>
      <span class="badge bg-{{ $sClass }}">{{ $sLabel }}</span>
      <div class="ms-auto d-flex gap-2">
        @if($adminUrl)<a href="{{ $adminUrl }}" class="btn btn-sm btn-outline-primary">ویرایش</a>@endif
        <a href="{{ route('admin.shop.ai-proposals.index') }}" class="btn btn-sm btn-outline-secondary">بازگشت</a>
      </div>
    </div>
    <div class="card-body small text-muted">
      <span dir="ltr">{{ $proposal->created_at?->format('Y-m-d H:i') }}</span> · {{ $proposal->actor ?? '—' }} · {{ $proposal->source }}
      @if($proposal->note)<div class="mt-2 text-body">یادداشت ایجنت: {{ $proposal->note }}</div>@endif
      @if($proposal->error)<div class="mt-2 text-danger">{{ $proposal->error }}</div>@endif
    </div>
  </div>

  @if(!$model)
    <div class="alert alert-warning">این مورد دیگر وجود ندارد.</div>
  @endif

  <form method="post" action="{{ route('admin.shop.ai-proposals.apply', $proposal) }}">
    @csrf
    <div class="card mb-3">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th style="width:40px"></th><th style="width:140px">فیلد</th><th>فعلی</th><th>پیشنهاد ایجنت</th></tr>
          </thead>
          <tbody>
            @foreach((array) $proposal->changes as $field => $value)
              @php
                $now = (string) ($live[$field] ?? '');
                $was = (string) data_get($proposal->before, $field, '');
                $drift = $pending && $now !== $was;
              @endphp
              <tr>
                <td><input type="checkbox" class="form-check-input" name="fields[]" value="{{ $field }}" @checked($pending) @disabled(!$pending || !$model)></td>
                <td class="fw-semibold">
                  {{ $spec[$field]['label'] ?? $field }}
                  @if($drift)<div class="small text-warning fw-normal"><i class="ph-warning me-1"></i>از زمان پیشنهاد عوض شده</div>@endif
                </td>
                <td class="text-muted" style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit(strip_tags($now), 600) ?: '—' }}</td>
                <td style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit(strip_tags((string) $value), 600) }}</td>
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
        <button type="submit" class="btn btn-outline-danger" formaction="{{ route('admin.shop.ai-proposals.reject', $proposal) }}" formnovalidate
                data-confirm="کل این پیشنهاد رد شود؟"
                data-confirm-title="رد پیشنهاد"
                data-confirm-desc="هیچ تغییری اعمال نمی‌شود و پیشنهاد بسته می‌شود."
                data-confirm-type="danger"
                data-confirm-ok="رد کن"
                data-confirm-icon="ph-x-circle">
          <i class="ph-x me-1"></i> رد کل پیشنهاد
        </button>
      </div>
    @endif
  </form>
</div>
@include('shop::admin.partials.confirm')
@endsection
