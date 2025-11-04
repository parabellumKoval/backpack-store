<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Log;
 
class OrderCreatedListener
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {
    }
 
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        if (!$order->requiresInvoice()) {
            return;
        }

        try {
            $this->invoiceService->generate($order);
        } catch (\Throwable $exception) {
            Log::error('Failed to generate invoice for order', [
                'order_id' => $order->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
