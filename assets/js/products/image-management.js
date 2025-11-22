// git-trigger
// Product Image Management for Image Library Integration
(function() {
    'use strict';

    let selectedImages = new Set();
    let currentProductId = null;

    // DOM elements
    let attachModal = null;
    let attachFromLibraryBtn = null;
    let librarySearch = null;
    let loadLibraryBtn = null;
    let libraryLoading = null;
    let libraryImages = null;
    let attachSelectedBtn = null;
    let selectedCount = null;
    let productImagesContainer = null;
    let saveSortBtn = null;

    // Initialize
    function init() {
        // Get product ID from page
        const productIdElement = document.querySelector('[data-product-id]');
        currentProductId = productIdElement ? productIdElement.getAttribute('data-product-id') : null;

        if (!currentProductId) return;

        // Get DOM elements
        attachModal = new bootstrap.Modal(document.getElementById('attach-modal'));
        attachFromLibraryBtn = document.getElementById('attach-from-library-btn');
        librarySearch = document.getElementById('library-search');
        loadLibraryBtn = document.getElementById('load-library-btn');
        libraryLoading = document.getElementById('library-loading');
        libraryImages = document.getElementById('library-images');
        attachSelectedBtn = document.getElementById('attach-selected-btn');
        selectedCount = document.getElementById('selected-count');
        productImagesContainer = document.getElementById('product-images-container');
        saveSortBtn = document.getElementById('save-sort-btn');

        setupEventListeners();
    }

    // Setup event listeners
    function setupEventListeners() {
        // Attach from library button
        if (attachFromLibraryBtn) {
            attachFromLibraryBtn.addEventListener('click', openAttachModal);
        }

        // Library search
        if (loadLibraryBtn) {
            loadLibraryBtn.addEventListener('click', loadLibraryImages);
        }

        if (librarySearch) {
            librarySearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadLibraryImages();
                }
            });
        }

        // Attach selected images
        if (attachSelectedBtn) {
            attachSelectedBtn.addEventListener('click', attachSelectedImages);
        }

        // Save sort order
        if (saveSortBtn) {
            saveSortBtn.addEventListener('click', saveSortOrder);
        }

        // Dynamic event delegation
        if (productImagesContainer) {
            productImagesContainer.addEventListener('click', handleProductImageClick);
        }
    }

    // Open attach modal
    function openAttachModal() {
        selectedImages.clear();
        updateSelectedCount();

        // Load initial images
        loadLibraryImages();

        attachModal.show();
    }

    // Load library images via AJAX
    async function loadLibraryImages() {
        if (!libraryLoading || !libraryImages) return;

        libraryLoading.classList.remove('d-none');
        libraryImages.classList.add('d-none');

        const searchQuery = librarySearch ? librarySearch.value.trim() : '';

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/image-library/images?search=${encodeURIComponent(searchQuery)}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                renderLibraryImages(result.data);
            } else {
                throw new Error(result.message || 'Failed to load images');
            }

        } catch (error) {
            console.error('Error loading library images:', error);
            showErrorToast('خطا در بارگذاری تصاویر کتابخانه');
        } finally {
            libraryLoading.classList.add('d-none');
            libraryImages.classList.remove('d-none');
        }
    }

    // Render library images
    function renderLibraryImages(images) {
        if (!libraryImages) return;

        libraryImages.innerHTML = '';

        if (images.length === 0) {
            libraryImages.innerHTML = `
                <div class="text-center py-4">
                    <i class="ph-image display-4 text-muted mb-3"></i>
                    <h6 class="text-muted">هیچ تصویری یافت نشد</h6>
                </div>
            `;
            return;
        }

        const row = document.createElement('div');
        row.className = 'row g-3';

        images.forEach(image => {
            const col = document.createElement('div');
            col.className = 'col-xl-2 col-lg-3 col-md-4 col-sm-6';

            col.innerHTML = `
                <div class="card h-100 library-image-card ${selectedImages.has(image.id) ? 'selected' : ''}" data-image-id="${image.id}">
                    <div class="position-relative">
                        <img src="${image.avif_url || image.url}" alt="${image.filename}" class="card-img-top" style="height: 120px; object-fit: cover;">
                        <div class="position-absolute top-0 end-0 m-2">
                            <input type="checkbox" class="form-check-input image-checkbox" data-image-id="${image.id}" ${selectedImages.has(image.id) ? 'checked' : ''}>
                        </div>
                        ${image.is_main ? '<div class="position-absolute top-0 start-0 m-2"><span class="badge bg-success">اصلی</span></div>' : ''}
                    </div>
                    <div class="card-body p-2">
                        <div class="text-center">
                            <small class="text-muted" title="${image.filename}">${image.filename.length > 15 ? image.filename.substring(0, 15) + '...' : image.filename}</small>
                        </div>
                    </div>
                </div>
            `;

            row.appendChild(col);
        });

        libraryImages.appendChild(row);

        // Setup checkbox event listeners
        libraryImages.querySelectorAll('.image-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleImageSelection);
        });
    }

    // Handle image selection
    function handleImageSelection(e) {
        const checkbox = e.target;
        const imageId = parseInt(checkbox.getAttribute('data-image-id'));

        if (checkbox.checked) {
            selectedImages.add(imageId);
            checkbox.closest('.library-image-card').classList.add('selected');
        } else {
            selectedImages.delete(imageId);
            checkbox.closest('.library-image-card').classList.remove('selected');
        }

        updateSelectedCount();
    }

    // Update selected count
    function updateSelectedCount() {
        const count = selectedImages.size;
        if (selectedCount) {
            selectedCount.textContent = count;
        }
        if (attachSelectedBtn) {
            attachSelectedBtn.disabled = count === 0;
        }
    }

    // Attach selected images
    async function attachSelectedImages() {
        if (selectedImages.size === 0) return;

        attachSelectedBtn.disabled = true;
        attachSelectedBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال اختصاص...';

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/image-library/assign`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_ids: Array.from(selectedImages)
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('تصاویر با موفقیت اختصاص یافتند', 'success');
                attachModal.hide();

                // Reload product images
                reloadProductImages();
            } else {
                throw new Error(result.message || 'Failed to attach images');
            }

        } catch (error) {
            console.error('Error attaching images:', error);
            showErrorToast('خطا در اختصاص تصاویر');
        } finally {
            attachSelectedBtn.disabled = false;
            attachSelectedBtn.innerHTML = '<i class="ph-plus me-1"></i> اختصاص تصاویر انتخاب شده (<span id="selected-count">0</span>)';
        }
    }

    // Handle product image clicks
    function handleProductImageClick(e) {
        const target = e.target.closest('.set-main-btn, .detach-btn');
        if (!target) return;

        e.preventDefault();

        const imageId = target.getAttribute('data-image-id');

        if (target.classList.contains('set-main-btn')) {
            setMainImage(imageId);
        } else if (target.classList.contains('detach-btn')) {
            detachImage(imageId);
        }
    }

    // Set main image
    async function setMainImage(imageId) {
        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/image-library/set-main`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_id: imageId
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('تصویر اصلی تنظیم شد', 'success');
                reloadProductImages();
            } else {
                throw new Error(result.message || 'Failed to set main image');
            }

        } catch (error) {
            console.error('Error setting main image:', error);
            showErrorToast('خطا در تنظیم تصویر اصلی');
        }
    }

    // Detach image
    async function detachImage(imageId) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این تصویر را از محصول جدا کنید؟')) {
            return;
        }

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/image-library/detach`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_id: imageId
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('تصویر از محصول جدا شد', 'success');
                reloadProductImages();
            } else {
                throw new Error(result.message || 'Failed to detach image');
            }

        } catch (error) {
            console.error('Error detaching image:', error);
            showErrorToast('خطا در جدا کردن تصویر');
        }
    }

    // Save sort order
    async function saveSortOrder() {
        const sortInputs = productImagesContainer.querySelectorAll('.sort-input');
        const imageIds = [];
        const sorts = [];

        sortInputs.forEach(input => {
            const imageId = input.getAttribute('data-image-id');
            const sort = parseInt(input.value) || 0;
            imageIds.push(parseInt(imageId));
            sorts.push(sort);
        });

        if (imageIds.length === 0) return;

        saveSortBtn.disabled = true;
        saveSortBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال ذخیره...';

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/image-library/sort`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_ids: imageIds
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('ترتیب تصاویر ذخیره شد', 'success');
                reloadProductImages();
            } else {
                throw new Error(result.message || 'Failed to save sort order');
            }

        } catch (error) {
            console.error('Error saving sort order:', error);
            showErrorToast('خطا در ذخیره ترتیب');
        } finally {
            saveSortBtn.disabled = false;
            saveSortBtn.innerHTML = '<i class="ph-floppy-disk me-1"></i> ذخیره ترتیب';
        }
    }

    // Reload product images
    async function reloadProductImages() {
        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();

            // Extract the product images section
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newImagesContainer = doc.getElementById('product-images-container');

            if (newImagesContainer && productImagesContainer) {
                productImagesContainer.innerHTML = newImagesContainer.innerHTML;
            }

        } catch (error) {
            console.error('Error reloading product images:', error);
            // Fallback: reload page
            window.location.reload();
        }
    }

    // Utility functions
    function showToast(message, type = 'info') {
        if (window.showToastMessage) {
            window.showToastMessage(type, '', message);
        } else if (window.showToast) {
            window.showToast(message, type);
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
