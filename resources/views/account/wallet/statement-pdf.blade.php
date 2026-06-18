<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lifeat Payout Statement</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #1f2937; font-size: 11px; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0 0 4px 0; color: #111827; }
        h2 { font-size: 14px; margin: 22px 0 8px 0; color: #111827; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 16px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grid td { padding: 8px 10px; border: 1px solid #e5e7eb; vertical-align: top; }
        .grid .label { color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        .grid .value { font-size: 16px; font-weight: 700; color: #111827; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { text-align: left; background: #f3f4f6; color: #374151; font-size: 10px; text-transform: uppercase; padding: 6px 8px; border: 1px solid #e5e7eb; }
        table.data td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; }
        .status-requested { color: #b45309; font-weight: 700; }
        .status-paid { color: #047857; font-weight: 700; }
        .status-cancelled, .status-rejected { color: #b91c1c; font-weight: 700; }
        .empty { color: #9ca3af; font-style: italic; padding: 8px 0; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Payout Statement</h1>
    <div class="meta">
        Generated {{ $generatedAt->format('Y-m-d H:i') }} · Wallet #{{ $wallet->id }} · {{ $holder->name }} &lt;{{ $holder->email }}&gt;
    </div>

    <table class="grid">
        <tr>
            <td style="width:33%;">
                <div class="label">Available balance</div>
                <div class="value">{{ $wallet->currency }} {{ number_format($wallet->available_balance, 2) }}</div>
            </td>
            <td style="width:33%;">
                <div class="label">Pending</div>
                <div class="value">{{ $wallet->currency }} {{ number_format($wallet->pending_balance, 2) }}</div>
            </td>
            <td style="width:34%;">
                <div class="label">Total paid out</div>
                <div class="value">{{ $wallet->currency }} {{ number_format($wallet->paid_out_total, 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Payout requests</h2>
    @if ($payoutRequests->isEmpty())
        <div class="empty">No payout requests on file.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Requested</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Bank</th>
                    <th>Account</th>
                    <th>Processed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payoutRequests as $req)
                    <tr>
                        <td>{{ optional($req->requested_at)->format('Y-m-d') ?: '—' }}</td>
                        <td class="right">{{ $req->currency }} {{ number_format((float) $req->amount, 2) }}</td>
                        <td><span class="status-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
                        <td>{{ $req->bank_name ?: '—' }}</td>
                        <td>{{ $req->account_number ? str_repeat('•', max(strlen($req->account_number) - 4, 0)).substr($req->account_number, -4) : '—' }}</td>
                        <td>{{ optional($req->processed_at)->format('Y-m-d') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Recent ledger entries</h2>
    @if ($ledgerEntries->isEmpty())
        <div class="empty">No ledger entries on file.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Recorded</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ledgerEntries as $entry)
                    <tr>
                        <td>{{ optional($entry->recorded_at)->format('Y-m-d') ?: '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', (string) $entry->entry_type)) }}</td>
                        <td>{{ class_basename((string) $entry->source_type) }}#{{ $entry->source_id ?: '—' }}</td>
                        <td>{{ $entry->description ?: '—' }}</td>
                        <td class="right">{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Lifeat · Generated by the wallet owner. This statement is for personal record-keeping.
    </div>
</body>
</html>
