<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\OrderInvoice;
use Backpack\Store\app\Services\Invoice\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {
    }

    public function preview(Request $request, $order): Response
    {
        $order = Order::findOrFail($order);
        // $this->authorizeOrder($order);

        $payload = $this->resolveInvoicePayload($order, $this->optionsFromRequest($request), false, true);
        $filename = $this->invoiceService->fileName($payload['invoice']);

        return response($payload['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    public function download(Request $request, $order)
    {
        $order = Order::findOrFail($order);
        // $this->authorizeOrder($order);

        $payload = $this->resolveInvoicePayload($order, $this->optionsFromRequest($request));

        return $this->invoiceService->downloadResponse($payload['invoice']);
    }

    public function qr(Request $request, $order)
    {
        $order = Order::findOrFail($order);
        // $this->authorizeOrder($order);

        $options = $this->optionsFromRequest($request);
        $payload = $this->resolveInvoicePayload($order, $options, true);
        $qr = $payload['qr'];

        return response($qr['binary'], 200, [
            'Content-Type' => $qr['mime'],
            'Content-Disposition' => sprintf('inline; filename="qr-%s.%s"', $payload['invoice']->getKey(), $qr['format']),
        ]);
    }

    public function previewFresh(Request $request, $order): Response
    {
        $order = Order::findOrFail($order);

        $result = $this->invoiceService->renderFresh($order, $this->optionsFromRequest($request));

        return response($result['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $result['filename']),
        ]);
    }

    public function template(Request $request, $order)
    {
        $order = Order::findOrFail($order);

        $result = $this->invoiceService->templateHtml($order, $this->optionsFromRequest($request));

        return response($result['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function downloadSigned(Request $request, $order, $invoice)
    {
        $order = Order::findOrFail($order);
        $invoice = OrderInvoice::find($invoice);

        if (!$invoice) {
            $invoice = $this->invoiceService->latestInvoice($order);
        }

        if (!$invoice) {
            abort(404, 'Invoice file not found.');
        }

        if ($invoice->order_id !== $order->getKey()) {
            abort(404);
        }

        $disk = config('dress.invoice.storage.disk', 'public');
        $storage = Storage::disk($disk);

        if (!$storage->exists($invoice->path)) {
            abort(404, 'Invoice file not found.');
        }

        return $storage->download($invoice->path, $this->invoiceService->fileName($invoice));
    }

    protected function optionsFromRequest(Request $request): array
    {
        return array_filter([
            'template' => $request->query('template'),
            'locale' => $request->query('locale'),
            'qr_format' => $request->query('format'),
            'force' => $request->boolean('regenerate'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function resolveInvoicePayload(Order $order, array $options, bool $needsQr = false, bool $needsBinary = false): array
    {
        $force = (bool) Arr::get($options, 'force', false);

        if (!$force) {
            $invoice = $this->invoiceService->latestInvoice($order);

            if ($invoice && $this->invoiceExists($invoice)) {
                $payload = [
                    'invoice' => $invoice,
                ];

                if ($needsBinary) {
                    $stream = $this->invoiceService->stream($invoice);
                    $payload['binary'] = $stream['stream'];
                }

                if ($needsQr) {
                    $qr = $this->invoiceService->existingQr($invoice);
                    if (!$qr) {
                        return $this->invoiceService->generate($order, $options);
                    }
                    $payload['qr'] = $qr;
                }

                return $payload;
            }
        }

        return $this->invoiceService->generate($order, $options);
    }

    protected function invoiceExists(OrderInvoice $invoice): bool
    {
        $disk = config('dress.invoice.storage.disk', 'public');
        return Storage::disk($disk)->exists($invoice->path);
    }

    protected function authorizeOrder(Order $order): void
    {
        if (backpack_auth()->check()) {
            return;
        }

        $guard = \Settings::get('dress.store.auth_guard', 'profile');
        $user = Auth::guard($guard)->user();

        if (!$user) {
            abort(403);
        }

        $matchesOrderable = $order->orderable_type === get_class($user)
            && (string) $order->orderable_id === (string) $user->getKey();

        $matchesEmail = Arr::get($order->info, 'user.email') && strtolower(Arr::get($order->info, 'user.email')) === strtolower((string) $user->email);

        if ($matchesOrderable || $matchesEmail) {
            return;
        }

        abort(403);
    }
}
