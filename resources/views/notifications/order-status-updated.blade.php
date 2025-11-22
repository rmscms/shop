<b>بروزرسانی سفارش</b>

سفارش #{{ $order->id }}

وضعیت: <b>{{ $new }}</b>

@if(!empty($order->tracking_code))
کد رهگیری: <code>{{ $order->tracking_code }}</code>
@endif
@if(!empty($order->tracking_url))

<a href="{{ $order->tracking_url }}">مشاهده رهگیری</a>
@endif
