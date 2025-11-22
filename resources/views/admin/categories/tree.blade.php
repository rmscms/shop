@extends('cms::admin.layout.index')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-3 gap-2">
        <div>
            <h5 class="mb-1">مدیریت درخت دسته‌بندی‌ها</h5>
            <p class="text-muted mb-0">دسته‌ها را به صورت درختی ببینید، زیردسته بسازید و سریع به صفحه ویرایش منتقل شوید.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.shop.categories.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ph-list me-1"></i> لیست جدول
            </a>
            <a href="{{ route('admin.shop.categories.create') }}" class="btn btn-primary btn-sm" id="btn-create-root">
                <i class="ph-plus me-1"></i> دسته اصلی جدید
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="ph-tree-structure me-2"></i> سلسله مراتب دسته‌ها</h6>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-light btn-sm" id="btn-expand-all"><i class="ph-arrows-out-cardinal me-1"></i>باز کردن همه</button>
                        <button type="button" class="btn btn-light btn-sm" id="btn-collapse-all"><i class="ph-arrows-in-cardinal me-1"></i>بستن همه</button>
                    </div>
                </div>
                <div class="card-body" style="min-height: 420px;">
                    <div id="shop-category-tree"
                         class="border rounded-3 p-2"
                         style="height: 380px; overflow: auto;"
                         data-tree='@json($treeData, JSON_UNESCAPED_UNICODE)'
                         data-endpoint="{{ route('admin.shop.categories.tree.data') }}"
                         data-default-id="{{ (int) ($defaultCategoryId ?? 0) }}"
                         data-fallback-label="{{ $fallbackLabel }}">
                    </div>
                    <div class="form-text mt-2">برای ساخت زیردسته، گره مورد نظر را انتخاب و روی دکمه «زیردسته جدید» کلیک کنید.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm" id="category-info-card" data-empty-state="true">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 d-flex align-items-center gap-2">
                        <i class="ph-info"></i>
                        <span id="category-info-title">هیچ دسته‌ای انتخاب نشده است</span>
                    </h6>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.shop.categories.index') }}" class="btn btn-sm btn-outline-secondary" id="btn-view-table" target="_blank" rel="noopener">
                            <i class="ph-table me-1"></i>مشاهده جدول
                        </a>
                        <a href="{{ route('admin.shop.categories.create') }}" class="btn btn-sm btn-outline-primary" id="btn-create-child" disabled>
                            <i class="ph-plus me-1"></i>زیردسته جدید
                        </a>
                        <a href="#" class="btn btn-sm btn-primary" id="btn-edit-category" hidden>
                            <i class="ph-pencil me-1"></i>ویرایش دسته
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0" id="category-details" hidden>
                        <dt class="col-sm-4 text-muted">شناسه</dt>
                        <dd class="col-sm-8 fw-semibold" id="category-detail-id">-</dd>

                        <dt class="col-sm-4 text-muted">نام</dt>
                        <dd class="col-sm-8 fw-semibold" id="category-detail-name">-</dd>

                        <dt class="col-sm-4 text-muted">لینک</dt>
                        <dd class="col-sm-8 fw-semibold" id="category-detail-slug">-</dd>

                        <dt class="col-sm-4 text-muted">وضعیت</dt>
                        <dd class="col-sm-8 fw-semibold" id="category-detail-status">-</dd>

                        <dt class="col-sm-4 text-muted">اولویت</dt>
                        <dd class="col-sm-8 fw-semibold" id="category-detail-sort">-</dd>
                    </dl>
                    <div id="category-empty-hint" class="text-muted">
                        برای مشاهده جزئیات، یک دسته را از درخت سمت چپ انتخاب کنید.
                    </div>
                </div>
            </div>
            <div class="alert alert-light border mt-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph-lightbulb text-warning"></i>
                    <strong>نکات مدیریتی</strong>
                </div>
                <ul class="mb-0 ps-3 text-muted small">
                    <li>دسته‌های غیرفعال به رنگ خاکستری نمایش داده می‌شوند.</li>
                    <li>برای ساخت زیردسته، ابتدا گره والد را انتخاب و سپس روی «زیردسته جدید» کلیک کنید.</li>
                    <li>زرنگ باشید! می‌توانید با دابل‌کلیک روی هر گره، سریعاً وارد صفحه ویرایش شوید.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

