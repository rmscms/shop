@extends('cms::admin.layout.index')

@section('title', 'کتابخانه تصاویر')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="ph-image me-2"></i>
                        کتابخانه تصاویر
                    </h5>
                    <button type="button" class="btn btn-primary btn-sm" id="upload-btn">
                        <i class="ph-plus me-1"></i>
                        آپلود تصویر جدید
                    </button>
                    <input type="file" id="upload-input" accept="image/*" multiple style="display: none;">
                </div>

                <div class="card-body">
                    <!-- Custom Content for Image Library -->
                    <div id="images-container">
                        @include('shop::admin.image-library.partials.images-grid', ['images' => $images])
                    </div>

                    <!-- Pagination -->
                    @if($images->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $images->appends(request()->query())->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
@include('shop::admin.image-library.partials.upload-modal')
@include('shop::admin.image-library.partials.delete-modal')

@endsection
