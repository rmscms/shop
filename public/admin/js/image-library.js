// git-trigger
(function() {
    'use strict';

    const config = (window.RMS && window.RMS.ImageLibraryConfig) || window.ImageLibraryConfig || {};
    let currentDeleteImageId = null;

    // DOM references (assigned on init)
    let searchInput;
    let searchBtn;
    let uploadBtn;
    let uploadInput;
    let uploadModal;
    let deleteModal;
    let imagesContainer;

    // Upload modal elements
    let uploadFilesInput;
    let uploadPreview;
    let uploadPreviewImages;
    let uploadDropzone;
    let uploadBrowse;
    let uploadSummary;
    let uploadTotal;
    let startUploadBtn;
    let uploadQueue = [];
    let uploadSeq = 0;
    let uploadBusy = false;
    const maxUploadBytes = 10 * 1024 * 1024;

    // Delete modal elements
    let deleteImagePreview;
    let deleteImageFilename;
    let deleteImageInfo;
    let deleteWarning;
    let deleteSuccess;
    let usageCount;
    let confirmDeleteBtn;

    let uploadRoute = '';
    let destroyRouteTemplate = '';

    // Initialize
    function init() {
        cacheDomElements();
        setupEventListeners();
    }

    function cacheDomElements() {
        searchInput = document.getElementById('search-input');
        searchBtn = document.getElementById('search-btn');
        uploadBtn = document.getElementById('upload-btn');
        uploadInput = document.getElementById('upload-input');
        imagesContainer = document.getElementById('images-container');

        uploadFilesInput = document.getElementById('upload-files');
        uploadPreview = document.getElementById('upload-preview');
        uploadPreviewImages = document.getElementById('upload-preview-images');
        uploadDropzone = document.getElementById('upload-dropzone');
        uploadBrowse = document.getElementById('upload-browse');
        uploadSummary = document.getElementById('upload-summary');
        uploadTotal = document.getElementById('upload-total');
        startUploadBtn = document.getElementById('start-upload-btn');

        deleteImagePreview = document.getElementById('delete-image-preview');
        deleteImageFilename = document.getElementById('delete-image-filename');
        deleteImageInfo = document.getElementById('delete-image-info');
        deleteWarning = document.getElementById('delete-warning');
        deleteSuccess = document.getElementById('delete-success');
        usageCount = document.getElementById('usage-count');
        confirmDeleteBtn = document.getElementById('confirm-delete-btn');

        const uploadModalEl = document.getElementById('upload-modal');
        if (uploadModalEl && window.bootstrap && bootstrap.Modal) {
            uploadModal = bootstrap.Modal.getOrCreateInstance(uploadModalEl);
        }

        const deleteModalEl = document.getElementById('delete-modal');
        if (deleteModalEl && window.bootstrap && bootstrap.Modal) {
            deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalEl);
        }

        uploadRoute = config.routes?.upload || '';
        destroyRouteTemplate = config.routes?.destroy || '';
    }

    // Setup event listeners
    function setupEventListeners() {
        // Search
        if (searchInput && searchBtn) {
            searchBtn.addEventListener('click', performSearch);
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        }

        if (uploadBtn && uploadModal) {
            uploadBtn.addEventListener('click', () => uploadModal.show());
        }

        if (uploadInput) {
            uploadInput.addEventListener('change', handleFileSelection);
        }

        if (uploadBrowse && uploadFilesInput) {
            uploadBrowse.addEventListener('click', () => uploadFilesInput.click());
        }

        if (uploadFilesInput) {
            uploadFilesInput.addEventListener('change', function () {
                addUploadFiles(uploadFilesInput.files);
                uploadFilesInput.value = '';
            });
        }

        if (uploadDropzone) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                uploadDropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    uploadDropzone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (eventName) {
                uploadDropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    uploadDropzone.classList.remove('is-dragover');
                });
            });
            uploadDropzone.addEventListener('drop', function (event) {
                addUploadFiles(event.dataTransfer ? event.dataTransfer.files : []);
            });
        }

        if (startUploadBtn) {
            startUploadBtn.addEventListener('click', startUpload);
        }

        // Delete modal
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', confirmDelete);
        }

        // Dynamic event delegation for image cards
        if (imagesContainer) {
            imagesContainer.addEventListener('click', handleImageCardClick);
        }

        // Generate AVIF button handler
        if (imagesContainer) {
            imagesContainer.addEventListener('click', handleGenerateAvifClick);
        }
    }

    // Search functionality
    function performSearch() {
        const query = searchInput.value.trim();
        const url = new URL(window.location);
        if (query) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }

    function handleFileSelection(e) {
        addUploadFiles(e.target.files);
        e.target.value = '';
        if (uploadModal) {
            uploadModal.show();
        }
    }

    function addUploadFiles(fileList) {
        Array.from(fileList || []).forEach(function (file) {
            const item = {
                id: ++uploadSeq,
                file: file,
                status: 'ready',
                progress: 0,
                error: '',
                previewUrl: file.type && file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : ''
            };

            const allowedType = /^image\/(jpeg|png|webp|avif)$/.test(file.type) || /\.(jpe?g|png|webp|avif)$/i.test(file.name);
            if (!allowedType) {
                item.status = 'invalid';
                item.error = 'فرمت مجاز نیست';
            } else if (file.size > maxUploadBytes) {
                item.status = 'invalid';
                item.error = 'بیشتر از ۱۰ مگابایت است';
            }

            uploadQueue.push(item);
        });

        renderUploadQueue();
    }

    function renderUploadQueue() {
        if (!uploadPreview || !uploadPreviewImages) {
            return;
        }

        const ready = uploadQueue.filter(function (item) { return item.status === 'ready' || item.status === 'error'; });
        const invalid = uploadQueue.filter(function (item) { return item.status === 'invalid'; });
        const readyBytes = ready.reduce(function (sum, item) { return sum + item.file.size; }, 0);

        uploadPreview.classList.toggle('d-none', uploadQueue.length === 0);
        if (uploadSummary) {
            uploadSummary.classList.toggle('d-none', uploadQueue.length === 0);
            uploadSummary.textContent = uploadQueue.length
                ? ready.length + ' فایل آماده' + (invalid.length ? ' · ' + invalid.length + ' فایل رد شد' : '')
                : '';
        }
        if (uploadTotal) {
            uploadTotal.textContent = ready.length ? formatBytes(readyBytes) : '';
        }
        if (startUploadBtn) {
            startUploadBtn.disabled = uploadBusy || ready.length === 0;
            if (!uploadBusy) {
                startUploadBtn.textContent = ready.length > 1 ? 'آپلود ' + ready.length + ' تصویر' : 'شروع آپلود';
            }
        }

        uploadPreviewImages.innerHTML = '';
        uploadQueue.forEach(function (item) {
            uploadPreviewImages.appendChild(createUploadQueueItem(item));
        });
    }

    function createUploadQueueItem(item) {
        const row = document.createElement('div');
        row.className = 'upload-queue-item'
            + (item.status === 'invalid' || item.status === 'error' ? ' is-invalid' : '')
            + (item.status === 'done' ? ' is-done' : '');
        row.innerHTML = ''
            + '<img alt="" src="' + (item.previewUrl || '') + '">'
            + '<div class="upload-queue-meta">'
            + '<strong title="' + escapeHtml(item.file.name) + '">' + escapeHtml(item.file.name) + '</strong>'
            + '<span class="' + (item.error ? 'is-error' : '') + '">' + escapeHtml(item.error || statusLabel(item) + ' · ' + formatBytes(item.file.size)) + '</span>'
            + '<div class="progress"><div class="progress-bar" style="width:' + item.progress + '%"></div></div>'
            + '</div>'
            + (item.status === 'uploading' || item.status === 'done' ? '' : '<button type="button" class="remove-btn" title="حذف از صف"><i class="ph-x"></i></button>');

        const removeBtn = row.querySelector('.remove-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                removeUploadItem(item.id);
            });
        }

        return row;
    }

    function statusLabel(item) {
        if (item.status === 'uploading') return 'در حال آپلود';
        if (item.status === 'done') return 'آپلود شد';
        if (item.status === 'error') return 'خطا';
        return 'آماده';
    }

    function removeUploadItem(id) {
        const item = uploadQueue.find(function (entry) { return entry.id === id; });
        if (item && item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
        uploadQueue = uploadQueue.filter(function (entry) { return entry.id !== id; });
        renderUploadQueue();
    }

    async function startUpload() {
        const pending = uploadQueue.filter(function (item) {
            return item.status === 'ready' || item.status === 'error';
        });
        if (!pending.length || uploadBusy) {
            return;
        }

        uploadBusy = true;
        startUploadBtn.disabled = true;
        startUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال آپلود...';

        for (let i = 0; i < pending.length; i++) {
            const item = pending[i];
            item.status = 'uploading';
            item.error = '';
            item.progress = 0;
            renderUploadQueue();

            try {
                await uploadSingleFile(item.file, function (percent) {
                    item.progress = percent;
                    const bar = uploadPreviewImages && uploadPreviewImages.children[uploadQueue.indexOf(item)];
                    const progress = bar && bar.querySelector('.progress-bar');
                    if (progress) {
                        progress.style.width = percent + '%';
                    }
                });
                item.status = 'done';
                item.progress = 100;
                item.error = '';
            } catch (error) {
                item.status = 'error';
                item.error = error.message || 'آپلود نشد';
            }
            renderUploadQueue();
        }

        uploadBusy = false;
        const failed = uploadQueue.some(function (item) { return item.status === 'error'; });
        const uploaded = uploadQueue.some(function (item) { return item.status === 'done'; });

        if (!failed && uploaded) {
            window.location.reload();
            return;
        }

        renderUploadQueue();
        if (failed) {
            showErrorToast('بعضی فایل‌ها آپلود نشدند. همان‌ها را دوباره بفرستید.');
        }
    }

    function uploadSingleFile(file, onProgress) {
        return new Promise(function (resolve, reject) {
            if (!uploadRoute) {
                reject(new Error('مسیر آپلود تنظیم نشده است'));
                return;
            }

            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('image', file);
            xhr.open('POST', uploadRoute);
            xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = function (event) {
                if (event.lengthComputable && onProgress) {
                    onProgress(Math.round((event.loaded / event.total) * 100));
                }
            };
            xhr.onerror = function () {
                reject(new Error('ارتباط قطع شد'));
            };
            xhr.onload = function () {
                let result = {};
                try {
                    result = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    result = {};
                }
                if (xhr.status >= 200 && xhr.status < 300 && result.success) {
                    resolve(result);
                    return;
                }
                const validation = result.errors && result.errors.image && result.errors.image[0];
                reject(new Error(validation || result.message || 'آپلود نشد'));
            };
            xhr.send(formData);
        });
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Handle image card clicks
    function handleImageCardClick(e) {
        const target = e.target.closest('.view-btn, .delete-btn');
        if (!target) return;

        e.preventDefault();

        if (target.classList.contains('view-btn')) {
            const imageUrl = target.dataset.imageUrl;
            showImageViewer(imageUrl);
        } else if (target.classList.contains('delete-btn')) {
            const imageId = target.dataset.imageId;
            const canDelete = target.dataset.canDelete === 'true';
            showDeleteModal(imageId, canDelete);
        }
    }

    // Handle generate AVIF button click
    async function handleGenerateAvifClick(e) {
        const target = e.target.closest('.generate-avif-btn');
        if (!target) return;

        e.preventDefault();
        e.stopPropagation();

        const imageId = target.dataset.imageId;
        if (!imageId) return;

        // Disable button and show loading
        target.disabled = true;
        const originalHTML = target.innerHTML;
        target.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

        try {
            const generateRoute = (config.routes && config.routes.generateAvif) || '';
            const generateUrl = resolveRoute(generateRoute, imageId);
            if (!generateUrl) {
                throw new Error('Route generate AVIF تنظیم نشده است');
            }

            const response = await fetch(generateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message || 'ساخت AVIF در صف قرار گرفت', 'success');
                // Update button state (hide it since AVIF will be generated)
                target.closest('.image-card').querySelector('.badge.bg-warning')?.classList.replace('bg-warning', 'bg-info');
                target.closest('.image-card').querySelector('.badge.bg-warning')?.querySelector('i')?.classList.replace('ph-warning', 'ph-clock');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showErrorToast(result.message || 'خطا در ساخت AVIF');
                target.disabled = false;
                target.innerHTML = originalHTML;
            }
        } catch (error) {
            console.error('Generate AVIF failed:', error);
            showErrorToast('خطا در ساخت AVIF: ' + error.message);
            target.disabled = false;
            target.innerHTML = originalHTML;
        }
    }

    // Show image viewer
    function showImageViewer(imageUrl) {
        // Simple image viewer - you can enhance this
        const viewer = window.open(imageUrl, '_blank');
        if (viewer) {
            viewer.focus();
        }
    }

    // Show delete modal
    async function showDeleteModal(imageId, canDelete) {
        try {
            // Fetch image data (you might want to add an API endpoint for this)
            // For now, we'll use the data from the card
            const card = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
            if (!card) return;

            const img = card.querySelector('img');
            const filename = card.querySelector('.filename').title;
            const usageBadge = card.querySelector('.badge');

            deleteImagePreview.src = img.src;
            deleteImageFilename.textContent = filename;
            deleteImageInfo.textContent = 'اطلاعات تصویر';

            const usageCountValue = parseInt(usageBadge.textContent) || 0;
            usageCount.textContent = usageCountValue;

            if (usageCountValue > 0) {
                deleteWarning.classList.remove('d-none');
                deleteSuccess.classList.add('d-none');
                confirmDeleteBtn.disabled = true;
            } else {
                deleteWarning.classList.add('d-none');
                deleteSuccess.classList.remove('d-none');
                confirmDeleteBtn.disabled = false;
            }

            currentDeleteImageId = imageId;
            if (deleteModal) {
                deleteModal.show();
            }

        } catch (error) {
            console.error('Error showing delete modal:', error);
        }
    }

    // Confirm delete
    async function confirmDelete() {
        if (!currentDeleteImageId) return;

        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال حذف...';

        try {
            const destroyUrl = resolveRoute(destroyRouteTemplate, currentDeleteImageId);
            if (!destroyUrl) {
                showErrorToast('Route حذف تصویر تنظیم نشده است.');
                return;
            }

            const response = await fetch(destroyUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': config.csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                showToast('تصویر با موفقیت حذف شد', 'success');
                if (deleteModal) {
                    deleteModal.hide();
                }
                // Remove the card from DOM
                const card = document.querySelector(`.image-card[data-image-id="${currentDeleteImageId}"]`);
                if (card) {
                    card.closest('.col-xl-2, .col-lg-3, .col-md-4, .col-sm-6').remove();
                }
            } else {
                showErrorToast(result.message || 'خطا در حذف تصویر');
            }

        } catch (error) {
            console.error('Delete failed:', error);
            showErrorToast('خطا در حذف تصویر');
        } finally {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = 'حذف تصویر';
            currentDeleteImageId = null;
        }
    }

    // Utility functions
    function resolveRoute(template, value) {
        if (!template || !value) {
            return '';
        }
        return template.replace('__ID__', value);
    }

    function showToast(message, type = 'info') {
        if (window.showToastMessage) {
            window.showToastMessage(type, '', message);
        } else {
            console.log(`${type}: ${message}`);
        }
    }

    function showErrorToast(message) {
        showToast(message, 'danger');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
