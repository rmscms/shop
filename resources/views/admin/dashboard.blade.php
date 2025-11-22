@extends('cms::admin.layout.index')
@section('title', 'داشبورد فروشگاه')
@section('content')
<div class="container-fluid">
    <!-- System Status Card -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card border-start border-{{ $overallStatus['status_color'] }} border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1">
                                <i class="ph-shield-check me-1 text-{{ $overallStatus['status_color'] }}"></i>
                                {{ $overallStatus['status_text'] }}
                            </h5>
                            <p class="text-muted mb-0">
                                {{ $overallStatus['passed'] }} از {{ $overallStatus['total'] }} مورد بررسی شده
                                ({{ $overallStatus['percentage'] }}%)
                            </p>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#systemRequirementsModal">
                                <i class="ph-info me-1"></i>
                                مستندات و نیازمندی‌ها
                            </button>
                        </div>
                    </div>

                    @if(!empty($overallStatus['critical_issues']))
                    <div class="alert alert-danger mt-3 mb-0">
                        <h6 class="alert-heading mb-2">
                            <i class="ph-warning-circle me-1"></i>
                            مشکلات حیاتی:
                        </h6>
                        <ul class="mb-0">
                            @foreach($overallStatus['critical_issues'] as $issue)
                            <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if(!empty($overallStatus['warnings']))
                    <div class="alert alert-warning mt-3 mb-0">
                        <h6 class="alert-heading mb-2">
                            <i class="ph-warning me-1"></i>
                            هشدارها:
                        </h6>
                        <ul class="mb-0">
                            @foreach($overallStatus['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">مجموع محصولات</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['products']['total']) }}</h3>
                            <span class="text-success">
                                <i class="ph-check-circle me-1"></i>
                                {{ number_format($stats['products']['active']) }} فعال
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-package display-6 text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">دسته‌بندی‌ها</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['categories']) }}</h3>
                            <span class="text-muted">دسته‌بندی محصولات</span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-tree-structure display-6 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">مجموع سفارشات</h6>
                            <h3 class="mb-0 mt-2">{{ number_format($stats['orders']['total']) }}</h3>
                            <span class="text-info">
                                <i class="ph-clock me-1"></i>
                                {{ number_format($stats['orders']['today']) }} امروز
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-shopping-cart display-6 text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="mb-0">وضعیت سیستم</h6>
                            <h3 class="mb-0 mt-2">{{ $overallStatus['percentage'] }}%</h3>
                            <span class="text-{{ $overallStatus['status_color'] }}">
                                <i class="ph-shield-check me-1"></i>
                                آماده به کار
                            </span>
                        </div>
                        <div class="ms-3">
                            <i class="ph-gear display-6 text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-plus-circle me-1 text-success"></i>
                        ایجاد سریع
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.shop.products.create') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ph-plus me-1"></i>
                            محصول جدید
                        </a>
                        <a href="{{ route('admin.shop.categories.create') }}" class="btn btn-outline-success btn-sm">
                            <i class="ph-plus me-1"></i>
                            دسته‌بندی جدید
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-list me-1 text-primary"></i>
                        مدیریت
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.shop.products.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ph-package me-1"></i>
                            لیست محصولات
                        </a>
                        <a href="{{ route('admin.shop.orders.index') }}" class="btn btn-outline-warning btn-sm">
                            <i class="ph-shopping-cart me-1"></i>
                            لیست سفارشات
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-gear me-1 text-info"></i>
                        تنظیمات
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.shop.currencies.index') }}" class="btn btn-outline-info btn-sm">
                            <i class="ph-currency-circle-dollar me-1"></i>
                            ارزها
                        </a>
                        <a href="{{ route('admin.shop.settings.edit') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ph-gear-six me-1"></i>
                            تنظیمات فروشگاه
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="ph-chart-line me-1 text-success"></i>
                        گزارشات
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.shop.product-purchase-stats.index') }}" class="btn btn-outline-success btn-sm">
                            <i class="ph-chart-bar me-1"></i>
                            آمار خریدها
                        </a>
                        <a href="{{ route('admin.shop.carts.index') }}" class="btn btn-outline-danger btn-sm">
                            <i class="ph-shopping-bag me-1"></i>
                            سبدهای خرید
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Requirements Modal -->
@include('shop::admin.partials.system-requirements-modal', [
    'requirements' => $requirements,
    'overallStatus' => $overallStatus
])

@endsection

@push('css_links')
<link rel="stylesheet" href="{{ asset('admin/plugins/prism/prism.css') }}">
@endpush

@push('js_scripts')
<script src="{{ asset('admin/plugins/prism/prism.js') }}"></script>
@endpush
