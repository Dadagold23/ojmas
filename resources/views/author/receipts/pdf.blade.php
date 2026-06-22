<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receipt->receipt_no ?? 'OJMAS' }}</title>
    <style>
        body{
            font-family: DejaVu Sans, Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.55;
            margin: 0;
            padding: 30px;
        }

        .receipt-wrap{
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            overflow: hidden;
        }

        .receipt-header{
            background: linear-gradient(135deg, #0d6efd, #0f172a);
            color: #ffffff;
            padding: 28px 30px;
        }

        .receipt-header h1{
            margin: 0 0 6px;
            font-size: 26px;
        }

        .receipt-header p{
            margin: 0;
            opacity: .9;
        }

        .receipt-body{
            padding: 30px;
        }

        .grid{
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .grid td{
            padding: 8px 0;
            vertical-align: top;
        }

        .label{
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            width: 180px;
        }

        .value{
            color: #0f172a;
            font-weight: 600;
        }

        .summary{
            margin-top: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }

        .summary table{
            width: 100%;
            border-collapse: collapse;
        }

        .summary th,
        .summary td{
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .summary th{
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .summary tr:last-child td{
            border-bottom: none;
        }

        .amount{
            font-size: 20px;
            font-weight: 700;
            color: #0d6efd;
        }

        .status{
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .footer{
            margin-top: 30px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }

        .brand{
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .divider{
            height: 1px;
            background: #e2e8f0;
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <div class="receipt-wrap">
        <div class="receipt-header">
            <div class="brand">OJMAS</div>
            <h1>Payment Receipt</h1>
            <p>Official receipt for journal payment transaction</p>
        </div>

        <div class="receipt-body">
            <table class="grid">
                <tr>
                    <td class="label">Receipt Number</td>
                    <td class="value">{{ $receipt->receipt_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Receipt Date</td>
                    <td class="value">{{ $receipt->created_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</td>
                </tr>
                <tr>
                    <td class="label">Author</td>
                    <td class="value">{{ $receipt->payment->user->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Email</td>
                    <td class="value">{{ $receipt->payment->user->email ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Submission Title</td>
                    <td class="value">{{ $receipt->payment->submission->title ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Submission Number</td>
                    <td class="value">{{ $receipt->payment->submission->submission_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Payment Status</td>
                    <td class="value">
                        <span class="status">{{ $receipt->payment->status ?? 'paid' }}</span>
                    </td>
                </tr>
            </table>

            <div class="divider"></div>

            <div class="summary">
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                Journal processing / vetting payment
                            </td>
                            <td>
                                {{ $receipt->payment->reference ?? $receipt->payment->payment_ref ?? 'N/A' }}
                            </td>
                            <td class="amount">
                                ₦{{ number_format($receipt->payment->amount ?? 0, 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="footer">
                This receipt was generated electronically by OJMAS and serves as proof of payment.
            </div>
        </div>
    </div>
</body>
</html>
