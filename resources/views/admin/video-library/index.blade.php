@extends('cms::admin.layout.index')

@section('title', 'کتابخانه ویدیو')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="ph-video-camera me-2"></i>
                        کتابخانه ویدیو
                    </h5>
                    <button type="button" class="btn btn-primary btn-sm" id="upload-video-btn">
                        <i class="ph-plus me-1"></i>
                        آپلود ویدیو جدید
                    </button>
                    <input type="file" id="upload-video-input" accept="video/*" style="display: none;">
                </div>

                <div class="card-body">
                    <!-- Search -->
                    <div class="mb-3">
                        <input type="text" class="form-control" id="video-search" placeholder="جستجو...">
                    </div>

                    <!-- Videos Grid -->
                    <div id="videos-container" class="row g-3">
                        @forelse($videos['data'] ?? [] as $video)
                        <div class="col-md-4" data-video-id="{{ $video['id'] }}">
                            <div class="card video-card h-100">
                                <div class="card-body">
                                    <div class="position-relative mb-2" style="height: 180px; overflow: hidden; border-radius: 8px;">
                                        @if(!empty($video['poster_url']))
                                        <img src="{{ $video['poster_url'] }}" 
                                             class="img-fluid w-100 h-100" 
                                             style="object-fit: cover;"
                                             alt="{{ $video['filename'] ?? '' }}">
                                        @else
                                        <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                            <i class="ph-video-camera text-muted" style="font-size: 48px;"></i>
                                        </div>
                                        @endif
                                        
                                        @if(!empty($video['is_transcoded']))
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <button class="btn btn-sm btn-success play-video-btn" 
                                                    data-video-id="{{ $video['id'] }}"
                                                    data-hls-url="{{ $video['hls_url'] ?? '' }}"
                                                    data-poster-url="{{ $video['poster_url'] ?? '' }}"
                                                    data-title="{{ $video['filename'] ?? '' }}"
                                                    title="پخش ویدیو">
                                                <i class="ph-play-circle"></i>
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                    
                                    <h6 class="mb-1 text-truncate" title="{{ $video['title'] ?? $video['filename'] ?? '' }}">
                                        {{ $video['title'] ?? $video['filename'] ?? 'بدون نام' }}
                                    </h6>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            {{ number_format(($video['size_bytes'] ?? 0) / 1024 / 1024, 2) }} MB
                                        </small>
                                        @if(!empty($video['duration_seconds']))
                                        <small class="text-muted">
                                            <i class="ph-clock me-1"></i>{{ gmdate('i:s', $video['duration_seconds']) }}
                                        </small>
                                        @endif
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            @if(!empty($video['is_transcoded']))
                                            <span class="badge bg-success">
                                                <i class="ph-check-circle me-1"></i>آماده
                                            </span>
                                            @else
                                            <span class="badge bg-warning">
                                                <i class="ph-spinner spinner-border spinner-border-sm me-1"></i>در حال پردازش...
                                            </span>
                                            @endif
                                            
                                            @if(!empty($video['products_count']))
                                            <span class="badge bg-info ms-1" title="تعداد محصولات">
                                                <i class="ph-shopping-cart me-1"></i>{{ $video['products_count'] }}
                                            </span>
                                            @endif
                                        </div>
                                        
                                        <button class="btn btn-sm btn-danger delete-video-btn" 
                                                data-video-id="{{ $video['id'] }}">
                                            <i class="ph-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="col-12 text-center py-5">
                            <i class="ph-video-camera text-muted" style="font-size: 64px;"></i>
                            <p class="text-muted mt-3">هیچ ویدیویی یافت نشد</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
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
                <video id="video-player" 
                       class="w-100" 
                       controls 
                       style="max-height: 70vh; background: #000;">
                </video>
            </div>
        </div>
    </div>
</div>
@endsection

