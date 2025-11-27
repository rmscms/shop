<?php

namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Shop\Http\Controllers\Admin\ShopAdminController;
use RMS\Shop\Models\Order;
use RMS\Core\Models\Setting;
use Illuminate\Database\Query\Builder;
use RMS\Core\Contracts\Export\ShouldExport;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Stats\HasStats;
use RMS\Core\Data\Field;
use RMS\Core\Data\StatCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use RMS\PDF\PDFFacade as PDF;
use RMS\PDF\{Seller, Buyer, Invoice};

class OrdersController extends ShopAdminController implements HasList, ShouldFilter, HasStats, ShouldExport
{
    public function table(): string { return 'orders'; }
    public function modelName(): string { return Order::class; }
    public function baseRoute(): string { return 'shop.orders'; }
    public function routeParameter(): string { return 'order'; }

    public function getListConfig(): array
    {
        return [
            'simple_pagination' => true,
            'show_create' => false,
        ];
    }

    public function query(Builder $sql): void
    {
        $itemsCount = DB::raw('(select count(*) from order_items oi where oi.order_id = a.id) as items_count');
        $sql->leftJoin('users','a.user_id','=','users.id')
            ->addSelect('a.*','users.name as user_name',$itemsCount)
            ->orderByDesc('a.id');
    }

    public function getListFields(): array
    {
        $statusOptions = ['' => trans('shop.common.all')] + \RMS\Shop\Models\Order::statusOptions();

        return [
            Field::make('id','a.id')->withTitle(trans('shop.common.id'))->sortable()->width('90px'),

            Field::make('user','users.name')
                ->withTitle(trans('admin.user') ?: 'کاربر')
                ->customMethod('renderUser')
                ->searchable()
                ->width('25%'),

            Field::make('items_count', '')
                ->withTitle(trans('admin.items') ?: 'آیتم‌ها')
                ->type(Field::NUMBER)
                ->skipDatabase()
                ->width('120px'),

            Field::make('subtotal','a.subtotal')->withTitle(trans('admin.amount') ?: 'جمع')->type(Field::PRICE)->width('130px')->sortable(),
            Field::make('discount','a.discount')->withTitle('تخفیف')->type(Field::PRICE)->width('120px')->sortable(),
            Field::make('total','a.total')->withTitle('مبلغ نهایی')->type(Field::PRICE)->width('140px')->sortable(),

            Field::select('status','a.status')
                ->withTitle(trans('admin.status') ?: 'وضعیت')
                ->setOptions($statusOptions)
                ->filterType(Field::SELECT)
                ->customMethod('renderStatusBadge')
                ->width('140px'),

            Field::make('paid_at','a.paid_at')->withTitle('تاریخ پرداخت')->type(Field::DATE_TIME)->filterType(Field::DATE_TIME)->width('170px'),
            Field::make('created_at','a.created_at')->withTitle(trans('admin.created_at'))->type(Field::DATE_TIME)->filterType(Field::DATE_TIME)->width('170px'),

            Field::make('flags','')
                ->withTitle('')
                ->skipDatabase()
                ->customMethod('renderFlags')
                ->width('80px'),
        ];
    }

    public function renderUser($row): string
    {
        if (!$row->user_id) { return '<span class="text-muted">-</span>'; }
        $name = e($row->user_name ?: ('#'.$row->user_id));
        $url = route('admin.users.edit', ['user' => (int)$row->user_id]);
        return '<a href="'.$url.'" target="_blank">'.$name.'</a>';
    }

    public function getStats(?Builder $query = null): array
    {
        $base = $query ?: \DB::table('orders');
        $total = (clone $base)->count();
        $pending = (clone $base)->where('status','pending')->count();
        $paid = (clone $base)
            ->whereNotNull('paid_at')
            ->whereNull('refunded_at')
            ->count();
        $sumTotal = (clone $base)->sum('total');

        return [
            StatCard::make('کل سفارش‌ها', number_format((int)$total))->withIcon('shopping-bag')->withColor('primary'),
            StatCard::make('در انتظار', number_format((int)$pending))->withIcon('clock')->withColor('warning'),
            StatCard::make('پرداخت‌شده', number_format((int)$paid))->withIcon('check-circle')->withColor('success'),
            StatCard::make('جمع مبلغ', number_format((float)$sumTotal))->withIcon('currency-circle-dollar')->withColor('info')->withDescription(trans('admin.based_on_active_filters')),
        ];
    }

