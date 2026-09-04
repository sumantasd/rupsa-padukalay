<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'RUPSA ERP Report' }}</title>
    <style>
        @page {
            margin: 12mm 10mm 15mm 10mm;
            size: A4 portrait;
        }

        * {
            font-family: 'DejaVu Sans', sans-serif !important;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif !important;
            font-size: 9px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        table, th, td, div, span, p {
            font-family: 'DejaVu Sans', sans-serif !important;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-b: 2px solid #dc2626;
            padding-bottom: 8px;
        }

        .brand-title {
            font-size: 18px;
            font-weight: bold;
            color: #991b1b;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .brand-subtitle {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .report-title-badge {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
        }

        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .meta-table td {
            padding: 2px 4px;
        }

        .meta-label {
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }

        .meta-val {
            font-weight: bold;
            color: #0f172a;
        }

        .kpi-container {
            width: 100%;
            margin-bottom: 14px;
        }

        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 8px;
            text-align: center;
        }

        .kpi-label {
            font-size: 8px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .kpi-value {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            font-family: 'DejaVu Sans', sans-serif !important;
        }

        /* Financial Statement / Profit Loss */
        .financial-statement {
            background-color: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
        }

        .statement-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .statement-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .statement-row-bold {
            font-weight: bold;
            color: #0f172a;
            background-color: #f8fafc;
        }

        .banner-profit {
            background-color: #dcfce7;
            border: 2px solid #16a34a;
            color: #15803d;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .banner-loss {
            background-color: #fee2e2;
            border: 2px solid #dc2626;
            color: #b91c1c;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            text-transform: uppercase;
        }

        /* Report Main Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9px;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table tr {
            page-break-inside: avoid;
        }

        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            padding: 6px 8px;
            border: 1px solid #0f172a;
            text-align: left;
        }

        .data-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'DejaVu Sans Mono', 'DejaVu Sans', monospace !important; }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="brand-title">RUPSA PADUKALAYA</div>
                <div class="brand-subtitle">Retail & Wholesale Footwear ERP • {{ $store_name }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="report-title-badge">{{ $title }}</div>
            </td>
        </tr>
    </table>

    <!-- Metadata Box -->
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td style="width: 15%;" class="meta-label">Selected Period:</td>
                <td style="width: 35%;" class="meta-val">{{ $period_label }}</td>
                <td style="width: 15%;" class="meta-label">Generated On:</td>
                <td style="width: 35%;" class="meta-val">{{ $generated_at }}</td>
            </tr>
            <tr>
                <td class="meta-label">Store / Outlet:</td>
                <td class="meta-val">{{ $store_name }}</td>
                <td class="meta-label">Generated By:</td>
                <td class="meta-val">{{ $user_name }}</td>
            </tr>
        </table>
    </div>

    <!-- KPI Cards Summary Box -->
    @if(!empty($kpis) && count($kpis) > 0)
    <div class="kpi-container">
        <table class="kpi-table">
            <tr>
                @foreach($kpis as $kpi)
                <td class="kpi-card">
                    <div class="kpi-label">{{ $kpi['label'] }}</div>
                    <div class="kpi-value">{{ $kpi['value'] }}</div>
                </td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    <!-- Financial Statement (Profit & Loss Special Branding) -->
    @if(!empty($financial_statement))
    <div class="financial-statement">
        <div style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-bottom: 8px;">
            STATEMENT OF PROFIT & LOSS CALCULATION
        </div>
        <table class="statement-table">
            <tr>
                <td>Gross Billed Sales Revenue</td>
                <td class="text-right font-mono">₹{{ number_format($financial_statement['gross_sales'], 2) }}</td>
            </tr>
            <tr>
                <td>Less: Discounts & Special Offers</td>
                <td class="text-right font-mono" style="color: #dc2626;">- ₹{{ number_format($financial_statement['discounts'], 2) }}</td>
            </tr>
            <tr>
                <td>Less: Customer Sales Returns & Refunds</td>
                <td class="text-right font-mono" style="color: #dc2626;">- ₹{{ number_format($financial_statement['returns'], 2) }}</td>
            </tr>
            <tr class="statement-row-bold">
                <td><strong>= NET REVENUE / NET SALES</strong></td>
                <td class="text-right font-mono"><strong>₹{{ number_format($financial_statement['net_sales'], 2) }}</strong></td>
            </tr>
            <tr>
                <td>Less: Cost of Goods Sold (COGS)</td>
                <td class="text-right font-mono" style="color: #dc2626;">- ₹{{ number_format($financial_statement['cogs'], 2) }}</td>
            </tr>
            <tr class="statement-row-bold">
                <td><strong>= GROSS PROFIT</strong></td>
                <td class="text-right font-mono"><strong>₹{{ number_format($financial_statement['gross_profit'], 2) }}</strong></td>
            </tr>
            <tr>
                <td>Less: Operating Expenses</td>
                <td class="text-right font-mono" style="color: #dc2626;">- ₹{{ number_format($financial_statement['operating_expenses'], 2) }}</td>
            </tr>
            <tr class="statement-row-bold">
                <td><strong>= NET STORE PROFIT / LOSS</strong></td>
                <td class="text-right font-mono"><strong>{{ $financial_statement['formatted_amount'] }}</strong></td>
            </tr>
        </table>

        <!-- PROFIT / LOSS BANNER -->
        @if($financial_statement['is_profit'])
            <div class="banner-profit">
                PROFIT: {{ $financial_statement['formatted_amount'] }}
            </div>
        @else
            <div class="banner-loss">
                LOSS: {{ $financial_statement['formatted_amount'] }}
            </div>
        @endif
    </div>
    @endif

    <!-- Main Report Data Table -->
    @if(!empty($rows) && count($rows) > 0)
    <table class="data-table">
        <thead>
            <tr>
                @foreach($headers as $h)
                <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                @foreach($row as $cell)
                <td>{{ $cell }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div style="text-align: center; padding: 30px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; color: #64748b; font-weight: bold;">
        No transaction records found for the selected period & filters.
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="width: 50%;">
                    RUPSA PADUKALAYA ERP • Confidential Internal Report
                </td>
                <td style="width: 50%; text-align: right;">
                    Page <script type="text/php">if (isset($pdf)) { echo $pdf->get_page_number(); }</script> of <script type="text/php">if (isset($pdf)) { echo $pdf->get_page_count(); }</script>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
