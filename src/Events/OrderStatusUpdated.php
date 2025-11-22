<?php

namespace RMS\Shop\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $orderId;
    public string $oldStatus;
    public string $newStatus;
    public ?int $adminId;
    public string $changedAt;

    public function __construct(int $orderId, string $oldStatus, string $newStatus, ?int $adminId = null)
    {
        $this->orderId = $orderId;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->adminId = $adminId;
        $this->changedAt = now()->toDateTimeString();
    }
}
