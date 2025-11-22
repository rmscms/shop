@extends('cms::admin.layout.index')

@section('content')
<div class="content">
  <!-- Page Header -->
  <div class="page-header page-header-light shadow-sm rounded mb-3">
    <div class="page-header-content d-lg-flex">
      <div class="d-flex">
        <div class="breadcrumb py-2">
          <a href="{{ route('admin.shop.orders.index') }}" class="breadcrumb-item"><i class="ph-shopping-bag me-1"></i> {{ trans('admin.list') }}</a>
          <span class="breadcrumb-item active">#{{ $order->id }}</span>
        </div>
      </div>
      <div class="ms-lg-auto my-auto d-flex gap-2 py-2">
        <span class="badge {{ $status['class'] }} align-self-center px-3 py-2"><i class="ph-circle-wavy-check me-1"></i> {{ $status['label'] }}</span>
        <a href="{{ route('admin.shop.orders.whatsapp', ['order' => $order->id]) }}" class="btn btn-success btn-sm" target="_blank"><i class="ph-whatsapp-logo me-2"></i> ارسال واتس‌اپ</a>
        <a href="{{ route('admin.shop.orders.index') }}" class="btn btn-light btn-sm"><i class="ph-arrow-left me-2"></i>{{ trans('admin.back') }}</a>
      </div>
    </div>
  </div>

  <!-- Top Stat Cards -->
  <div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
