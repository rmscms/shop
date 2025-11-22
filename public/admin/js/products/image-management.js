// git-trigger
// Product Image Management for Image Library Integration
(function() {
    'use strict';

    let selectedImages = new Set();
    let currentProductId = null;
    let currentPage = 1;
    let hasMorePages = false;
    let isLoading = false;

    // DOM elements
    let attachModal = null;
    let combinationsModal = null;
    let attachFromLibraryBtn = null;
    let librarySearch = null;
    let loadLibraryBtn = null;
    let loadMoreBtn = null;
    let libraryLoading = null;
    let libraryImages = null;
    let attachSelectedBtn = null;
    let saveCombinationsBtn = null;
    let detachAllCombinationsBtn = null;
    let selectedCount = null;
    let productImagesContainer = null;
    let saveSortBtn = null;
    let currentImageId = null;

    // Initialize
    function init() {
        // Get product ID from window.RMS or from data attribute
        currentProductId = window.RMS?.productId || null;

        if (!currentProductId) {
            console.warn('⚠️ Product ID not found');
            return;
        }

        console.log('📦 Image Management initialized with product ID:', currentProductId);

        // Get DOM elements
        attachModal = new bootstrap.Modal(document.getElementById('attach-modal'));
        combinationsModal = new bootstrap.Modal(document.getElementById('combinations-modal'));
        attachFromLibraryBtn = document.getElementById('attach-from-library-btn');
        librarySearch = document.getElementById('library-search');
        loadLibraryBtn = document.getElementById('load-library-btn');
        loadMoreBtn = document.getElementById('load-more-btn');
        libraryLoading = document.getElementById('library-loading');
        libraryImages = document.getElementById('library-images');
        attachSelectedBtn = document.getElementById('attach-selected-btn');
        saveCombinationsBtn = document.getElementById('save-combinations-btn');
        detachAllCombinationsBtn = document.getElementById('detach-all-combinations-btn');
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
            loadLibraryBtn.addEventListener('click', function() {
                currentPage = 1;
                loadLibraryImages(false);
            });
        }

        if (librarySearch) {
            librarySearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    currentPage = 1;
                    loadLibraryImages(false);
                }
            });
        }

        // Attach selected images
        if (attachSelectedBtn) {
            attachSelectedBtn.addEventListener('click', attachSelectedImages);
        }

        // Load more button
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', loadMoreImages);
        }

        // Save combinations button
        if (saveCombinationsBtn) {
            saveCombinationsBtn.addEventListener('click', saveCombinationsAssignment);
        }

        // Detach all combinations button
        if (detachAllCombinationsBtn) {
            detachAllCombinationsBtn.addEventListener('click', detachAllCombinations);
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
        currentPage = 1;
        hasMorePages = false;

        // Load initial images
        loadLibraryImages(false);

        attachModal.show();
    }

    // Load library images via AJAX
    async function loadLibraryImages(append = false) {
        if (isLoading) return;
        
        console.log('🔄 loadLibraryImages called, append:', append, 'page:', currentPage);
        console.log('📦 libraryLoading:', libraryLoading);
        console.log('📦 libraryImages:', libraryImages);
        
        if (!libraryLoading || !libraryImages) {
            console.error('❌ Required elements not found!');
            return;
        }

        isLoading = true;
        libraryLoading.classList.remove('d-none');
        
        if (!append) {
            libraryImages.classList.add('d-none');
        }

        const searchQuery = librarySearch ? librarySearch.value.trim() : '';

        console.log('🔍 Search query:', searchQuery);
        console.log('🌐 Fetching:', `/admin/shop/products/${currentProductId}/image-library/images?search=${encodeURIComponent(searchQuery)}&page=${currentPage}`);

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/image-library/images?search=${encodeURIComponent(searchQuery)}&page=${currentPage}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                console.error('❌ HTTP Error:', response.status);
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            console.log('✅ Response received:', result);
            console.log('📊 Images count:', result.data?.length || 0);
            console.log('📄 Pagination:', result.pagination);

            if (result.success) {
                hasMorePages = result.pagination?.has_more || false;
                renderLibraryImages(result.data, append);
                updateLoadMoreButton();
            } else {
                throw new Error(result.message || 'Failed to load images');
            }

        } catch (error) {
            console.error('Error loading library images:', error);
            showErrorToast('خطا در بارگذاری تصاویر کتابخانه');
        } finally {
            isLoading = false;
            libraryLoading.classList.add('d-none');
            libraryImages.classList.remove('d-none');
        }
    }

    // Load more images
    function loadMoreImages() {
        if (!hasMorePages || isLoading) return;
        currentPage++;
        loadLibraryImages(true);
    }

    // Update load more button visibility
    function updateLoadMoreButton() {
        if (loadMoreBtn) {
            if (hasMorePages) {
                loadMoreBtn.classList.remove('d-none');
            } else {
                loadMoreBtn.classList.add('d-none');
            }
        }
    }

    // Render library images
    function renderLibraryImages(images, append = false) {
        if (!libraryImages) return;

        if (!append) {
            libraryImages.innerHTML = '';
        }

        if (images.length === 0 && !append) {
            libraryImages.innerHTML = `
                <div class="text-center py-4">
                    <i class="ph-image display-4 text-muted mb-3"></i>
                    <h6 class="text-muted">هیچ تصویری یافت نشد</h6>
                </div>
            `;
            return;
        }

        let row = append && libraryImages.querySelector('.row')
            ? libraryImages.querySelector('.row')
            : null;

        if (!row) {
            row = document.createElement('div');
            row.className = 'row g-3';
            libraryImages.appendChild(row);
        }

        images.forEach(image => {
            const col = document.createElement('div');
            col.className = 'col-xl-2 col-lg-3 col-md-4 col-sm-6';

            const isAssignedBadge = image.is_assigned 
                ? '<div class="position-absolute top-0 start-0 m-2"><span class="badge bg-info">اختصاص یافته</span></div>' 
                : '';
            
            const assignmentsBadge = image.assignments_count > 0
                ? `<div class="position-absolute bottom-0 start-0 m-2"><span class="badge bg-secondary">${image.assignments_count} استفاده</span></div>`
                : '<div class="position-absolute bottom-0 start-0 m-2"><span class="badge bg-success">جدید</span></div>';

            col.innerHTML = `
                <div class="card h-100 library-image-card ${selectedImages.has(image.id) ? 'selected' : ''}" data-image-id="${image.id}">
                    <div class="position-relative">
                        <img src="${image.avif_url || image.url}" alt="${image.filename}" class="card-img-top" style="height: 120px; object-fit: cover;">
                        <div class="position-absolute top-0 end-0 m-2">
                            <input type="checkbox" class="form-check-input image-checkbox" data-image-id="${image.id}" ${selectedImages.has(image.id) ? 'checked' : ''}>
                        </div>
                        ${isAssignedBadge}
                        ${assignmentsBadge}
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

        // Setup checkbox event listeners for new images
        libraryImages.querySelectorAll('.image-checkbox:not([data-listener])').forEach(checkbox => {
            checkbox.setAttribute('data-listener', 'true');
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
        const target = e.target.closest('.set-main-btn, .detach-btn, .assign-combinations-btn');
        if (!target) return;

        e.preventDefault();

        const imageId = target.getAttribute('data-image-id');

        if (target.classList.contains('set-main-btn')) {
            setMainImage(imageId);
        } else if (target.classList.contains('detach-btn')) {
            detachImage(imageId);
        } else if (target.classList.contains('assign-combinations-btn')) {
            openCombinationsModal(imageId);
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
        // Use confirmAction if available
        if (typeof window.confirmAction === 'function') {
            const confirmed = await window.confirmAction(
                'جدا کردن تصویر از محصول',
                'آیا مطمئن هستید که می‌خواهید این تصویر را از محصول جدا کنید؟',
                {
                    icon: 'ph-warning',
                    confirmClass: 'btn-danger',
                    confirmText: 'بله، جدا شود',
                    cancelText: 'انصراف'
                }
            );
            
            if (!confirmed) {
                return;
            }
        } else if (!confirm('آیا مطمئن هستید که می‌خواهید این تصویر را از محصول جدا کنید؟')) {
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

    // Open combinations modal
    function openCombinationsModal(imageId) {
        currentImageId = imageId;
        
        // Get image data from window.RMS
        const combinations = window.RMS?.data?.combinations || [];
        
        if (combinations.length === 0) {
            showErrorToast('هیچ ترکیبی برای این محصول وجود ندارد');
            return;
        }

        // Find image element to get its src
        const imageCard = document.querySelector(`[data-image-id="${imageId}"]`);
        const imageSrc = imageCard ? imageCard.querySelector('img').src : '';
        
        // Set image in modal
        const modalImage = document.getElementById('combination-modal-image');
        if (modalImage) {
            modalImage.src = imageSrc;
        }

        // Get currently assigned combinations for this image
        getAssignedCombinations(imageId).then(assignedIds => {
            renderCombinationsList(combinations, assignedIds);
            
            // Show modal AFTER rendering with a small delay
            setTimeout(() => {
                combinationsModal.show();
            }, 100);
        });
    }

    // Get assigned combinations for an image
    async function getAssignedCombinations(imageId) {
        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/images/${imageId}/assigned-combinations`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                return result.data || [];
            }
            
            return [];
        } catch (error) {
            console.error('Error fetching assigned combinations:', error);
            return [];
        }
    }

    // Render combinations list
    function renderCombinationsList(combinations, assignedIds = []) {
        const container = document.getElementById('modal-combinations-list');
        if (!container) {
            console.error('Container not found!');
            return;
        }

        container.innerHTML = '';

        combinations.forEach(combo => {
            // Build combination label
            const attributes = window.RMS?.data?.attributes || [];
            let label = '';
            
            if (combo.attribute_value_ids && Array.isArray(combo.attribute_value_ids)) {
                const labels = [];
                combo.attribute_value_ids.forEach(valueId => {
                    attributes.forEach(attr => {
                        const value = attr.values?.find(v => v.id === valueId);
                        if (value) {
                            labels.push(`${attr.name}: ${value.value}`);
                        }
                    });
                });
                label = labels.join(' / ');
            }

            const isChecked = assignedIds.includes(combo.id);
            
            const checkboxHtml = `
                <div class="form-check mb-2">
                    <input class="form-check-input combination-checkbox" 
                           type="checkbox" 
                           value="${combo.id}" 
                           id="combo-${combo.id}"
                           ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label" for="combo-${combo.id}">
                        ${label || `ترکیب #${combo.id}`}
                        <small class="text-muted">(موجودی: ${combo.stock || 0})</small>
                    </label>
                </div>
            `;
            
            container.innerHTML += checkboxHtml;
        });
    }

    // Save combinations assignment
    async function saveCombinationsAssignment() {
        if (!currentImageId) return;

        const checkboxes = document.querySelectorAll('.combination-checkbox:checked');
        const combinationIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        if (combinationIds.length === 0) {
            showErrorToast('لطفاً حداقل یک ترکیب انتخاب کنید');
            return;
        }

        saveCombinationsBtn.disabled = true;
        saveCombinationsBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال ذخیره...';

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/images/${currentImageId}/assign-combinations`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    combination_ids: combinationIds
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message || 'تصویر به ترکیب‌ها اختصاص یافت', 'success');
                combinationsModal.hide();
            } else {
                throw new Error(result.message || 'Failed to assign to combinations');
            }

        } catch (error) {
            console.error('Error assigning to combinations:', error);
            showErrorToast('خطا در اختصاص به ترکیب‌ها');
        } finally {
            saveCombinationsBtn.disabled = false;
            saveCombinationsBtn.innerHTML = '<i class="ph-floppy-disk me-1"></i> ذخیره';
        }
    }

    // Detach from all combinations
    async function detachAllCombinations() {
        if (!currentImageId) return;

        // Use confirmAction if available
        let confirmed = false;
        if (typeof window.confirmAction === 'function') {
            confirmed = await window.confirmAction(
                'حذف از همه ترکیبات',
                'آیا از حذف این تصویر از همه ترکیبات اطمینان دارید؟',
                {
                    icon: 'ph-warning',
                    confirmClass: 'btn-danger',
                    confirmText: 'بله، حذف شود',
                    cancelText: 'انصراف'
                }
            );
        } else {
            confirmed = confirm('آیا از حذف این تصویر از همه ترکیبات اطمینان دارید؟');
        }

        if (!confirmed) return;

        detachAllCombinationsBtn.disabled = true;
        detachAllCombinationsBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> در حال حذف...';

        try {
            const response = await fetch(`/admin/shop/products/${currentProductId}/images/${currentImageId}/detach-combinations`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message || 'تصویر از همه ترکیبات جدا شد', 'success');
                combinationsModal.hide();
                
                // Uncheck all checkboxes
                document.querySelectorAll('.combination-checkbox').forEach(cb => cb.checked = false);
            } else {
                throw new Error(result.message || 'Failed to detach from combinations');
            }

        } catch (error) {
            console.error('Error detaching from combinations:', error);
            showErrorToast('خطا در جداسازی از ترکیب‌ها');
        } finally {
            detachAllCombinationsBtn.disabled = false;
            detachAllCombinationsBtn.innerHTML = '<i class="ph-x-circle me-1"></i> حذف از همه ترکیبات';
        }
    }

    // Utility functions (keep existing ones)
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