    // حذف اکشن ادیت از لیست چون صفحه edit نداریم
    protected function beforeGenerateList(\RMS\Core\View\HelperList\Generator &$generator): void
    {
        parent::beforeGenerateList($generator);
        $generator->removeActions('edit');
        $generator->removeActions('destroy');
    }

    // Render status badge in list using Order::statuses()
    public function renderStatusBadge($row): string
    {
        $map = \RMS\Shop\Models\Order::statuses();
        $code = (string)($row->status ?? '');
        $info = $map[$code] ?? (object)['label'=>$code, 'class'=>'bg-secondary'];
        $label = e($info->label);
        $class = e($info->class);
        return '<span class="badge '.$class.'">'.$label.'</span>';
    }

    public function renderFlags($row): string
    {
        $out = '';
        // Tracking icon
        if (!empty($row->tracking_code)) {
            $out .= '<i class="ph-package text-secondary me-1" title="Tracking"></i>';
        }
        // Visible notes count
        $cnt = \DB::table('order_admin_notes')->where(['order_id'=>(int)$row->id,'visible_to_user'=>1])->count();
        if ($cnt > 0) {
            $out .= '<span class="badge bg-teal" title="Notes">'.$cnt.'</span>';
        }
        return $out ?: '<span class="text-muted">—</span>';
    }

    // ----- Custom Detail Page -----
    public function show(Request $request, int $id)
    {
        $data = \RMS\Shop\Services\OrderViewService::adminShow((int)$id);
        // Live user finance snapshot for badges and actions
        $userCredit = 0; $userDebt = 0;
        try {
            $u = DB::table('users')->where('id', (int)$data['order']->user_id)->first(['credit','debt']);
            $currentDebt = (int) DB::table('finances')->where('user_id', (int)$data['order']->user_id)->where('paid', 0)->where('down', false)->sum('amount');
            $userCredit = (int)($u->credit ?? 0);
            $userDebt = (int)($u->debt ?? $currentDebt);
        } catch (\Throwable $e) {}

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('orders.show')
            ->withPlugins(['confirm-modal', 'amount-formatter'])
            ->withCss('vendor/shop/admin/css/orders.css', true)
            ->withJs('vendor/shop/admin/js/orders-show.js', true)
            ->withVariables([
                'order' => $data['order'],
                'items' => $data['items'],
                'status' => $data['status'],
                'notes' => $data['notes'],
                'points_sum' => $data['points_sum'],
                'points_applied' => (bool)$data['points_applied'],
                'user_points_total' => (int)$data['user_points_total'],
                'user_credit' => $userCredit,
                'user_debt' => $userDebt,
            ])
            ->withJsVariables([
                'orderId' => (int)$data['order']->id,
                'apiEndpoints' => [
                    'add_note' => route('admin.shop.orders.notes.add', (int)$data['order']->id),
                    'update_tracking' => route('admin.shop.orders.tracking.update', (int)$data['order']->id),
                    'whatsapp' => route('admin.shop.orders.whatsapp', (int)$data['order']->id),
                    'charge' => route('admin.shop.orders.charge', (int)$data['order']->id),
                ],
            ]);

        return $this->view();
    }

    public function invoice(Request $request, int $id)
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as user_name','u.credit as user_credit','u.debt as user_debt')
            ->where('o.id',(int)$id)
            ->first();
        abort_if(!$order,404);

        // Build seller from config or fallback
        $seller = new Seller([
            'company' => config('app.name'),
            'address' => config('company.address', ''),
            'postal_code' => config('company.postal_code', ''),
            'phone' => config('company.phone', ''),
            'bank' => [
                'account_holder' => config('company.bank.account_holder', config('app.name')),
                'card_number' => config('company.bank.card_number', ''),
                'sheba' => config('company.bank.sheba', ''),
            ],
        ]);

        // Buyer from order shipping
        $buyer = new Buyer([
            'name' => $order->shipping_name ?: ($order->user_name ?? ''),
            'address' => $order->shipping_address ?: '',
            'postal_code' => $order->shipping_postal_code ?: '',
            'phone' => $order->shipping_mobile ?: '',
        ]);