@include('cms::components.statistical-card', [
        'title' => 'جمع سبد',
        'value' => number_format($order->subtotal, 0),
        'unit' => 'تومان',
        'icon' => 'shopping-cart',
        'color' => 'primary',
        'colSize' => 'col-12'
      ])
    </div>
    <div class="col-xl-3 col-md-6">
      @include('cms::components.statistical-card', [
        'title' => 'تخفیف',
        'value' => number_format($order->discount, 0),
        'unit' => 'تومان',
        'icon' => 'percent',
        'color' => 'warning',
        'colSize' => 'col-12'
      ])
    </div>
    <div class="col-xl-3 col-md-6">
      @include('cms::components.statistical-card', [
        'title' => 'ارسال',
        'value' => number_format($order->shipping_cost, 0),
        'unit' => 'تومان',
        'icon' => 'truck',
        'color' => 'info',
        'colSize' => 'col-12'
      ])
    </div>
    <div class="col-xl-3 col-md-6">
      @include('cms::components.statistical-card', [
        'title' => 'مبلغ کل',
        'value' => number_format($order->total, 0),
        'unit' => 'تومان',
        'icon' => 'currency-circle-dollar',
        'color' => 'success',
        'colSize' => 'col-12'
      ])
    </div>
  </div>

  <div class="row g-3">
    <!-- Items Table -->
    <div class="col-xl-8">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h5 class="mb-0"><i class="ph-list me-2 text-primary"></i> اقلام سفارش</h5>
          <div class="ms-auto d-flex gap-2">
            <a href="{{ route('admin.shop.orders.invoice', ['order' => $order->id]) }}" class="btn btn-light btn-sm" target="_blank"><i class="ph-printer me-2"></i> چاپ فاکتور</a>
            <a href="{{ route('admin.shop.orders.label', ['order' => $order->id]) }}" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="ph-address-book me-2"></i> برچسپ پستی</a>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle admin-order-items-table">
            <thead>
              <tr>
                <th class="thumb-col"></th>
                <th>محصول</th>
                <th class="text-center qty-col">تعداد</th>
                <th class="text-end unit-col">فی (تومان)</th>
                <th class="text-end sum-col">جمع (تومان)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($items as $it)
                <tr>
                  <td>
                    @if($it['thumb'])
                      <a href="#" class="order-item-thumb" data-img-url="{{ $it['image_url'] ?? '' }}" data-img-avif="{{ $it['image_avif'] ?? '' }}" title="نمایش تصویر بزرگ">
                        <img src="{{ $it['thumb'] }}" alt="thumb" class="rounded thumb-56 object-fit-cover" style="width:56px;height:56px;object-fit:cover;">
                      </a>
                    @else
                      <div class="bg-light rounded d-flex align-items-center justify-content-center thumb-56-fallback"><i class="ph-image fs-4 text-muted"></i></div>
                    @endif
                  </td>
                  <td>
                    <div class="fw-semibold">{{ $it['name'] }}</div>
                    <div class="text-muted small">{{ $it['sku'] }}</div>
                    @if($it['attributes'])
                      <div class="text-muted small"><i class="ph-squares-four me-1"></i>{{ $it['attributes'] }}</div>
                    @endif
                  </td>
                  <td class="text-center">{{ $it['qty'] }}</td>
                  <td class="text-end">{{ number_format($it['unit_price'], 0) }}</td>
                  <td class="text-end fw-bold">{{ number_format($it['total'], 0) }}</td>
                </tr>
              @endforeach
              @if(empty($items))
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">
                    <i class="ph-shopping-bag-open me-2"></i> آیتمی ثبت نشده است
                  </td>
                </tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>

      <!-- Timeline -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><i class="ph-timer me-2 text-info"></i> تاریخچه سفارش</h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled">
            <li class="d-flex align-items-start mb-3">
              <i class="ph-shopping-bag text-primary me-2 fs-base mt-1"></i>
              <div>
                <div class="fw-semibold">ایجاد سفارش</div>
                <div class="text-muted small">{{ \RMS\Helper\persian_date($order->created_at, 'Y/m/d H:i') }}</div>
              </div>
            </li>
            @if($order->paid_at)
            <li class="d-flex align-items-start mb-3">
              <i class="ph-check-circle text-success me-2 fs-base mt-1"></i>
              <div>
                <div class="fw-semibold">پرداخت</div>
                <div class="text-muted small">{{ \RMS\Helper\persian_date($order->paid_at, 'Y/m/d H:i') }}</div>
              </div>
            </li>
            @endif
            <li class="d-flex align-items-start">
              <i class="ph-list-checks text-muted me-2 fs-base mt-1"></i>
              <div>
                <div class="fw-semibold">وضعیت فعلی</div>
                <div class="text-muted small">{{ $status['label'] }}</div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="ph-percent me-2 text-warning"></i> مدیریت تخفیف</h6></div>
            <div class="card-body">
              <div class="mb-2 text-muted">تخفیف فعلی: <strong>{{ number_format((float)$order->discount,0) }}</strong> تومان</div>
              <form method="POST" action="{{ route('admin.shop.orders.discount', ['order'=>$order->id]) }}" class="row g-2">
                @csrf
                <div class="col-12">
                  <label class="form-label">مبلغ تخفیف</label>
                  <div class="input-group">
                    <input type="text" name="amount_display" id="discount-amount-manual" class="form-control form-control-sm amount-field" data-type="amount" placeholder="مثلاً 1,000,000" />
                    <span class="input-group-text">تومان</span>
                  </div>
                  <input type="hidden" name="amount" id="discount-amount-raw-manual" />
                  <div class="form-text text-muted small">با جداکننده هزارگان وارد کنید</div>
                </div>
                <div class="col-12">
                  <label class="form-label">توضیحات (نمایش به کاربر)</label>
                  <textarea name="note" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="اختیاری"></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary btn-sm"><i class="ph-floppy-disk me-1"></i> اعمال تخفیف</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="ph-stars me-2 text-info"></i> امتیاز سفارش</h6></div>
            <div class="card-body">
              <div class="mb-2 d-flex align-items-center gap-2">
                <span class="text-muted">امتیاز فعلی کاربر:</span>
                <span class="badge bg-primary rounded-pill px-3 py-2">{{ number_format((int)($user_points_total ?? 0)) }}</span>
              </div>
              <div class="mb-3">امتیاز این سفارش: <strong>{{ number_format((int)($points_sum ?? 0)) }}</strong></div>
              <div class="d-flex gap-2 align-items-end">
                @if(!($points_applied ?? false))
                  <form method="POST" action="{{ route('admin.shop.orders.apply-points', ['order'=>$order->id]) }}" class="d-flex gap-2 align-items-end">
                    @csrf
                    <div>
                      <label class="form-label">امتیاز قابل اعمال</label>
                      <input type="number" name="points" class="form-control" min="1" step="1" value="{{ (int)($points_sum ?? 0) }}">
                    </div>
                    <div class="pb-1">
                      <button class="btn btn-outline-info btn-sm mt-4"><i class="ph-check-circle me-1"></i> اعمال امتیاز</button>
                    </div>
                  </form>
                @else
                  <span class="badge bg-success"><i class="ph-check"></i> اعمال شده</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Address + Tracking Row -->
      <div class="row g-3 mt-0">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header d-flex align-items-center">
              <h6 class="mb-0"><i class="ph-truck me-2 text-info"></i> ارسال</h6>
            </div>
            <div class="card-body">
              @if($order->shipping_address)
                <div class="mb-2">
                  <div class="fw-semibold">{{ $order->shipping_name ?: '—' }}</div>
                  <div class="text-muted">{{ $order->shipping_mobile ?: '' }}</div>
                </div>
                <div class="text-muted"><i class="ph-map-pin me-1"></i>{{ $order->shipping_address }}</div>
                @if($order->shipping_postal_code)
                  <div class="text-muted mt-1"><i class="ph-barcode me-1"></i>{{ $order->shipping_postal_code }}</div>
                @endif
              @else
                <div class="text-muted">آدرس ثبت نشده است</div>
              @endif
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card">
            <div class="card-header d-flex align-items-center">
              <h6 class="mb-0"><i class="ph-package me-2 text-secondary"></i> رهگیری پست</h6>
            </div>
            <div class="card-body">
              <form method="POST" action="{{ route('admin.shop.orders.tracking.update', ['order' => $order->id]) }}" class="row g-2 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-12">
                  <label class="form-label">کد رهگیری</label>
                  <div class="input-group">
                    <input type="text" id="tracking-code-input" name="tracking_code" value="{{ $order->tracking_code }}" class="form-control" maxlength="64">
                    <button class="btn btn-outline-secondary" id="copy-tracking-code" type="button"><i class="ph-copy"></i></button>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">لینک رهگیری</label>
                  <div class="input-group">
                    <input type="url" name="tracking_url" value="{{ $order->tracking_url }}" class="form-control" maxlength="255">
                    @if(!empty($order->tracking_url))
                      <a href="{{ $order->tracking_url }}" target="_blank" class="btn btn-outline-primary"><i class="ph-arrow-square-out"></i></a>
                    @endif
                  </div>
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary btn-sm"><i class="ph-floppy-disk me-1"></i> ذخیره</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar: Summary & Customer -->
    <div class="col-xl-4">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h6 class="mb-0"><i class="ph-arrow-clockwise me-2 text-primary"></i> وضعیت سفارش</h6>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.shop.orders.update-status', ['order' => $order->id]) }}" class="d-flex gap-2 align-items-end">
            @csrf
            @method('PUT')
            <div class="flex-grow-1">
              <label class="form-label">تغییر وضعیت</label>
              <select class="form-select" name="status">
                @php $statusOptions = \RMS\Shop\Models\Order::statusOptions(); @endphp
                @foreach($statusOptions as $key => $label)
                  <option value="{{ $key }}" {{ $order->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-primary">به‌روزرسانی</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><i class="ph-receipt me-2 text-success"></i> خلاصه پرداخت</h6>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">جمع سبد</span>
            <span>{{ number_format($order->subtotal, 0) }} <span class="text-muted">تومان</span></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">تخفیف</span>
            <span>{{ number_format($order->discount, 0) }} <span class="text-muted">تومان</span></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">هزینه ارسال</span>
            <span>{{ number_format($order->shipping_cost, 0) }} <span class="text-muted">تومان</span></span>
          </div>
          <hr>
          <div class="d-flex justify-content-between align-items-center">
            <span class="fw-bold">قابل پرداخت</span>
            <span class="fw-bold fs-5">{{ number_format($order->total, 0) }} <span class="text-muted">تومان</span></span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><i class="ph-user me-2 text-primary"></i> مشتری</h6>
        </div>
        <div class="card-body">
          <div class="mb-2">
            <div class="fw-semibold">{{ $order->user_name ?? ('#'.$order->user_id) }}</div>
            <div class="text-muted">{{ $order->shipping_mobile ?? '—' }}</div>
            <a href="{{ route('admin.users.edit', ['user' => $order->user_id]) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
              <i class="ph-arrow-square-out me-1"></i> مشاهده پروفایل
            </a>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
              <span class="badge bg-success"><i class="ph-wallet me-1"></i> اعتبار: {{ \RMS\Helper\displayAmount((int)($user_credit ?? ($order->user_credit ?? 0))) }}</span>
              <span class="badge bg-warning text-dark"><i class="ph-hand-coins me-1"></i> بدهی: {{ \RMS\Helper\displayAmount((int)($user_debt ?? ($order->user_debt ?? 0))) }}</span>
            </div>
          </div>
        </div>
      </div>



      <!-- Finance Actions -->
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="ph-currency-circle-dollar me-2 text-success"></i> پرداخت سرویسی</h6></div>
        <div class="card-body">
          @php $refundStatuses = (array) config('shop.refund_statuses', ['returned']); @endphp
          @php $isRefunded = !empty($order->refunded_at) || in_array((string)$order->status, $refundStatuses, true); @endphp
          @if(!empty($order->paid_at) && !$isRefunded)
            <div class="alert alert-success d-flex align-items-center" role="alert">
              <i class="ph-check-circle me-2"></i>
              <div>
                این سفارش پرداخت شده است
                @if($order->paid_at)
                  — {{ \RMS\Helper\persian_date($order->paid_at, 'Y/m/d H:i') }}
                @endif
              </div>
            </div>
            <form id="cancel-refund-form" method="POST" action="{{ route('admin.shop.orders.update-status', ['order' => $order->id]) }}" class="d-flex gap-2 mt-2">
              @csrf
              @method('PUT')
              <input type="hidden" name="status" value="returned">
              <button type="button" class="btn btn-outline-danger w-100" data-refund-trigger="true"><i class="ph-arrow-counter-clockwise me-1"></i> لغو سفارش و بازگشت مبلغ</button>
            </form>
          @elseif($isRefunded)
            <div class="alert alert-info d-flex align-items-center" role="alert">
              <i class="ph-arrow-counter-clockwise me-2"></i>
              <div>
                این سفارش برگشت شده است — {{ \RMS\Helper\persian_date($order->refunded_at, 'Y/m/d H:i') }}
              </div>
            </div>
          @else
            @php $canUseCredit = (float)($user_credit ?? ($order->user_credit ?? 0)) >= (float)($order->total ?? 0); @endphp
            <form method="POST" action="{{ route('admin.shop.orders.charge', ['order' => $order->id]) }}" class="d-flex gap-2">
              @csrf
              <input type="hidden" name="mode" value="use_credit">
              @if($canUseCredit)
                <button type="submit" class="btn btn-outline-success w-100"><i class="ph-arrow-down me-1"></i> کاهش از اعتبار</button>
              @else
                <button type="button" class="btn btn-outline-success w-100" disabled title="اعتبار کافی نیست"><i class="ph-arrow-down me-1"></i> کاهش از اعتبار</button>
              @endif
            </form>
            <form method="POST" action="{{ route('admin.shop.orders.charge', ['order' => $order->id]) }}" class="d-flex gap-2 mt-2">
              @csrf
              <input type="hidden" name="mode" value="add_debt">
              <button type="submit" class="btn btn-outline-warning w-100"><i class="ph-arrow-up me-1"></i> افزایش بدهی</button>
            </form>
          @endif
        </div>
      </div>

      <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Confirm for finance charge forms (prevent default, then submit on OK)
        document.querySelectorAll('form[action$="/charge"]').forEach(function(frm){
          frm.addEventListener('submit', function(e){
            e.preventDefault();
            const mode = (frm.querySelector('input[name="mode"]')||{}).value;
            const title = mode === 'use_credit' ? 'کاهش از اعتبار' : 'افزایش بدهی';
            const msg = mode === 'use_credit'
              ? 'این سفارش از اعتبار کاربر کسر می‌شود. ادامه می‌دهید؟'
              : 'این سفارش به بدهی کاربر اضافه می‌شود. ادامه می‌دهید؟';
            if (typeof confirmAction === 'function') {
              Promise.resolve(confirmAction(title, msg, { icon: 'ph-warning', confirmClass: 'btn-primary', confirmText: 'تایید' }))
                .then(function(ok){ if (ok) frm.submit(); });
            } else {
              if (window.confirm(msg)) { frm.submit(); }
            }
          });
        });
      });
      </script>

      <!-- Customer Note -->
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><i class="ph-note text-teal me-2"></i> یادداشت مشتری</h6>
        </div>
        <div class="card-body">
          @php $cn = trim((string)($order->customer_note ?? '')); @endphp
          @if($cn !== '')
            <div class="text-muted small mb-1">ثبت‌شده هنگام ثبت سفارش</div>
            <div>{!! nl2br(e($cn)) !!}</div>
          @else
            <div class="text-muted">یادداشتی ثبت نشده است</div>
          @endif
        </div>
      </div>

      <!-- Admin Notes -->
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h6 class="mb-0"><i class="ph-note-pencil me-2 text-primary"></i> یادداشت‌های ادمین</h6>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.shop.orders.notes.add', ['order' => $order->id]) }}" class="mb-3">
            @csrf
            <label class="form-label">یادداشت جدید</label>
            <textarea name="note_text" class="form-control mb-2" rows="3" required></textarea>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="visibleSwitch" name="visible_to_user" value="1">
              <label class="form-check-label" for="visibleSwitch">نمایش به کاربر</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="ph-plus me-1"></i> افزودن</button>
          </form>

          <ul class="list-unstyled mb-0">
            @forelse(($notes ?? []) as $n)
              <li class="mb-3 p-2 border rounded">
                <div class="d-flex align-items-start">
                  <div class="flex-fill">
                    <div class="small text-muted mb-1">{{ $n->admin_name ?: 'ادمین' }} • {{ \RMS\Helper\persian_date($n->created_at, 'Y/m/d H:i') }}</div>
                    <div>{{ nl2br(e($n->note_text)) }}</div>
                    @if($n->visible_to_user)
                      <span class="badge bg-teal mt-1"><i class="ph-eye me-1"></i> نمایش به کاربر</span>
                    @else
                      <span class="badge bg-secondary mt-1"><i class="ph-eye-slash me-1"></i> فقط ادمین</span>
                    @endif
                  </div>
                  <div class="ms-2 d-flex flex-column gap-1">
                    <form method="POST" action="{{ route('admin.shop.orders.notes.update', ['order'=>$order->id,'note'=>$n->id]) }}">
                      @csrf @method('PUT')
                      <input type="hidden" name="visible_to_user" value="{{ $n->visible_to_user ? 0 : 1 }}">
                      <button class="btn btn-sm {{ $n->visible_to_user ? 'btn-outline-secondary' : 'btn-outline-teal' }}" title="تغییر نمایش">
                        <i class="{{ $n->visible_to_user ? 'ph-eye-slash' : 'ph-eye' }}"></i>
                      </button>
                    </form>
                    <form method="POST" action="{{ route('admin.shop.orders.notes.delete', ['order'=>$order->id,'note'=>$n->id]) }}">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" title="حذف"><i class="ph-trash"></i></button>
                    </form>
                  </div>
                </div>
              </li>
            @empty
              <li class="text-muted">یادداشتی ثبت نشده است</li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
  </div>
  <!-- Image Modal -->
  <div class="modal fade" id="orderItemImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-body p-0 position-relative">
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="p-2 text-center">
          <picture id="order-item-picture">
            <source id="order-item-source-avif" type="image/avif">
            <img id="order-item-image" class="img-fluid" alt="item-image">
          </picture>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection


