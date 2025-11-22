<b>سفارش جدید</b>

سفارش #{{ $order->id }} توسط {{ $order->user_name ?? ('کاربر #'.$order->user_id) }} ثبت شد.

مبلغ: <b>{{ number_format((float)$order->total, 0) }}</b> تومان

وضعیت: <b>{{ $order->status }}</b>
