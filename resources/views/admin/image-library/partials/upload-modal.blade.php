<div class="modal fade" id="upload-modal" tabindex="-1" aria-labelledby="upload-modal-title">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="upload-modal-title">آپلود چند تصویر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <div id="upload-dropzone" class="upload-dropzone">
                    <i class="ph-cloud-arrow-up" aria-hidden="true"></i>
                    <strong>چند تصویر را با هم رها کنید</strong>
                    <p>یا از دکمهٔ زیر انتخاب کنید. می‌توانید چند بار فایل اضافه کنید.</p>
                    <button type="button" class="btn btn-primary btn-sm" id="upload-browse">انتخاب فایل‌ها</button>
                    <input type="file" id="upload-files" accept="image/jpeg,image/png,image/webp,image/avif,.jpg,.jpeg,.png,.webp,.avif" multiple hidden>
                    <small>JPG، PNG، WebP، AVIF — هر فایل تا ۱۰ مگابایت</small>
                </div>
                <div id="upload-summary" class="upload-summary d-none"></div>
                <div id="upload-preview" class="d-none">
                    <div id="upload-preview-images"></div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="upload-total me-auto" id="upload-total"></span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="start-upload-btn" disabled>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    شروع آپلود
                </button>
            </div>
        </div>
    </div>
</div>
