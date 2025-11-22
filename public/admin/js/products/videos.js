// Video Management for Products
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    let currentProductId = window.RMS?.productId;
    let currentPage = 1;
    let selectedVideos = new Set();

    console.log('Video Management JS loaded', {
        productId: currentProductId,
        button: document.getElementById('select-from-library-btn'),
        modal: document.getElementById('video-library-modal')
    });

    // Helper function for toast messages
    function showToast(message, type = 'info') {
        if (window.showToastMessage) {
            window.showToastMessage(type, '', message);
        } else if (window.showToast) {
            window.showToast(message, type);
        } else {
            alert(message);
        }
    }

    function showError(message) {
        showToast(message, 'danger');
    }

    // Select from library button
    const selectBtn = document.getElementById('select-from-library-btn');
    if (selectBtn) {
        selectBtn.addEventListener('click', function() {
            console.log('Select button clicked');
            const modal = new bootstrap.Modal(document.getElementById('video-library-modal'));
            modal.show();
            loadLibraryVideos();
        });
    } else {
        console.error('Select from library button not found!');
    }

    // Search videos
    document.getElementById('search-videos-btn')?.addEventListener('click', function() {
        currentPage = 1;
        selectedVideos.clear();
        loadLibraryVideos();
    });

    // Load videos from library
    function loadLibraryVideos() {
        const search = document.getElementById('video-library-search').value;
        const loading = document.getElementById('video-library-loading');
        const grid = document.getElementById('video-library-grid');

        loading?.classList.remove('d-none');
        grid.innerHTML = '';

        fetch(window.RMS.videos.library + '?search=' + encodeURIComponent(search) + '&page=' + currentPage)
            .then(r => r.json())
            .then(data => {
                loading?.classList.add('d-none');
                renderVideos(data.data || []);
            })
            .catch(err => {
                loading?.classList.add('d-none');
                console.error(err);
                showError('خطا در بارگذاری ویدیوها');
            });
    }

    // Render videos
    function renderVideos(videos) {
        const grid = document.getElementById('video-library-grid');
        
        videos.forEach(video => {
            const col = document.createElement('div');
            col.className = 'col-md-3';
            
            const posterHtml = video.poster_url ? 
                `<img src="${video.poster_url}" class="card-img-top" style="height: 120px; object-fit: cover;">` : 
                `<div class="bg-light d-flex align-items-center justify-content-center" style="height: 120px;">
                    <i class="ph-video-camera" style="font-size: 48px;"></i>
                </div>`;
            
            col.innerHTML = `
                <div class="card video-select-card" data-video-id="${video.id}">
                    <div class="position-relative">
                        ${posterHtml}
                        <div class="form-check position-absolute top-0 end-0 m-2">
                            <input class="form-check-input video-checkbox" type="checkbox" value="${video.id}">
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <small class="text-truncate d-block" title="${video.title || video.filename}">${video.title || video.filename}</small>
                    </div>
                </div>
            `;
            grid.appendChild(col);
        });

        // Attach checkbox listeners
        document.querySelectorAll('.video-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    selectedVideos.add(parseInt(this.value));
                } else {
                    selectedVideos.delete(parseInt(this.value));
                }
                updateSelectedCount();
            });
        });
    }

    // Update selected count
    function updateSelectedCount() {
        const count = selectedVideos.size;
        document.getElementById('selected-videos-count').textContent = count;
        document.getElementById('assign-selected-videos-btn').disabled = count === 0;
    }

    // Assign selected videos
    document.getElementById('assign-selected-videos-btn')?.addEventListener('click', function() {
        if (selectedVideos.size === 0) return;

        fetch(window.RMS.videos.list.replace('/list', '/assign'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({ video_ids: Array.from(selectedVideos) })
        })
        .then(r => r.json())
        .then(data => {
            showToast('ویدیوها با موفقیت اختصاص یافتند', 'success');
            bootstrap.Modal.getInstance(document.getElementById('video-library-modal'))?.hide();
            // Reload and return to videos tab
            window.location.hash = '#videos';
            location.reload();
        })
        .catch(err => {
            console.error(err);
            showError('خطا در اختصاص ویدیوها');
        });
    });

    // Set main video (using event delegation)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.set-main-video-btn')) {
            e.preventDefault();
            const btn = e.target.closest('.set-main-video-btn');
            const videoId = btn.dataset.videoId;
            
            fetch(window.RMS.videos['set-main'], {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ video_id: parseInt(videoId) })
            })
            .then(r => r.json())
            .then(data => {
                showToast('ویدیو اصلی تنظیم شد', 'success');
                // Reload and return to videos tab
                window.location.hash = '#videos';
                location.reload();
            })
            .catch(err => {
                console.error(err);
                showError('خطا در تنظیم ویدیو اصلی');
            });
        }
    });

    // Detach video (using event delegation)
    document.addEventListener('click', async function(e) {
        if (e.target.closest('.detach-video-btn')) {
            e.preventDefault();
            const btn = e.target.closest('.detach-video-btn');
            const videoId = btn.dataset.videoId;
            
            let confirmed = false;
            if (typeof window.confirmAction === 'function') {
                confirmed = await window.confirmAction(
                    'جدا کردن ویدیو',
                    'آیا مطمئن هستید که می‌خواهید این ویدیو را از محصول جدا کنید؟',
                    {
                        icon: 'ph-warning',
                        confirmClass: 'btn-danger',
                        confirmText: 'بله، جدا کن'
                    }
                );
            } else {
                confirmed = confirm('آیا مطمئن هستید؟');
            }
            
            if (confirmed) {
                performDetach(videoId);
            }
        }
    });

    function performDetach(videoId) {
        fetch(window.RMS.videos.detach, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({ video_ids: [parseInt(videoId)] })
        })
        .then(r => r.json())
        .then(data => {
            showToast('ویدیو جدا شد', 'success');
            // Reload and return to videos tab
            window.location.hash = '#videos';
            location.reload();
        })
        .catch(err => {
            console.error(err);
            showError('خطا در جدا کردن ویدیو');
        });
    }
});
