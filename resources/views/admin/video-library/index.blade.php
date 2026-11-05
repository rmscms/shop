@extends('cms::admin.layout.index')

@section('title', 'کتابخانه ویدیو')

@section('content')
@include('shop::admin.partials.ui')
<div class="container-fluid shop-ui">
    <div class="shop-head">
        <span class="shop-icon shop-icon--lg tone-info"><i class="ph-video-camera"></i></span>
        <div>
            <h4 class="shop-head__title">کتابخانه ویدیو</h4>
            <p class="shop-head__sub">ویدیوهای محصول‌ها؛ بعد از آپلود خودکار برای پخش آماده می‌شوند</p>
        </div>
        <div class="shop-head__actions">
            <input type="search" class="form-control" id="video-search" placeholder="جستجوی نام ویدیو…" autocomplete="off" style="min-width:14rem">
            <button type="button" class="btn btn-primary" id="upload-video-btn"><i class="ph-upload-simple me-1"></i> آپلود ویدیو جدید</button>
            <input type="file" id="upload-video-input" accept="video/*" style="display: none;">
        </div>
    </div>

    <div id="videos-container" class="row g-3">
        @forelse($videos['data'] ?? [] as $video)
        <div class="col-md-6 col-xl-4" data-video-id="{{ $video['id'] }}" data-video-title="{{ mb_strtolower($video['title'] ?? $video['filename'] ?? '') }}">
            <div class="card video-card h-100 mb-0">
                <div class="position-relative shop-video-thumb">
                    @if(!empty($video['poster_url']))
                        <img src="{{ $video['poster_url'] }}" class="w-100 h-100" style="object-fit: cover;" alt="{{ $video['filename'] ?? '' }}" loading="lazy">
                    @else
                        <div class="d-flex align-items-center justify-content-center h-100"><i class="ph-video-camera text-muted" style="font-size: 3rem;"></i></div>
                    @endif

                    @if(!empty($video['is_transcoded']))
                        <button class="btn btn-light btn-icon rounded-circle play-video-btn shop-video-play"
                                data-video-id="{{ $video['id'] }}"
                                data-hls-url="{{ $video['hls_url'] ?? '' }}"
                                data-poster-url="{{ $video['poster_url'] ?? '' }}"
                                data-title="{{ $video['filename'] ?? '' }}"
                                title="پخش ویدیو"><i class="ph-play-circle"></i></button>
                    @endif
                </div>

                <div class="card-body">
                    <div class="fw-semibold text-truncate mb-1" title="{{ $video['title'] ?? $video['filename'] ?? '' }}">
                        {{ $video['title'] ?? $video['filename'] ?? 'بدون نام' }}
                    </div>
                    <div class="d-flex justify-content-between align-items-center fs-sm text-muted mb-3">
                        <span>{{ number_format(($video['size_bytes'] ?? 0) / 1024 / 1024, 2) }} MB</span>
                        @if(!empty($video['duration_seconds']))
                            <span><i class="ph-clock me-1"></i>{{ gmdate('i:s', $video['duration_seconds']) }}</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            @if(!empty($video['is_transcoded']))
                                <span class="shop-dot tone-success">آماده</span>
                            @else
                                <span class="shop-dot tone-warning">در حال پردازش…</span>
                            @endif
                            @if(!empty($video['products_count']))
                                <span class="shop-soft tone-muted" title="تعداد محصولات"><i class="ph-package"></i>{{ $video['products_count'] }}</span>
                            @endif
                        </div>
                        <button class="btn btn-light btn-icon btn-sm delete-video-btn" data-video-id="{{ $video['id'] }}" title="حذف ویدیو" style="color:rgb(var(--tone-danger))">
                            <i class="ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card mb-0">@include('shop::admin.partials.empty', ['icon' => 'ph-video-camera', 'title' => 'هیچ ویدیویی یافت نشد', 'hint' => 'با «آپلود ویدیو جدید» اولین ویدیو را اضافه کن.'])</div>
        </div>
        @endforelse
    </div>

    <div id="video-search-empty" class="card mt-3 mb-0" style="display:none">
        @include('shop::admin.partials.empty', ['icon' => 'ph-magnifying-glass', 'title' => 'ویدیویی با این نام نیست'])
    </div>
</div>

<!-- Video Player Modal -->
<div class="modal fade" id="video-player-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="video-player-title">پخش ویدیو</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <video id="video-player" class="w-100" controls style="max-height: 70vh; background: #000;"></video>
            </div>
        </div>
    </div>
</div>

@include('shop::admin.partials.confirm')
<script>
    // Delete confirmation uses the shared modern dialog on this page (video-library.js asks window.confirmAction).
    window.confirmAction = function (title, message, opts) {
        opts = opts || {};
        return window.rmsConfirm({
            title: title, message: message, type: 'danger', icon: 'ph-trash',
            ok: opts.confirmText || 'بله، حذف شود', cancel: opts.cancelText || 'انصراف'
        });
    };

    // The search box filters the cards already on the page.
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('video-search');
        var cards = Array.prototype.slice.call(document.querySelectorAll('#videos-container [data-video-id]'));
        var empty = document.getElementById('video-search-empty');
        if (!input) { return; }
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var shown = 0;
            cards.forEach(function (card) {
                var hit = q === '' || (card.getAttribute('data-video-title') || '').indexOf(q) !== -1;
                card.style.display = hit ? '' : 'none';
                if (hit) { shown++; }
            });
            if (empty) { empty.style.display = (q !== '' && shown === 0) ? '' : 'none'; }
        });
    });
</script>
@endsection
