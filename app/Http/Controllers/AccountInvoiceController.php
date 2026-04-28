<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        return view('account.invoices.index', [
            'invoices' => Invoice::with(['order.payments'])
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
        $invoice->load(['order.user', 'order.items.package', 'order.items.purchasable', 'order.payments']);
        abort_unless($invoice->order?->user_id === $request->user()->id, 403);

        return view('account.invoices.show', [
            'invoice' => $invoice,
            'order' => $invoice->order,
            'payment' => $invoice->order?->payments->sortByDesc('id')->first(),
        ]);
    }
}
