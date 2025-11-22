@extends('cms::admin.layout.index')

@section('content')
<div class="content">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="mb-0">تنظیمات شاپ</h6>
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('admin.shop.settings.update') }}" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-12">
          <label class="form-label fw-semibold">فعال بودن خرید</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="purchase_enabled" name="purchase_enabled" value="1" {{ $purchase_enabled ? 'checked' : '' }}>
            <label class="form-check-label" for="purchase_enabled">در صورت غیرفعال بودن، دکمه‌های خرید و افزودن به سبد قفل می‌شوند.</label>
          </div>
          @if(!$purchase_enabled)
            <div class="form-text text-danger">خرید هم‌اکنون غیرفعال است.</div>
          @endif
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold" for="shipping_flat">هزینه حمل ثابت</label>
          <div class="input-group">
            <input type="text" inputmode="numeric" dir="ltr" class="form-control text-start order-1" id="shipping_flat" name="shipping_flat" value="{{ number_format((int) $shipping_flat, 0, '.', ',') }}" required>
            <span class="input-group-text order-2">تومان</span>
          </div>
          <div class="form-text">در صفحه سبد و پرداخت به مجموع اضافه می‌شود (اگر سبد خالی نباشد).</div>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold" for="telegram_order_template">تمپلیت پیام تلگرام سفارش جدید</label>
          <textarea class="form-control" id="telegram_order_template" name="telegram_order_template" rows="8" required>{{ $telegram_order_template }}</textarea>
          <div class="form-text">
            متغیرهای موجود: <code>${{ '$order->id' }}</code>, <code>${{ '$order->user_name' }}</code>, <code>${{ '$order->user_id' }}</code>, <code>${{ '$order->total' }}</code>, <code>${{ '$order->status' }}</code><br>
            از HTML برای فرمت‌بندی استفاده کنید: <code>&lt;b&gt;</code>, <code>&lt;i&gt;</code>
          </div>
        </div>

        <!-- Telegram: Order Update Template -->
        <div class="col-12">
          <label class="form-label fw-semibold" for="telegram_order_update_template">تمپلیت پیام تلگرام بروزرسانی سفارش</label>
          <textarea class="form-control" id="telegram_order_update_template" name="telegram_order_update_template" rows="7" required>{{ $telegram_order_update_template ?? '' }}</textarea>
          <div class="form-text">
            متغیرها: <code>${{ '$order->id' }}</code>, <code>${{ '$old' }}</code>, <code>${{ '$new' }}</code>, <code>${{ '$order->tracking_code' }}</code>, <code>${{ '$order->tracking_url' }}</code>
          </div>
        </div>

        <!-- WhatsApp: Order Update Template -->
        <div class="col-12">
          <label class="form-label fw-semibold" for="whatsapp_order_update_template">تمپلیت واتس‌اپ بروزرسانی سفارش (و کد رهگیری)</label>
          <textarea class="form-control" id="whatsapp_order_update_template" name="whatsapp_order_update_template" rows="7" required>{{ $whatsapp_order_update_template ?? '' }}</textarea>
          <div class="form-text">
            متغیرها: <code>${{ '$order->id' }}</code>, <code>${{ '$order->status' }}</code>, <code>${{ '$order->tracking_code' }}</code>, <code>${{ '$order->tracking_url' }}</code>
          </div>
        </div>

        <div class="col-12 mt-3">
          <div class="d-flex justify-content-end gap-2">
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm px-3">انصراف</a>
            <button type="submit" class="btn btn-primary btn-sm px-3">ذخیره</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  function formatWithCommas(n){
    n = (n||'').replace(/[^0-9]/g,'');
    return n.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
  document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('shipping_flat');
    if (!el) return;
    el.value = formatWithCommas(el.value||'');
    el.addEventListener('input', function(){
      var pos = el.selectionStart;
      var raw = el.value;
      var beforeLen = raw.length;
      el.value = formatWithCommas(raw);
      var afterLen = el.value.length;
      try { el.setSelectionRange(pos + (afterLen - beforeLen), pos + (afterLen - beforeLen)); } catch(_){}
    });
    var form = el.closest('form');
    if (form) form.addEventListener('submit', function(){ el.value = (el.value||'').replace(/[^0-9]/g,''); });
  });
})();
</script>
@endpush
@endsection
