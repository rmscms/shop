// RMS Video Library JS
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const config = window.RMS?.VideoLibraryConfig || {};
    const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB chunks
    let selectedFile = null;

    const uploadBtn = document.getElementById('upload-video-btn');
    const uploadInput = document.getElementById('upload-video-input');

    // Upload button click
    if (uploadBtn && uploadInput) {
        uploadBtn.addEventListener('click', function() {
            uploadInput.click();
        });

        uploadInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length === 0) return;

            selectedFile = files[0];
            showNameModal();
            uploadInput.value = '';
        });
    }

    // Show modal to get video name
    function showNameModal() {
        const modalHtml = `
            <div class="modal fade" id="video-name-modal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">نام ویدیو</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">نام فایل: <strong>${selectedFile.name}</strong></label>
                            </div>
                            <div class="mb-3">
                                <label for="video-custom-name" class="form-label">نام دلخواه (اختیاری):</label>
                                <input type="text" class="form-control" id="video-custom-name" 
                                       placeholder="نام ویدیو را وارد کنید">
                                <small class="text-muted">اگر خالی بگذارید، نام فایل استفاده می‌شود</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="button" class="btn btn-primary" id="start-upload-btn">
                                <i class="ph-upload me-1"></i>
                                شروع آپلود
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const existingModal = document.getElementById('video-name-modal');
        if (existingModal) existingModal.remove();

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('video-name-modal'));
        modal.show();

        document.getElementById('start-upload-btn').addEventListener('click', function() {
            const customName = document.getElementById('video-custom-name').value.trim();
            modal.hide();
            uploadVideoWithChunks(selectedFile, customName);
        });
    }

    // Upload video with chunks and progress
    function uploadVideoWithChunks(file, customName) {
        const progressHtml = `
            <div class="modal fade" id="upload-progress-modal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">آپلود ویدیو</h5>
                        </div>
                        <div class="modal-body">
                            <p>در حال آپلود: <strong>${customName || file.name}</strong></p>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                     role="progressbar" 
                                     id="upload-progress-bar"
                                     style="width: 0%">0%</div>
                            </div>
                            <p class="mt-2 text-muted text-center" id="upload-status">در حال آماده‌سازی...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const existingModal = document.getElementById('upload-progress-modal');
        if (existingModal) existingModal.remove();

        document.body.insertAdjacentHTML('beforeend', progressHtml);
        const modal = new bootstrap.Modal(document.getElementById('upload-progress-modal'));
        modal.show();

        const progressBar = document.getElementById('upload-progress-bar');
        const statusText = document.getElementById('upload-status');

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        let currentChunk = 0;
        const uploadId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        const finalFilename = customName ? (customName + '.' + file.name.split('.').pop()) : file.name;

        function uploadChunk() {
            const start = currentChunk * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const formData = new FormData();
            formData.append('chunk', chunk);
            formData.append('chunk_index', currentChunk);
            formData.append('total_chunks', totalChunks);
            formData.append('upload_id', uploadId);
            formData.append('filename', finalFilename);
            formData.append('custom_name', customName || '');
            formData.append('_token', config.csrf);

            fetch(config.routes.upload, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': config.csrf
                }
            })
            .then(response => response.json())
            .then(data => {
                currentChunk++;
                const progress = Math.round((currentChunk / totalChunks) * 100);
                progressBar.style.width = progress + '%';
                progressBar.textContent = progress + '%';
                statusText.textContent = `آپلود قطعه ${currentChunk} از ${totalChunks}`;

                if (currentChunk < totalChunks) {
                    uploadChunk();
                } else {
                    statusText.textContent = 'آپلود کامل شد! در حال پردازش...';
                    setTimeout(() => {
                        modal.hide();
                        if (window.showToastMessage) {
                            window.showToastMessage('success', 'موفق', 'ویدیو آپلود شد و در صف پردازش قرار گرفت', 3000);
                        }
                        setTimeout(() => window.location.reload(), 1500);
                    }, 1000);
                }
            })
            .catch(error => {
                console.error('❌ Upload error:', error);
                modal.hide();
                if (window.showToastMessage) {
                    window.showToastMessage('danger', 'خطا', 'خطا در آپلود ویدیو', 3000);
                }
            });
        }

        uploadChunk();
    }

    // Delete video buttons
    document.querySelectorAll('.delete-video-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const videoId = this.dataset.videoId;

            let confirmed = false;
            if (typeof window.confirmAction === 'function') {
                confirmed = await window.confirmAction(
                    'حذف ویدیو',
                    'آیا از حذف این ویدیو مطمئن هستید؟',
                    {
                        icon: 'ph-warning',
                        confirmClass: 'btn-danger',
                        confirmText: 'بله، حذف شود',
                        cancelText: 'انصراف'
                    }
                );
            } else if (confirm('آیا از حذف این ویدیو مطمئن هستید؟')) {
                confirmed = true;
            }
            
            if (confirmed) {
                deleteVideo(videoId);
            }
        });
    });

    // Play video buttons
    document.querySelectorAll('.play-video-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const hlsUrl = this.dataset.hlsUrl;
            const posterUrl = this.dataset.posterUrl;
            const title = this.dataset.title;

            playVideo(hlsUrl, posterUrl, title);
        });
    });

    function playVideo(hlsUrl, posterUrl, title) {
        const modal = new bootstrap.Modal(document.getElementById('video-player-modal'));
        const video = document.getElementById('video-player');
        const modalTitle = document.getElementById('video-player-title');

        modalTitle.textContent = title || 'پخش ویدیو';
        video.poster = posterUrl || '';

        // Check if HLS.js is supported and URL is HLS
        if (hlsUrl && hlsUrl.includes('.m3u8')) {
            if (window.Hls && Hls.isSupported()) {
                const hls = new Hls();
                hls.loadSource(hlsUrl);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play().catch(e => {
                        console.error('Play error:', e);
                        if (window.showToastMessage) {
                            window.showToastMessage('danger', 'خطا', 'خطا در پخش ویدیو', 2000);
                        }
                    });
                });
                hls.on(Hls.Events.ERROR, function(event, data) {
                    console.error('HLS Error:', data);
                    if (data.fatal) {
                        if (window.showToastMessage) {
                            window.showToastMessage('danger', 'خطا', 'خطا در بارگذاری ویدیو', 2000);
                        }
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Native HLS support (Safari)
                video.src = hlsUrl;
                video.addEventListener('loadedmetadata', function() {
                    video.play();
                });
            } else {
                if (window.showToastMessage) {
                    window.showToastMessage('warning', 'هشدار', 'مرورگر شما از پخش HLS پشتیبانی نمی‌کند', 3000);
                }
            }
        } else {
            // Fallback to direct video
            video.src = hlsUrl;
            video.play();
        }

        modal.show();

        // Clean up on modal close
        document.getElementById('video-player-modal').addEventListener('hidden.bs.modal', function() {
            video.pause();
            video.src = '';
            if (window.Hls && typeof hls !== 'undefined' && hls) {
                hls.destroy();
            }
        }, { once: true });
    }

    function deleteVideo(videoId) {
        const url = config.routes.destroy.replace('__ID__', videoId);

        fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrf
            }
        })
        .then(response => response.json())
        .then(data => {
            if (window.showToastMessage) {
                window.showToastMessage('success', 'حذف شد', data.message || 'ویدیو با موفقیت حذف شد', 2000);
            }
            const videoCard = document.querySelector(`[data-video-id="${videoId}"]`);
            if (videoCard) videoCard.remove();
        })
        .catch(error => {
            console.error('❌ Delete error:', error);
            if (window.showToastMessage) {
                window.showToastMessage('danger', 'خطا', 'خطا در حذف ویدیو', 3000);
            }
        });
    }
});
