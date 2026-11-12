<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $document->invoiceId }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 36px 40px;
            line-height: 1.45;
        }
        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .brand {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #111827;
        }
        .subtitle {
            margin-top: 4px;
            color: #6b7280;
            font-size: 10px;
        }
        .meta-grid {
            width: 100%;
            margin-bottom: 22px;
        }
        .meta-grid td {
            vertical-align: top;
            padding: 0;
        }
        .meta-label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 11px;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-transform: uppercase;
            font-size: 9px;
            font-weight: bold;
        }
        .void-banner {
            margin-bottom: 18px;
            padding: 10px 12px;
            border: 2px solid #b91c1c;
            color: #991b1b;
            background: #fef2f2;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.items th {
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            text-align: left;
            padding: 8px 6px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .description { font-weight: bold; }
        .muted { color: #6b7280; font-size: 9px; }
        .totals {
            width: 280px;
            margin-left: auto;
            margin-top: 18px;
        }
        .totals td { padding: 6px 0; }
        .totals .label { color: #6b7280; }
        .totals .grand {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #111827;
            padding-top: 8px;
        }
        .footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">FLeetX</div>
        <div class="subtitle">Monthly Invoice</div>
    </div>

    @if($document->status === 'void')
        <div class="void-banner">VOID — This invoice is no longer valid for billing</div>
    @endif

    <table class="meta-grid">
        <tr>
            <td style="width: 55%;">
                <div class="meta-label">Customer</div>
                <div class="meta-value"><strong>{{ $document->companyName }}</strong></div>
            </td>
            <td style="width: 45%;">
                <div class="meta-label">Invoice Reference</div>
                <div class="meta-value"><strong>#{{ $document->invoiceId }}</strong></div>

                <div class="meta-label">Status</div>
                <div class="meta-value"><span class="status-badge">{{ $document->status }}</span></div>

                <div class="meta-label">Billing Period</div>
                <div class="meta-value">{{ $document->billingPeriodStart }} to {{ $document->billingPeriodEnd }}</div>

                <div class="meta-label">Currency</div>
                <div class="meta-value">{{ $document->currency }}</div>

                @if($document->issuedAtFormatted)
                    <div class="meta-label">Issued At</div>
                    <div class="meta-value">{{ $document->issuedAtFormatted }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 20%;">Shipment</th>
                <th style="width: 36%;">Description</th>
                <th class="text-right" style="width: 8%;">Qty</th>
                <th class="text-right" style="width: 18%;">Unit Price</th>
                <th class="text-right" style="width: 18%;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($document->lineItems as $lineItem)
                <tr>
                    <td>{{ $lineItem->shipmentReference ?? '—' }}</td>
                    <td class="description">{{ $lineItem->description }}</td>
                    <td class="text-right">{{ $lineItem->quantity }}</td>
                    <td class="text-right">{{ $lineItem->unitPriceFormatted }}</td>
                    <td class="text-right">{{ $lineItem->lineTotalFormatted }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">No line items recorded on this invoice.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="text-right">{{ $document->subtotalFormatted }}</td>
        </tr>
        <tr>
            <td class="grand">Total</td>
            <td class="text-right grand">{{ $document->totalFormatted }}</td>
        </tr>
    </table>

    <div class="footer">
        This document reflects the billed amount at the time of invoicing.
    </div>
</body>
</html>
