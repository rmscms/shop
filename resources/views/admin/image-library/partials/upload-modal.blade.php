<!-- Upload Modal -->
<div class="modal fade" id="upload-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">آپلود تصویر جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">انتخاب تصاویر</label>
                    <input type="file" class="form-control" id="upload-files" accept="image/*" multiple>
                    <div class="form-text">
                        فرمت‌های مجاز: JPG, PNG, WebP, AVIF | حداکثر اندازه: 5MB
                    </div>
                </div>
                <div id="upload-preview" class="d-none">
                    <div class="border rounded p-3 bg-light">
                        <div class="row g-2" id="upload-preview-images"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="start-upload-btn" disabled>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    شروع آپلود
                </button>
            </div>
        </div>
    </div>
</div>
