@extends('cms::admin.list.index')

@section('content')
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold">{{ trans('shop.image_size.regenerate') }}</div>
                <div class="text-muted small">{{ trans('shop.image_size.regenerate_help') }}</div>
            </div>
            <button
                type="button"
                class="btn btn-warning btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#imageSizesRegenerateModal"
            >
                <i class="ph-arrows-clockwise me-1"></i>
                {{ trans('shop.image_size.regenerate') }}
            </button>
        </div>
    </div>

    <div class="modal fade" id="imageSizesRegenerateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ trans('shop.image_size.regenerate_confirm_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{ trans('shop.image_size.regenerate_confirm_body') }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                    <form method="post" action="{{ route('admin.shop.image-sizes.regenerate') }}" class="m-0">
                        @csrf
                        <input type="hidden" name="force" value="1">
                        <button type="submit" class="btn btn-warning">
                            <i class="ph-arrows-clockwise me-1"></i>
                            {{ trans('shop.image_size.regenerate') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @parent
@endsection
