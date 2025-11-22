@if($product && $product->exists)
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="ph-video-camera me-2"></i>
            ویدیوهای محصول
            <span class="badge bg-primary ms-2" id="videos-count">{{ $product->assignedVideos->count() ?? 0 }}</span>
        </h6>
        <div class="btn-group">
            <a href="{{ route('admin.shop.video-library.index') }}" class="btn btn-outline-primary btn-sm" target="_blank">
                <i class="ph-folder-open me-1"></i>
                کتابخانه ویدیو
            </a>
            <button type="button" class="btn btn-primary btn-sm" id="select-from-library-btn">
                <i class="ph-plus me-1"></i>
                انتخاب از کتابخانه
            </button>
        </div>
    </div>
    <div class="card-body">
        @if(($product->assignedVideos->count() ?? 0) > 0)
        <div class="row g-3" id="assigned-videos">
            @foreach($product->assignedVideos->sortBy('pivot.sort') as $video)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card h-100 product-video-card" data-video-id="{{ $video->id }}">
                    <div class="position-relative">
                        @if(!empty($video->poster_path))
                        <img src="{{ Storage::url($video->poster_path) }}" class="card-img-top" style="height: 150px; object-fit: cover;">
                        @else
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                            <i class="ph-video-camera text-muted" style="font-size: 48px;"></i>
                        </div>
                        @endif

                        @if($video->pivot && $video->pivot->is_main)
                        <div class="position-absolute top-0 start-0 m-2">
                            <span class="badge bg-success">اصلی</span>
                        </div>
                        @endif

                        <div class="position-absolute top-0 end-0 m-2">
                            @if(!empty($video->is_transcoded))
                            <span class="badge bg-success">
                                <i class="ph-check-circle me-1"></i>آماده
                            </span>
                            @else
                            <span class="badge bg-warning">
                                <i class="ph-spinner spinner-border spinner-border-sm me-1"></i>در حال پردازش...
                            </span>
                            @endif
                        </div>
                    </div>

                    <div class="card-body p-2">
                        <small class="text-muted d-block text-truncate" title="{{ $video->title ?? $video->filename }}">
                            {{ $video->title ?? $video->filename }}
                        </small>
                        @if(!empty($video->duration_seconds))
                        <div class="text-muted"><small>مدت: {{ gmdate('i:s', $video->duration_seconds) }}</small></div>
                        @endif
                        <div class="mt-2 d-flex gap-1">
                            @if($video->pivot && $video->pivot->is_main)
                            <button class="btn btn-sm btn-success set-main-video-btn flex-fill" data-video-id="{{ $video->id }}" disabled>
                                <i class="ph-star-fill"></i> ویدیو اصلی
                            </button>
                            @else
                            <button class="btn btn-sm btn-outline-primary set-main-video-btn flex-fill" data-video-id="{{ $video->id }}">
                                <i class="ph-star"></i> تنظیم اصلی
                            </button>
                            @endif
                            <button class="btn btn-sm btn-outline-danger detach-video-btn" data-video-id="{{ $video->id }}">
                                <i class="ph-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-4">
            <i class="ph-video-camera display-4 text-muted mb-3"></i>
            <h6 class="text-muted">هیچ ویدیویی اختصاص نیافته</h6>
            <p class="text-muted">از کتابخانه ویدیو انتخاب کنید</p>
        </div>
        @endif
    </div>
</div>

<!-- Video Library Modal -->
<div class="modal fade" id="video-library-modal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">انتخاب ویدیو از کتابخانه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Search -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="video-library-search" placeholder="جستجو...">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary w-100" id="search-videos-btn">
                            <i class="ph-magnifying-glass me-1"></i>
                            جستجو
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div id="video-library-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary"></div>
                    <div class="mt-2">در حال بارگذاری...</div>
                </div>

                <!-- Videos Grid -->
                <div id="video-library-grid" class="row g-3">
                    <!-- Videos will be loaded here via AJAX -->
                </div>

                <!-- Load More -->
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-primary d-none" id="load-more-videos-btn">
                        <i class="ph-arrow-down me-1"></i>
                        نمایش بیشتر
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="assign-selected-videos-btn" disabled>
                    <i class="ph-plus me-1"></i>
                    اختصاص ویدیوهای انتخاب شده (<span id="selected-videos-count">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>
@else
<div class="card">
    <div class="card-body text-center py-5">
        <i class="ph-video-camera display-4 text-muted mb-3"></i>
        <h6 class="text-muted">ابتدا محصول را ذخیره کنید</h6>
        <p class="text-muted">پس از ذخیره محصول، می‌توانید ویدیو اضافه کنید</p>
    </div>
</div>
@endif
