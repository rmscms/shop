{{-- git-trigger --}}
@extends('cms::admin.layout.index')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">{{ $product ? trans('shop.product.edit') : trans('shop.product.create') }}</h5>
    <div class="d-flex gap-2">
      @if(!empty($product) && $product->exists)
        @php
          $shopHost = config('shop.frontend_api.shop_url', env('SHOP_FRONT_URL', 'http://127.0.0.1:8001'));
          $productPage = rtrim($shopHost, '/').'/shop/products/'.($product->slug ?? $product->id);
        @endphp
        <a href="{{ $productPage }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
          <i class="ph-eye me-1"></i> نمایش محصول
        </a>
      @endif
      <a href="{{ route('admin.shop.products.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 rounded-pill px-3">
        <i class="ph-arrow-left me-1"></i><span>@lang('shop.common.back')</span>
      </a>
    </div>
  </div>

  <form id="productForm" class="needs-validation" method="post" action="{{ $product ? route('admin.shop.products.update', $product->id) : route('admin.shop.products.store') }}">
    @csrf
    @if($product)
      @method('PUT')
      <input type="hidden" name="_method" value="PUT">
    @endif
    @php($selectedCategoryId = old('category_id', $product->category_id ?? $defaultCategoryId))
    <input type="hidden" name="active_tab" id="active_tab" value="{{ old('active_tab', request()->has('tab') ? ('tab_'.request('tab')) : 'tab_basic') }}">

    @if($errors->any())
      <div class="alert alert-danger">
        <div class="fw-semibold mb-1">خطا در اعتبارسنجی:</div>
        <ul class="mb-0 ps-3">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card shadow-sm">
      <div class="card-header border-0 pb-0">
        <ul class="nav nav-tabs nav-tabs-highlight card-header-tabs" role="tablist">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab_basic"><i class="ph-info me-2"></i>@lang('shop.tabs.basic')</a></li>
          <li class="nav-item"><a class="nav-link {{ empty($product) ? 'disabled' : '' }}" @if(!empty($product)) data-bs-toggle="tab" href="#tab_pricing" @else href="javascript:void(0)" @endif><i class="ph-currency-dollar me-2"></i>@lang('shop.tabs.pricing')</a></li>
          <li class="nav-item"><a class="nav-link {{ empty($product) ? 'disabled' : '' }}" @if(!empty($product)) data-bs-toggle="tab" href="#tab_attributes" @else href="javascript:void(0)" @endif><i class="ph-sliders me-2"></i>@lang('shop.tabs.attributes')</a></li>
          <li class="nav-item"><a class="nav-link {{ empty($product) ? 'disabled' : '' }}" @if(!empty($product)) data-bs-toggle="tab" href="#tab_features" @else href="javascript:void(0)" @endif><i class="ph-list me-2"></i>ویژگی‌ها</a></li>
          <li class="nav-item"><a class="nav-link {{ empty($product) ? 'disabled' : '' }}" @if(!empty($product)) data-bs-toggle="tab" href="#tab_images" @else href="javascript:void(0)" @endif><i class="ph-image me-2"></i>@lang('shop.tabs.images')</a></li>
          <li class="nav-item">
              <a class="nav-link" id="videos-tab" data-bs-toggle="tab" href="#videos" role="tab" aria-controls="videos" aria-selected="false">
                  <i class="ph ph-video-camera me-1"></i> @lang('shop::shop.tabs.videos')
                  <span class="badge bg-secondary ms-1" id="video-count">{{ $product->assignedVideos->count() ?? 0 }}</span>
              </a>
          </li>
        </ul>
      </div>

    <div class="card-body">
      <div class="tab-content">
      <div id="tab_basic" class="tab-pane fade show active p-3">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">@lang('shop.product.name')</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name ?? '') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-3">
            <label class="form-label">@lang('shop.product.slug')</label>
            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $product->slug ?? '') }}" required>
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-3">
            <label class="form-label">@lang('shop.product.sku')</label>
            <input type="text" name="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku', $product->sku ?? '') }}">
            @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label class="form-label">@lang('shop.product.category')</label>
            <div class="card border shadow-none">
              <div class="card-body p-2">
                <div id="product-category-tree"
                     class="border rounded-3 p-2"
                     style="height: 260px; overflow:auto;"
                     data-tree='@json($categoryTree, JSON_UNESCAPED_UNICODE)'
                     data-selected="{{ $selectedCategoryId }}"
                     data-default="{{ $defaultCategoryId }}"
                     data-endpoint="{{ route('admin.shop.categories.tree.data') }}"
                     data-fallback="{{ config('shop.categories.fallback_label', 'بدون دسته') }}">
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                  <button type="button" class="btn btn-light btn-sm" id="btn-clear-category">
                    <i class="ph-x me-1"></i> حذف انتخاب
                  </button>
                  <a href="{{ route('admin.shop.categories.tree') }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                    <i class="ph-tree-structure me-1"></i> مدیریت دسته‌ها
                  </a>
                </div>
              </div>
            </div>
            <input type="hidden" name="category_id" id="product-category-id" value="{{ $selectedCategoryId }}">
            @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text mt-1">برای انتخاب/تغییر دسته، از درخت بالا استفاده کنید.</div>
          </div>
          <div class="col-12">
            <label class="form-label">توضیح کوتاه</label>
            <textarea name="short_desc" class="form-control js-ckeditor" data-editor="ckeditor" data-min-height="200px" rows="5" data-upload-url="{{ route('admin.shop.editor.upload') }}">{{ old('short_desc', $product->short_desc ?? '') }}</textarea>
          </div>
          <div class="col-12">
            <label class="form-label">توضیحات محصول</label>
            <textarea name="description" class="form-control js-ckeditor" data-editor="ckeditor" data-min-height="360px" rows="18" data-upload-url="{{ route('admin.shop.editor.upload') }}">{{ old('description', $product->description ?? '') }}</textarea>
          </div>
          @if($product)
          <div class="col-12 d-flex gap-2 justify-content-end mt-2">
            <button type="button" id="btn-save-stay" class="btn btn-success">
              <span class="label"><i class="ph-floppy-disk me-1"></i> ذخیره و ماندن</span>
              <span class="loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>در حال ذخیره...</span>
            </button>
          </div>
          @else
          <div class="col-12 d-flex gap-2 justify-content-end mt-2">
            <button type="submit" class="btn btn-primary">
              <i class="ph-floppy-disk me-1"></i> ذخیره
            </button>
          </div>
          @endif
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', ($product->active ?? 1)) ? 'checked' : '' }}>
              <label class="form-check-label">@lang('shop.product.active')</label>
            </div>
          </div>
        </div>
      </div>

      <div id="tab_pricing" class="tab-pane fade p-3">
        @if(empty($product))
          <div class="alert alert-info mb-0"><i class="ph-info me-1"></i> ابتدا محصول را ذخیره کنید تا این تب فعال شود.</div>
        @else
        
        <div class="d-flex justify-content-end mb-2">
          <button type="button" class="btn btn-success btn-sm" id="btn-save-pricing-ajax">
            <span class="label"><i class="ph-floppy-disk me-1"></i> ذخیره قیمت/موجودی</span>
            <span class="loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>در حال ذخیره...</span>
          </button>
        </div>
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card shadow-sm border pricing-card pricing-prices">
              <div class="card-header">
                <h6 class="mb-0 d-flex align-items-center gap-2"><i class="ph-currency-circle-dollar"></i><span>قیمت‌ها (CNY)</span></h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">@lang('shop.product.cost_cny')</label>
                  <div class="input-group">
                    <input type="text" name="cost_cny" class="form-control @error('cost_cny') is-invalid @enderror" value="{{ old('cost_cny', $product->cost_cny ?? '') }}">
                    <span class="input-group-text">CNY</span>
                  </div>
                  @error('cost_cny')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label class="form-label">@lang('shop.product.sale_price_cny')</label>
                  <div class="input-group">
                    <input type="text" name="sale_price_cny" class="form-control @error('sale_price_cny') is-invalid @enderror" value="{{ old('sale_price_cny', $product->sale_price_cny ?? '') }}">
                    <span class="input-group-text">CNY</span>
                  </div>
                  @error('sale_price_cny')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card shadow-sm border pricing-card pricing-discount">
              <div class="card-header">
                <h6 class="mb-0 d-flex align-items-center gap-2"><i class="ph-ticket"></i><span>تخفیف</span></h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">نوع تخفیف</label>
                  <select name="discount_type" class="form-select @error('discount_type') is-invalid @enderror">
                    <option value="">بدون تخفیف</option>
                    <option value="percent" @selected(old('discount_type', $product->discount_type ?? '')==='percent')>درصدی</option>
                    <option value="amount" @selected(old('discount_type', $product->discount_type ?? '')==='amount')>مبلغی</option>
                  </select>
                  @error('discount_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label class="form-label">مقدار تخفیف</label>
                  <div class="input-group">
                    <input type="number" step="0.01" min="0" name="discount_value" class="form-control @error('discount_value') is-invalid @enderror" value="{{ old('discount_value', $product->discount_value ?? '') }}" placeholder="مثلاً 10 یا 5.50">
                    <span class="input-group-text" id="discount-unit">{{ old('discount_type', $product->discount_type ?? '')==='percent' ? '%' : 'CNY' }}</span>
                  </div>
                  <div class="form-text">واحد مبلغ «CNY» است؛ درصد بین 0 تا 100.</div>
                  @error('discount_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card shadow-sm border pricing-card pricing-stock">
              <div class="card-header">
                <h6 class="mb-0 d-flex align-items-center gap-2"><i class="ph-package"></i><span>موجودی و امتیاز</span></h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">@lang('shop.combinations.stock')</label>
                  <input type="number" step="1" min="0" name="stock_qty" class="form-control @error('stock_qty') is-invalid @enderror" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}">
                  @error('stock_qty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label class="form-label">@lang('shop.product.point_per_unit')</label>
                  <input type="number" step="1" min="0" name="point_per_unit" class="form-control @error('point_per_unit') is-invalid @enderror" value="{{ old('point_per_unit', $product->point_per_unit ?? 0) }}">
                  @error('point_per_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
          </div>
        </div>

        
        @endif
      </div>

      <div id="tab_attributes" class="tab-pane fade p-3">
        @if(empty($product))
          <div class="alert alert-info mb-0"><i class="ph-info me-1"></i> ابتدا محصول را ذخیره کنید تا این تب فعال شود.</div>
        @else
        <!-- Attributes Section -->
        <div class="mb-3">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="mb-0">@lang('shop.attributes.title')</h6>
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-attribute">
              <i class="ph-plus me-1"></i>@lang('shop.actions.add_attribute')
            </button>
          </div>
          <div id="attributes-root" class="row g-3"></div>
        </div>

        <!-- Combinations Section -->
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h6 class="mb-0">@lang('shop.combinations.title')</h6>
            <div class="ms-auto d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-save-combinations-ajax">
                <span class="save-label"><i class="ph-floppy-disk me-1"></i>ذخیره ترکیب‌ها</span>
                <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>در حال ذخیره...</span>
              </button>
              <button type="button" class="btn btn-outline-primary btn-sm" id="btn-generate-combinations">
                <i class="ph-shuffle me-1"></i>@lang('shop.actions.generate')
              </button>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="border-0" style="width: 140px; min-width: 140px;">SKU</th>
                    <th class="border-0" style="min-width: 200px;">ویژگی‌ها</th>
                    <th class="border-0 text-center" style="width: 120px; min-width: 120px;">قیمت خرید (CNY)</th>
                    <th class="border-0 text-center" style="width: 120px; min-width: 120px;">قیمت فروش (CNY)</th>
                    <th class="border-0 text-center" style="width: 100px; min-width: 100px;">موجودی</th>
                    <th class="border-0 text-center" style="width: 80px; min-width: 80px;">فعال</th>
                    <th class="border-0 text-center" style="width: 100px; min-width: 100px;">تصاویر</th>
                    <th class="border-0 text-center" style="width: 90px; min-width: 90px;">عملیات</th>
                  </tr>
                </thead>
                <tbody id="combinations-list">
                  <!-- Combinations will be rendered here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <input type="hidden" name="attributes_json" id="attributes_json">
        <input type="hidden" name="combinations_json" id="combinations_json">
        @endif
      </div>

      <div id="tab_features" class="tab-pane fade p-3">
        @if(!empty($product))
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h6 class="mb-0">
              <i class="ph-list-bullets me-2"></i>
              ویژگی‌های محصول
              <span class="badge bg-primary ms-2">دسته‌بندی شده</span>
            </h6>
            <div class="ms-auto d-flex gap-2">
              <button type="button" class="btn btn-outline-info btn-sm" id="btn-add-category">
                <i class="ph-folder-plus me-1"></i>دسته جدید
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-feature">
                <i class="ph-plus me-1"></i>ویژگی جدید
              </button>
              <button type="button" class="btn btn-primary btn-sm" id="btn-save-features" data-save-url="{{ route('admin.shop.products.save-features', ['product'=>(int)($product->id ?? 0)]) }}">
                <span class="save-label"><i class="ph-floppy-disk me-1"></i>ذخیره همه</span>
                <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>در حال ذخیره...</span>
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="alert alert-info d-flex align-items-center mb-3">
              <i class="ph-info me-2"></i>
              <div class="small">
                <strong>راهنما:</strong> ویژگی‌ها را در دسته‌های مختلف گروه‌بندی کنید. هر تغییر خودکار ذخیره می‌شود.
              </div>
            </div>
            <div id="features-root"></div>
          </div>
        </div>
        @else
          <div class="text-muted small">بعد از ایجاد محصول می‌توانید ویژگی‌ها را ثبت کنید.</div>
        @endif
      </div>

      <div id="tab_images" class="tab-pane fade p-3" @if(!empty($product)) data-product-id="{{ $product->id }}" @endif>
        @if(empty($product))
          <div class="alert alert-info mb-0"><i class="ph-info me-1"></i> ابتدا محصول را ذخیره کنید تا بتوانید تصاویر را مدیریت کنید.</div>
        @else
        <!-- Image Library Integration -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <a href="{{ route('admin.shop.image-library.index') }}" target="_blank" class="btn btn-outline-primary">
              <i class="ph-image me-1"></i>
              مشاهده کتابخانه تصاویر
            </a>
          </div>
          <div class="col-md-6 text-end">
            <button type="button" class="btn btn-success" id="attach-from-library-btn">
              <i class="ph-plus me-1"></i>
              انتخاب از کتابخانه
            </button>
          </div>
        </div>

        <!-- Current Product Images -->
        <div id="product-images-container">
          @include('shop::admin.products.partials.product-images', ['product' => $product])
        </div>

        @endif
      </div>

      <div class="tab-pane fade" id="videos" role="tabpanel" aria-labelledby="videos-tab">
          @include('shop::admin.products.partials.product-videos')
      </div>
      </div>
    </div>

    <div class="bg-body border-top mt-3 position-sticky bottom-0 py-2" style="z-index:1010;">
      <div class="d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.shop.products.index') }}" class="btn btn-light">@lang('shop.common.back')</a>
      </div>
    </div>
  </form>
  
  <!-- Modal: Combination Images Preview -->
  <div class="modal fade" id="comboImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">@lang('shop.tabs.images')</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="combo-image-grid" class="row g-2"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">@lang('shop.common.cancel')</button>
          <a href="#tab_images" class="btn btn-primary" data-bs-toggle="tab">@lang('shop.tabs.images')</a>
        </div>
      </div>
    </div>
  </div>
  
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/shop/admin/js/products/image-management.js') }}"></script>
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/shop/admin/css/products/edit.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/shop/admin/css/image-library.css') }}">
@endpush
