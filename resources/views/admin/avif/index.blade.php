@extends('cms::admin.layout.index')

@section('title', 'مدیریت تصاویر AVIF')

@section('content')
<div class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">
                        <i class="ph ph-images me-2 text-primary"></i>
                        آمار کلی فایل‌های AVIF
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <div class="card bg-primary text-white shadow-sm">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0">{{ number_format($stats['total_images'] ?? 0) }}</h4>
                                        <small>کل تصاویر</small>
                                    </div>
                                    <i class="ph ph-image ph-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="card bg-success text-white shadow-sm">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0">{{ number_format($stats['total_avif'] ?? 0) }}</h4>
                                        <small>فایل‌های AVIF</small>
                                    </div>
                                    <i class="ph ph-check-circle ph-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="card bg-warning text-white shadow-sm">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0">{{ number_format($stats['missing_avif'] ?? 0) }}</h4>
                                        <small>فاقد AVIF</small>
                                    </div>
                                    <i class="ph ph-warning ph-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="card bg-info text-white shadow-sm">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0">{{ $stats['conversion_rate'] ?? 0 }}%</h4>
                                        <small>درصد تبدیل</small>
                                    </div>
                                    <i class="ph ph-percent ph-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">
                        <i class="ph ph-folder-simple-plus me-2 text-info"></i>
                        افزودن پوشه جدید
                    </h5>
                </div>
                <div class="card-body">
                    <form id="addDirectoryForm" class="row g-3 align-items-end">
                        <div class="col-12 col-md-6">
                            <label class="form-label">مسیر (نسبت به public یا storage)</label>
                            <input type="text" class="form-control" id="directoryPath" placeholder="uploads/custom-folder" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">نوع مسیر</label>
                            <select class="form-select" id="directoryType">
                                <option value="public">public_path()</option>
                                <option value="storage">storage/app/public</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 d-grid">
                            <button class="btn btn-primary" type="submit">
                                <i class="ph ph-plus-circle me-1"></i>
                                افزودن پوشه
                            </button>
                        </div>
                    </form>
                    <div class="alert alert-light border mt-3 mb-0">
                        مسیرها بدون اسلش ابتدایی/انتهایی ثبت می‌شوند (مثال: <code>uploads/banners</code>). برای پوشه‌های داخل
                        <span class="fw-semibold">storage/app/public</span> نوع «storage» را انتخاب کنید.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph ph-magic-wand me-2 text-success"></i>
                        عملیات AVIF
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <button class="btn btn-success btn-lg w-100 d-flex align-items-center justify-content-center gap-2"
                                    id="regenerateAllBtn">
                                <i class="ph ph-magic-wand" id="regenerateIcon"></i>
                                <i class="ph ph-spinner ph-spin d-none" id="regenerateSpinner"></i>
                                بازسازی همه فایل‌ها
                            </button>
                            <small class="text-muted d-block mt-2">تبدیل تمام تصاویر پشتیبانی‌شده به AVIF</small>
                        </div>
                        <div class="col-12 col-md-4">
                            <button class="btn btn-warning btn-lg w-100 d-flex align-items-center justify-content-center gap-2"
                                    id="cleanAllBtn">
                                <i class="ph ph-broom" id="cleanIcon"></i>
                                <i class="ph ph-spinner ph-spin d-none" id="cleanSpinner"></i>
                                حذف همه AVIF
                            </button>
                            <small class="text-muted d-block mt-2">پاک‌سازی نسخه‌های AVIF برای بازسازی مجدد</small>
                        </div>
                        <div class="col-12 col-md-4">
                            <button class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2"
                                    id="refreshStatsBtn">
                                <i class="ph ph-chart-bar" id="refreshIcon"></i>
                                <i class="ph ph-spinner ph-spin d-none" id="refreshSpinner"></i>
                                به‌روزرسانی آمار
                            </button>
                            <small class="text-muted d-block mt-2">دریافت آخرین وضعیت تبدیل</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ph ph-folders me-2 text-warning"></i>
                        جزئیات پوشه‌ها
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>نام پوشه</th>
                                    <th>کل تصاویر</th>
                                    <th>فایل‌های AVIF</th>
                                    <th>فاقد AVIF</th>
                                    <th>درصد تبدیل</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($stats['directories'] ?? []) as $dir => $dirStats)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <code>{{ $dir }}</code>
                                                <div class="mt-1 d-flex flex-wrap gap-1">
                                                    <span class="badge bg-light text-body border">{{ ($dirStats['type'] ?? 'public') === 'storage' ? 'storage' : 'public' }}</span>
                                                    @if(!empty($dirStats['is_default']))
                                                        <span class="badge bg-secondary">پیش‌فرض</span>
                                                    @endif
                                                    @if(empty($dirStats['active']))
                                                        <span class="badge bg-dark">غیرفعال</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary">{{ $dirStats['total_images'] ?? 0 }}</span></td>
                                        <td><span class="badge bg-success">{{ $dirStats['avif_files'] ?? 0 }}</span></td>
                                        <td><span class="badge bg-warning text-dark">{{ $dirStats['missing_avif'] ?? 0 }}</span></td>
                                        <td style="min-width: 160px;">
                                            <div class="progress" style="height: 22px;">
                                                @php($rate = (float)($dirStats['conversion_rate'] ?? 0))
                                                @php($barClass = $rate >= 80 ? 'bg-success' : ($rate >= 50 ? 'bg-warning text-dark' : 'bg-danger'))
                                                <div class="progress-bar {{ $barClass }}" style="width: {{ $rate }}%;">
                                                    {{ $rate }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if(($dirStats['exists'] ?? true) === false)
                                                <span class="badge bg-danger">وجود ندارد</span>
                                            @elseif(($dirStats['total_images'] ?? 0) === 0)
                                                <span class="badge bg-secondary">خالی</span>
                                            @elseif(empty($dirStats['active']))
                                                <span class="badge bg-dark">غیرفعال</span>
                                            @elseif(($dirStats['conversion_rate'] ?? 0) == 100)
                                                <span class="badge bg-success">کامل</span>
                                            @elseif(($dirStats['conversion_rate'] ?? 0) > 0)
                                                <span class="badge bg-warning text-dark">جزئی</span>
                                            @else
                                                <span class="badge bg-danger">فاقد AVIF</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group gap-1 flex-wrap">
                                                @if(!empty($dirStats['id']))
                                                    <button type="button"
                                                            class="btn btn-sm {{ !empty($dirStats['active']) ? 'btn-outline-secondary' : 'btn-outline-success' }} directory-toggle-btn"
                                                            data-id="{{ $dirStats['id'] }}"
                                                            data-active="{{ !empty($dirStats['active']) ? '1' : '0' }}"
                                                            title="{{ !empty($dirStats['active']) ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                                        <i class="ph {{ !empty($dirStats['active']) ? 'ph-pause' : 'ph-play' }}"></i>
                                                    </button>
                                                    @if(empty($dirStats['is_default']))
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-danger directory-delete-btn"
                                                                data-id="{{ $dirStats['id'] }}"
                                                                title="حذف پوشه">
                                                            <i class="ph ph-trash"></i>
                                                        </button>
                                                    @endif
                                                @endif

                                                @if(($dirStats['exists'] ?? false) && ($dirStats['total_images'] ?? 0) > 0)
                                                    <button class="btn btn-sm btn-outline-success convert-dir-btn"
                                                            data-directory="{{ $dir }}"
                                                            data-bs-toggle="tooltip"
                                                            title="تبدیل این پوشه">
                                                        <i class="ph ph-arrows-clockwise"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="progressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">در حال پردازش...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%;">
                        0%
                    </div>
                </div>
                <div id="progressMessage">شروع پردازش...</div>
                <div id="progressDetails" class="mt-2 small text-muted"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const config = {
        routes: @json($routes ?? []),
        csrf: '{{ csrf_token() }}',
    };

    const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
    const progressBar = document.getElementById('progressBar');
    const progressMessage = document.getElementById('progressMessage');
    const progressDetails = document.getElementById('progressDetails');

    const actionButtons = [
        {btn: 'regenerateAllBtn', spinner: 'regenerateSpinner', icon: 'regenerateIcon', url: config.routes.regenerateAll, confirmTitle: 'بازسازی AVIF', confirmText: 'بازسازی'},
        {btn: 'cleanAllBtn', spinner: 'cleanSpinner', icon: 'cleanIcon', url: config.routes.cleanAll, confirmTitle: 'حذف AVIF', confirmText: 'حذف'},
    ];

    const addForm = document.getElementById('addDirectoryForm');
    const pathInput = document.getElementById('directoryPath');
    const typeSelect = document.getElementById('directoryType');

    const notify = (type, message) => {
        if (typeof window.showToastMessage === 'function') {
            window.showToastMessage(type, '', message);
        } else {
            alert(message);
        }
    };

    const buildRoute = (template, id) => {
        if (!template || !id) {
            return '';
        }
        return template.replace('__ID__', id);
    };

    actionButtons.forEach(({btn, spinner, icon, url, confirmTitle, confirmText}) => {
        const buttonEl = document.getElementById(btn);
        if (!buttonEl) return;

        buttonEl.addEventListener('click', async () => {
            if (!url) return;

            let ok = true;
            if (typeof confirmAction === 'function') {
                ok = await confirmAction(confirmTitle, 'آیا مطمئن هستید؟ این عملیات ممکن است زمان‌بر باشد.', {
                    icon: 'ph-magic-wand',
                    confirmClass: 'btn-success',
                    confirmText,
                });
            } else {
                ok = window.confirm('آیا مطمئن هستید؟ این عملیات ممکن است زمان‌بر باشد.');
            }

            if (ok) {
                performAction(url, btn, spinner, icon);
            }
        });
    });

    const refreshBtn = document.getElementById('refreshStatsBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => window.location.reload());
    }

    if (addForm) {
        addForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!config.routes.directoryStore) return;

            const path = pathInput.value.trim();
            const type = typeSelect.value;

            if (!path) {
                notify('error', 'مسیر را وارد کنید.');
                return;
            }

            try {
                const response = await fetch(config.routes.directoryStore, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrf,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ path, type }),
                });
                const data = await response.json();
                if (data?.success) {
                    notify('success', data.message || 'پوشه ثبت شد.');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    notify('error', data?.message || 'خطا در ثبت پوشه.');
                }
            } catch (e) {
                notify('error', 'خطا در ارتباط با سرور.');
            }
        });
    }

    document.querySelectorAll('.convert-dir-btn').forEach(button => {
        button.addEventListener('click', async () => {
            if (!config.routes.regenerateDirectory) return;
            const directory = button.dataset.directory;
            let ok = true;

            if (typeof confirmAction === 'function') {
                ok = await confirmAction('تبدیل پوشه', `آیا می‌خواهید پوشه ${directory} را بازسازی کنید؟`, {
                    icon: 'ph-folders',
                    confirmClass: 'btn-success',
                    confirmText: 'تایید',
                });
            } else {
                ok = window.confirm(`آیا می‌خواهید پوشه ${directory} را بازسازی کنید؟`);
            }

            if (ok) {
                performDirectoryAction(directory);
            }
        });
    });

    document.querySelectorAll('.directory-toggle-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.dataset.id;
            if (!id || !config.routes.directoryToggle) {
                return;
            }
            const url = buildRoute(config.routes.directoryToggle, id);
            if (!url) return;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrf,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await response.json();
                if (data?.success) {
                    notify('success', data.message || 'به‌روزرسانی شد.');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    notify('error', data?.message || 'خطا در به‌روزرسانی.');
                }
            } catch {
                notify('error', 'خطا در ارتباط با سرور.');
            }
        });
    });

    document.querySelectorAll('.directory-delete-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.dataset.id;
            if (!id || !config.routes.directoryDelete) {
                return;
            }
            let ok = true;
            if (typeof confirmAction === 'function') {
                ok = await confirmAction('حذف پوشه', 'آیا از حذف این پوشه مطمئن هستید؟', {
                    icon: 'ph-trash',
                    confirmClass: 'btn-danger',
                    confirmText: 'حذف',
                });
            } else {
                ok = window.confirm('آیا از حذف این پوشه مطمئن هستید؟');
            }

            if (!ok) {
                return;
            }

            const url = buildRoute(config.routes.directoryDelete, id);
            if (!url) return;

            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': config.csrf,
                        'Content-Type': 'application/json',
                    },
                });
                const data = await response.json();
                if (data?.success) {
                    notify('success', data.message || 'حذف شد.');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    notify('error', data?.message || 'خطا در حذف.');
                }
            } catch {
                notify('error', 'خطا در ارتباط با سرور.');
            }
        });
    });

    function performAction(url, btnId, spinnerId, iconId) {
        const btn = document.getElementById(btnId);
        const spinner = document.getElementById(spinnerId);
        const icon = document.getElementById(iconId);

        btn?.setAttribute('disabled', 'disabled');
        spinner?.classList.remove('d-none');
        icon?.classList.add('d-none');

        progressBar.style.width = '35%';
        progressBar.textContent = '35%';
        progressMessage.textContent = 'در حال پردازش...';
        progressDetails.textContent = '';
        progressModal.show();

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': config.csrf,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        })
        .then(res => res.json())
        .then(data => handleQueueResponse(data))
        .catch(() => {
            progressMessage.innerHTML = '<span class="text-danger">خطا در ارتباط با سرور</span>';
        })
        .finally(() => {
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            btn?.removeAttribute('disabled');
            spinner?.classList.add('d-none');
            icon?.classList.remove('d-none');
        });
    }

    function performDirectoryAction(directory) {
        progressBar.style.width = '25%';
        progressBar.textContent = '25%';
        progressMessage.textContent = `در حال پردازش پوشه ${directory} ...`;
        progressDetails.textContent = '';
        progressModal.show();

        fetch(config.routes.regenerateDirectory, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': config.csrf,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ directory }),
        })
        .then(res => res.json())
        .then(data => handleQueueResponse(data))
        .catch(() => {
            progressMessage.innerHTML = '<span class="text-danger">خطا در ارتباط با سرور</span>';
        })
        .finally(() => {
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
        });
    }

    function handleQueueResponse(data) {
        if (data?.success) {
            progressMessage.innerHTML = '<span class="text-success"><i class="ph ph-check-circle me-1"></i>' + (data.message || 'انجام شد') + '</span>';
            if (data.data) {
                const rows = Object.entries(data.data)
                    .map(([key, value]) => `<div><strong>${key}</strong>: ${value}</div>`)
                    .join('');
                progressDetails.innerHTML = rows;
            }
            setTimeout(() => window.location.reload(), 1500);
        } else {
            progressMessage.innerHTML = '<span class="text-danger"><i class="ph ph-x-circle me-1"></i>' + (data?.message || 'خطا در پردازش') + '</span>';
        }
    }
});
</script>
@endpush

