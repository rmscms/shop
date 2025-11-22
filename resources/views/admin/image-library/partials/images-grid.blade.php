@if($images->count() > 0)
<div class="row g-3">
    @foreach($images as $image)
    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
        <div class="card h-100 image-card" data-image-id="{{ $image->id }}">
            <div class="position-relative">
                <img src="{{ $image->avif_url ?: $image->url }}"
                     alt="{{ $image->filename }}"
                     class="card-img-top"
                     style="height: 150px; object-fit: cover;">

                <!-- Overlay with actions -->
                <div class="card-img-overlay d-flex align-items-center justify-content-center opacity-0 hover-overlay">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-light view-btn"
                                data-image-url="{{ $image->url }}" title="مشاهده بزرگ">
                            <i class="ph-eye"></i>
                        </button>
                        @if(!$image->has_avif)
                        <button type="button" class="btn btn-sm btn-outline-warning generate-avif-btn"
                                data-image-id="{{ $image->id }}" title="ساخت AVIF">
                            <i class="ph-lightning"></i>
                        </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                data-image-id="{{ $image->id }}"
                                data-can-delete="{{ $image->canBeDeleted() ? 'true' : 'false' }}"
                                title="حذف">
                            <i class="ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1 min-w-0">
                        <small class="text-muted d-block filename" title="{{ $image->filename }}">
                            {{ Str::limit($image->filename, 20) }}
                        </small>
                        <small class="text-muted">
                            {{ $image->formatted_size }}
                        </small>
                    </div>
                    <div class="ms-2">
                        <span class="badge bg-secondary">{{ $image->assignments_count }}</span>
                    </div>
                </div>

                <div class="mt-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        {{ $image->created_at->diffForHumans() }}
                    </small>
                    @if($image->has_avif)
                    <span class="badge bg-success" title="AVIF موجود است">
                        <i class="ph-check-circle"></i> AVIF
                    </span>
                    @else
                    <span class="badge bg-warning" title="AVIF ساخته نشده">
                        <i class="ph-warning"></i> No AVIF
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="text-center py-5">
    <i class="ph-image display-4 text-muted mb-3"></i>
    <h5 class="text-muted">هیچ تصویری یافت نشد</h5>
    <p class="text-muted">تصاویر آپلود شده در اینجا نمایش داده می‌شوند</p>
</div>
@endif
