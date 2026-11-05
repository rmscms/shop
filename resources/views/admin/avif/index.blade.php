@extends('cms::admin.layout.index')

@section('title', 'مدیریت تصاویر AVIF')

@section('content')
@include('shop::admin.partials.ui')
<div class="container-fluid shop-ui">
    <div class="shop-head">
        <span class="shop-icon shop-icon--lg tone-info"><i class="ph ph-image"></i></span>
        <div>
            <h4 class="shop-head__title">مدیریت تصاویر AVIF</h4>
            <p class="shop-head__sub">نسخهٔ سبک‌تر تصویرها برای سرعت بیشتر سایت</p>
        </div>
        <div class="shop-head__actions">
            <button class="btn btn-light" id="refreshStatsBtn">
                <i class="ph ph-chart-bar" id="refreshIcon"></i>
                <i class="ph ph-spinner ph-spin d-none" id="refreshSpinner"></i>
                به‌روزرسانی آمار
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">@include('shop::admin.partials.stat', ['label' => 'کل تصاویر', 'value' => number_format($stats['total_images'] ?? 0), 'icon' => 'ph-image', 'tone' => 'primary'])</div>
        <div class="col-6 col-xl-3">@include('shop::admin.partials.stat', ['label' => 'فایل‌های AVIF', 'value' => number_format($stats['total_avif'] ?? 0), 'icon' => 'ph-check-circle', 'tone' => 'success'])</div>
        <div class="col-6 col-xl-3">@include('shop::admin.partials.stat', ['label' => 'فاقد AVIF', 'value' => number_format($stats['missing_avif'] ?? 0), 'icon' => 'ph-warning', 'tone' => ($stats['missing_avif'] ?? 0) > 0 ? 'warning' : 'muted'])</div>
        <div class="col-6 col-xl-3">@include('shop::admin.partials.stat', ['label' => 'درصد تبدیل', 'value' => ($stats['conversion_rate'] ?? 0).'٪', 'icon' => 'ph-percent', 'tone' => 'info', 'meter' => (int) ($stats['conversion_rate'] ?? 0)])</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card mb-0 h-100">
                <div class="card-header"><h6 class="shop-card-title"><i class="ph ph-magic-wand"></i> عملیات AVIF</h6></div>
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2" style="min-width:13rem" id="regenerateAllBtn">
                            <i class="ph ph-magic-wand" id="regenerateIcon"></i>
                            <i class="ph ph-spinner ph-spin d-none" id="regenerateSpinner"></i>
                            بازسازی همه فایل‌ها
                        </button>
                        <span class="fs-sm text-muted">تبدیل تمام تصاویر پشتیبانی‌شده به AVIF</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <button class="btn btn-light d-flex align-items-center justify-content-center gap-2" style="min-width:13rem;color:rgb(var(--tone-danger))" id="cleanAllBtn">
                            <i class="ph ph-trash-simple" id="cleanIcon"></i>
                            <i class="ph ph-spinner ph-spin d-none" id="cleanSpinner"></i>
                            حذف همه AVIF
                        </button>
                        <span class="fs-sm text-muted">پاک‌سازی نسخه‌های AVIF برای بازسازی مجدد</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-0 h-100">
                <div class="card-header"><h6 class="shop-card-title"><i class="ph ph-folder-simple-plus"></i> افزودن پوشهٔ جدید</h6></div>
                <div class="card-body">
                    <form id="addDirectoryForm" class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label">مسیر (نسبت به public یا storage)</label>
                            <input type="text" class="form-control" id="directoryPath" placeholder="uploads/custom-folder" required dir="ltr">
                        </div>
                        <div class="col-7">
                            <label class="form-label">نوع مسیر</label>
                            <select class="form-select" id="directoryType">
                                <option value="public">public_path()</option>
                                <option value="storage">storage/app/public</option>
                            </select>
                        </div>
                        <div class="col-5 d-grid">
                            <button class="btn btn-primary" type="submit"><i class="ph ph-plus-circle me-1"></i> افزودن</button>
                        </div>
                    </form>
                    <div class="shop-note tone-muted mt-3">
                        مسیرها بدون اسلش ابتدایی/انتهایی ثبت می‌شوند (مثال: <code>uploads/banners</code>). برای پوشهٔ <span class="fw-semibold">storage/app/public</span> نوع «storage» را انتخاب کن.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="shop-card-title"><i class="ph ph-folders"></i> جزئیات پوشه‌ها</h6></div>
        <div class="table-responsive">
            <table class="table table-hover shop-table">
                <thead>
                    <tr>
                        <th>پوشه</th>
                        <th>کل تصاویر</th>
                        <th>AVIF</th>
                        <th>فاقد AVIF</th>
                        <th style="min-width:11rem">درصد تبدیل</th>
                        <th>وضعیت</th>
                        <th style="width:1%">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($stats['directories'] ?? []) as $dir => $dirStats)
                        @php
                            $rate = (float) ($dirStats['conversion_rate'] ?? 0);
                            $rateTone = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                            $exists = ($dirStats['exists'] ?? true) !== false;
                            $total = (int) ($dirStats['total_images'] ?? 0);
                            $active = !empty($dirStats['active']);
                            if (!$exists) { [$stLabel, $stTone] = ['وجود ندارد', 'danger']; }
                            elseif ($total === 0) { [$stLabel, $stTone] = ['خالی', 'muted']; }
                            elseif (!$active) { [$stLabel, $stTone] = ['غیرفعال', 'muted']; }
                            elseif ($rate == 100) { [$stLabel, $stTone] = ['کامل', 'success']; }
                            elseif ($rate > 0) { [$stLabel, $stTone] = ['جزئی', 'warning']; }
                            else { [$stLabel, $stTone] = ['فاقد AVIF', 'danger']; }
                        @endphp
                        <tr>
                            <td>
                                <code dir="ltr">{{ $dir }}</code>
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <span class="shop-soft tone-muted">{{ ($dirStats['type'] ?? 'public') === 'storage' ? 'storage' : 'public' }}</span>
                                    @if(!empty($dirStats['is_default']))<span class="shop-soft tone-info">پیش‌فرض</span>@endif
                                    @if(!$active)<span class="shop-soft tone-muted">غیرفعال</span>@endif
                                </div>
                            </td>
                            <td>{{ $total }}</td>
                            <td>{{ $dirStats['avif_files'] ?? 0 }}</td>
                            <td>{{ $dirStats['missing_avif'] ?? 0 }}</td>
                            <td>
                                <div class="shop-meter-row tone-{{ $rateTone }}">
                                    <div class="shop-meter"><span style="width: {{ max(0, min(100, $rate)) }}%"></span></div>
                                    <span class="shop-score">{{ $rate }}٪</span>
                                </div>
                            </td>
                            <td><span class="shop-dot tone-{{ $stTone }}">{{ $stLabel }}</span></td>
                            <td>
                                <div class="d-flex gap-1 flex-nowrap">
                                    @if(!empty($dirStats['id']))
                                        <button type="button"
                                                class="btn btn-light btn-icon btn-sm directory-toggle-btn"
                                                data-id="{{ $dirStats['id'] }}"
                                                data-active="{{ $active ? '1' : '0' }}"
                                                title="{{ $active ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                            <i class="ph {{ $active ? 'ph-pause' : 'ph-play' }}"></i>
                                        </button>
                                        @if(empty($dirStats['is_default']))
                                            <button type="button" class="btn btn-light btn-icon btn-sm directory-delete-btn" data-id="{{ $dirStats['id'] }}" title="حذف پوشه" style="color:rgb(var(--tone-danger))">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        @endif
                                    @endif

                                    @if(($dirStats['exists'] ?? false) && $total > 0)
                                        <button class="btn btn-light btn-icon btn-sm convert-dir-btn" data-directory="{{ $dir }}" data-bs-toggle="tooltip" title="تبدیل این پوشه">
                                            <i class="ph ph-arrows-clockwise"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">@include('shop::admin.partials.empty', ['icon' => 'ph-folders', 'title' => 'هنوز پوشه‌ای ثبت نشده است'])</td></tr>
                    @endforelse
                </tbody>
            </table>
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

@include('shop::admin.partials.confirm')
<script>
    // The page script asks confirmAction(title, message, options); answer with the shared modern dialog.
    window.confirmAction = function (title, message, opts) {
        opts = opts || {};
        var danger = /btn-danger/.test(opts.confirmClass || '') || /حذف/.test(opts.confirmText || title || '');
        return window.rmsConfirm({
            title: title, message: message, type: danger ? 'danger' : 'info',
            ok: opts.confirmText || (danger ? 'حذف' : 'تأیید'), cancel: opts.cancelText || 'انصراف'
        });
    };
</script>
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

