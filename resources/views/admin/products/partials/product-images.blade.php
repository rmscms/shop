<div class="card">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="ph-images me-2"></i>
            تصاویر محصول
            <span class="badge bg-primary ms-2" id="images-count">{{ $product->assignedImages->count() }}</span>
        </h6>
    </div>
    <div class="card-body">
        @if($product->assignedImages->count() > 0)
        <style>
            .product-image-card .hover-overlay {
                transition: opacity 0.3s ease;
                background: rgba(0, 0, 0, 0.6);
            }
            .product-image-card:hover .hover-overlay {
                opacity: 1 !important;
            }
        </style>
        <div class="row g-3" id="assigned-images">
            @foreach($product->assignedImages->sortBy('pivot.sort') as $image)
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="card h-100 product-image-card" data-image-id="{{ $image->id }}">
                    <div class="position-relative">
                        <img src="{{ $image->avif_url ?: $image->url }}"
                             alt="{{ $image->filename }}"
                             class="card-img-top"
                             style="height: 120px; object-fit: cover;">

                        @if($image->pivot->is_main)
                        <div class="position-absolute top-0 start-0 m-2">
                            <span class="badge bg-success">اصلی</span>
                        </div>
                        @endif

                        <!-- Overlay with actions -->
                        <div class="card-img-overlay d-flex align-items-center justify-content-center opacity-0 hover-overlay">
                            <div class="btn-group-vertical">
                                <button type="button" class="btn btn-sm btn-outline-light set-main-btn"
                                        data-image-id="{{ $image->id }}"
                                        title="تنظیم به عنوان تصویر اصلی">
                                    <i class="ph-star"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-light assign-combinations-btn"
                                        data-image-id="{{ $image->id }}"
                                        title="اختصاص به ترکیب‌ها">
                                    <i class="ph-grid-four"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger detach-btn"
                                        data-image-id="{{ $image->id }}"
                                        title="جدا کردن از محصول">
                                    <i class="ph-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-2">
                        <div class="text-center">
                            <small class="text-muted filename" title="{{ $image->filename }}">
                                {{ Str::limit($image->filename, 15) }}
                            </small>
                        </div>
                        <div class="mt-1">
                            <input type="number" class="form-control form-control-sm sort-input"
                                   value="{{ $image->pivot->sort }}" min="0"
                                   data-image-id="{{ $image->id }}"
                                   placeholder="ترتیب">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-3">
            <button type="button" class="btn btn-outline-primary btn-sm" id="save-sort-btn">
                <i class="ph-floppy-disk me-1"></i>
                ذخیره ترتیب
            </button>
        </div>
        @else
        <div class="text-center py-4">
            <i class="ph-image display-4 text-muted mb-3"></i>
            <h6 class="text-muted">هیچ تصویری اختصاص نیافته</h6>
            <p class="text-muted">از کتابخانه تصاویر انتخاب کنید</p>
        </div>
        @endif
    </div>
</div>

<!-- Attach from Library Modal -->
<div class="modal fade" id="attach-modal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">انتخاب تصاویر از کتابخانه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Search -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="library-search" placeholder="جستجو: نام محصول یا کلمه کلیدی...">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary w-100" id="load-library-btn">
                            <i class="ph-magnifying-glass me-1"></i>
                            جستجو
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div id="library-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                    <div class="mt-2">در حال بارگذاری تصاویر...</div>
                </div>

                <!-- Images Grid -->
                <div id="library-images" class="d-none">
                    <!-- Images will be loaded here via AJAX -->
                </div>

                <!-- Load More Button -->
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-primary d-none" id="load-more-btn">
                        <i class="ph-arrow-down me-1"></i>
                        نمایش 20 عکس بعدی
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="attach-selected-btn" disabled>
                    <i class="ph-plus me-1"></i>
                    اختصاص تصاویر انتخاب شده (<span id="selected-count">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign to Combinations Modal -->
<div class="modal fade" id="combinations-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">اختصاص تصویر به ترکیب‌ها</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 text-center">
                    <img id="combination-modal-image" src="" alt="" class="img-thumbnail" style="max-height: 150px;">
                </div>
                
                <div class="form-group">
                    <label class="form-label fw-bold">انتخاب ترکیب‌ها:</label>
                    <div id="modal-combinations-list" style="max-height: 400px; overflow-y: auto;">
                        <!-- Will be loaded via JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="detach-all-combinations-btn">
                    <i class="ph-x-circle me-1"></i>
                    حذف از همه ترکیبات
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="save-combinations-btn">
                    <i class="ph-floppy-disk me-1"></i>
                    ذخیره
                </button>
            </div>
        </div>
    </div>
</div>