        // Items
        $rows = DB::table('order_items')->where('order_id',(int)$order->id)->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
'description' => (string)($r->item_name ?: (DB::table('products')->where('id',$r->product_id)->value('name') ?: ('#'.$r->product_id))),
                'quantity' => (int) $r->qty,
                'unit_price' => (float) $r->unit_price,
                'total' => (float) $r->total,
            ];
        }

        // Prepare data for 'cube' theme (expects arrays, not objects)
        $sellerArr = [
            'company' => (string)$seller->company,
            'address' => (string)$seller->address,
            'postal_code' => (string)$seller->postal_code,
            'phone' => (string)$seller->phone,
        ];
        $bankArr = [
            'account_holder' => (string)($seller->bank->account_holder ?? ''),
            'card_number' => (string)($seller->bank->card_number ?? ''),
            'sheba' => (string)($seller->bank->sheba ?? ''),
        ];
        $buyerArr = [
            'name' => (string)$buyer->name,
            'address' => (string)$buyer->address,
            'postal_code' => (string)$buyer->postal_code,
            'phone' => (string)$buyer->phone,
        ];

        $data = [
            'title' => 'فاکتور فروش',
            'invoice_number' => 'ORD-'.$order->id,
            'invoice_date' => \RMS\Helper\persian_date($order->created_at, 'Y/m/d'),
            'seller' => $sellerArr,
            'buyer' => $buyerArr,
            'items' => $items,
            'total_services' => (float)$order->subtotal,
            'postage_cost' => (float)$order->shipping_cost,
            'discount' => (float)$order->discount,
            'discount_note' => '',
            'final_amount' => (float)$order->total,
            'bank' => $bankArr,
            'footer_left' => 'مهر و امضای فروشنده',
            'footer_right' => 'امضای خریدار',
        ];

        return PDF::setFont('vazir')
            ->enableRTL()
            ->loadTheme('cube', $data)
            ->download('invoice-ORD-'.$order->id.'.pdf');
    }

    public function label(Request $request, int $id)
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as user_name')
            ->where('o.id',(int)$id)
            ->first();
        abort_if(!$order,404);

        $receiver = [
            'name' => (string)($order->shipping_name ?: ($order->user_name ?? '')),
            'phone' => (string)($order->shipping_mobile ?: ''),
            'postal_code' => (string)($order->shipping_postal_code ?? ''),
            'address' => (string)($order->shipping_address ?? ''),
        ];
        $sender = [
            'company' => (string) config('company.name', config('app.name')),
            'address' => (string) config('company.address', ''),
            'postal_code' => (string) config('company.postal_code', ''),
            'phone' => (string) config('company.phone', ''),
        ];

        $data = [
            'order_id' => 'ORD-'.$order->id,
            'receiver' => $receiver,
            'sender' => $sender,
            'note' => '',
        ];

        return PDF::setFont('vazir')
            ->setFormat('A6')
            ->setOrientation('P')
            ->setMargins(4,4,4,4)
            ->enableRTL()
            ->loadTheme('shipping-label-a6', $data)
            ->download('label-ORD-'.$order->id.'.pdf');
    }

    // Update order status + fire event
    public function updateStatus(\RMS\Shop\Http\Requests\Admin\OrderUpdateStatusRequest $request, int $id)
    {
        $data = $request->validated();
        \RMS\Shop\Services\OrderAdminService::updateStatus((int)$id, (string)$data['status'], (int)auth('admin')->id());
        return back()->with('success', trans('admin.updated_successfully'));
    }

    // ----- Admin Notes -----
    public function addNote(Request $request, int $id)
    {
        $data = $request->validate([
            'note_text' => ['required','string','max:5000'],
            'visible_to_user' => ['nullable','boolean'],
        ]);
        $noteId = \DB::table('order_admin_notes')->insertGetId([
            'order_id' => (int)$id,
            'admin_id' => auth('admin')->id(),
            'note_text' => (string)$data['note_text'],
            'visible_to_user' => (bool)($data['visible_to_user'] ?? false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', trans('admin.saved'));
    }

    public function updateNote(Request $request, int $id, int $note)
    {
        $data = $request->validate([
            'note_text' => ['sometimes','string','max:5000'],
            'visible_to_user' => ['sometimes','boolean'],
        ]);
        \DB::table('order_admin_notes')->where(['id'=>$note,'order_id'=>(int)$id])->update(array_merge($data,[
            'updated_at' => now(),
        ]));
        return back()->with('success', trans('admin.updated_successfully'));
    }

    public function deleteNote(Request $request, int $id, int $note)
    {
        \DB::table('order_admin_notes')->where(['id'=>$note,'order_id'=>(int)$id])->delete();
        return back()->with('success', trans('admin.deleted_successfully'));
    }

    // ----- Tracking -----
    public function updateTracking(\RMS\Shop\Http\Requests\Admin\OrderUpdateTrackingRequest $request, int $id)
    {
        $data = $request->validated();
        \RMS\Shop\Services\OrderAdminService::updateTracking((int)$id, $data['tracking_code'] ?? null, $data['tracking_url'] ?? null);
        return back()->with('success', trans('admin.updated_successfully'));
    }

    // ----- WhatsApp -----
    public function whatsapp(Request $request, int $id)
    {
        $order = \DB::table('orders')->where('id',(int)$id)->first();
        abort_if(!$order,404);
        $user = \DB::table('users')->where('id',(int)$order->user_id)->first();

        // phone from shipping address/mobile, fallback to user.mobile
        $phone = (string)($order->shipping_mobile ?? ($user->mobile ?? ''));
        $phone = preg_replace('/\D+/', '', $phone ?? '');
        if (str_starts_with($phone, '0')) { $phone = substr($phone, 1); }
        if (!str_starts_with($phone, '+')) { $phone = '+98' . $phone; }

        // Build message from setting template (Blade) with safe fallback
        $tpl = (string) Setting::get('shop_whatsapp_order_update_template', '');
        $defaultTpl = <<<'BLADE'
🔔 بروزرسانی سفارش #{{ $order->id }}
⏳ وضعیت: {{ $order->status }}
@if(!empty($order->tracking_code))
📦 کد رهگیری: {{ $order->tracking_code }}
@endif
@if(!empty($order->tracking_url))
🔗 لینک رهگیری: {{ $order->tracking_url }}
@endif
BLADE;
        if (trim($tpl) === '') { $tpl = $defaultTpl; }
        try {
            $message = Blade::render($tpl, ['order' => $order]);
        } catch (\Throwable $e) {
            // Fallback to default template if custom one has syntax errors
            $message = Blade::render($defaultTpl, ['order' => $order]);
        }

        // Ensure UTF-8 percent-encoding for WhatsApp
        $encoded = rawurlencode(mb_convert_encoding($message, 'UTF-8', 'UTF-8'));
        $phoneParam = preg_replace('/\D+/', '', (string)$phone);
        $url = 'https://api.whatsapp.com/send?phone=' . $phoneParam . '&text=' . $encoded;
        return redirect()->away($url);
    }

    // ----- Finance (service-based) -----
    public function charge(Request $request, int $id)
    {
        $data = $request->validate([
            'mode' => ['required','in:use_credit,add_debt'],
        ]);
        $order = \RMS\Shop\Models\Order::findOrFail((int)$id);
        // Prevent double-charge if already paid or refunded
        if (!empty($order->paid_at)) {
            return back()->with('error', 'این سفارش قبلاً پرداخت شده است');
        }
        if (!empty($order->refunded_at)) {
            return back()->with('error', 'این سفارش برگشت/لغو شده است');
        }
        $finance = app(\RMS\Shop\Services\OrderFinanceService::class)
            ->charge($order, $data['mode'], (int)auth('admin')->id());
        return back()->with('success', trans('admin.updated_successfully'));
    }

    // ----- Discount -----
    public function applyDiscount(\RMS\Shop\Http\Requests\Admin\OrderApplyDiscountRequest $request, int $id)
    {
        $amountStr = (string) ($request->input('amount') ?? $request->input('amount_display') ?? '0');
        $amount = (float) preg_replace('/[^\d.]/','', $amountStr);
        \RMS\Shop\Services\OrderAdminService::applyDiscount((int)$id, (float)$amount, $request->input('note'), (int)auth('admin')->id());
        return back()->with('success', trans('admin.updated_successfully'));
    }

    // ----- Points -----
    public function applyPoints(\RMS\Shop\Http\Requests\Admin\OrderApplyPointsRequest $request, int $id)
    {
        $calc = (int) DB::table('order_items')->where('order_id',(int)$id)->sum('points_awarded');
        $reqPoints = (int) max(0, (int)$request->input('points', 0));
        $points = $reqPoints > 0 ? $reqPoints : $calc;
        \RMS\Shop\Services\OrderAdminService::applyPoints((int)$id, (int)$points);
        return back()->with('success', 'امتیازها اعمال شد');
    }
}
