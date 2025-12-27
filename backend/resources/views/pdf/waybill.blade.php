<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Waybill - {{ $waybill->waybill_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 28px;
            color: #2563eb;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 11px;
            color: #666;
        }
        .waybill-info {
            background: #f3f4f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .waybill-info h2 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #1f2937;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 5px 0;
            width: 150px;
        }
        .info-value {
            display: table-cell;
            padding: 5px 0;
        }
        .section {
            margin-bottom: 20px;
            border: 1px solid #d1d5db;
            padding: 15px;
            border-radius: 5px;
        }
        .section h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .barcode {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 2px dashed #d1d5db;
        }
        .barcode-number {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            margin-top: 10px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
        .two-column {
            display: table;
            width: 100%;
        }
        .column {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }
        .column-spacer {
            display: table-cell;
            width: 4%;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>T-TRADE WAYBILL</h1>
            <p>Secure Escrow Marketplace Platform</p>
        </div>

        <!-- Waybill Information -->
        <div class="waybill-info">
            <h2>Waybill Information</h2>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Waybill Number:</div>
                    <div class="info-value">{{ $waybill->waybill_number }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tracking Code:</div>
                    <div class="info-value">{{ $waybill->tracking_code }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order ID:</div>
                    <div class="info-value">#{{ $waybill->order_id }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Generated:</div>
                    <div class="info-value">{{ $waybill->generated_at->format('F d, Y h:i A') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Delivery Type:</div>
                    <div class="info-value">{{ strtoupper($waybill->delivery_type) }}</div>
                </div>
            </div>
        </div>

        <!-- Sender and Recipient -->
        <div class="two-column">
            <div class="column">
                <div class="section">
                    <h3>SENDER INFORMATION</h3>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Name:</div>
                            <div class="info-value">{{ $waybill->sender_name }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone:</div>
                            <div class="info-value">{{ $waybill->sender_phone }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address:</div>
                            <div class="info-value">{{ $waybill->sender_address }}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="column-spacer"></div>
            
            <div class="column">
                <div class="section">
                    <h3>RECIPIENT INFORMATION</h3>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Name:</div>
                            <div class="info-value">{{ $waybill->recipient_name }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone:</div>
                            <div class="info-value">{{ $waybill->recipient_phone }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address:</div>
                            <div class="info-value">{{ $waybill->recipient_address }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Package Details -->
        <div class="section">
            <h3>PACKAGE DETAILS</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value">{{ $waybill->item_description }}</div>
                </div>
                @if($waybill->weight)
                <div class="info-row">
                    <div class="info-label">Weight:</div>
                    <div class="info-value">{{ $waybill->weight }} kg</div>
                </div>
                @endif
                @if($waybill->dimensions)
                <div class="info-row">
                    <div class="info-label">Dimensions:</div>
                    <div class="info-value">{{ $waybill->dimensions }} cm</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Declared Value:</div>
                    <div class="info-value">₦{{ number_format($waybill->declared_value, 2) }}</div>
                </div>
                @if($waybill->courier_service)
                <div class="info-row">
                    <div class="info-label">Courier:</div>
                    <div class="info-value">{{ $waybill->courier_service }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Barcode/Tracking -->
        <div class="barcode">
            <div style="font-size: 11px; color: #666;">SCAN FOR TRACKING</div>
            <div class="barcode-number">{{ $waybill->tracking_code }}</div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>IMPORTANT:</strong> This waybill must accompany the package at all times.</p>
            <p>For tracking and support, visit www.t-trade.ng or call +234-XXX-XXX-XXXX</p>
            <p style="margin-top: 10px;">Generated by T-Trade Escrow Platform • Document ID: {{ $waybill->id }}</p>
        </div>
    </div>
</body>
</html>
