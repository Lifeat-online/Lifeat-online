<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        return view('account.invoices.index', [
            'invoices' => Invoice::with(['order.payments', 'order.renewedSubscription.package'])
                ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $invoice->load([
            'order.user',
            'order.items.package',
            'order.items.purchasable',
            'order.payments.attempts',
            'order.renewedSubscription.package',
        ]);
        abort_unless($invoice->order, 404);
        Gate::authorize('manage', $invoice->order);

        $paymentAttempts = $invoice->order->payments
            ->flatMap(fn ($payment) => $payment->attempts)
            ->sortByDesc('attempted_at')
            ->values();

        return view('account.invoices.show', [
            'invoice' => $invoice,
            'order' => $invoice->order,
            'payment' => $invoice->order?->payments->sortByDesc('id')->first(),
            'paymentAttempts' => $paymentAttempts,
        ]);
    }
}
