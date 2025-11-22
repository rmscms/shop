<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use RMS\Shop\Models\Order;

class OrderFinanceService
{
    /**
     * Apply finance action for an order and link finance_id on order.
     * $mode: 'use_credit' | 'add_debt'
     */
    public function charge(Order $order, string $mode, int $adminId): Finance
    {
        $mode = in_array($mode, ['use_credit','add_debt'], true) ? $mode : 'use_credit';

        return DB::transaction(function () use ($order, $mode, $adminId) {
            if ($order->finance_id) {
                // Already processed
                return Finance::findOrFail((int)$order->finance_id);
            }

            /** @var User $user */
            $user = User::findOrFail((int)$order->user_id);
            $amount = (int) round((float)$order->total);

            // Build finance row according to legacy logic in FinancesController@afterAdd
            $title = $mode === 'use_credit'
                ? (trans('admin.finances.default_titles.decrease_credit') . ' بابت سفارش #' . (int)$order->id)
                : ('افزایش بدهی بابت سفارش #' . (int)$order->id);
            $payload = [
                'title' => $title,
                'user_id' => (int)$user->id,
                'admin_id' => (int)$adminId,
                'amount' => $amount,
                'spend' => true, // expense record for the user (both credit and debt flows)
                'paid' => $mode === 'use_credit' ? 1 : 0,
                'balance' => (int)($user->credit ?? 0),
                'down' => $mode === 'use_credit',
                'debt_down' => 0,
                // Important: avoid NULLs to satisfy NOT NULL columns (e.g., account_id)
                'account_id' => 0,
                'protocol' => 10, // 10 => Shop
                'bank_id' => 0,
                // Link back to the originating shop order for traceability
                'order_id' => (int)$order->id,
            ];

            /** @var Finance $finance */
            $finance = Finance::create($payload);

            // Apply balance/debt changes similar to FinancesController logic
            if ($mode === 'use_credit') {
                $newCredit = (int)$user->credit - $amount;
                $user->update(['credit' => $newCredit]);
                $finance->update([
                    'balance' => $newCredit,
                    'title' => trans('admin.finances.default_titles.decrease_credit') . ' بابت سفارش #' . (int)$order->id,
                    'spend' => true,
                ]);
            } else {
                // add debt
                $user->update(['debt' => (int)$user->debt + $amount]);
                $finance->update([
                    'balance' => (int)$user->credit,
                    'title' => 'افزایش بدهی بابت سفارش #' . (int)$order->id,
                    'spend' => true,
                ]);
            }

            // Mark order as paid (service-based) and link finance
            $prev = (string)($order->status ?? 'pending');
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'finance_id' => (int)$finance->id,
            ]);

            // Fire status update event if changed
            if ($prev !== 'paid') {
                event(new \App\Events\OrderStatusUpdated((int)$order->id, $prev, 'paid', $adminId));
            }

            return $finance;
        });
    }
}
