<!-- Delete Modal -->
<div class="modal fade" id="delete-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">حذف تصویر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <img id="delete-image-preview" src="" alt="" class="rounded me-3" style="width: 80px; height: 80px; object-fit: cover;">
                    <div>
                        <h6 id="delete-image-filename"></h6>
                        <p class="text-muted mb-0" id="delete-image-info"></p>
                    </div>
                </div>

                <div id="delete-warning" class="alert alert-warning d-none">
                    <i class="ph-warning me-1"></i>
                    این تصویر در <strong id="usage-count"></strong> محصول استفاده شده است.
                    حذف این تصویر ممکن است بر نمایش محصولات تأثیر بگذارد.
                </div>

                <div id="delete-success" class="alert alert-info d-none">
                    <i class="ph-info me-1"></i>
                    این تصویر در هیچ محصولی استفاده نشده و قابل حذف است.
                </div>

                <p class="mb-0">آیا مطمئن هستید که می‌خواهید این تصویر را حذف کنید؟</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn" disabled>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    حذف تصویر
                </button>
            </div>
        </div>
    </div>
</div>
