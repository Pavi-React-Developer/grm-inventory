<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Fabric Roll Labels') ?> — GRM Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="<?= asset('js/qrcode.min.js') ?>"></script>
    <script>if (typeof QRCode === 'undefined') document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>');</script>
    <style>
        body {
            background-color: #f1f5f9;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .labels-container {
            max-width: 1080px;
            margin: 0 auto;
        }

        .stickers-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            justify-content: flex-start;
        }

        /* 50x30mm / 2x1 inch standard thermal sticker card */
        .fabric-sticker-card {
            width: 250px;
            min-height: 130px;
            background: #fff;
            border: 1.5px solid #0f172a;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .sticker-header {
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }

        .sticker-title {
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .sticker-supplier {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            line-height: 1;
        }

        .sticker-body {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .qr-wrapper {
            width: 72px;
            flex-shrink: 0;
            text-align: center;
        }

        .qr-canvas-box {
            width: 68px;
            height: 68px;
            background: #fff;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .qr-canvas-box img,
        .qr-canvas-box canvas {
            max-width: 100% !important;
            max-height: 100% !important;
            display: block;
        }

        .code-text {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-weight: 800;
            font-size: 10px;
            color: #0f172a;
            letter-spacing: 0.8px;
            margin-top: 2px;
            line-height: 1;
            text-align: center;
        }

        .details-col {
            flex-grow: 1;
            overflow: hidden;
            line-height: 1.15;
        }

        .roll-badge {
            font-size: 10.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .material-text {
            font-size: 7.5px;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        .spec-row {
            margin-top: 3px;
            font-size: 8px;
            color: #334155;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .spec-item {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .piece-seq-badge {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-top: 3px;
        }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .labels-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .stickers-grid {
                gap: 8px !important;
                justify-content: flex-start !important;
            }

            .fabric-sticker-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<div class="labels-container">
    <!-- Top Action Bar (hidden on print) -->
    <div class="no-print bg-white p-3 rounded-3 shadow-sm border mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1">
                <i class="bi bi-qr-code text-primary me-2"></i><?= e($pageTitle ?? 'Fabric Roll Labels') ?>
            </h5>
            <div class="text-muted small">
                Supplier: <strong class="text-dark"><?= e($seller['company'] ?: $seller['name']) ?></strong> • 
                Fabric: <span class="badge bg-light text-dark border"><?= e($seller['material_type'] ?: 'Standard Fabric') ?></span> • 
                Total: <span class="badge bg-primary text-white"><?= count($stickers) ?> Sticker<?= count($stickers) === 1 ? '' : 's' ?></span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-lg shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print All Stickers (<?= count($stickers) ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="window.close()">
                <i class="bi bi-x-lg me-1"></i> Close
            </button>
        </div>
    </div>

    <!-- Stickers Grid -->
    <div class="stickers-grid" id="stickersGrid">
        <?php foreach ($stickers as $idx => $stk): ?>
            <div class="fabric-sticker-card" id="card_<?= $idx ?>">
                <!-- Header -->
                <div class="sticker-header d-flex justify-content-between align-items-center">
                    <div class="sticker-title text-truncate" title="GRM RAW MATERIAL">GRM RAW MATERIAL</div>
                    <div class="sticker-supplier text-truncate" style="max-width: 120px;" title="<?= e($stk['supplier_name']) ?>">
                        <?= e($stk['supplier_name']) ?>
                    </div>
                </div>

                <!-- Body -->
                <div class="sticker-body">
                    <div class="qr-wrapper">
                        <div class="qr-canvas-box" id="qr_canvas_<?= $idx ?>"></div>
                        <div class="code-text"><?= e($stk['code']) ?></div>
                    </div>
                    <div class="details-col">
                        <div class="roll-badge" title="<?= e($stk['roll_number']) ?>"><?= e($stk['roll_number']) ?></div>
                        <div class="material-text" title="<?= e($stk['material_type']) ?>"><?= e($stk['material_type']) ?></div>
                        <div class="spec-row">
                            <?php if (!empty($stk['color'])): ?>
                                <div class="spec-item"><strong>Color:</strong> <?= e($stk['color']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($stk['design_name'])): ?>
                                <div class="spec-item"><strong>Design:</strong> <?= e($stk['design_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($stk['weight'] > 0): ?>
                                <div class="spec-item"><strong>Weight:</strong> <?= number_format($stk['weight'], 2) ?> kg</div>
                            <?php endif; ?>
                        </div>
                        <div class="piece-seq-badge">
                            PIECE <?= $stk['piece_index'] ?> OF <?= $stk['total_qty'] ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stickers = <?= json_encode($stickers) ?>;
    stickers.forEach((stk, idx) => {
        const container = document.getElementById('qr_canvas_' + idx);
        if (container && window.QRCode) {
            new QRCode(container, {
                text: stk.qr_payload || stk.code,
                width: 64,
                height: 64,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
});
</script>

</body>
</html>
