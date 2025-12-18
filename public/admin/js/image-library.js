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
    let startUploadBtn;

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

        // Upload
        if (uploadBtn && uploadInput) {
            uploadBtn.addEventListener('click', () => uploadInput.click());
            uploadInput.addEventListener('change', handleFileSelection);
        }

        // Upload modal
        if (uploadFilesInput) {
            uploadFilesInput.addEventListener('change', handleUploadFileSelection);
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

    // File selection for main upload button
    function handleFileSelection(e) {
        if (!uploadFilesInput) {
            return;
        }

        const files = Array.from(e.target.files);
        if (files.length > 0) {
            uploadFilesInput.files = e.target.files;
            handleUploadFileSelection({ target: uploadFilesInput });
            if (uploadModal) {
                uploadModal.show();
            }
        }
    }

    // Handle upload file selection
    function handleUploadFileSelection(e) {
        if (!uploadPreview || !uploadPreviewImages || !startUploadBtn) {
            return;
        }

        const files = Array.from(e.target.files);

        if (files.length === 0) {
            uploadPreview.classList.add('d-none');
            startUploadBtn.disabled = true;
            return;
        }

        // Show preview
        uploadPreview.classList.remove('d-none');
        uploadPreviewImages.innerHTML = '';

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = createUploadPreviewItem(e.target.result, file.name, index);
                uploadPreviewImages.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });

        startUploadBtn.disabled = false;
    }

    // Create upload preview item
    function createUploadPreviewItem(src, filename, index) {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';

        col.innerHTML = `
            <div class="upload-preview-item">
                <img src="${src}" alt="${filename}">
                <button type="button" class="remove-btn" data-index="${index}" title="حذف">
                    <i class="ph-x"></i>
                </button>
                <div class="progress">
                    <div class="progress-bar" style="width: 0%"></div>
                </div>
                <small class="text-center d-block mt-1 text-truncate" title="${filename}">${filename}</small>
            </div>
        `;

        // Remove button event
        col.querySelector('.remove-btn').addEventListener('click', function() {
            col.remove();
            updateFileListAfterRemove(index);
        });

        return col;
    }

    // Update file list after removing a file
    function updateFileListAfterRemove(removedIndex) {
        if (!uploadFilesInput || !uploadPreview || !startUploadBtn) {
            return;
        }

        const dt = new DataTransfer();
        const files = Array.from(uploadFilesInput.files);

        files.forEach((file, index) => {
            if (index !== removedIndex) {
                dt.items.add(file);
            }
        });

        uploadFilesInput.files = dt.files;

        if (uploadFilesInput.files.length === 0) {
            uploadPreview.classList.add('d-none');
            startUploadBtn.disabled = true;
        }
    }

    // Start upload process
    async function startUpload() {
        if (!uploadFilesInput || !uploadPreview || !uploadPreviewImages || !startUploadBtn) {
            return;
        }

        const files = Array.from(uploadFilesInput.files);
        if (files.length === 0) return;

        startUploadBtn.disabled = true;
        startUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال آپلود...';

        const previewItems = uploadPreviewImages.querySelectorAll('.upload-preview-item');

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const previewItem = previewItems[i];

            try {
                await uploadSingleFile(file, previewItem);
            } catch (error) {
                console.error('Upload failed:', error);
                showErrorToast(`خطا در آپلود ${file.name}: ${error.message}`);
            }
        }

        // Reset form and reload page
        if (uploadModal) {
            uploadModal.hide();
        }
        uploadFilesInput.value = '';
        uploadPreview.classList.add('d-none');
        startUploadBtn.disabled = false;
        startUploadBtn.innerHTML = 'شروع آپلود';

        // Reload to show new images
        window.location.reload();
    }

    // Upload single file
    async function uploadSingleFile(file, previewItem) {
        const formData = new FormData();
        formData.append('image', file);

        const progressBar = previewItem.querySelector('.progress-bar');

        if (!uploadRoute) {
            throw new Error('upload route not configured');
        }

        const response = await fetch(uploadRoute, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': config.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Upload failed');
        }

        // Update progress
        progressBar.style.width = '100%';
        previewItem.style.opacity = '0.6';
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
