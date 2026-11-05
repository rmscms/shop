{{--
  Full detail of one agent change, in a modal (the tables only have room for a short line).
  @include('shop::admin.partials.change-modal', [
      'modalId' => 'chg-12', 'title' => 'Product name', 'meta' => '2026-11-05 09:55 · token name',
      'instruction' => 'what the admin said', 'before' => ['field' => 'old'], 'after' => ['field' => 'new'], 'link' => $url (optional)
  ])
  Open it with a button:  data-bs-toggle="modal" data-bs-target="#chg-12"
--}}
@php
  $fields = array_values(array_unique(array_merge(array_keys((array) ($after ?? [])), array_keys((array) ($before ?? [])))));
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shop-ui">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">{{ $title }}</h5>
          @if(!empty($meta))<div class="fs-sm text-muted mt-1">{{ $meta }}</div>@endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>
      <div class="modal-body">
        @if(!empty($instruction))
          <div class="shop-note tone-info mb-3">
            <div class="fw-semibold mb-1"><i class="ph-chat-circle-text me-1"></i> دستور ادمین</div>
            <div style="white-space:pre-wrap;line-height:1.9">{{ $instruction }}</div>
          </div>
        @endif

        @forelse($fields as $field)
          <div class="mb-3">
            <div class="fw-semibold mb-2"><i class="ph-pencil-simple me-1 text-muted"></i> {{ $field }}</div>
            <div class="row g-2">
              <div class="col-md-6">
                <div class="fs-xs text-muted mb-1">قبل</div>
                <div class="shop-diff shop-diff--old" style="max-height:18rem;overflow:auto">{{ \RMS\Shop\Support\UiText::plain(data_get($before ?? [], $field, '')) ?: '—' }}</div>
              </div>
              <div class="col-md-6">
                <div class="fs-xs text-muted mb-1">بعد</div>
                <div class="shop-diff shop-diff--new" style="max-height:18rem;overflow:auto">{{ \RMS\Shop\Support\UiText::plain(data_get($after ?? [], $field, '')) ?: '—' }}</div>
              </div>
            </div>
          </div>
        @empty
          <div class="text-muted">جزئیاتی ثبت نشده است.</div>
        @endforelse
      </div>
      <div class="modal-footer">
        @if(!empty($link))<a href="{{ $link }}" class="btn btn-light me-auto"><i class="ph-arrow-square-out me-1"></i> باز کردن</a>@endif
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">بستن</button>
      </div>
    </div>
  </div>
</div>
