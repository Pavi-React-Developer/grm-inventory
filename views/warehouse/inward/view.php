<style>
    /* Inward GRN Responsive & Clean Alignment Styles */
    .inward-meta-list .list-group-item {
        padding: 0.65rem 0.25rem;
        border-color: #f1f5f9;
    }
    .inward-meta-list .list-group-item:last-child {
        border-bottom: 0;
    }
    .badge-unit-origin {
        white-space: normal;
        text-align: left;
        line-height: 1.35;
    }
    .table-compact th {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        vertical-align: middle;
        padding: 0.65rem 0.5rem;
    }
    .table-compact td {
        padding: 0.65rem 0.5rem;
        vertical-align: middle;
    }
    .input-qty-box {
        max-width: 80px;
        min-width: 60px;
    }
    .input-dmg-box {
        max-width: 55px;
        min-width: 45px;
        transition: all 0.2s ease;
    }
    .input-dmg-box.is-locked {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
        color: #94a3b8 !important;
        cursor: not-allowed;
    }
    .input-dmg-box.is-active {
        background-color: #fef2f2 !important;
        border-color: #ef4444 !important;
        color: #dc2626 !important;
        font-weight: bold;
    }
    .scanner-mode-damage {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
    }
    .hover-shadow:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: all 0.2s ease;
    }
    @media (max-width: 991.98px) {
        .inward-meta-card { margin-bottom: 1.5rem; }
    }
</style>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h2 class="mb-0"><i class="bi bi-qr-code-scan me-2 text-primary"></i>Cross-Check Inward Batch #<?= e($batch['batch_number']) ?></h2>
            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 fs-6 px-3 py-2 shadow-sm">
                <i class="bi bi-gear-wide-connected me-1 text-warning"></i>Source Unit: <strong><?= e($batch['production_unit_name'] ?? 'Main Apparel Facility') ?> (<?= e($batch['production_unit_code'] ?? 'PU1') ?>)</strong>
            </span>
            <span class="badge bg-primary fs-6 px-3 py-2 shadow-sm">
                <i class="bi bi-building me-1"></i>Receiving Warehouse: <strong><?= e($batch['warehouse_name']) ?> (<?= e($batch['warehouse_code'] ?? '') ?>)</strong>
            </span>
        </div>
        <span class="text-muted">Origin Unit: <strong><?= e($batch['production_unit_name'] ?? 'Manufacturing Unit') ?></strong> &rarr; Inward Receiving Warehouse: <strong><?= e($batch['warehouse_name']) ?></strong></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#batchHistoryModal">
            <i class="bi bi-clock-history me-1"></i> Batch Lifecycle &amp; Logs
        </button>
        <a href="<?= url('warehouse/inward/label/' . $batch['id']) ?>" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-printer me-1"></i> Print Carton QR Sticker
        </a>
        <a href="<?= url('warehouse/inward') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Inward Queue
        </a>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. LIVE SUMMARY METRIC CARDS (5-CARD RESPONSIVE GRID WITH DAMAGE TRACKER) -->
<!-- ========================================================================= -->
<?php
    $displayRec = $batch['status'] === 'RECEIVED' ? (int)($totalReceived ?? ($batch['received_qty'] ?? $totalExpected)) : 0;
    $displayDmg = $batch['status'] === 'RECEIVED' ? (int)($totalDamaged ?? ($batch['damaged_qty'] ?? 0)) : 0;
    $displayMis = $batch['status'] === 'RECEIVED' ? (int)($totalMissing ?? ($batch['missing_qty'] ?? 0)) : $totalExpected;
    $displayPct = $totalExpected > 0 ? round(($displayRec / $totalExpected) * 100) : ($batch['status'] === 'RECEIVED' ? 100 : 0);

    // Can scan or edit only if user has inward verify or create permission AND batch is not fully received
    $canVerify = (hasPermission('inward', 'verify') || hasPermission('inward', 'create') || hasPermission('inward', 'edit'));
    $isFullyReceived = ($batch['status'] === 'RECEIVED' && $displayMis === 0 && ($displayRec + $displayDmg) >= $totalExpected);
    $canScanOrEdit = $canVerify && ($batch['status'] === 'DISPATCHED' || !$isFullyReceived);
?>
<?php if (!$canVerify): ?>
    <div class="alert alert-secondary border-0 shadow-sm py-2 px-3 mb-4 d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
        <div class="small">
            <strong>Read-Only Mode:</strong> Your assigned staff account has view-only access. Inward stock scanning, damage reporting, and GRN verification actions are restricted.
        </div>
    </div>
<?php endif; ?>
<div class="row g-3 mb-4">
    <!-- 1. Outward Dispatched -->
    <div class="col-sm-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Outward Dispatched</span>
                        <h4 class="mb-0 fw-bold text-dark mt-1" id="statExpected"><?= number_format($totalExpected) ?> <small class="fs-6 text-muted">pcs</small></h4>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary">
                        <i class="bi bi-truck fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- 2. Inward Verified Intact -->
    <div class="col-sm-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Inward Verified (Intact)</span>
                        <h4 class="mb-0 fw-bold text-success mt-1" id="statScanned"><?= number_format($displayRec) ?> <small class="fs-6 text-muted">pcs</small></h4>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success">
                        <i class="bi bi-upc-scan fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- 3. Damaged / Inactive -->
    <div class="col-sm-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm border-start border-danger border-4 h-100" id="statDamagedCard">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Damaged / Inactive</span>
                        <h4 class="mb-0 fw-bold text-danger mt-1" id="statDamaged"><?= number_format($displayDmg) ?> <small class="fs-6 text-muted">pcs</small></h4>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger">
                        <i class="bi bi-slash-circle fs-4"></i>
                    </div>
                </div>
                <small class="text-danger d-block mt-1 font-monospace" style="font-size: 0.72rem;" id="statDamagedSubtext"><?= $displayDmg > 0 ? "{$displayDmg} quarantined units" : "0 quarantined units" ?></small>
            </div>
        </div>
    </div>
    <!-- 4. Pending / Missing -->
    <div class="col-sm-6 col-md-6 col-xl">
        <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Pending / Missing</span>
                        <h4 class="mb-0 fw-bold text-warning mt-1" id="statMissing"><?= number_format($displayMis) ?> <small class="fs-6 text-muted">pcs</small></h4>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 text-warning">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- 5. Verification Match -->
    <div class="col-sm-12 col-md-6 col-xl">
        <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Verification Match</span>
                        <h4 class="mb-0 fw-bold text-info mt-1" id="statProgressPct"><?= $displayPct ?>%</h4>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 p-2 text-info">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div id="statProgressBar" class="progress-bar <?= $displayPct >= 100 ? ($displayDmg > 0 ? 'bg-warning' : 'bg-success') : 'bg-info' ?>" role="progressbar" style="width: <?= $displayPct ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- LEFT COLUMN: Outward Dispatch Info & QR Widget (Responsive 4-col on XL) -->
    <div class="col-xl-4 col-lg-5">
        <!-- Scannable Batch QR Card -->
        <div class="card border-0 shadow-sm mb-4 text-center">
            <div class="card-header bg-white py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-qr-code me-2 text-primary"></i>Batch Carton QR Code</h6>
            </div>
            <div class="card-body p-4">
                <div id="batchQrView" class="d-flex justify-content-center mb-3"></div>
                <div class="font-monospace fw-bold text-primary fs-5"><?= e($batch['batch_number']) ?></div>
                <small class="text-muted d-block mt-1">Scan this master code to auto-verify the entire carton box.</small>
            </div>
        </div>

        <!-- Outward Dispatch & Gate Pass Metadata -->
        <div class="card border-0 shadow-sm mb-4 inward-meta-card">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Outward Dispatch Details</h6>
                <button type="button" class="btn btn-xs btn-outline-info py-1 px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#batchHistoryModal">
                    <i class="bi bi-clock-history me-1"></i> Traceability
                </button>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush inward-meta-list">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Outward Gate Pass:</span>
                        <span class="fw-bold text-primary font-monospace"><?= e($outward['outward_number'] ?? 'N/A') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Production Batch:</span>
                        <span class="fw-bold font-monospace text-dark"><?= e($batch['batch_number']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Program #:</span>
                        <span class="fw-semibold font-monospace"><?= e($batch['program_number']) ?></span>
                    </li>
                    <li class="list-group-item">
                        <div class="text-muted small mb-1">Product / Style:</div>
                        <div class="fw-bold text-dark text-truncate" title="<?= e($batch['product_name']) ?>"><?= e($batch['product_name']) ?></div>
                    </li>
                    <li class="list-group-item bg-warning bg-opacity-10 rounded p-2 my-2 border border-warning border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-dark fw-bold small"><i class="bi bi-gear-wide-connected me-1 text-warning"></i>Origin Unit:</span>
                            <span class="badge bg-warning text-dark font-monospace border border-warning"><?= e($batch['production_unit_code'] ?? 'PU1') ?></span>
                        </div>
                        <div class="fw-bold text-dark small badge-unit-origin"><?= e($batch['production_unit_name'] ?? 'Main Production Facility') ?></div>
                        <?php if (!empty($batch['production_unit_manager']) || !empty($batch['production_unit_phone'])): ?>
                            <small class="text-muted d-block mt-1"><?= e($batch['production_unit_manager'] ?? '') ?> <?= !empty($batch['production_unit_phone']) ? '('.e($batch['production_unit_phone']).')' : '' ?></small>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item bg-primary bg-opacity-10 rounded p-2 my-2 border border-primary border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-primary fw-bold small"><i class="bi bi-building me-1 text-primary"></i>Receiving Warehouse:</span>
                            <span class="badge bg-primary font-monospace"><?= e($batch['warehouse_code'] ?? 'WH') ?></span>
                        </div>
                        <div class="fw-bold text-primary small badge-unit-origin"><?= e($batch['warehouse_name']) ?></div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Carton Box Count:</span>
                        <span class="badge bg-light text-dark border font-monospace"><?= number_format($batch['box_count'] ?? 1) ?> Boxes</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Vehicle / Carrier:</span>
                        <span class="fw-semibold small font-monospace"><?= e($outward['vehicle_number'] ?? 'Internal Transfer') ?></span>
                    </li>
                    <?php if (!empty($outward['driver_name']) || !empty($outward['driver_phone'])): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center small text-muted">
                        <span>Driver:</span>
                        <span><?= e($outward['driver_name'] ?? 'Driver') ?> <?= !empty($outward['driver_phone']) ? '('.e($outward['driver_phone']).')' : '' ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Dispatch Date:</span>
                        <span class="small"><?= !empty($outward['dispatch_date']) ? date('d M Y, h:i A', strtotime($outward['dispatch_date'])) : 'N/A' ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Current Status:</span>
                        <?php if ($batch['status'] === 'DISPATCHED'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Awaiting GRN</span>
                        <?php elseif ($batch['status'] === 'RECEIVED' && $displayMis > 0): ?>
                            <span class="badge bg-warning text-dark border border-warning"><i class="bi bi-exclamation-triangle me-1"></i>Partially Inwarded (<?= $displayMis ?> Short)</span>
                        <?php elseif ($batch['status'] === 'RECEIVED'): ?>
                            <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>Received &amp; Stocked (100%)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= e($batch['status']) ?></span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Scanner Engine & Cross-Check Matrix (Responsive 8-col on XL) -->
    <div class="col-xl-8 col-lg-7">
        <?php if ($canScanOrEdit): ?>
            <!-- 2. LIVE CROSS-CHECK SCANNER CARD WITH MODE SWITCH -->
            <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4" id="liveScannerCard">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <label class="form-label fw-bold text-dark mb-0">
                                <i class="bi bi-upc-scan text-primary me-1"></i> Live Product &amp; QR Cross-Check Scanner
                            </label>
                            <small class="text-muted d-block" id="scannerModeHint">Toggle mode below to scan intact goods or quarantine damaged pieces</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                <i class="bi bi-building me-1"></i>Inward to: <strong><?= e($batch['warehouse_name']) ?> (<?= e($batch['warehouse_code'] ?? '') ?>)</strong>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cameraScanModal">
                                <i class="bi bi-camera me-1"></i> Camera Scanner
                            </button>
                        </div>
                    </div>

                    <!-- Scan Mode Selector -->
                    <div class="mb-3">
                        <label class="small fw-bold text-dark d-block mb-2"><i class="bi bi-sliders me-1"></i>Scanner Mode (Click to Switch):</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="radio" class="btn-check" name="scanModeToggle" id="modeGood" value="good" checked autocomplete="off" onchange="setScanMode('good')">
                            <label class="btn btn-outline-success fw-bold py-2 fs-6 flex-grow-1" for="modeGood">
                                <i class="bi bi-check-circle-fill me-1"></i> 🟢 Good / Intact Mode
                            </label>

                            <input type="radio" class="btn-check" name="scanModeToggle" id="modeDamage" value="damage" autocomplete="off" onchange="setScanMode('damage')">
                            <label class="btn btn-outline-danger fw-bold py-2 fs-6 flex-grow-1" for="modeDamage">
                                <i class="bi bi-slash-circle-fill me-1"></i> 🔴 Damaged / Inactivate Mode
                            </label>
                        </div>
                        <div id="modeStatusBadge" class="mt-2 text-center">
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-1.5 w-100 shadow-sm" style="font-size: 0.85rem;">
                                <i class="bi bi-check2-all me-1"></i>Currently scanning verified sellable stock
                            </span>
                        </div>
                    </div>

                    <div class="input-group input-group-lg mb-2">
                        <span class="input-group-text bg-white" id="scannerIconSpan"><i class="bi bi-qr-code text-primary" id="scannerIcon"></i></span>
                        <input type="text" id="liveScannerInput" class="form-control font-monospace" placeholder="Scan Variant QR / Barcode / SKU / Master Carton..." autofocus autocomplete="off">
                        <button class="btn btn-primary px-4 fw-semibold" type="button" id="btnLiveScanSubmit">
                            <i class="bi bi-plus-circle me-1"></i> Scan
                        </button>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
                        <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Physical USB scanner guns will instantly cross-check on scan.</span>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnAutoFillExpected">
                            <i class="bi bi-magic me-1"></i> Quick Fill All Expected (Master Scan)
                        </button>
                    </div>

                    <div id="scanAlertMsg" class="mt-3 d-none"></div>
                </div>
            </div>

            <!-- 3. LIVE MISSING PRODUCT QR CODES TRACKER (PROFILE-WISE BREAKDOWN) -->
            <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4" id="missingProductsCard">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="card-title mb-0 fw-bold text-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Missing / Unscanned Products &amp; QR Codes (Profile-Wise)
                        </h6>
                        <small class="text-muted">Shows the full product variant profile first, followed by pending missing pieces</small>
                    </div>
                    <span class="badge bg-danger rounded-pill px-3 py-2 fs-7" id="missingBadgeCount">0 items pending</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" id="missingTableContainer">
                        <table class="table table-hover align-middle mb-0" id="missingTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Variant Profile</th>
                                    <th>QR Code / Tag</th>
                                    <th>Size &amp; Color</th>
                                    <th class="text-center">Expected</th>
                                    <th class="text-center">Scanned</th>
                                    <th class="text-end">Missing Qty</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="missingTableBody">
                                <!-- Populated by JS with full product profile cards -->
                            </tbody>
                        </table>
                    </div>
                    <div id="missingEmptyNotice" class="text-center py-4 d-none text-success">
                        <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                        <span class="fw-bold">All outward products matched perfectly! Zero missing items.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4. CROSS-CHECK MATRIX TABLE & FORM -->
        <form method="POST" action="<?= url('warehouse/inward/verify/' . $batch['id']) ?>" id="inwardForm">
            <?= CSRF::input() ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="card-title mb-0 fw-bold">
                            <i class="bi bi-table me-2 text-primary"></i>Outward vs Inward Cross-Check Matrix
                        </h6>
                        <small class="text-muted">Scan QR or enter verified units. Damaged items are locked by default &amp; quarantined on inactivate.</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-compact align-middle mb-0" id="matrixTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25px;">#</th>
                                    <th>SKU &amp; QR Range</th>
                                    <th>Size &amp; Color</th>
                                    <th class="text-end" style="width: 90px;">Expected</th>
                                    <th class="text-center" style="width: 110px;">Inward Recv</th>
                                    <th class="text-center" style="width: 140px;" title="Damaged pieces are isolated/inactivated from active sellable inventory">Damaged / Inactive</th>
                                    <th class="text-center" style="width: 85px;">Difference</th>
                                    <th class="text-center" style="width: 95px;">Status</th>
                                    <th class="text-center" style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $globalSeqCounter = 1;
                                    foreach ($items as $idx => $item): 
                                        $vid = $item['variant_id'];
                                        $expected = (int)$item['quantity'];
                                        $initReceived = $batch['status'] === 'RECEIVED' ? (isset($item['received_qty']) ? (int)$item['received_qty'] : $expected) : 0;
                                        $initDamaged = (int)($item['damaged_qty'] ?? 0);
                                        $initMissing = isset($item['missing_qty']) ? (int)$item['missing_qty'] : max(0, $expected - ($initReceived + $initDamaged));
                                        $initDiff = ($initReceived + $initDamaged) - $expected;

                                        $itemStartSeq = !empty($item['start_seq']) ? (int)$item['start_seq'] : $globalSeqCounter;
                                        $itemEndSeq = !empty($item['end_seq']) ? (int)$item['end_seq'] : ($itemStartSeq + max(1, $expected) - 1);
                                        $globalSeqCounter = $itemEndSeq + 1;

                                        if (!empty($item['start_code'])) {
                                            $tagStart = $item['start_code'];
                                            $tagEnd = !empty($item['end_code']) ? $item['end_code'] : $item['start_code'];
                                        } else {
                                            $c1 = !empty($item['category_name']) ? trim($item['category_name']) : (!empty($item['category_code']) ? trim($item['category_code']) : '');
                                            $c2 = !empty($item['subcategory_name']) ? trim($item['subcategory_name']) : (!empty($item['subcategory_code']) ? trim($item['subcategory_code']) : '');
                                            $c3 = !empty($item['sub_subcategory_name']) ? trim($item['sub_subcategory_name']) : (!empty($item['sub_subcategory_code']) ? trim($item['sub_subcategory_code']) : '');

                                            $prefix = '';
                                            if ($c1 !== '') $prefix .= strtoupper($c1[0]);
                                            if ($c2 !== '') $prefix .= strtoupper($c2[0]);
                                            if ($c3 !== '') $prefix .= strtoupper($c3[0]);

                                            if (strlen($prefix) < 2) {
                                                if (!empty($item['qr_code_prefix'])) {
                                                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $item['qr_code_prefix']);
                                                    $prefix = strtoupper(substr($clean, 0, 3));
                                                }
                                            }
                                            if (empty($prefix)) $prefix = 'TTS';
                                            $monthStr = sprintf('%02d', date('n'));
                                            $yearStr = date('y');
                                            $tagStart = $prefix . $monthStr . $yearStr . sprintf('%02d', $itemStartSeq);
                                            $tagEnd = $prefix . $monthStr . $yearStr . sprintf('%02d', $itemEndSeq);
                                        }
                                        $tagRange = ($tagStart !== $tagEnd) ? ($tagStart . ' - ' . $tagEnd) : $tagStart;
                                    ?>
                                    <tr id="row-<?= $vid ?>" data-variant-id="<?= $vid ?>" data-sku="<?= e($item['sku']) ?>" data-qr="<?= e($tagStart) ?>" data-expected="<?= $expected ?>">
                                        <td class="text-muted small fw-semibold"><?= $idx + 1 ?></td>
                                        <td>
                                            <span class="fw-bold font-monospace text-primary d-block small"><?= e($item['sku']) ?></span>
                                            <small class="badge bg-dark text-white font-monospace mt-1" style="font-size: 0.72rem;"><i class="bi bi-qr-code me-1 text-warning"></i><?= e($tagRange) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <?php if (!empty($item['fabric_photo'])): ?>
                                                    <img src="<?= e(uploadUrl($item['fabric_photo'])) ?>" 
                                                         alt="<?= e($item['color_name']) ?>" 
                                                         style="width: 24px; height: 24px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1;"
                                                         loading="lazy">
                                                <?php endif; ?>
                                                <div>
                                                    <span class="badge bg-light text-dark border"><?= e($item['size_name'] ?? 'Free') ?></span>
                                                    <span class="ms-1 small fw-semibold"><?= e($item['color_name'] ?? 'Std') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="fw-bold fs-6 text-dark"><?= number_format($expected) ?> <small class="text-muted">pcs</small></div>
                                            <?php 
                                                $credited = (int)($item['received_qty'] ?? 0);
                                                $rem = max(0, $expected - $credited);
                                            ?>
                                            <?php if ($batch['status'] === 'RECEIVED' && $credited > 0 && $rem > 0): ?>
                                                <div class="mt-1 d-flex flex-column align-items-end gap-1" style="font-size: 0.72rem;">
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-1.5 py-0.5" title="Already credited to warehouse inventory">
                                                        <i class="bi bi-check2"></i> <?= number_format($credited) ?> Credited
                                                    </span>
                                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-1.5 py-0.5" title="Remaining to inward in this session">
                                                        <i class="bi bi-hourglass-split"></i> <?= number_format($rem) ?> Remaining
                                                    </span>
                                                </div>
                                            <?php elseif ($batch['status'] === 'RECEIVED' && $credited >= $expected): ?>
                                                <div class="mt-1" style="font-size: 0.72rem;">
                                                    <span class="badge bg-success text-white px-1.5 py-0.5">
                                                        <i class="bi bi-check-all"></i> All <?= number_format($expected) ?> Credited
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($canScanOrEdit): ?>
                                                <input type="number" name="items[<?= $vid ?>][received_qty]" 
                                                       class="form-control form-control-sm text-center fw-bold text-success item-received-input input-qty-box mx-auto" 
                                                       id="input-recv-<?= $vid ?>" 
                                                       value="<?= $initReceived ?>" min="<?= $batch['status'] === 'RECEIVED' ? $initReceived : 0 ?>" max="99999" data-variant-id="<?= $vid ?>"
                                                       <?= ($batch['status'] === 'RECEIVED' && $initReceived > 0) ? 'title="Minimum received is ' . $initReceived . ' pcs (already credited to inventory)"' : '' ?>>
                                            <?php else: ?>
                                                <span class="fw-bold text-success fs-6"><?= number_format($initReceived) ?> pcs</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($canScanOrEdit): ?>
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="adjustDamage(<?= $vid ?>, -1)" title="Decrease Damaged Count" style="min-width: 20px; font-size: 11px; line-height: 1.5;">-</button>
                                                    <input type="number" name="items[<?= $vid ?>][damaged_qty]" 
                                                           class="form-control form-control-sm text-center item-damaged-input input-dmg-box <?= $initDamaged > 0 ? 'is-active' : 'is-locked' ?>" 
                                                           id="input-dmg-<?= $vid ?>" 
                                                           value="<?= $initDamaged ?>" min="0" max="99999" data-variant-id="<?= $vid ?>" <?= $initDamaged > 0 ? '' : 'readonly' ?>
                                                           title="Damaged input is locked by default. Click + to report damage.">
                                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 fw-bold" onclick="adjustDamage(<?= $vid ?>, 1)" title="Add +1 Damaged Piece & Inactivate" style="min-width: 20px; font-size: 11px; line-height: 1.5;">+</button>
                                                    <button type="button" class="btn btn-sm <?= $initDamaged > 0 ? 'btn-danger text-white' : 'btn-outline-danger' ?> btn-toggle-damage p-1" 
                                                            id="btn-toggle-dmg-<?= $vid ?>" 
                                                            onclick="toggleDamageInput(<?= $vid ?>)" 
                                                            title="Unlock or lock damage reporting for this variant">
                                                        <i class="bi <?= $initDamaged > 0 ? 'bi-exclamation-octagon-fill' : 'bi-slash-circle' ?>" id="icon-dmg-<?= $vid ?>"></i>
                                                    </button>
                                                </div>
                                                <div class="text-danger <?= $initDamaged > 0 ? '' : 'd-none' ?> mt-1 fw-bold font-monospace" id="dmg-label-<?= $vid ?>" style="font-size: 9px; line-height: 1;"><?= $initDamaged ?> QUARANTINED</div>
                                            <?php else: ?>
                                                <span class="<?= $initDamaged > 0 ? 'badge bg-danger text-white' : 'text-muted' ?>"><?= $initDamaged ?> pcs</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span id="diff-badge-<?= $vid ?>" class="badge <?= $initDiff === 0 ? 'bg-success' : ($initDiff < 0 ? 'bg-danger' : 'bg-primary') ?> fs-7 font-monospace">
                                                 <?= $initDiff > 0 ? "+$initDiff pcs" : "$initDiff pcs" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span id="status-badge-<?= $vid ?>" class="badge <?= $initDiff === 0 ? ($initDamaged > 0 ? 'bg-warning text-dark border border-warning' : 'bg-success-subtle text-success border border-success') : 'bg-danger-subtle text-danger border border-danger' ?>" style="font-size: 0.72rem;">
                                                 <?= $initDiff === 0 ? ($initDamaged > 0 ? "DMG ($initDamaged)" : 'MATCHED') : "SHORT (" . abs($initDiff) . ")" ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold shadow-sm" onclick="openMissingProductModal(<?= $vid ?>, 'all')" title="View Product Details & QR Breakdown">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </button>
                                                <?php if ($canScanOrEdit): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 fw-semibold shadow-sm" onclick="adjustDamage(<?= $vid ?>, 1)" title="Add +1 Damaged Piece & Inactivate">
                                                        <i class="bi bi-slash-circle"></i> +1 Dmg
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold shadow-sm btn-credit-single" onclick="creditSingleItem(<?= $vid ?>)" id="btn-credit-item-<?= $vid ?>" title="Quick Match All Expected Pieces">
                                                        <i class="bi bi-box-arrow-in-down me-1"></i> All
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($canScanOrEdit): ?>
                    <div class="card-footer bg-light p-3">
                        <?php if ($batch['status'] === 'RECEIVED' && $displayMis > 0): ?>
                            <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-xs">
                                <div class="small">
                                    <i class="bi bi-info-circle-fill me-1 text-warning"></i><strong>Partial Inward Batch:</strong> <?= number_format($displayRec) ?> pcs have already been credited. Scan or enter remaining <?= number_format($displayMis) ?> pcs below to credit incremental stock to warehouse.
                                </div>
                                <span class="badge bg-danger font-monospace"><?= number_format($displayMis) ?> pcs pending</span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <input type="hidden" name="notes" id="inwardNotes" value="">
                            <div class="text-muted small">
                                <i class="bi bi-shield-check text-success me-1"></i>Ready for physical piece inspection &amp; instant stock crediting.
                            </div>
                            <div>
                                <button type="button" class="btn btn-success px-4 py-2 fw-bold shadow-sm fs-6" id="btnCreditScannedOnly" onclick="creditScannedOnly();">
                                    <i class="bi bi-box-arrow-in-down me-2"></i> <?= ($batch['status'] === 'RECEIVED' && $displayMis > 0) ? 'Update / Credit Inward Stock (<span id="btnCreditScannedCount">+0</span> pcs)' : 'Credit Scanned Qty (<span id="btnCreditScannedCount">0</span> pcs)' ?> to <?= e($batch['warehouse_name']) ?> Inventory
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card-footer <?= !$canVerify ? 'bg-light text-muted' : 'bg-success-subtle text-success' ?> p-3 text-center fw-semibold fs-6">
                        <?php if (!$canVerify): ?>
                            <i class="bi bi-shield-lock me-2 text-primary"></i><strong>View-Only Mode:</strong> Inward stock verification is restricted to authorized warehouse staff with verify permissions.
                        <?php else: ?>
                            <i class="bi bi-check-circle-fill me-2"></i>Batch GRN Completed (100% Matched). Stock balances credited to <strong><?= e($batch['warehouse_name']) ?> (<?= e($batch['warehouse_code'] ?? '') ?>)</strong> warehouse inventory.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($damages)): ?>
            <!-- QUARANTINED DAMAGE CASES ASSOCIATED WITH THIS BATCH -->
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-danger">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="card-title mb-0 fw-bold text-danger">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>Quarantined Damaged Pieces (<?= count($damages) ?> Cases Logged)
                        </h6>
                        <small class="text-muted">These units were flagged during inward verification and automatically isolated into the Damage Management Module.</small>
                    </div>
                    <a href="<?= url('warehouse/damage?search=' . urlencode($batch['batch_number'])) ?>" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-shield-exclamation me-1"></i> View All in Damage Module
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 text-uppercase fs-7 fw-bold text-muted">Case #</th>
                                    <th class="text-uppercase fs-7 fw-bold text-muted">Scanned QR Tag</th>
                                    <th class="text-uppercase fs-7 fw-bold text-muted">Product &amp; Variant</th>
                                    <th class="text-uppercase fs-7 fw-bold text-muted">Damage Reason</th>
                                    <th class="text-center text-uppercase fs-7 fw-bold text-muted">Damaged Qty</th>
                                    <th class="text-uppercase fs-7 fw-bold text-muted">Disposition Status</th>
                                    <th class="pe-3 text-end text-uppercase fs-7 fw-bold text-muted">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($damages as $dmg): ?>
                                    <tr>
                                        <td class="ps-3 text-nowrap">
                                            <a href="<?= url('warehouse/damage/view/' . $dmg['id']) ?>" class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2.5 py-1.5 fs-7 fw-bold text-decoration-none shadow-2xs d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-file-earmark-text text-danger"></i>
                                                <?= e($dmg['damage_number']) ?>
                                            </a>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php 
                                                $displayTag = $dmg['tag_code'] ?? '';
                                                if (empty($displayTag) || str_contains($displayTag, '-') || str_contains($displayTag, 'SKU')) {
                                                    $c1 = !empty($dmg['category_name']) ? strtoupper(substr(trim($dmg['category_name']), 0, 1)) : 'M';
                                                    $c2 = !empty($dmg['subcategory_name']) ? strtoupper(substr(trim($dmg['subcategory_name']), 0, 1)) : 'T';
                                                    $c3 = !empty($dmg['sub_subcategory_name']) ? strtoupper(substr(trim($dmg['sub_subcategory_name']), 0, 1)) : 'P';
                                                    $mmyy = date('my', !empty($dmg['created_at']) ? strtotime($dmg['created_at']) : time());
                                                    $displayTag = $c1 . $c2 . $c3 . $mmyy . '01';
                                                }
                                            ?>
                                            <?php if (!empty($displayTag)): ?>
                                                <span class="badge bg-dark font-monospace text-warning px-2.5 py-1.5"><i class="bi bi-qr-code me-1 text-warning"></i><?= e($displayTag) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark small"><?= e($dmg['product_name']) ?></div>
                                            <span class="badge bg-light text-dark border small font-monospace"><?= e($dmg['sku']) ?></span>
                                            <span class="badge bg-light text-secondary border small"><?= e($dmg['size_name'] ?? 'Std') ?> / <?= e($dmg['color_name'] ?? 'Std') ?></span>
                                        </td>
                                        <td>
                                            <div class="small text-danger fw-semibold"><?= e($dmg['damage_reason']) ?></div>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-7 px-2.5 py-1 fw-bold">
                                                <?= number_format($dmg['quantity']) ?> pcs
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge bg-danger text-white px-2.5 py-1 rounded-pill fw-semibold">
                                                <?= e($dmg['status']) ?>
                                            </span>
                                        </td>
                                        <td class="pe-3 text-end text-nowrap">
                                            <a href="<?= url('warehouse/damage/view/' . $dmg['id']) ?>" class="btn btn-sm btn-outline-danger px-2.5 py-1 shadow-sm d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-eye"></i>
                                                <span>View Case</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Camera QR Scanner Modal -->
<div class="modal fade" id="cameraScanModal" tabindex="-1" aria-labelledby="cameraScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="cameraScanModalLabel"><i class="bi bi-camera me-2"></i>Live Camera QR Scanner</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="btnCloseCam"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="reader" style="width: 100%; min-height: 280px; border-radius: 8px; overflow: hidden; background: #000;"></div>
                <small class="text-muted d-block mt-3">Position the Carton or Variant QR code within the camera frame.</small>
            </div>
        </div>
    </div>
</div>

<!-- Missing Product Details & QR Code Modal -->
<div class="modal fade" id="missingProductModal" tabindex="-1" aria-labelledby="missingProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center justify-content-between w-100 me-2">
                    <h5 class="modal-title fw-bold" id="missingProductModalLabel">
                        <i class="bi bi-qr-code-scan me-2 text-warning"></i>Product Variant Good &amp; Damage Stock Verification
                    </h5>
                    <span class="badge bg-primary font-monospace px-3 py-1.5 fs-7">
                        <i class="bi bi-building me-1"></i>WH: <?= e($batch['warehouse_name']) ?> (<?= e($batch['warehouse_code'] ?? '') ?>)
                    </span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Product Overview Banner -->
                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                    <div id="modalProductImgContainer">
                        <!-- Image or fallback icon -->
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="fw-bold mb-0 text-dark" id="modalProductName">-</h5>
                            <span class="badge bg-primary-subtle text-primary border" id="modalProductCategory">-</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                            <span class="badge bg-dark font-monospace text-white px-2 py-1" id="modalProductSku">-</span>
                            <span class="badge bg-light text-dark border px-2 py-1">Size: <strong id="modalProductSize">-</strong></span>
                            <span class="badge bg-light text-dark border px-2 py-1">Color: <strong id="modalProductColor">-</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Metrics Counter (4 Columns) -->
                <div class="row g-2 mb-4 text-center">
                    <div class="col-6 col-sm-3">
                        <div class="p-2 bg-light rounded border">
                            <small class="text-muted fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Expected</small>
                            <h4 class="fw-bold text-dark mb-0 mt-1" id="modalExpectedQty">0</h4>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2 bg-success bg-opacity-10 rounded border border-success border-opacity-25">
                            <small class="text-success fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Good Products (Intact)</small>
                            <h4 class="fw-bold text-success mb-0 mt-1" id="modalScannedQty">0</h4>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2 bg-danger bg-opacity-10 rounded border border-danger border-opacity-25">
                            <small class="text-danger fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Damage Products (Quarantined)</small>
                            <h4 class="fw-bold text-danger mb-0 mt-1" id="modalDamagedQty">0</h4>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2 bg-warning bg-opacity-10 rounded border border-warning border-opacity-25">
                            <small class="text-warning fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Pending Missing</small>
                            <h4 class="fw-bold text-dark mb-0 mt-1" id="modalMissingQty">0</h4>
                        </div>
                    </div>
                </div>

                <!-- QR Display & Action Box -->
                <div class="row g-4 align-items-center mb-4">
                    <div class="col-md-5 text-center">
                        <div class="p-3 bg-white rounded border shadow-sm d-inline-block position-relative">
                            <div id="modalQrCanvas" class="d-flex justify-content-center"></div>
                            <div class="mt-2 text-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1" style="font-size: 0.75rem;" id="modalQrTypeLabel">
                                    <i class="bi bi-qr-code me-1"></i>Exact Product Variant QR
                                </span>
                            </div>
                        </div>

                        <!-- Segmented Switch for QR Code mode -->
                        <div class="d-flex justify-content-center mt-2">
                            <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="QR Code Mode">
                                <button type="button" class="btn btn-primary active fw-semibold" id="btnModeExactQr" onclick="switchModalQrMode('exact')">
                                    <i class="bi bi-qr-code me-1"></i>Product Exact QR
                                </button>
                                <button type="button" class="btn btn-outline-secondary fw-semibold" id="btnModePieceTag" onclick="switchModalQrMode('piece')">
                                    <i class="bi bi-tag-fill me-1"></i>Piece Tag
                                </button>
                            </div>
                        </div>

                        <!-- QR / Variant Info Card -->
                        <div class="mt-2 text-start bg-light p-2.5 rounded border" id="modalQrInfoCard">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted fw-bold text-uppercase" style="font-size: 0.68rem;"><i class="bi bi-upc-scan me-1 text-primary"></i>Product Exact QR / SKU:</small>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold shadow-xs" onclick="copyExactQrToClipboard()" title="Copy Exact QR Value">
                                    <i class="bi bi-clipboard me-1"></i>Copy
                                </button>
                            </div>
                            <div class="font-monospace fw-bold text-primary text-break small mb-2 p-1.5 bg-white rounded border d-flex align-items-center justify-content-between" id="modalExactQrBox">
                                <span id="modalExactQrText">-</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted fw-bold text-uppercase" style="font-size: 0.68rem;"><i class="bi bi-tag-fill me-1 text-secondary"></i>Next Sequential Tag:</small>
                                <span class="badge bg-dark font-monospace text-warning px-2 py-1" id="modalNextPieceTagText">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <?php if ($canVerify): ?>
                            <label class="small fw-bold text-dark d-block mb-2"><i class="bi bi-sliders me-1 text-primary"></i>Scanner Mode (Click to Switch):</label>
                            <div class="mb-3">
                                <div class="d-flex gap-2 flex-wrap">
                                    <input type="radio" class="btn-check" name="modalScanModeToggle" id="modalModeGood" value="good" checked autocomplete="off" onchange="setScanMode('good')">
                                    <label class="btn btn-outline-success fw-bold py-2 fs-6 flex-grow-1" for="modalModeGood" id="modalLabelModeGood">
                                        <i class="bi bi-check-circle-fill me-1"></i> 🟢 Good / Intact Mode
                                    </label>

                                    <input type="radio" class="btn-check" name="modalScanModeToggle" id="modalModeDamage" value="damage" autocomplete="off" onchange="setScanMode('damage')">
                                    <label class="btn btn-outline-danger fw-bold py-2 fs-6 flex-grow-1" for="modalModeDamage" id="modalLabelModeDamage">
                                        <i class="bi bi-slash-circle-fill me-1"></i> 🔴 Damaged / Inactivate Mode
                                    </label>
                                </div>
                                <div id="modalModeStatusBadge" class="mt-2 text-center">
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-1.5 w-100 shadow-sm" style="font-size: 0.85rem;">
                                        <i class="bi bi-check2-all me-1"></i>Currently scanning verified sellable stock
                                    </span>
                                </div>
                            </div>

                            <div class="p-2.5 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded mb-3">
                                <small class="text-primary d-block fw-semibold" style="font-size: 0.78rem;">
                                    <i class="bi bi-info-circle-fill me-1"></i><strong>Live Barcode Gun Target:</strong> Scan the product's exact QR code label (<code class="text-dark bg-white px-1 rounded" id="modalScanTargetSku">-</code>) or piece tag with your USB scanner to auto-verify pieces.
                                </small>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-success fw-bold py-2 flex-grow-1 shadow-sm" id="btnModalProcessNext">
                                        <i class="bi bi-check-circle-fill me-1" id="btnModalProcessIcon"></i> <span id="btnModalProcessText">Inward Next Piece (Good Product)</span>
                                    </button>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary fw-semibold py-2 flex-grow-1 shadow-sm" id="btnModalScanAllMissing">
                                        <i class="bi bi-check2-all me-1"></i> Quick All Good Products
                                    </button>
                                    <button type="button" class="btn btn-outline-danger fw-semibold py-2 shadow-sm" id="btnModalResetItem" title="Reset / Un-verify all scanned pieces for this item">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary border-0 text-center py-4 rounded-3">
                                <i class="bi bi-shield-lock-fill fs-3 text-muted d-block mb-2"></i>
                                <h6 class="fw-bold mb-1">View Only Mode</h6>
                                <p class="text-muted small mb-0">Your staff account is permitted for viewing inward records only. Verification and stock placement actions are disabled.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pieces Individual Card Grid with Filter Options -->
                <div class="border-top pt-3" id="modalPiecesSection">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0" id="modalPiecesSectionTitle">
                                <i class="bi bi-grid-3x3-gap-fill text-primary me-1"></i> Individual Pieces Breakdown (<span id="modalMissingPiecesCount">0</span> pending)
                            </h6>
                            <small class="text-muted" id="modalPiecesSubtitle">Click 🟢 Good Product to verify or 🔴 Damage Product to report damage</small>
                        </div>
                        <!-- Filter Pill Controls: Only Mismatched vs All Pieces vs Verified -->
                        <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="Piece Filter">
                            <button type="button" class="btn btn-danger btn-sm active fw-semibold" id="btnFilterMismatched" onclick="setModalPieceFilter('mismatched')" title="Show only unverified / mismatched pieces">
                                <i class="bi bi-exclamation-circle me-1"></i> Only Mismatched (<span id="filterCountMismatched">0</span>)
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" id="btnFilterAll" onclick="setModalPieceFilter('all')" title="Show all pieces for this product">
                                <i class="bi bi-grid-3x3 me-1"></i> All Pieces (<span id="filterCountAll">0</span>)
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm fw-semibold" id="btnFilterVerified" onclick="setModalPieceFilter('verified')" title="Show only verified pieces">
                                <i class="bi bi-check2-circle me-1"></i> Verified (<span id="filterCountVerified">0</span>)
                            </button>
                        </div>
                    </div>
                    <div class="row g-2.5 overflow-y-auto overflow-x-hidden p-1" style="max-height: 380px; overflow-x: hidden !important;" id="modalMissingPiecesGrid">
                        <!-- Populated by JS based on filter -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: BATCH LIFECYCLE HISTORY & TRACEABILITY LOGS -->
<!-- ========================================================================= -->
<div class="modal fade" id="batchHistoryModal" tabindex="-1" aria-labelledby="batchHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history fs-4 text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="batchHistoryModalLabel">Batch Traceability & Lifecycle Logs</h5>
                        <small class="text-white-50">Complete manufacturing, unit origin &amp; inward audit trail for #<?= e($batch['batch_number']) ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="batchHistoryBody">
                <div class="text-center py-5" id="batchHistoryLoading">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading batch lifecycle &amp; production unit logs...</p>
                </div>
                <div id="batchHistoryContent" class="d-none">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: 2D QR CODE TAG PREVIEW (SINGLE PIECE STICKER PREVIEW) -->
<!-- ========================================================================= -->
<div class="modal fade" id="singleQRModal" tabindex="-1" aria-labelledby="singleQRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-white py-3 border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="singleQRModalLabel">
                    <i class="bi bi-qr-code me-2 text-primary"></i>2D QR Code Tag Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center bg-light">
                <div class="p-3 border rounded-3 bg-white d-inline-block shadow-sm mb-3 text-start position-relative" style="width: 290px; min-height: 120px;">
                    <div class="d-flex align-items-start gap-2.5">
                        <div class="text-center flex-shrink-0">
                            <div id="singleQRCanvas" class="bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" style="width: 78px; height: 78px;"></div>
                            <div class="badge bg-dark text-white font-monospace mt-1 px-2 py-1 w-100" id="previewDamageCode" style="font-size: 0.75rem; letter-spacing: 0.5px;">TTS092601</div>
                        </div>
                        <div class="overflow-hidden ms-1 flex-grow-1" style="margin-top: 2px;">
                            <div class="fw-bold text-uppercase text-dark text-truncate" id="previewBrandName" style="font-size: 0.82rem; letter-spacing: 0.3px;">GRM WAREHOUSE</div>
                            <div class="fw-bold text-secondary text-truncate" id="previewAttrText" style="font-size: 0.80rem;">4XL-BLUE</div>
                            <div class="text-muted small text-decoration-line-through" id="previewBasePrice" style="font-size: 0.76rem;">MRP: ₹1000.00</div>
                            <div class="fw-bold text-success" id="previewDiscPrice" style="font-size: 0.84rem;">Disc: ₹800.00</div>
                        </div>
                    </div>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Scannable 2D QR Code containing SKU, Style, Size, Color &amp; Price payload.
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Load QRCode & Camera Scanner Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
    // In-memory data structures
    const BATCH_ID = <?= (int)$batch['id'] ?>;
    const BATCH_NUMBER = <?= json_encode($batch['batch_number']) ?>;
    const BATCH_CREATED_AT = <?= json_encode($batch['created_at'] ?? '') ?>;
    const WAREHOUSE_NAME = <?= json_encode($batch['warehouse_name']) ?>;
    const WAREHOUSE_CODE = <?= json_encode($batch['warehouse_code'] ?? '') ?>;
    const OUTWARD_NUMBER = <?= json_encode($outward['outward_number'] ?? '') ?>;
    const TOTAL_EXPECTED = <?= (int)$totalExpected ?>;
    const BATCH_STATUS = <?= json_encode($batch['status']) ?>;
    const ITEMS = <?= json_encode($items) ?>;

    let activeModalVariantId = null;
    let currentScanMode = 'good'; // 'good' or 'damage'
    const SCANNED_TAGS = new Set();
    const DAMAGED_TAGS = new Set();
    const PERSISTED_INWARD_TAGS = new Set();
    const PERSISTED_DAMAGED_TAGS = new Set();
    
    const LIFECYCLE = <?= json_encode($lifecycle ?? null) ?>;
    const QC_EXCLUDED_TAGS = new Set();
    if (LIFECYCLE && LIFECYCLE.qc && LIFECYCLE.qc.pieces_data) {
        try {
            const pd = JSON.parse(LIFECYCLE.qc.pieces_data);
            pd.forEach(p => {
                if (p.status === 'REJECTED' || p.status === 'REWORK') {
                    QC_EXCLUDED_TAGS.add((p.tag_code || '').toUpperCase());
                }
            });
        } catch(e) {}
    }

    // LocalStorage keys for scan persistence across page navigation
    const SCAN_STORAGE_KEY = `grm_scan_tags_batch_${BATCH_ID}`;
    const DMG_STORAGE_KEY = `grm_dmg_tags_batch_${BATCH_ID}`;

    // Save scan state to localStorage so it survives page navigation
    function saveScanState() {
        try {
            localStorage.setItem(SCAN_STORAGE_KEY, JSON.stringify([...SCANNED_TAGS]));
            localStorage.setItem(DMG_STORAGE_KEY, JSON.stringify([...DAMAGED_TAGS]));
        } catch(e) { /* storage full or unavailable */ }
    }

    // Restore scan state from localStorage
    function restoreScanState() {
        try {
            const saved = localStorage.getItem(SCAN_STORAGE_KEY);
            const savedDmg = localStorage.getItem(DMG_STORAGE_KEY);
            if (saved) {
                JSON.parse(saved).forEach(tag => SCANNED_TAGS.add(tag));
            }
            if (savedDmg) {
                JSON.parse(savedDmg).forEach(tag => DAMAGED_TAGS.add(tag));
            }
        } catch(e) { /* parse error or unavailable */ }
    }

    // Initialize global sequence ranges for multi-variant batches
    let currentGlobalSeq = 1;
    ITEMS.forEach(it => {
        if (it.start_seq && it.end_seq) {
            it.startSeq = parseInt(it.start_seq);
            it.endSeq = parseInt(it.end_seq);
            currentGlobalSeq = it.endSeq + 1;
        } else {
            it.startSeq = currentGlobalSeq;
            const qty = parseInt(it.quantity) || 1;
            it.endSeq = currentGlobalSeq + qty - 1;
            currentGlobalSeq += qty;
        }
        const qty = parseInt(it.quantity) || 1;
        
        let excluded = 0;
        for (let p = it.startSeq; p <= it.endSeq; p++) {
            if (QC_EXCLUDED_TAGS.has(getLabelCode(p, it).toUpperCase())) excluded++;
        }
        it.quantity = Math.max(0, qty - excluded);
        it.original_quantity = qty;

        // If batch has previously received items, seed tags based on exact persisted received and damaged quantities
        if (BATCH_STATUS === 'RECEIVED') {
            if (it.received_qr_codes && String(it.received_qr_codes).trim()) {
                String(it.received_qr_codes).split(',').forEach(t => {
                    const cleanT = t.trim().toUpperCase();
                    if (cleanT) {
                        SCANNED_TAGS.add(cleanT);
                        PERSISTED_INWARD_TAGS.add(cleanT);
                    }
                });
            } else {
                const recCount = parseInt(it.received_qty) || 0;
                let currentP = it.startSeq;
                for (let i = 0; i < recCount && currentP <= it.endSeq; i++) {
                    const code = getLabelCode(currentP++, it).toUpperCase();
                    SCANNED_TAGS.add(code);
                    PERSISTED_INWARD_TAGS.add(code);
                }
            }

            if (it.damaged_qr_codes && String(it.damaged_qr_codes).trim()) {
                String(it.damaged_qr_codes).split(',').forEach(t => {
                    const cleanT = t.trim().toUpperCase();
                    if (cleanT) {
                        DAMAGED_TAGS.add(cleanT);
                        PERSISTED_DAMAGED_TAGS.add(cleanT);
                    }
                });
            } else {
                const dmgCount = parseInt(it.damaged_qty) || 0;
                let currentP = it.startSeq + (parseInt(it.received_qty) || 0);
                for (let i = 0; i < dmgCount && currentP <= it.endSeq; i++) {
                    const code = getLabelCode(currentP++, it).toUpperCase();
                    DAMAGED_TAGS.add(code);
                    PERSISTED_DAMAGED_TAGS.add(code);
                }
            }
        }
    });

    // Restore previously scanned tags from localStorage (survives page navigation and module switching)
    restoreScanState();

    // Synchronize SCANNED_TAGS & DAMAGED_TAGS with input field values
    function syncVariantTagsWithInput(item) {
        const vid = item.variant_id;
        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        const expected = parseInt(item.quantity) || 0;
        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;

        let currentGood = recvInput ? (parseInt(recvInput.value) || 0) : creditedCount;
        let currentDmg = dmgInput ? (parseInt(dmgInput.value) || 0) : (BATCH_STATUS === 'RECEIVED' ? (parseInt(item.damaged_qty) || 0) : 0);

        // Prevent reducing below previously credited stock
        if (BATCH_STATUS === 'RECEIVED' && currentGood < creditedCount) {
            currentGood = creditedCount;
            if (recvInput) recvInput.value = creditedCount;
        }

        let variantTags = [];
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            variantTags.push(getLabelCode(p, item).toUpperCase());
        }

        // Clear existing tags for this variant
        variantTags.forEach(t => {
            SCANNED_TAGS.delete(t);
            DAMAGED_TAGS.delete(t);
        });

        // Assign intact tags
        let assignedGood = 0;
        for (let i = 0; i < variantTags.length && assignedGood < currentGood; i++) {
            SCANNED_TAGS.add(variantTags[i]);
            assignedGood++;
        }

        // Assign damaged tags to subsequent piece sequence
        let assignedDmg = 0;
        for (let i = assignedGood; i < variantTags.length && assignedDmg < currentDmg; i++) {
            DAMAGED_TAGS.add(variantTags[i]);
            assignedDmg++;
        }
    }

    // Get count of verified intact tags in SCANNED_TAGS for a specific variant
    function getVariantVerifiedTagCount(item) {
        let count = 0;
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            const tag = getLabelCode(p, item).toUpperCase();
            if (SCANNED_TAGS.has(tag)) count++;
        }
        return count;
    }

    // Get count of damaged quarantined tags in DAMAGED_TAGS for a specific variant
    function getVariantDamagedTagCount(item) {
        let count = 0;
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            const tag = getLabelCode(p, item).toUpperCase();
            if (DAMAGED_TAGS.has(tag)) count++;
        }
        return count;
    }

    // Web Audio API Synthesizer for instant audible feedback
    let audioCtx = null;
    function getAudioContext() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playBeep(type = 'success') {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (type === 'success') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime); // A5
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                osc.start();
                osc.stop(ctx.currentTime + 0.15);
            } else if (type === 'double') {
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(660, ctx.currentTime);
                osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            } else if (type === 'error') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            }
        } catch (e) {
            console.log("Audio feedback note:", e);
        }
    }

    // Production-aligned Catalog Abbreviation and Tag Code Generators (e.g. GPF092601, MFK092601)
    function getCatalogAbbreviation(item) {
        // 1. Explicit QR Code Prefix (e.g. 'BSD', 'GPF')
        if (item && item.qr_code_prefix && String(item.qr_code_prefix).trim()) {
            const rawPrefix = String(item.qr_code_prefix).trim().replace(/[^A-Za-z0-9]/g, '');
            if (rawPrefix.length >= 2) return rawPrefix.slice(0, 4).toUpperCase();
        }

        // 2. Product Style Code Alpha Prefix (e.g. 'GPF-020' -> 'GPF', 'MFK-002' -> 'MFK', 'KPT-010' -> 'KPT', 'IOR-030' -> 'IOR', 'BCS-050' -> 'BCS', 'MBL-040' -> 'MBL')
        if (item && item.product_code) {
            const m = String(item.product_code).match(/^([A-Za-z]{2,5})/i);
            if (m) return m[1].toUpperCase();
        }

        // 3. Variant SKU Alpha Prefix (e.g. 'GPF020-36M-PNK' -> 'GPF')
        if (item && item.sku) {
            const m = String(item.sku).match(/^([A-Za-z]{2,5})/i);
            if (m) return m[1].toUpperCase();
        }

        // 4. Combined Category Codes (e.g. MOM + FK -> MFK, KIDS + POLO -> KPT)
        const c1 = (item && item.category_code ? String(item.category_code).trim() : '');
        const c2 = (item && item.subcategory_code ? String(item.subcategory_code).trim() : '');
        const c3 = (item && item.sub_subcategory_code ? String(item.sub_subcategory_code).trim() : '');
        if (c1 || c2 || c3) {
            const comb = (c1.slice(0, 1) + c2.slice(0, 1) + c3.slice(0, 1)).toUpperCase();
            if (comb.length >= 2) return comb;
        }

        // 5. Category Hierarchy Initials
        const n1 = (item && item.category_name ? String(item.category_name).trim() : '');
        const n2 = (item && item.subcategory_name ? String(item.subcategory_name).trim() : '');
        const n3 = (item && item.sub_subcategory_name ? String(item.sub_subcategory_name).trim() : '');
        let prefix = '';
        if (n1) prefix += n1.charAt(0).toUpperCase();
        if (n2) prefix += n2.charAt(0).toUpperCase();
        if (n3) prefix += n3.charAt(0).toUpperCase();
        if (prefix.length >= 2) return prefix.slice(0, 3);

        return 'GRM';
    }

    function getLabelCode(pieceIndex, item) {
        if (item) {
            // Priority 1: Match directly from piece_tags array from production QC / labels
            if (Array.isArray(item.piece_tags) && item.piece_tags.length > 0) {
                const sSeq = parseInt(item.start_seq || item.startSeq || 1);
                const tagIdx = pieceIndex - sSeq;
                if (tagIdx >= 0 && tagIdx < item.piece_tags.length) {
                    return String(item.piece_tags[tagIdx]).toUpperCase();
                }
            }
            // Priority 2: Derive from item start_code and start_seq
            if (item.start_code && (item.start_seq || item.startSeq)) {
                const sSeq = parseInt(item.start_seq || item.startSeq);
                const sCode = String(item.start_code).trim();
                const m = sCode.match(/^([A-Z0-9]+?)(\d{2,4})$/i);
                if (m) {
                    const prefix = m[1];
                    const numLen = m[2].length;
                    const curNum = sSeq + (pieceIndex - sSeq);
                    return (prefix + String(curNum).padStart(numLen, '0')).toUpperCase();
                }
            }
        }
        const abbr = getCatalogAbbreviation(item);
        const mfgDate = (item && (item.variant_mfg_date || item.mfg_date)) ? new Date(item.variant_mfg_date || item.mfg_date) : (BATCH_CREATED_AT ? new Date(BATCH_CREATED_AT) : new Date());
        const now = isNaN(mfgDate.getTime()) ? new Date() : mfgDate;
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = String(now.getFullYear()).slice(-2);
        const seq = String(pieceIndex).padStart(2, '0');
        return `${abbr}${month}${year}${seq}`.toUpperCase();
    }

    function getQRPayload(pieceIndex, item) {
        const tagCode = getLabelCode(pieceIndex, item);
        const sku = (item && item.sku) ? item.sku : '';
        const mrp = parseFloat(item && (item.qc_base_price || item.mrp || item.base_price || item.selling_price) ? (item.qc_base_price || item.mrp || item.base_price || item.selling_price) : 0);
        const costPrice = parseFloat(item && (item.qc_discount_price || item.purchase_price || item.production_cost || item.cost_price || item.selling_price || mrp) ? (item.qc_discount_price || item.purchase_price || item.production_cost || item.cost_price || item.selling_price || mrp) : 0);
        const fmtMrp = mrp > 0 ? mrp.toFixed(2) : '—';
        const fmtDisc = costPrice > 0 ? costPrice.toFixed(2) : '—';
        return `${tagCode}|${sku}|MRP:${fmtMrp}|Disc:${fmtDisc}`;
    }

    // Product Variant Exact QR Code Resolver
    function getExactProductQR(item) {
        if (!item) return '';
        if (item.qr_code && String(item.qr_code).trim()) {
            return String(item.qr_code).trim();
        }
        if (item.sku && String(item.sku).trim()) {
            return String(item.sku).trim();
        }
        return getLabelCode(item.startSeq || 1, item);
    }

    // 2D QR Code Tag Preview Modal Handler (Matching Single Garment Sticker Mockup)
    window.previewSinglePieceTag = function(variantId, pieceNum, tagCode) {
        const item = ITEMS.find(i => String(i.variant_id) === String(variantId));
        if (!item) return;

        let mrp = parseFloat(item.qc_base_price || item.mrp || item.base_price || item.selling_price || 0);
        let cost = parseFloat(item.qc_discount_price || item.purchase_price || item.production_cost || item.cost_price || item.selling_price || 0);

        if (isNaN(mrp)) mrp = 0;
        if (isNaN(cost)) cost = 0;

        const fmtMrp = mrp > 0 ? mrp.toFixed(2) : '—';
        const fmtCost = cost > 0 ? cost.toFixed(2) : '—';
        const finalTag = (tagCode || getLabelCode(item.startSeq + pieceNum - 1, item)).toUpperCase();

        const qrContainer = document.getElementById('modalQrCanvas');
        if (qrContainer && typeof QRCode !== 'undefined') {
            qrContainer.innerHTML = '';
            const qrPayload = `${finalTag}|${item.sku || ''}|MRP:${fmtMrp}|Disc:${fmtCost}`;
            new QRCode(qrContainer, {
                text: qrPayload,
                width: 190,
                height: 190,
                colorDark: "#0f172a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
        
        const typeLabelEl = document.getElementById('modalQrTypeLabel');
        if (typeLabelEl) {
            typeLabelEl.innerHTML = `<i class="bi bi-tag-fill me-1 text-warning"></i>Selected Tag: <strong class="text-dark">${escapeHtml(finalTag)}</strong>`;
        }
        
        const nextTagEl = document.getElementById('modalNextPieceTagText');
        if (nextTagEl) {
            nextTagEl.textContent = `${finalTag} (Preview)`;
        }

        const scanTargetSku = document.getElementById('modalScanTargetSku');
        if (scanTargetSku) {
            scanTargetSku.textContent = finalTag;
        }
        
        // Highlight the clicked item in the grid
        document.querySelectorAll('.inward-piece-qr').forEach(el => el.classList.remove('border-primary', 'shadow'));
        const clickedQr = document.getElementById(`piece-qr-${pieceNum}`);
        if (clickedQr) {
            clickedQr.classList.add('border-primary', 'shadow');
        }
    };

    let modalQrDisplayMode = 'exact'; // 'exact' (Product Exact QR) or 'piece' (Sequential Piece Tag)

    window.switchModalQrMode = function(mode) {
        modalQrDisplayMode = mode;
        const btnExact = document.getElementById('btnModeExactQr');
        const btnPiece = document.getElementById('btnModePieceTag');
        if (mode === 'exact') {
            if (btnExact) {
                btnExact.className = 'btn btn-primary active fw-semibold';
            }
            if (btnPiece) {
                btnPiece.className = 'btn btn-outline-secondary fw-semibold';
            }
        } else {
            if (btnExact) {
                btnExact.className = 'btn btn-outline-secondary fw-semibold';
            }
            if (btnPiece) {
                btnPiece.className = 'btn btn-primary active fw-semibold';
            }
        }
        if (activeModalVariantId) {
            updateModalState(activeModalVariantId);
        }
    };

    window.copyExactQrToClipboard = function() {
        const textEl = document.getElementById('modalExactQrText');
        const code = textEl ? (textEl.dataset.qrValue || textEl.textContent.trim()) : '';
        if (!code || code === '-') return;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(() => {
                showToastMessage(`Copied Product QR: ${code}`);
            }).catch(() => {
                fallbackCopy(code);
            });
        } else {
            fallbackCopy(code);
        }
    };

    function fallbackCopy(text) {
        const tempInp = document.createElement('input');
        tempInp.value = text;
        document.body.appendChild(tempInp);
        tempInp.select();
        try {
            document.execCommand('copy');
            showToastMessage(`Copied Product QR: ${text}`);
        } catch(e) {
            prompt('Copy Product QR Code:', text);
        }
        document.body.removeChild(tempInp);
    }

    function showToastMessage(msg) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '99999';
        toast.innerHTML = `
            <div class="toast show align-items-center text-white bg-dark border-0 shadow" role="alert">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-check-circle-fill text-success me-2"></i>${escapeHtml(msg)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => { toast.remove(); }, 3000);
    }

    // =========================================================================
    // SCANNER MODE SWITCH HANDLER (GOOD vs DAMAGE SCANNING)
    // =========================================================================
    window.setScanMode = function(mode) {
        currentScanMode = mode;
        const card = document.getElementById('liveScannerCard');
        const input = document.getElementById('liveScannerInput');
        const icon = document.getElementById('scannerIcon');
        const btnSubmit = document.getElementById('btnLiveScanSubmit');
        const modeBadge = document.getElementById('modeStatusBadge');
        const radioGood = document.getElementById('modeGood');
        const radioDamage = document.getElementById('modeDamage');

        const modalRadioGood = document.getElementById('modalModeGood');
        const modalRadioDamage = document.getElementById('modalModeDamage');
        const modalModeBadge = document.getElementById('modalModeStatusBadge');
        const btnModalProcess = document.getElementById('btnModalProcessNext');
        const btnModalProcessIcon = document.getElementById('btnModalProcessIcon');
        const btnModalProcessText = document.getElementById('btnModalProcessText');

        if (mode === 'damage') {
            if (radioDamage) radioDamage.checked = true;
            if (modalRadioDamage) modalRadioDamage.checked = true;
            if (card) {
                card.classList.remove('border-primary');
                card.classList.add('border-danger');
            }
            if (input) {
                input.placeholder = "🔴 DAMAGE SCAN ACTIVE: Scan QR to Inactivate & Quarantine Piece...";
                input.classList.add('border-danger', 'bg-danger-subtle');
                input.focus();
            }
            if (icon) icon.className = "bi bi-slash-circle-fill text-danger";
            if (btnSubmit) {
                btnSubmit.className = "btn btn-danger px-4 fw-semibold";
                btnSubmit.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Scan Damaged';
            }
            if (modeBadge) {
                modeBadge.innerHTML = `<span class="badge bg-danger text-white px-2 py-1 shadow-sm"><i class="bi bi-exclamation-octagon-fill me-1"></i>DAMAGE QUARANTINE MODE ACTIVE</span>`;
            }
            if (modalModeBadge) {
                modalModeBadge.innerHTML = `<span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1.5 w-100 shadow-sm" style="font-size: 0.85rem;"><i class="bi bi-slash-circle-fill me-1"></i>Quarantining damaged pieces from inventory</span>`;
            }
            if (btnModalProcess) {
                btnModalProcess.className = "btn btn-danger fw-bold py-2 flex-grow-1 shadow-sm";
            }
            if (btnModalProcessIcon) {
                btnModalProcessIcon.className = "bi bi-slash-circle-fill me-1";
            }
            if (btnModalProcessText) {
                btnModalProcessText.textContent = "Quarantine Next Piece (Damage Product)";
            }
            playBeep('error');
            showScanFeedback(`🔴 <strong>Damaged Goods Scanner Activated:</strong> Any item scanned will be recorded as Damaged, incrementing the top Damaged count and quarantining it from active inventory.`, 'warning');
        } else {
            if (radioGood) radioGood.checked = true;
            if (modalRadioGood) modalRadioGood.checked = true;
            if (card) {
                card.classList.remove('border-danger');
                card.classList.add('border-primary');
            }
            if (input) {
                input.placeholder = "Scan Variant QR / Barcode / SKU / Master Carton...";
                input.classList.remove('border-danger', 'bg-danger-subtle');
                input.focus();
            }
            if (icon) icon.className = "bi bi-qr-code text-primary";
            if (btnSubmit) {
                btnSubmit.className = "btn btn-primary px-4 fw-semibold";
                btnSubmit.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Scan';
            }
            if (modeBadge) {
                modeBadge.innerHTML = `<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check2 me-1"></i>Scanning verified sellable stock</span>`;
            }
            if (modalModeBadge) {
                modalModeBadge.innerHTML = `<span class="badge bg-success-subtle text-success border border-success px-3 py-1.5 w-100 shadow-sm" style="font-size: 0.85rem;"><i class="bi bi-check2-all me-1"></i>Currently scanning verified sellable stock</span>`;
            }
            if (btnModalProcess) {
                btnModalProcess.className = "btn btn-success fw-bold py-2 flex-grow-1 shadow-sm";
            }
            if (btnModalProcessIcon) {
                btnModalProcessIcon.className = "bi bi-check-circle-fill me-1";
            }
            if (btnModalProcessText) {
                btnModalProcessText.textContent = "Inward Next Piece (Good Product)";
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Render Master Carton QR Code safely
        const qrViewEl = document.getElementById("batchQrView");
        if (qrViewEl && typeof QRCode !== 'undefined') {
            try {
                new QRCode(qrViewEl, {
                    text: BATCH_NUMBER,
                    width: 140,
                    height: 140,
                    colorDark : "#0f172a",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            } catch(e) {
                console.warn("QR render note:", e);
            }
        }

        // 2. Initialize live cross check engine and calculate initial matrix & missing tables
        initCrossCheckEngine();

        // 3. Modal Action listeners
        const btnModalProcess = document.getElementById('btnModalProcessNext');
        if (btnModalProcess) {
            btnModalProcess.addEventListener('click', function() {
                if (activeModalVariantId) {
                    fastScanVariant(activeModalVariantId, '', currentScanMode);
                }
            });
        }

        const btnModalScanAll = document.getElementById('btnModalScanAllMissing');
        if (btnModalScanAll) {
            btnModalScanAll.addEventListener('click', function() {
                if (activeModalVariantId) {
                    creditSingleItem(activeModalVariantId);
                }
            });
        }

        const btnModalReset = document.getElementById('btnModalResetItem');
        if (btnModalReset) {
            btnModalReset.addEventListener('click', function() {
                if (activeModalVariantId) {
                    resetVariantPieces(activeModalVariantId);
                }
            });
        }

        // Auto refocus scanner input when modal closes
        const missingModal = document.getElementById('missingProductModal');
        if (missingModal) {
            missingModal.addEventListener('hidden.bs.modal', function() {
                activeModalVariantId = null;
                const liveInput = document.getElementById('liveScannerInput');
                if (liveInput) liveInput.focus();
            });
        }

        const inwardForm = document.getElementById('inwardForm');
        if (inwardForm) {
            inwardForm.addEventListener('submit', function() {
                try {
                    // Populate exact verified intact and damaged piece tags for each variant
                    ITEMS.forEach(item => {
                        const vid = item.variant_id;
                        let vReceivedTags = [];
                        let vDamagedTags = [];
                        for (let p = item.startSeq; p <= item.endSeq; p++) {
                            const t = getLabelCode(p, item).toUpperCase();
                            if (SCANNED_TAGS.has(t)) {
                                vReceivedTags.push(t);
                            } else if (DAMAGED_TAGS.has(t)) {
                                vDamagedTags.push(t);
                            }
                        }

                        let inpRecv = document.getElementById(`hidden-recv-tags-${vid}`);
                        if (!inpRecv) {
                            inpRecv = document.createElement('input');
                            inpRecv.type = 'hidden';
                            inpRecv.id = `hidden-recv-tags-${vid}`;
                            inpRecv.name = `items[${vid}][received_tags]`;
                            inwardForm.appendChild(inpRecv);
                        }
                        inpRecv.value = vReceivedTags.join(',');

                        let inpDmg = document.getElementById(`hidden-dmg-tags-${vid}`);
                        if (!inpDmg) {
                            inpDmg = document.createElement('input');
                            inpDmg.type = 'hidden';
                            inpDmg.id = `hidden-dmg-tags-${vid}`;
                            inpDmg.name = `items[${vid}][damaged_tags]`;
                            inwardForm.appendChild(inpDmg);
                        }
                        inpDmg.value = vDamagedTags.join(',');
                    });
                    localStorage.removeItem(SCAN_STORAGE_KEY);
                    localStorage.removeItem(DMG_STORAGE_KEY);
                } catch(e) {}
            });
        }

        initCameraScanner();
        setupGlobalScannerListener();
    });

    // Global USB / Bluetooth barcode scanner listener
    let globalScanBuffer = '';
    let lastKeyStrokeTime = Date.now();

    function setupGlobalScannerListener() {
        window.addEventListener('keydown', function(e) {
            const activeEl = document.activeElement;
            const isEditingInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA');
            const now = Date.now();
            const timeDiff = now - lastKeyStrokeTime;
            lastKeyStrokeTime = now;

            if (e.key === 'Enter') {
                if (globalScanBuffer.length >= 3) {
                    e.preventDefault();
                    const code = globalScanBuffer;
                    globalScanBuffer = '';
                    processScanInput(code);
                    return;
                }
            }

            if (e.key.length === 1) {
                if (timeDiff > 120) {
                    globalScanBuffer = '';
                }
                globalScanBuffer += e.key;
            }
        }, true);
    }

    function initCrossCheckEngine() {
        const liveInput = document.getElementById('liveScannerInput');
        const btnScan = document.getElementById('btnLiveScanSubmit');
        const btnAutoFill = document.getElementById('btnAutoFillExpected');

        // Sync input fields and damage indicators from current SCANNED_TAGS & DAMAGED_TAGS (including restored localStorage state)
        ITEMS.forEach(item => {
            const vid = item.variant_id;
            const goodCount = getVariantVerifiedTagCount(item);
            const dmgCount = getVariantDamagedTagCount(item);
            const recvInput = document.getElementById(`input-recv-${vid}`);
            const dmgInput = document.getElementById(`input-dmg-${vid}`);
            const dmgLabel = document.getElementById(`dmg-label-${vid}`);
            const btnToggle = document.getElementById(`btn-toggle-dmg-${vid}`);
            const iconToggle = document.getElementById(`icon-dmg-${vid}`);

            if (recvInput) recvInput.value = goodCount;
            if (dmgInput) {
                dmgInput.value = dmgCount;
                if (dmgCount > 0) {
                    dmgInput.readOnly = false;
                    dmgInput.removeAttribute('readonly');
                    dmgInput.classList.remove('is-locked');
                    dmgInput.classList.add('is-active');
                    if (dmgLabel) {
                        dmgLabel.textContent = `${dmgCount} QUARANTINED`;
                        dmgLabel.classList.remove('d-none');
                    }
                    if (btnToggle) btnToggle.className = 'btn btn-sm btn-danger text-white p-1 shadow-sm';
                    if (iconToggle) iconToggle.className = 'bi bi-exclamation-octagon-fill';
                }
            }
        });

        // Calculate metrics and render matrix and missing tables
        recalculateMatrix();

        // Focus scanner input
        if (liveInput) {
            liveInput.focus();
            liveInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    processScanInput(liveInput.value);
                }
            });
        }

        if (btnScan) {
            btnScan.addEventListener('click', function() {
                if (liveInput) processScanInput(liveInput.value);
            });
        }

        if (btnAutoFill) {
            btnAutoFill.addEventListener('click', function() {
                autoFillAllExpected();
            });
        }

        // Attach change listeners to table inputs for manual keypad entry
        document.querySelectorAll('.item-received-input, .item-damaged-input').forEach(input => {
            input.addEventListener('input', function() {
                const vid = this.dataset.variantId;
                const item = ITEMS.find(i => String(i.variant_id) === String(vid));
                if (item) {
                    syncVariantTagsWithInput(item);
                }
                recalculateMatrix();
                if (activeModalVariantId) {
                    updateModalState(activeModalVariantId);
                }
            });
        });
    }

    function processScanInput(rawCode) {
        // Strip non-printable ASCII and control characters
        const code = (rawCode || '').replace(/[^\x20-\x7E]/g, '').trim();
        const liveInput = document.getElementById('liveScannerInput');
        if (!code) return;

        // Check if Master Carton QR or Outward Number
        if (code === BATCH_NUMBER || code === OUTWARD_NUMBER || code.includes(BATCH_NUMBER) || (OUTWARD_NUMBER && code.includes(OUTWARD_NUMBER))) {
            if (currentScanMode === 'damage') {
                showScanFeedback("⚠️ Master Carton QR cannot be marked damaged en-masse. Switch to Good mode or scan individual damaged pieces.", "warning");
                playBeep('error');
            } else {
                let totalAcct = 0;
                let totalCredited = 0;
                ITEMS.forEach(it => {
                    totalAcct += (getVariantVerifiedTagCount(it) + getVariantDamagedTagCount(it));
                    totalCredited += (parseInt(it.received_qty) || 0) + (parseInt(it.damaged_qty) || 0);
                });
                if (totalCredited >= TOTAL_EXPECTED || totalAcct >= TOTAL_EXPECTED) {
                    playBeep('error');
                    showScanFeedback(`🔒 <strong>ALREADY INWARDED:</strong> Master Carton QR for Batch #${BATCH_NUMBER} has ALREADY been fully scanned & inwarded (${TOTAL_EXPECTED} pcs)!`, "warning");
                } else {
                    autoFillAllExpected();
                    playBeep('double');
                    showScanFeedback("⚡ Master Carton QR scanned! Remaining " + (TOTAL_EXPECTED - totalCredited) + " pcs verified intact!", "success");
                }
            }
            if (liveInput) { liveInput.value = ''; liveInput.focus(); }
            return;
        }

        let cleanCode = code.toLowerCase();
        let extractedTag = '';
        let extractedVariantDesc = '';

        // Handle Pipe format: TagCode|SKU_or_Size-Color|MRP|Disc (e.g. BRF92601|Extra Small-Baby Pink|MRP:1000|Disc:500)
        if (code.includes('|')) {
            const parts = code.split('|');
            extractedTag = parts[0].trim().toLowerCase();
            if (parts.length > 1) extractedVariantDesc = parts[1].trim().toLowerCase();
        } else if (code.startsWith('{') && code.endsWith('}')) {
            try {
                const j = JSON.parse(code);
                if (j.tag_code) extractedTag = j.tag_code.trim().toLowerCase();
                if (j.sku) extractedVariantDesc = j.sku.trim().toLowerCase();
                if (j.variant) extractedVariantDesc = j.variant.trim().toLowerCase();
            } catch(e) {}
        } else {
            extractedTag = cleanCode;
        }

        let matched = false;
        let matchedItem = null;
        let identifiedTag = '';

        // Strategy 0: Match Exact Product Variant QR Code (e.g. pv.qr_code, Exact QR tag)
        ITEMS.forEach(item => {
            if (matched) return;
            const vQr = (item.qr_code || '').trim().toLowerCase();
            const exactQr = getExactProductQR(item).toLowerCase();
            if (
                (vQr && (vQr === cleanCode || (extractedTag && vQr === extractedTag) || (extractedVariantDesc && vQr === extractedVariantDesc) || cleanCode.includes(vQr))) ||
                (exactQr && (exactQr === cleanCode || (extractedTag && exactQr === extractedTag) || (extractedVariantDesc && exactQr === extractedVariantDesc) || cleanCode.includes(exactQr)))
            ) {
                matched = true;
                matchedItem = item;
                identifiedTag = (vQr || exactQr).toUpperCase();
            }
        });

        // Strategy 1: Match exact Piece Tag Code in sequence (e.g. BRF92601 in startSeq..endSeq)
        ITEMS.forEach(item => {
            if (matched) return;
            const start = item.startSeq || 1;
            const end = item.endSeq || (parseInt(item.quantity) || 1);

            for (let p = start; p <= end; p++) {
                const pTag = getLabelCode(p, item).toLowerCase();
                if (pTag === cleanCode || (extractedTag && pTag === extractedTag)) {
                    matched = true;
                    matchedItem = item;
                    identifiedTag = pTag.toUpperCase();
                    return;
                }
            }
        });

        // Strategy 2: Match by Sequence Number extracted from Tag (e.g. BRF92601 -> 1, BRF92615 -> 15)
        if (!matched && extractedTag) {
            const tagMatch = extractedTag.match(/[a-z]+[0-9]*?([0-9]{1,4})$/i);
            if (tagMatch && tagMatch[1]) {
                const seqNum = parseInt(tagMatch[1], 10);
                if (!isNaN(seqNum)) {
                    ITEMS.forEach(item => {
                        if (matched) return;
                        const start = item.startSeq || 1;
                        const end = item.endSeq || (parseInt(item.quantity) || 1);
                        if (seqNum >= start && seqNum <= end) {
                            matched = true;
                            matchedItem = item;
                            identifiedTag = ''; // Exact sequence match, but let fastScanVariant find the right sequence if needed, or pass the piece tag
                            // Actually, if it's a sequence match, we DO want to pass the tag!
                            identifiedTag = extractedTag.toUpperCase();
                        }
                    });
                }
            }
        }

        // Strategy 3: Match by SKU or Variant Description (e.g. "Extra Small-Baby Pink", "GRM-STY-0001-XS-BP")
        if (!matched) {
            ITEMS.forEach(item => {
                if (matched) return;
                const sku = (item.sku || '').trim().toLowerCase();
                const sizeColor = ((item.size_name || '') + '-' + (item.color_name || '')).trim().toLowerCase();
                const sizeColorSpace = ((item.size_name || '') + ' - ' + (item.color_name || '')).trim().toLowerCase();
                const sizeColorFlat = ((item.size_name || '') + (item.color_name || '')).trim().toLowerCase().replace(/\s+/g, '');
                const sizeCodeColorCode = ((item.size_code || '') + '-' + (item.color_name || '')).trim().toLowerCase();

                // Check SKU
                if (sku && (sku === cleanCode || (extractedVariantDesc && sku === extractedVariantDesc) || cleanCode.includes(sku))) {
                    matched = true;
                    matchedItem = item;
                    if (extractedTag) identifiedTag = extractedTag.toUpperCase();
                    return;
                }

                // Check Size - Color combinations
                if (extractedVariantDesc && (
                    extractedVariantDesc === sizeColor || 
                    extractedVariantDesc === sizeColorSpace || 
                    extractedVariantDesc.replace(/\s+/g, '') === sizeColorFlat ||
                    extractedVariantDesc === sizeCodeColorCode
                )) {
                    matched = true;
                    matchedItem = item;
                    if (extractedTag) identifiedTag = extractedTag.toUpperCase();
                    return;
                }

                if (cleanCode.includes(sizeColor) || (item.size_name && cleanCode.includes(item.size_name.toLowerCase()) && item.color_name && cleanCode.includes(item.color_name.toLowerCase()))) {
                    matched = true;
                    matchedItem = item;
                    if (extractedTag) identifiedTag = extractedTag.toUpperCase();
                    return;
                }
            });
        }

        // Strategy 4: Fallback single item match
        if (!matched && ITEMS.length === 1) {
            matched = true;
            matchedItem = ITEMS[0];
            if (extractedTag) identifiedTag = extractedTag.toUpperCase();
        }

        if (matched && matchedItem) {
            fastScanVariant(matchedItem.variant_id, identifiedTag, currentScanMode);

            // Auto-switch modal to the matched variant if modal is open
            const modalEl = document.getElementById('missingProductModal');
            if (modalEl && modalEl.classList.contains('show')) {
                activeModalVariantId = matchedItem.variant_id;
                updateModalState(matchedItem.variant_id);
            }
        } else {
            playBeep('error');
            showScanFeedback(`⚠️ Scanned QR code '<strong>${escapeHtml(code)}</strong>' not found in this inward batch!`, "danger");
        }

        if (liveInput) {
            liveInput.value = '';
            liveInput.focus();
        }
    }

    function autoFillAllExpected() {
        ITEMS.forEach(item => {
            const vid = item.variant_id;
            const recvInput = document.getElementById(`input-recv-${vid}`);
            const dmgInput = document.getElementById(`input-dmg-${vid}`);
            const dmgLabel = document.getElementById(`dmg-label-${vid}`);
            const btnToggle = document.getElementById(`btn-toggle-dmg-${vid}`);
            const iconToggle = document.getElementById(`icon-dmg-${vid}`);

            if (recvInput) recvInput.value = item.quantity;
            if (dmgInput) {
                dmgInput.value = 0;
                dmgInput.readOnly = true;
                dmgInput.setAttribute('readonly', 'readonly');
                dmgInput.classList.add('is-locked');
                dmgInput.classList.remove('is-active');
            }
            if (dmgLabel) dmgLabel.classList.add('d-none');
            if (btnToggle) btnToggle.className = 'btn btn-sm btn-outline-danger p-1';
            if (iconToggle) iconToggle.className = 'bi bi-slash-circle';

            // Mark all valid sequential tags as verified intact (preserving previously quarantined damaged tags)
            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const tag = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(tag)) continue;
                if (PERSISTED_DAMAGED_TAGS.has(tag)) continue;
                SCANNED_TAGS.add(tag);
                DAMAGED_TAGS.delete(tag);
            }
        });
        recalculateMatrix();
        if (activeModalVariantId) {
            updateModalState(activeModalVariantId);
        }
    }

    // =========================================================================
    // TOGGLE DAMAGE INPUT & INACTIVATE PRODUCT HANDLER
    // =========================================================================
    window.toggleDamageInput = function(vid) {
        const input = document.getElementById(`input-dmg-${vid}`);
        const btn = document.getElementById(`btn-toggle-dmg-${vid}`);
        const icon = document.getElementById(`icon-dmg-${vid}`);
        const label = document.getElementById(`dmg-label-${vid}`);
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));

        if (!input) return;

        if (input.readOnly || input.hasAttribute('readonly')) {
            // Unlock / Enable damage mode
            input.readOnly = false;
            input.removeAttribute('readonly');
            input.classList.remove('is-locked');
            input.classList.add('is-active');

            if (btn) {
                btn.className = 'btn btn-sm btn-danger text-white p-1 shadow-sm';
                btn.title = 'Damage active. Click to lock/reset.';
            }
            if (icon) {
                icon.className = 'bi bi-exclamation-octagon-fill';
            }
            if (label) {
                label.classList.remove('d-none');
            }

            // If damage is currently 0, add 1 piece damage immediately
            const currentVal = parseInt(input.value) || 0;
            if (currentVal === 0) {
                adjustDamage(vid, 1);
            } else {
                input.focus();
                input.select();
                playBeep('error');
                recalculateMatrix();
            }
            showScanFeedback(`⚠️ <strong>Damaged Goods Reporting Active:</strong> Damaged units for <strong>${escapeHtml(item ? item.sku : 'Product')}</strong> are quarantined and inactivated from sellable stock.`, 'warning');
        } else {
            // Lock / Inactivate damage mode
            const currentVal = parseInt(input.value) || 0;
            if (currentVal === 0) {
                input.readOnly = true;
                input.setAttribute('readonly', 'readonly');
                input.classList.add('is-locked');
                input.classList.remove('is-active');
                if (btn) {
                    btn.className = 'btn btn-sm btn-outline-danger p-1';
                    btn.title = 'Unlock damage reporting for this variant';
                }
                if (icon) {
                    icon.className = 'bi bi-slash-circle';
                }
                if (label) {
                    label.classList.add('d-none');
                }
                recalculateMatrix();
                showScanFeedback(`ℹ️ Damage mode locked. All verified pieces for <strong>${escapeHtml(item ? item.sku : 'Product')}</strong> are intact.`, 'info');
            } else {
                // If user wants to reset to 0 and lock:
                if (confirm(`Reset damaged count to 0 for ${item ? item.sku : 'this product'} and lock?`)) {
                    // Remove damaged tags and restore to intact or pending
                    for (let p = item.startSeq; p <= item.endSeq; p++) {
                        const tag = getLabelCode(p, item).toUpperCase();
                        if (QC_EXCLUDED_TAGS.has(tag)) continue;
                        DAMAGED_TAGS.delete(tag);
                    }
                    input.value = 0;
                    input.readOnly = true;
                    input.setAttribute('readonly', 'readonly');
                    input.classList.add('is-locked');
                    input.classList.remove('is-active');
                    if (btn) {
                        btn.className = 'btn btn-sm btn-outline-danger p-1';
                        btn.title = 'Unlock damage reporting for this variant';
                    }
                    if (icon) {
                        icon.className = 'bi bi-slash-circle';
                    }
                    if (label) {
                        label.classList.add('d-none');
                    }
                    recalculateMatrix();
                    if (activeModalVariantId) updateModalState(activeModalVariantId);
                } else {
                    input.select();
                }
            }
        }
    };

    // =========================================================================
    // QUICK ADJUST DAMAGE (+1 / -1) & INACTIVATE PIECES
    // =========================================================================
    window.adjustDamage = function(vid, delta) {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;

        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        const recvInput = document.getElementById(`input-recv-${vid}`);
        const btnToggle = document.getElementById(`btn-toggle-dmg-${vid}`);
        const iconToggle = document.getElementById(`icon-dmg-${vid}`);
        const dmgLabel = document.getElementById(`dmg-label-${vid}`);
        const row = document.getElementById(`row-${vid}`);

        const expected = parseInt(item.quantity) || 0;
        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;
        const currentGood = getVariantVerifiedTagCount(item);
        const currentDmg = getVariantDamagedTagCount(item);

        if (delta > 0) {
            // Adding damaged piece (+1)
            if (currentGood + currentDmg >= expected) {
                // If all pieces already accounted for, convert one uncommitted Good piece to Damaged
                if (currentGood > creditedCount) {
                    for (let p = item.endSeq; p >= item.startSeq + creditedCount; p--) {
                        const tag = getLabelCode(p, item).toUpperCase();
                        if (QC_EXCLUDED_TAGS.has(tag)) continue;
                        if (SCANNED_TAGS.has(tag)) {
                            SCANNED_TAGS.delete(tag);
                            DAMAGED_TAGS.add(tag);
                            break;
                        }
                    }
                } else {
                    playBeep('error');
                    showScanFeedback(`⚠️ Cannot mark as damaged: All ${creditedCount} verified pcs for <strong>${escapeHtml(item.sku)}</strong> are already credited to warehouse inventory.`, 'warning');
                    return;
                }
            } else {
                // Assign next unverified piece tag as damaged
                for (let p = item.startSeq + creditedCount; p <= item.endSeq; p++) {
                    const tag = getLabelCode(p, item).toUpperCase();
                    if (QC_EXCLUDED_TAGS.has(tag)) continue;
                    if (!SCANNED_TAGS.has(tag) && !DAMAGED_TAGS.has(tag)) {
                        DAMAGED_TAGS.add(tag);
                        break;
                    }
                }
            }

            // Unlock damage input and show active quarantine badges
            if (dmgInput) {
                dmgInput.readOnly = false;
                dmgInput.removeAttribute('readonly');
                dmgInput.classList.remove('is-locked');
                dmgInput.classList.add('is-active');
            }
            if (btnToggle) btnToggle.className = 'btn btn-sm btn-danger text-white p-1 shadow-sm';
            if (iconToggle) iconToggle.className = 'bi bi-exclamation-octagon-fill';
            if (dmgLabel) dmgLabel.classList.remove('d-none');

            playBeep('error');
            if (row) {
                row.classList.add('table-danger');
                setTimeout(() => row.classList.remove('table-danger'), 700);
            }
        } else if (delta < 0) {
            // Decreasing damage count (-1)
            if (currentDmg <= 0) return;
            for (let p = item.endSeq; p >= item.startSeq; p--) {
                const tag = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(tag)) continue;
                if (DAMAGED_TAGS.has(tag)) {
                    DAMAGED_TAGS.delete(tag);
                    break;
                }
            }
            playBeep('success');
        }

        const newGood = getVariantVerifiedTagCount(item);
        const newDmg = getVariantDamagedTagCount(item);

        if (recvInput) recvInput.value = newGood;
        if (dmgInput) dmgInput.value = newDmg;

        if (newDmg === 0 && dmgInput) {
            dmgInput.readOnly = true;
            dmgInput.setAttribute('readonly', 'readonly');
            dmgInput.classList.add('is-locked');
            dmgInput.classList.remove('is-active');
            if (btnToggle) btnToggle.className = 'btn btn-sm btn-outline-danger p-1';
            if (iconToggle) iconToggle.className = 'bi bi-slash-circle';
            if (dmgLabel) dmgLabel.classList.add('d-none');
        }

        recalculateMatrix();
        if (delta > 0) {
            showScanFeedback(`🔴 Recorded Damaged/Inactivated Piece for <strong>${escapeHtml(item.sku)}</strong>. Total Damaged: <strong>${newDmg} pcs</strong> (Quarantined from inventory).`, 'danger');
        } else {
            showScanFeedback(`ℹ️ Damaged count reduced for <strong>${escapeHtml(item.sku)}</strong>. Total Damaged: <strong>${newDmg} pcs</strong>.`, 'info');
        }

        if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
            updateModalState(vid);
        }
    };

    // Mark individual piece tag as damaged
    window.markPieceDamaged = function(vid, tagCode) {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;

        const normalizedTag = tagCode.toUpperCase();
        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;

        // Verify if piece was already credited in previous inward session
        let tagSeq = -1;
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            if (getLabelCode(p, item).toUpperCase() === normalizedTag) {
                tagSeq = p;
                break;
            }
        }
        if (PERSISTED_INWARD_TAGS.has(normalizedTag) || (tagSeq !== -1 && (tagSeq - item.startSeq) < creditedCount && BATCH_STATUS === 'RECEIVED')) {
            playBeep('error');
            showScanFeedback(`🔒 Piece <span class="badge bg-dark font-monospace">${escapeHtml(normalizedTag)}</span> was already credited to warehouse inventory and cannot be changed to damaged.`, 'warning');
            return;
        }

        SCANNED_TAGS.delete(normalizedTag);
        DAMAGED_TAGS.add(normalizedTag);

        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        if (recvInput) recvInput.value = getVariantVerifiedTagCount(item);
        if (dmgInput) {
            dmgInput.value = getVariantDamagedTagCount(item);
            dmgInput.readOnly = false;
            dmgInput.removeAttribute('readonly');
            dmgInput.classList.remove('is-locked');
            dmgInput.classList.add('is-active');
        }

        playBeep('error');
        recalculateMatrix();
        showScanFeedback(`🔴 Piece <span class="badge bg-dark font-monospace">${escapeHtml(normalizedTag)}</span> marked as DAMAGED & QUARANTINED for <strong>${escapeHtml(item.sku)}</strong>.`, 'danger');

        if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
            updateModalState(vid);
        }
    };

    // Restore individual piece tag to verified intact
    window.markPieceIntact = function(vid, tagCode) {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;

        const normalizedTag = tagCode.toUpperCase();
        DAMAGED_TAGS.delete(normalizedTag);
        SCANNED_TAGS.add(normalizedTag);

        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        if (recvInput) recvInput.value = getVariantVerifiedTagCount(item);
        if (dmgInput) {
            const remainingDmg = getVariantDamagedTagCount(item);
            dmgInput.value = remainingDmg;
            if (remainingDmg === 0) {
                dmgInput.readOnly = true;
                dmgInput.setAttribute('readonly', 'readonly');
                dmgInput.classList.add('is-locked');
                dmgInput.classList.remove('is-active');
            }
        }

        playBeep('success');
        recalculateMatrix();
        showScanFeedback(`🟢 Piece <span class="badge bg-dark font-monospace">${escapeHtml(normalizedTag)}</span> restored to VERIFIED INTACT for <strong>${escapeHtml(item.sku)}</strong>.`, 'success');

        if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
            updateModalState(vid);
        }
    };

    // =========================================================================
    // RECALCULATE CROSS-CHECK MATRIX & METRIC CARDS
    // =========================================================================
    function recalculateMatrix() {
        // Persist scan state to sessionStorage for navigation resilience
        saveScanState();

        let totalScanned = 0;
        let totalDamaged = 0;
        let missingItems = [];

        ITEMS.forEach(item => {
            const vid = item.variant_id;
            const expected = parseInt(item.quantity) || 0;
            const recvInput = document.getElementById(`input-recv-${vid}`);
            const dmgInput = document.getElementById(`input-dmg-${vid}`);
            const dmgLabel = document.getElementById(`dmg-label-${vid}`);
            const btnToggle = document.getElementById(`btn-toggle-dmg-${vid}`);
            const iconToggle = document.getElementById(`icon-dmg-${vid}`);

            const received = getVariantVerifiedTagCount(item);
            const damaged = getVariantDamagedTagCount(item);

            if (recvInput && parseInt(recvInput.value) !== received) {
                recvInput.value = received;
            }
            if (dmgInput && parseInt(dmgInput.value) !== damaged) {
                dmgInput.value = damaged;
            }

            totalScanned += received;
            totalDamaged += damaged;

            // Sync damage visual state
            if (damaged > 0) {
                if (dmgLabel) {
                    dmgLabel.textContent = `${damaged} QUARANTINED`;
                    dmgLabel.classList.remove('d-none');
                }
                if (dmgInput) {
                    dmgInput.classList.add('is-active');
                    dmgInput.classList.remove('is-locked');
                }
                if (btnToggle) {
                    btnToggle.className = 'btn btn-sm btn-danger text-white p-1 shadow-sm';
                }
                if (iconToggle) {
                    iconToggle.className = 'bi bi-exclamation-octagon-fill';
                }
            } else {
                if (dmgInput && (dmgInput.readOnly || dmgInput.hasAttribute('readonly'))) {
                    if (dmgLabel) dmgLabel.classList.add('d-none');
                    dmgInput.classList.add('is-locked');
                    dmgInput.classList.remove('is-active');
                    if (btnToggle) btnToggle.className = 'btn btn-sm btn-outline-danger p-1';
                    if (iconToggle) iconToggle.className = 'bi bi-slash-circle';
                }
            }

            const totalAccounted = received + damaged;
            const diff = totalAccounted - expected;
            const diffBadge = document.getElementById(`diff-badge-${vid}`);
            const statusBadge = document.getElementById(`status-badge-${vid}`);

            if (diffBadge) {
                diffBadge.textContent = (diff > 0 ? `+${diff}` : `${diff}`) + ' pcs';
                if (diff === 0) {
                    diffBadge.className = 'badge bg-success fs-7 font-monospace';
                } else if (diff < 0) {
                    diffBadge.className = 'badge bg-danger fs-7 font-monospace';
                } else {
                    diffBadge.className = 'badge bg-primary fs-7 font-monospace';
                }
            }

            if (statusBadge) {
                if (diff === 0) {
                    if (damaged > 0) {
                        statusBadge.textContent = `DMG (${damaged})`;
                        statusBadge.className = 'badge bg-warning text-dark border border-warning shadow-sm';
                    } else {
                        statusBadge.textContent = 'MATCHED';
                        statusBadge.className = 'badge bg-success-subtle text-success border border-success';
                    }
                } else if (diff < 0) {
                    const missingQty = Math.abs(diff);
                    statusBadge.textContent = `SHORT (${missingQty})`;
                    statusBadge.className = 'badge bg-danger-subtle text-danger border border-danger';
                } else {
                    statusBadge.textContent = `EXCESS (+${diff})`;
                    statusBadge.className = 'badge bg-primary-subtle text-primary border border-primary';
                }
            }

            if (diff < 0) {
                missingItems.push({
                    variant_id: vid,
                    sku: item.sku,
                    qr_code: item.qr_code || item.sku,
                    qr_code_prefix: item.qr_code_prefix || '',
                    category_code: item.category_code || '',
                    subcategory_code: item.subcategory_code || '',
                    sub_subcategory_code: item.sub_subcategory_code || '',
                    mrp: item.qc_discount_price || item.qc_base_price || item.mrp || item.selling_price || 0,
                    selling_price: item.qc_discount_price || item.qc_base_price || item.selling_price || 0,
                    product_name: item.product_name || 'Finished Garment',
                    category_name: item.category_name || '',
                    product_image: item.product_image || '',
                    size_name: item.size_name || 'Free',
                    color_name: item.color_name || 'Std',
                    expected: expected,
                    scanned: received,
                    damaged: damaged,
                    missing: Math.abs(diff),
                    startSeq: item.startSeq || 1,
                    endSeq: item.endSeq || expected
                });
            }
        });

        // Update Top Summary Cards
        const statScanned = document.getElementById('statScanned');
        const statDamaged = document.getElementById('statDamaged');
        const statDamagedSubtext = document.getElementById('statDamagedSubtext');
        const statMissing = document.getElementById('statMissing');
        const statProgressPct = document.getElementById('statProgressPct');
        const statProgressBar = document.getElementById('statProgressBar');
        const btnScannedCount = document.getElementById('btnCreditScannedCount');

        const overallAccounted = totalScanned + totalDamaged;
        const overallMissing = Math.max(0, TOTAL_EXPECTED - overallAccounted);
        const pct = TOTAL_EXPECTED > 0 ? Math.min(100, Math.round((overallAccounted / TOTAL_EXPECTED) * 100)) : 100;

        let prevCredited = 0;
        if (BATCH_STATUS === 'RECEIVED') {
            ITEMS.forEach(it => {
                prevCredited += (parseInt(it.received_qty) || 0);
            });
        }
        const deltaSession = Math.max(0, totalScanned - prevCredited);

        if (statScanned) statScanned.innerHTML = `${totalScanned} <small class="fs-6 text-muted">pcs</small>`;
        if (statDamaged) statDamaged.innerHTML = `${totalDamaged} <small class="fs-6 text-muted">pcs</small>`;
        if (statDamagedSubtext) {
            statDamagedSubtext.textContent = totalDamaged > 0 ? `${totalDamaged} quarantined unit(s)` : '0 quarantined units';
        }
        if (statMissing) statMissing.innerHTML = `${overallMissing} <small class="fs-6 text-muted">pcs</small>`;
        if (statProgressPct) statProgressPct.textContent = `${pct}%`;
        if (btnScannedCount) {
            if (BATCH_STATUS === 'RECEIVED' && prevCredited > 0) {
                btnScannedCount.textContent = `+${deltaSession}`;
            } else {
                btnScannedCount.textContent = totalScanned;
            }
        }
        if (statProgressBar) {
            statProgressBar.style.width = `${pct}%`;
            if (pct >= 100 && overallMissing === 0) {
                statProgressBar.className = totalDamaged > 0 ? 'progress-bar bg-warning' : 'progress-bar bg-success';
            } else {
                statProgressBar.className = 'progress-bar bg-info';
            }
        }

        // Render Missing Products Table
        renderMissingTable(missingItems);
    }

    function renderMissingTable(missingItems) {
        const tbody = document.getElementById('missingTableBody');
        const emptyNotice = document.getElementById('missingEmptyNotice');
        const missingBadge = document.getElementById('missingBadgeCount');
        const missingTable = document.getElementById('missingTable');

        if (!tbody) return;

        if (missingBadge) {
            missingBadge.textContent = `${missingItems.length} SKU(s) pending`;
            missingBadge.className = missingItems.length > 0 ? 'badge bg-danger rounded-pill px-3 py-2 fs-7' : 'badge bg-success rounded-pill px-3 py-2 fs-7';
        }

        if (missingItems.length === 0) {
            tbody.innerHTML = '';
            if (missingTable) missingTable.classList.add('d-none');
            if (emptyNotice) emptyNotice.classList.remove('d-none');
            return;
        }

        if (missingTable) missingTable.classList.remove('d-none');
        if (emptyNotice) emptyNotice.classList.add('d-none');

        let html = '';
        missingItems.forEach(item => {
            const imgHtml = item.product_image 
                ? `<img src="${escapeHtml(item.product_image)}" alt="" class="rounded border shadow-sm" style="width: 36px; height: 36px; object-fit: cover;">`
                : `<div class="rounded bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;"><i class="bi bi-box-seam fs-5"></i></div>`;

            // Calculate exact pending unscanned tags for this variant
            let pendingTags = [];
            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const t = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(t)) continue;
                if (!SCANNED_TAGS.has(t) && !DAMAGED_TAGS.has(t)) {
                    pendingTags.push(t);
                }
            }

            const nextPendingTag = pendingTags[0] || getLabelCode(item.startSeq, item);
            let tagRangeDisplay = nextPendingTag;
            if (pendingTags.length > 1) {
                tagRangeDisplay = `${pendingTags[0]} - ${pendingTags[pendingTags.length - 1]}`;
            }

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            ${imgHtml}
                            <div>
                                <div class="fw-bold text-dark small">${escapeHtml(item.product_name)}</div>
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <span class="badge bg-dark font-monospace text-white" style="font-size: 0.72rem;">${escapeHtml(item.sku)}</span>
                                    ${item.category_name ? `<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.72rem;">${escapeHtml(item.category_name)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-dark text-white font-monospace d-block mb-1" style="font-size: 0.72rem;">
                            <i class="bi bi-qr-code me-1 text-warning"></i>${escapeHtml(tagRangeDisplay)}
                        </span>
                        <small class="text-muted d-block font-monospace" style="font-size: 0.72rem;">Next: <strong class="text-danger">${escapeHtml(nextPendingTag)}</strong></small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">${escapeHtml(item.size_name)}</span>
                        <span class="ms-1 small text-muted">${escapeHtml(item.color_name)}</span>
                    </td>
                    <td class="text-center fw-bold fs-6">${item.expected}</td>
                    <td class="text-center fw-semibold text-success">${item.scanned}</td>
                    <td class="text-end fw-bold text-danger fs-6">-${item.missing} pcs</td>
                    <td class="text-center">
                        <div class="d-inline-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold shadow-sm text-nowrap" onclick="openMissingProductModal(${item.variant_id}, 'mismatched')" title="View Missed Pieces & QR Breakdown">
                                <i class="bi bi-eye me-1"></i> View
                            </button>
                            <button type="button" class="btn btn-sm btn-success py-1 px-2 fw-semibold shadow-sm text-nowrap" onclick="fastScanVariant(${item.variant_id}, '', 'good')" title="Scan / Inward Good Product">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 fw-semibold shadow-sm text-nowrap" onclick="fastScanVariant(${item.variant_id}, '', 'damage')" title="Report Damage Product">
                                <i class="bi bi-slash-circle"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    let modalPieceFilter = 'mismatched'; // 'mismatched' | 'all' | 'verified'

    function setModalPieceFilter(filter) {
        modalPieceFilter = filter;
        if (activeModalVariantId) {
            updateModalState(activeModalVariantId);
        }
    }

    function openMissingProductModal(variantId, initialFilter = 'mismatched') {
        activeModalVariantId = variantId;
        modalPieceFilter = initialFilter;
        updateModalState(variantId);
        
        const modalEl = document.getElementById('missingProductModal');
        const modalHeader = modalEl ? modalEl.querySelector('.modal-header') : null;
        const modalTitle = document.getElementById('missingProductModalLabel');
        
        if (modalHeader && modalTitle) {
            if (initialFilter === 'mismatched') {
                modalHeader.className = 'modal-header bg-danger text-white border-0';
                modalTitle.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Missed / Pending Pieces Inspection';
            } else {
                modalHeader.className = 'modal-header bg-dark text-white border-0';
                modalTitle.innerHTML = '<i class="bi bi-box-seam-fill me-2 text-warning"></i>Product Specifications &amp; All Individual Piece QR Codes';
            }
        }

        if (modalEl) {
            setScanMode(currentScanMode);
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function updateModalState(variantId) {
        const item = ITEMS.find(i => String(i.variant_id) === String(variantId));
        if (!item) return;

        const expected = parseInt(item.quantity) || 1;
        const scanned = getVariantVerifiedTagCount(item);
        const damaged = getVariantDamagedTagCount(item);
        const missing = Math.max(0, expected - (scanned + damaged));
        const exactQr = getExactProductQR(item);

        // Find the next unverified sequential piece tag for this specific variant
        let currentTagSeq = item.startSeq;
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            const testTag = getLabelCode(p, item).toUpperCase();
            if (QC_EXCLUDED_TAGS.has(testTag)) continue;
            if (!SCANNED_TAGS.has(testTag) && !DAMAGED_TAGS.has(testTag)) {
                currentTagSeq = p;
                break;
            }
        }

        const currentTagCode = getLabelCode(currentTagSeq, item);
        const pieceQrPayload = getQRPayload(currentTagSeq, item);
        const activeQrPayload = (modalQrDisplayMode === 'exact') ? exactQr : pieceQrPayload;

        // Update modal info elements
        const modalImg = document.getElementById('modalProductImgContainer');
        if (modalImg) {
            modalImg.innerHTML = item.product_image 
                ? `<img src="${escapeHtml(item.product_image)}" alt="" class="rounded border shadow-sm" style="width: 56px; height: 56px; object-fit: cover;">`
                : `<div class="rounded bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;"><i class="bi bi-box-seam fs-3"></i></div>`;
        }

        document.getElementById('modalProductName').textContent = item.product_name || 'Finished Product';
        document.getElementById('modalProductCategory').textContent = item.category_name || item.product_code || 'Apparel';
        document.getElementById('modalProductSku').textContent = item.sku || '';
        document.getElementById('modalProductSize').textContent = item.size_name || 'Free';
        document.getElementById('modalProductColor').textContent = item.color_name || 'Std';

        document.getElementById('modalExpectedQty').textContent = expected;
        document.getElementById('modalScannedQty').textContent = scanned;
        document.getElementById('modalDamagedQty').textContent = damaged;
        document.getElementById('modalMissingQty').textContent = missing;

        // Update Exact QR Text Box & Next Piece Tag
        const exactQrEl = document.getElementById('modalExactQrText');
        if (exactQrEl) {
            exactQrEl.textContent = exactQr;
            exactQrEl.dataset.qrValue = exactQr;
        }

        const nextTagEl = document.getElementById('modalNextPieceTagText');
        if (nextTagEl) {
            const pieceNum = currentTagSeq - item.startSeq + 1;
            nextTagEl.textContent = missing > 0 ? `${currentTagCode} (Piece #${pieceNum})` : `${currentTagCode} (Completed)`;
        }

        const typeLabelEl = document.getElementById('modalQrTypeLabel');
        if (typeLabelEl) {
            typeLabelEl.innerHTML = (modalQrDisplayMode === 'exact')
                ? `<i class="bi bi-qr-code me-1 text-primary"></i>Exact Product QR: <strong class="text-dark">${escapeHtml(exactQr)}</strong>`
                : `<i class="bi bi-tag-fill me-1 text-warning"></i>Sequential Tag: <strong class="text-dark">${escapeHtml(currentTagCode)}</strong>`;
        }

        const scanTargetSku = document.getElementById('modalScanTargetSku');
        if (scanTargetSku) {
            scanTargetSku.textContent = exactQr;
        }

        // Render QR Code on Canvas (High size 190x190)
        const qrContainer = document.getElementById('modalQrCanvas');
        if (qrContainer && typeof QRCode !== 'undefined') {
            qrContainer.innerHTML = '';
            try {
                // Delay render slightly to allow DOM to flush, fixes blank QR bug
                setTimeout(() => {
                    if (!qrContainer) return;
                    qrContainer.innerHTML = '';
                    new QRCode(qrContainer, {
                        text: String(activeQrPayload || 'Empty QR Data'),
                        width: 190,
                        height: 190,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }, 50);
            } catch(e) {
                console.warn("Modal QR render note:", e);
            }
        }

        // Update modal buttons without +1 / -1 counts
        const btnProcess = document.getElementById('btnModalProcessNext');
        const btnScanAll = document.getElementById('btnModalScanAllMissing');
        const btnReset = document.getElementById('btnModalResetItem');

        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;

        if (missing === 0) {
            if (btnProcess) btnProcess.disabled = true;
            if (btnScanAll) btnScanAll.disabled = true;
        } else {
            if (btnProcess) btnProcess.disabled = false;
            if (btnScanAll) btnScanAll.disabled = false;
        }

        if (btnReset) {
            // Can only reset if there are uncommitted scans or damage beyond creditedCount
            btnReset.disabled = (scanned <= creditedCount && damaged === 0);
        }

        // Update Filter Button Badges and Active Styles
        const filterCountMismatched = document.getElementById('filterCountMismatched');
        const filterCountAll = document.getElementById('filterCountAll');
        const filterCountVerified = document.getElementById('filterCountVerified');
        if (filterCountMismatched) filterCountMismatched.textContent = missing;
        if (filterCountAll) filterCountAll.textContent = expected;
        if (filterCountVerified) filterCountVerified.textContent = scanned + damaged;

        const btnFilterMismatched = document.getElementById('btnFilterMismatched');
        const btnFilterAll = document.getElementById('btnFilterAll');
        const btnFilterVerified = document.getElementById('btnFilterVerified');
        const titleEl = document.getElementById('modalPiecesSectionTitle');

        if (btnFilterMismatched && btnFilterAll && btnFilterVerified) {
            btnFilterMismatched.className = (modalPieceFilter === 'mismatched') ? 'btn btn-danger btn-sm active fw-bold' : 'btn btn-outline-danger btn-sm fw-semibold';
            btnFilterAll.className = (modalPieceFilter === 'all') ? 'btn btn-secondary btn-sm active fw-bold' : 'btn btn-outline-secondary btn-sm fw-semibold';
            btnFilterVerified.className = (modalPieceFilter === 'verified') ? 'btn btn-success btn-sm active fw-bold' : 'btn btn-outline-success btn-sm fw-semibold';
        }

        if (titleEl) {
            if (modalPieceFilter === 'mismatched') {
                titleEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Mismatched / Missing Pieces (<span id="modalMissingPiecesCount">${missing}</span> pending)`;
            } else if (modalPieceFilter === 'verified') {
                titleEl.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> Verified & Quarantined Pieces (<span id="modalMissingPiecesCount">${scanned + damaged}</span> verified)`;
            } else {
                titleEl.innerHTML = `<i class="bi bi-grid-3x3-gap-fill text-dark me-1"></i> All Individual Pieces (<span id="modalMissingPiecesCount">${expected}</span> total)`;
            }
        }

        // Render Individual Pieces Breakdown Grid Filtered by modalPieceFilter with High-Size QR Display
        const piecesCount = document.getElementById('modalMissingPiecesCount');
        const piecesGrid = document.getElementById('modalMissingPiecesGrid');
        if (piecesCount) piecesCount.textContent = (modalPieceFilter === 'mismatched') ? missing : ((modalPieceFilter === 'verified') ? (scanned + damaged) : expected);

        if (piecesGrid) {
            let gridHtml = '';
            let displayedCount = 0;

            const displayBrand = (item.brand_name || item.product_name || WAREHOUSE_NAME || 'GRM WAREHOUSE').toUpperCase();
            const displayAttr = `${item.size_name || 'Free'}-${item.color_name || 'Std'}`.toUpperCase();

            let mrp = parseFloat(item.qc_base_price || item.mrp || item.base_price || item.selling_price || 0);
            let cost = parseFloat(item.qc_discount_price || item.purchase_price || item.production_cost || item.cost_price || item.selling_price || 0);

            if (isNaN(mrp)) mrp = 0;
            if (isNaN(cost)) cost = 0;

            const fmtMrp = mrp > 0 ? mrp.toFixed(2) : '—';
            const fmtCost = cost > 0 ? cost.toFixed(2) : '—';

            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const pieceTagCode = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(pieceTagCode)) continue;
                
                const isGood = SCANNED_TAGS.has(pieceTagCode);
                const isDamaged = DAMAGED_TAGS.has(pieceTagCode);
                const isVerified = isGood || isDamaged;
                const isAlreadyCredited = PERSISTED_INWARD_TAGS.has(pieceTagCode);

                // Apply Active Filter: Skip non-matching pieces
                if (modalPieceFilter === 'mismatched' && isVerified) {
                    continue; // Skip already verified pieces when viewing only mismatched!
                }
                if (modalPieceFilter === 'verified' && !isVerified) {
                    continue; // Skip unverified pieces when viewing verified only
                }

                displayedCount++;
                const pieceQrPayload = `${pieceTagCode}|${item.sku || ''}|MRP:${fmtMrp}|Disc:${fmtCost}`;

                // use globalSeq index for ID numbering
                const i = p - item.startSeq + 1;

                if (isGood) {
                    if (isAlreadyCredited) {
                        // LOCKED & DISABLED ACTION FOR ALREADY INVENTORY ADDED PRODUCT
                        gridHtml += `
                            <div class="col-12 col-md-6 mb-2">
                                <div class="p-2.5 border rounded-3 bg-success-subtle border-success border-opacity-50 d-flex align-items-center justify-content-between gap-2 shadow-xs h-100 position-relative overflow-hidden" style="background-color: #f0fdf4;">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        <div class="text-center flex-shrink-0" style="width: 82px;">
                                            <div class="inward-piece-qr bg-white p-1 rounded border border-success-subtle shadow-xs d-flex align-items-center justify-content-center cursor-pointer" 
                                                 id="piece-qr-${i}" 
                                                 data-qr="${escapeHtml(pieceQrPayload)}" 
                                                 onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                                 title="Click to view full 2D QR Code Tag" 
                                                 style="width: 80px; height: 80px; cursor: pointer;">
                                            </div>
                                            <div class="badge bg-dark text-white font-monospace mt-1 px-1 py-1 w-100 text-truncate cursor-pointer shadow-xs" 
                                                 onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                                 title="${escapeHtml(pieceTagCode)}" 
                                                 style="font-size: 0.68rem; letter-spacing: 0.3px; cursor: pointer;">
                                                ${escapeHtml(pieceTagCode)}
                                            </div>
                                        </div>
                                        <div class="overflow-hidden flex-grow-1 min-w-0" style="line-height: 1.25;">
                                            <div class="fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="${escapeHtml(displayBrand)}">
                                                ${escapeHtml(displayBrand)}
                                            </div>
                                            <div class="fw-bold text-secondary text-truncate mt-0.5" style="font-size: 0.76rem;">
                                                ${escapeHtml(displayAttr)}
                                            </div>
                                            <div class="text-muted text-decoration-line-through mt-0.5" style="font-size: 0.74rem;">
                                                MRP: ₹${fmtMrp}
                                            </div>
                                            <div class="fw-bold text-success mt-0.5" style="font-size: 0.82rem;">
                                                Disc: ₹${fmtCost}
                                            </div>
                                            <div class="mt-1">
                                                <span class="badge bg-success text-white py-0.5 px-1.5 shadow-xs" style="font-size: 0.68rem; letter-spacing: 0.2px;">
                                                    <i class="bi bi-lock-fill me-1"></i>Stock Added
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5 flex-shrink-0 align-items-center">
                                        <button type="button" class="btn btn-sm btn-success d-flex align-items-center justify-content-center shadow-xs disabled" style="width: 32px; height: 30px; padding: 0; border-radius: 6px; opacity: 0.95;" disabled title="Good Product (Credited to Inventory)">
                                            <i class="bi bi-check-lg" style="font-size: 1.05rem;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center shadow-xs disabled" style="width: 32px; height: 30px; padding: 0; border-radius: 6px; opacity: 0.55;" disabled title="Already credited to inventory in previous inward session (Locked)">
                                            <i class="bi bi-lock-fill" style="font-size: 0.90rem;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        gridHtml += `
                            <div class="col-12 col-md-6 mb-2">
                                <div class="p-2.5 border rounded-3 bg-success-subtle border-success border-opacity-50 d-flex align-items-center justify-content-between gap-2 shadow-xs h-100 position-relative overflow-hidden">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        <div class="text-center flex-shrink-0" style="width: 82px;">
                                            <div class="inward-piece-qr bg-white p-1 rounded border border-success-subtle shadow-xs d-flex align-items-center justify-content-center cursor-pointer" 
                                                 id="piece-qr-${i}" 
                                                 data-qr="${escapeHtml(pieceQrPayload)}" 
                                                 onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                                 title="Click to view full 2D QR Code Tag" 
                                                 style="width: 80px; height: 80px; cursor: pointer;">
                                            </div>
                                            <div class="badge bg-dark text-white font-monospace mt-1 px-1 py-1 w-100 text-truncate cursor-pointer shadow-xs" 
                                                 onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                                 title="${escapeHtml(pieceTagCode)}" 
                                                 style="font-size: 0.68rem; letter-spacing: 0.3px; cursor: pointer;">
                                                ${escapeHtml(pieceTagCode)}
                                            </div>
                                        </div>
                                        <div class="overflow-hidden flex-grow-1 min-w-0" style="line-height: 1.25;">
                                            <div class="fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="${escapeHtml(displayBrand)}">
                                                ${escapeHtml(displayBrand)}
                                            </div>
                                            <div class="fw-bold text-secondary text-truncate mt-0.5" style="font-size: 0.76rem;">
                                                ${escapeHtml(displayAttr)}
                                            </div>
                                            <div class="text-muted text-decoration-line-through mt-0.5" style="font-size: 0.74rem;">
                                                MRP: ₹${fmtMrp}
                                            </div>
                                            <div class="fw-bold text-success mt-0.5" style="font-size: 0.82rem;">
                                                Disc: ₹${fmtCost}
                                            </div>
                                            <div class="mt-1">
                                                <span class="badge bg-success-subtle text-success border border-success py-0.5 px-1.5 shadow-xs" style="font-size: 0.68rem; letter-spacing: 0.2px;">
                                                    <i class="bi bi-check2 me-1"></i>Newly Scanned
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5 flex-shrink-0 align-items-center">
                                        <button type="button" class="btn btn-sm btn-success d-flex align-items-center justify-content-center shadow-xs disabled" style="width: 32px; height: 30px; padding: 0; border-radius: 6px; opacity: 0.9;" disabled title="Good Product">
                                            <i class="bi bi-check-lg" style="font-size: 1.05rem;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center shadow-xs" style="width: 32px; height: 30px; padding: 0; border-radius: 6px;" onclick="markPieceDamaged(${variantId}, '${escapeHtml(pieceTagCode)}');" title="Change to Damage Product">
                                            <i class="bi bi-slash-circle" style="font-size: 0.95rem;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else if (isDamaged) {
                    gridHtml += `
                        <div class="col-12 col-md-6 mb-2">
                            <div class="p-2.5 border rounded-3 bg-danger-subtle border-danger border-opacity-50 d-flex align-items-center justify-content-between gap-2 shadow-xs h-100 position-relative overflow-hidden">
                                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                    <div class="text-center flex-shrink-0" style="width: 82px;">
                                        <div class="inward-piece-qr bg-white p-1 rounded border border-danger-subtle shadow-xs d-flex align-items-center justify-content-center cursor-pointer" 
                                             id="piece-qr-${i}" 
                                             data-qr="${escapeHtml(pieceQrPayload)}" 
                                             onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                             title="Click to view full 2D QR Code Tag" 
                                             style="width: 80px; height: 80px; cursor: pointer;">
                                        </div>
                                        <div class="badge bg-danger text-white font-monospace mt-1 px-1 py-1 w-100 text-truncate cursor-pointer shadow-xs" 
                                             onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                             title="${escapeHtml(pieceTagCode)}" 
                                             style="font-size: 0.68rem; letter-spacing: 0.3px; cursor: pointer;">
                                            ${escapeHtml(pieceTagCode)}
                                        </div>
                                    </div>
                                    <div class="overflow-hidden flex-grow-1 min-w-0" style="line-height: 1.25;">
                                        <div class="fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="${escapeHtml(displayBrand)}">
                                            ${escapeHtml(displayBrand)}
                                        </div>
                                        <div class="fw-bold text-secondary text-truncate mt-0.5" style="font-size: 0.76rem;">
                                            ${escapeHtml(displayAttr)}
                                        </div>
                                        <div class="text-muted text-decoration-line-through mt-0.5" style="font-size: 0.74rem;">
                                            MRP: ₹${fmtMrp}
                                        </div>
                                        <div class="fw-bold text-danger mt-0.5" style="font-size: 0.82rem;">
                                            <i class="bi bi-exclamation-octagon-fill me-1"></i>QUARANTINED
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-1.5 flex-shrink-0 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-success d-flex align-items-center justify-content-center shadow-xs" style="width: 32px; height: 30px; padding: 0; border-radius: 6px;" onclick="markPieceIntact(${variantId}, '${escapeHtml(pieceTagCode)}');" title="Change to Good Product">
                                        <i class="bi bi-check-lg" style="font-size: 1.05rem;"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger d-flex align-items-center justify-content-center shadow-xs disabled" style="width: 32px; height: 30px; padding: 0; border-radius: 6px; opacity: 0.9;" disabled title="Damage Product">
                                        <i class="bi bi-slash-circle" style="font-size: 0.95rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    gridHtml += `
                        <div class="col-12 col-md-6 mb-2">
                            <div class="p-2.5 border rounded-3 bg-white d-flex align-items-center justify-content-between gap-2 shadow-xs h-100 hover-shadow position-relative overflow-hidden">
                                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                    <div class="text-center flex-shrink-0" style="width: 82px;">
                                        <div class="inward-piece-qr bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center cursor-pointer" 
                                             id="piece-qr-${i}" 
                                             data-qr="${escapeHtml(pieceQrPayload)}" 
                                             onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                             title="Click to view full 2D QR Code Tag" 
                                             style="width: 80px; height: 80px; cursor: pointer;">
                                        </div>
                                        <div class="badge bg-dark text-warning font-monospace mt-1 px-1 py-1 w-100 text-truncate cursor-pointer shadow-xs" 
                                             onclick="previewSinglePieceTag(${variantId}, ${i}, '${escapeHtml(pieceTagCode)}');" 
                                             title="${escapeHtml(pieceTagCode)}" 
                                             style="font-size: 0.68rem; letter-spacing: 0.3px; cursor: pointer;">
                                            ${escapeHtml(pieceTagCode)}
                                        </div>
                                    </div>
                                    <div class="overflow-hidden flex-grow-1 min-w-0" style="line-height: 1.25;">
                                        <div class="fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;" title="${escapeHtml(displayBrand)}">
                                            ${escapeHtml(displayBrand)}
                                        </div>
                                        <div class="fw-bold text-secondary text-truncate mt-0.5" style="font-size: 0.76rem;">
                                            ${escapeHtml(displayAttr)}
                                        </div>
                                        <div class="text-muted text-decoration-line-through mt-0.5" style="font-size: 0.74rem;">
                                            MRP: ₹${fmtMrp}
                                        </div>
                                        <div class="fw-bold text-success mt-0.5" style="font-size: 0.82rem;">
                                            Disc: ₹${fmtCost}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-1.5 flex-shrink-0 align-items-center">
                                    <button type="button" class="btn btn-sm btn-success d-flex align-items-center justify-content-center shadow-xs" style="width: 32px; height: 30px; padding: 0; border-radius: 6px;" onclick="fastScanVariant(${variantId}, '${escapeHtml(pieceTagCode)}', 'good');" title="Mark Good Product">
                                        <i class="bi bi-check-lg" style="font-size: 1.05rem;"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center shadow-xs" style="width: 32px; height: 30px; padding: 0; border-radius: 6px;" onclick="fastScanVariant(${variantId}, '${escapeHtml(pieceTagCode)}', 'damage');" title="Mark Damage Product">
                                        <i class="bi bi-slash-circle" style="font-size: 0.95rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }

            if (displayedCount === 0) {
                if (modalPieceFilter === 'mismatched') {
                    gridHtml = `
                        <div class="col-12 text-center py-4">
                            <div class="p-3 bg-success-subtle text-success rounded-3 border border-success-subtle shadow-sm">
                                <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                                <span class="fw-bold fs-6">All pieces matched!</span>
                                <p class="small text-muted mb-0 mt-1">No mismatched or missing pieces remaining for <strong>${escapeHtml(item.sku)}</strong>.</p>
                            </div>
                        </div>
                    `;
                } else if (modalPieceFilter === 'verified') {
                    gridHtml = `
                        <div class="col-12 text-center py-4">
                            <div class="p-3 bg-light text-muted rounded-3 border">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                <span class="fw-bold">No pieces verified yet</span>
                                <p class="small text-muted mb-0 mt-1">Scan or click pieces to mark them as received.</p>
                            </div>
                        </div>
                    `;
                } else {
                    gridHtml = `
                        <div class="col-12 text-center py-4 text-muted">
                            <i class="bi bi-box-seam fs-3 d-block mb-1"></i>
                            No pieces found.
                        </div>
                    `;
                }
            }

            piecesGrid.innerHTML = gridHtml;

            // Render live 2D QR Code on each piece card
            if (typeof QRCode !== 'undefined') {
                piecesGrid.querySelectorAll('.inward-piece-qr').forEach(box => {
                    const payload = box.getAttribute('data-qr');
                    if (payload) {
                        box.innerHTML = '';
                        try {
                            new QRCode(box, {
                                text: payload,
                                width: 52,
                                height: 52,
                                colorDark: "#0f172a",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        } catch(err) {
                            console.warn("QR render err:", err);
                        }
                    }
                });
            }
        }
    }

    function fastScanVariant(vid, specificTag = '', mode = 'good') {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;

        const expected = parseInt(item.quantity) || 1;
        const row = document.getElementById(`row-${vid}`);
        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;
        const currentGood = getVariantVerifiedTagCount(item);
        const currentDmg = getVariantDamagedTagCount(item);

        // 1. Check if variant was already fully credited/inwarded from a previous inward session
        if (creditedCount > 0 && creditedCount >= expected) {
            playBeep('error');
            if (row) {
                row.classList.add('table-warning');
                setTimeout(() => row.classList.remove('table-warning'), 1200);
            }
            showScanFeedback(`🔒 <strong>ALREADY INWARDED:</strong> Product <strong>${escapeHtml(item.sku)}</strong> (${expected} pcs) has ALREADY been fully verified and inwarded into <strong>${escapeHtml(WAREHOUSE_NAME)}</strong> inventory (${creditedCount} pcs credited).`, 'warning');
            return;
        }

        let tagToProcess = specificTag ? specificTag.toUpperCase() : '';
        
        // Validate if tagToProcess is actually a valid piece tag for this item.
        let isValidPieceTag = false;
        let pieceSeq = -1;
        if (tagToProcess) {
            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const code = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(code)) continue;
                if (code === tagToProcess) {
                    isValidPieceTag = true;
                    pieceSeq = p;
                    break;
                }
            }
        }

        // 2. Specific Piece Tag Validations
        if (isValidPieceTag) {
            // Already credited piece check
            if (PERSISTED_INWARD_TAGS.has(tagToProcess) || (pieceSeq !== -1 && (pieceSeq - item.startSeq) < creditedCount && BATCH_STATUS === 'RECEIVED')) {
                playBeep('error');
                showScanFeedback(`🔒 <strong>ALREADY INWARDED:</strong> Piece <span class="badge bg-dark font-monospace">${escapeHtml(tagToProcess)}</span> was already credited to warehouse inventory.`, 'warning');
                return;
            }

            if (mode === 'good') {
                if (SCANNED_TAGS.has(tagToProcess)) {
                    playBeep('error');
                    if (row) {
                        row.classList.add('table-warning');
                        setTimeout(() => row.classList.remove('table-warning'), 1200);
                    }
                    showScanFeedback(`⚠️ <strong>ALREADY INWARDED:</strong> Piece <span class="badge bg-dark font-monospace">${escapeHtml(tagToProcess)}</span> for <strong>${escapeHtml(item.sku)}</strong> has ALREADY been scanned and inwarded as Good intact stock!`, 'warning');
                    return;
                }
                if (DAMAGED_TAGS.has(tagToProcess)) {
                    playBeep('error');
                    if (row) {
                        row.classList.add('table-danger');
                        setTimeout(() => row.classList.remove('table-danger'), 1200);
                    }
                    showScanFeedback(`🚫 <strong>ALREADY RECORDED AS DAMAGED:</strong> Piece <span class="badge bg-danger font-monospace">${escapeHtml(tagToProcess)}</span> for <strong>${escapeHtml(item.sku)}</strong> is ALREADY marked as DAMAGED & quarantined! To change its status, click the piece in the breakdown modal.`, 'danger');
                    return;
                }
            } else {
                // Damage mode
                if (DAMAGED_TAGS.has(tagToProcess)) {
                    playBeep('error');
                    if (row) {
                        row.classList.add('table-danger');
                        setTimeout(() => row.classList.remove('table-danger'), 1200);
                    }
                    showScanFeedback(`⚠️ <strong>ALREADY RECORDED AS DAMAGED:</strong> Piece <span class="badge bg-danger font-monospace">${escapeHtml(tagToProcess)}</span> for <strong>${escapeHtml(item.sku)}</strong> is ALREADY recorded as damaged & quarantined!`, 'warning');
                    return;
                }
                if (SCANNED_TAGS.has(tagToProcess)) {
                    playBeep('error');
                    if (row) {
                        row.classList.add('table-warning');
                        setTimeout(() => row.classList.remove('table-warning'), 1200);
                    }
                    showScanFeedback(`⚠️ <strong>ALREADY SCANNED AS INTACT:</strong> Piece <span class="badge bg-dark font-monospace">${escapeHtml(tagToProcess)}</span> for <strong>${escapeHtml(item.sku)}</strong> was already scanned as Good product. To modify, click the piece in the breakdown modal.`, 'warning');
                    return;
                }
            }
        } else {
            // 3. General Product SKU / Barcode / Variant QR Scanned
            // If all pieces for this variant are already accounted for (e.g. 8 intact + 2 damaged = 10 expected)
            if (currentGood + currentDmg >= expected) {
                playBeep('error');
                if (row) {
                    row.classList.add('table-warning');
                    setTimeout(() => row.classList.remove('table-warning'), 1200);
                }
                const statusSummary = currentDmg > 0 
                    ? `(${currentGood} intact, ${currentDmg} damaged)` 
                    : `(${currentGood} intact)`;
                showScanFeedback(`⚠️ <strong>ALREADY INWARDED:</strong> All <strong>${expected} pcs</strong> for <strong>${escapeHtml(item.sku)}</strong> have ALREADY been scanned and inwarded ${statusSummary}! No remaining units to inward for this variant.`, 'warning');
                return;
            }

            // Find next unverified piece in sequence for this variant
            tagToProcess = '';
            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const code = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(code)) continue;
                if (PERSISTED_INWARD_TAGS.has(code) || PERSISTED_DAMAGED_TAGS.has(code)) continue;
                if (!SCANNED_TAGS.has(code) && !DAMAGED_TAGS.has(code)) {
                    tagToProcess = code;
                    break;
                }
            }
        }

        // 4. Record new scan: Inward ALL counts in single scan for Series & Complementary products
        const isSeriesOrComp = (item.is_series == 1 || item.is_complementary == 1 || item.qr_type === 'SERIES_BATCH' || item.category_type === 'SERIES' || item.category_type === 'COMPLEMENTARY' || (item.pack_quantity && parseInt(item.pack_quantity) > 1));

        if (isSeriesOrComp && mode === 'good') {
            // Single scan inwards ALL counts for series and complementary products!
            let newlyAdded = 0;
            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const code = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(code)) continue;
                if (PERSISTED_INWARD_TAGS.has(code) || PERSISTED_DAMAGED_TAGS.has(code)) continue;
                if (!SCANNED_TAGS.has(code) && !DAMAGED_TAGS.has(code)) {
                    SCANNED_TAGS.add(code);
                    newlyAdded++;
                }
            }
            playBeep('success');
            if (row) {
                row.classList.add('table-success');
                setTimeout(() => row.classList.remove('table-success'), 800);
            }
            showScanFeedback(`✅ Verified <strong>${escapeHtml(item.sku)}</strong> (Series/Complementary Pack: ALL ${expected} pcs inwarded in single scan)!`, 'success');
        } else if (mode === 'damage') {
            if (tagToProcess) DAMAGED_TAGS.add(tagToProcess);
            playBeep('error');
            if (row) {
                row.classList.add('table-danger');
                setTimeout(() => row.classList.remove('table-danger'), 700);
            }
            showScanFeedback(`🔴 Recorded Piece <span class="badge bg-dark font-monospace">${escapeHtml(tagToProcess || 'Item')}</span> as DAMAGED & QUARANTINED for <strong>${escapeHtml(item.sku)}</strong>.`, 'danger');
        } else {
            // Normal products: 1 piece per scan
            if (tagToProcess) SCANNED_TAGS.add(tagToProcess);
            playBeep('success');
            if (row) {
                row.classList.add('table-success');
                setTimeout(() => row.classList.remove('table-success'), 600);
            }
            showScanFeedback(`✅ Verified Piece <span class="badge bg-dark font-monospace">${escapeHtml(tagToProcess || 'Item')}</span> as INTACT for <strong>${escapeHtml(item.sku)}</strong> (+1 pc).`, 'success');
        }

        // Notify if all pieces for this variant just completed
        const updatedGood = getVariantVerifiedTagCount(item);
        const updatedDmg = getVariantDamagedTagCount(item);
        if (updatedGood + updatedDmg >= expected) {
            const sumText = updatedDmg > 0 ? `${updatedGood} intact, ${updatedDmg} damaged` : `${updatedGood} intact`;
            showScanFeedback(`✅ <strong>COMPLETED:</strong> All ${expected} pcs for <strong>${escapeHtml(item.sku)}</strong> successfully verified (${sumText})!`, 'success');
        }

        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        const newGood = getVariantVerifiedTagCount(item);
        const newDmg = getVariantDamagedTagCount(item);

        if (recvInput) recvInput.value = newGood;
        if (dmgInput) {
            dmgInput.value = newDmg;
            if (newDmg > 0) {
                dmgInput.readOnly = false;
                dmgInput.removeAttribute('readonly');
                dmgInput.classList.remove('is-locked');
                dmgInput.classList.add('is-active');
            } else {
                dmgInput.readOnly = true;
                dmgInput.setAttribute('readonly', 'readonly');
                dmgInput.classList.add('is-locked');
                dmgInput.classList.remove('is-active');
            }
        }

        recalculateMatrix();
        if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
            updateModalState(vid);
        }
    }

    function unverifyPiece(vid, tagCode) {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;

        const expected = parseInt(item.quantity) || 1;
        const normalizedTag = (tagCode || '').toUpperCase();
        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;

        let tagSeq = -1;
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            if (getLabelCode(p, item).toUpperCase() === normalizedTag) {
                tagSeq = p;
                break;
            }
        }
        if (PERSISTED_INWARD_TAGS.has(normalizedTag) || (tagSeq !== -1 && (tagSeq - item.startSeq) < creditedCount && BATCH_STATUS === 'RECEIVED')) {
            playBeep('error');
            showScanFeedback(`🔒 Piece <span class="badge bg-dark font-monospace">${escapeHtml(normalizedTag)}</span> was already credited to warehouse inventory and cannot be un-verified.`, 'warning');
            return;
        }

        SCANNED_TAGS.delete(normalizedTag);
        DAMAGED_TAGS.delete(normalizedTag);

        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        const newVerifiedCount = getVariantVerifiedTagCount(item);
        const newDmgCount = getVariantDamagedTagCount(item);

        if (recvInput) recvInput.value = newVerifiedCount;
        if (dmgInput) {
            dmgInput.value = newDmgCount;
            if (newDmgCount === 0) {
                dmgInput.readOnly = true;
                dmgInput.setAttribute('readonly', 'readonly');
                dmgInput.classList.add('is-locked');
                dmgInput.classList.remove('is-active');
            }
        }

        playBeep('double');
        recalculateMatrix();
        showScanFeedback(`↩️ Reset piece <span class="badge bg-dark font-monospace">${escapeHtml(normalizedTag)}</span> for <strong>${escapeHtml(item.sku)}</strong>. Verified: <strong>${newVerifiedCount} / ${expected} pcs</strong>`, 'info');

        if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
            updateModalState(vid);
        }
    }

    function resetVariantPieces(vid) {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;

        const expected = parseInt(item.quantity) || 1;
        const creditedCount = (BATCH_STATUS === 'RECEIVED') ? (parseInt(item.received_qty) || 0) : 0;

        // Reset ONLY uncommitted tags (preserving previously persisted tags)
        for (let p = item.startSeq; p <= item.endSeq; p++) {
            const tag = getLabelCode(p, item).toUpperCase();
            if (!PERSISTED_INWARD_TAGS.has(tag) && !PERSISTED_DAMAGED_TAGS.has(tag)) {
                SCANNED_TAGS.delete(tag);
                DAMAGED_TAGS.delete(tag);
            }
        }

        // Ensure previously credited tags remain intact in SCANNED_TAGS
        PERSISTED_INWARD_TAGS.forEach(tag => {
            SCANNED_TAGS.add(tag);
            DAMAGED_TAGS.delete(tag);
        });
        PERSISTED_DAMAGED_TAGS.forEach(tag => {
            DAMAGED_TAGS.add(tag);
            SCANNED_TAGS.delete(tag);
        });

        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        if (recvInput) recvInput.value = creditedCount;
        if (dmgInput) {
            dmgInput.value = 0;
            dmgInput.readOnly = true;
            dmgInput.setAttribute('readonly', 'readonly');
            dmgInput.classList.add('is-locked');
            dmgInput.classList.remove('is-active');
            const dmgLabel = document.getElementById(`dmg-label-${vid}`);
            const btnToggle = document.getElementById(`btn-toggle-dmg-${vid}`);
            const iconToggle = document.getElementById(`icon-dmg-${vid}`);
            if (dmgLabel) dmgLabel.classList.add('d-none');
            if (btnToggle) btnToggle.className = 'btn btn-sm btn-outline-danger p-1';
            if (iconToggle) iconToggle.className = 'bi bi-slash-circle';
        }

        playBeep('double');
        recalculateMatrix();
        showScanFeedback(`↩️ Reset uncommitted scans for <strong>${escapeHtml(item.sku)}</strong> (${creditedCount} / ${expected} pcs credited).`, 'info');

        if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
            updateModalState(vid);
        }
    }

    function creditSingleItem(vid) {
        const item = ITEMS.find(i => String(i.variant_id) === String(vid));
        if (!item) return;
        const recvInput = document.getElementById(`input-recv-${vid}`);
        const dmgInput = document.getElementById(`input-dmg-${vid}`);
        const expected = parseInt(item.quantity) || 0;
        
        if (recvInput) {
            for (let p = item.startSeq; p <= item.endSeq; p++) {
                const tag = getLabelCode(p, item).toUpperCase();
                if (QC_EXCLUDED_TAGS.has(tag)) continue;
                if (PERSISTED_DAMAGED_TAGS.has(tag)) continue; // keep previously quarantined pieces damaged
                SCANNED_TAGS.add(tag);
                DAMAGED_TAGS.delete(tag);
            }
            recvInput.value = expected - getVariantDamagedTagCount(item);
            if (dmgInput) {
                dmgInput.value = 0;
                dmgInput.readOnly = true;
                dmgInput.setAttribute('readonly', 'readonly');
                dmgInput.classList.add('is-locked');
                dmgInput.classList.remove('is-active');
                const dmgLabel = document.getElementById(`dmg-label-${vid}`);
                const btnToggle = document.getElementById(`btn-toggle-dmg-${vid}`);
                const iconToggle = document.getElementById(`icon-dmg-${vid}`);
                if (dmgLabel) dmgLabel.classList.add('d-none');
                if (btnToggle) btnToggle.className = 'btn btn-sm btn-outline-danger p-1';
                if (iconToggle) iconToggle.className = 'bi bi-slash-circle';
            }
            playBeep('double');
            
            // Flash row green
            const row = document.getElementById(`row-${vid}`);
            if (row) {
                row.classList.add('table-success');
                setTimeout(() => row.classList.remove('table-success'), 800);
            }
            
            recalculateMatrix();
            showScanFeedback(`✅ Item <strong>${escapeHtml(item.sku)}</strong> verified with full expected qty (${expected} pcs)!`, "success");
            if (activeModalVariantId && String(activeModalVariantId) === String(vid)) {
                updateModalState(vid);
            }
        }
    }

    function creditScannedOnly() {
        let totalScanned = 0;
        let totalDamaged = 0;
        let missingCount = 0;
        let prevCredited = 0;
        ITEMS.forEach(item => {
            const vid = item.variant_id;
            const recvInput = document.getElementById(`input-recv-${vid}`);
            const dmgInput = document.getElementById(`input-dmg-${vid}`);
            const received = recvInput ? (parseInt(recvInput.value) || 0) : 0;
            const damaged = dmgInput ? (parseInt(dmgInput.value) || 0) : 0;
            const expected = parseInt(item.quantity) || 0;
            totalScanned += received;
            totalDamaged += damaged;
            if (BATCH_STATUS === 'RECEIVED') {
                prevCredited += (parseInt(item.received_qty) || 0);
            }
            if ((received + damaged) < expected) missingCount += (expected - (received + damaged));
        });

        const deltaSession = Math.max(0, totalScanned - prevCredited);

        if (totalScanned === 0 && totalDamaged === 0) {
            alert("⚠️ No items have been scanned or verified yet! Please scan or enter received quantities before crediting.");
            return;
        }

        let confirmMsg = (BATCH_STATUS === 'RECEIVED' && prevCredited > 0)
            ? `Confirm crediting +${deltaSession} pcs incremental stock (Total: ${totalScanned} / ${TOTAL_EXPECTED} pcs) to ${WAREHOUSE_NAME} (${WAREHOUSE_CODE}) warehouse?`
            : `Confirm crediting ${totalScanned} pcs of verified intact inventory to ${WAREHOUSE_NAME} (${WAREHOUSE_CODE}) warehouse?`;

        if (totalDamaged > 0) {
            confirmMsg += `\n\n⚠️ Note: ${totalDamaged} piece(s) are marked DAMAGED and will be quarantined / inactivated from sellable stock.`;
        }
        if (missingCount > 0) {
            confirmMsg += `\n\n⚠️ Note: There are ${missingCount} pending missing pieces. Shortages will be recorded in the discrepancy ledger.`;
        }

        if (confirm(confirmMsg)) {
            const form = document.getElementById('inwardForm');
            if (form) {
                // Populate exact intact and damaged piece tags for each variant before submit
                ITEMS.forEach(item => {
                    const vid = item.variant_id;
                    let vReceivedTags = [];
                    let vDamagedTags = [];
                    for (let p = item.startSeq; p <= item.endSeq; p++) {
                        const t = getLabelCode(p, item).toUpperCase();
                        if (SCANNED_TAGS.has(t)) {
                            vReceivedTags.push(t);
                        } else if (DAMAGED_TAGS.has(t)) {
                            vDamagedTags.push(t);
                        }
                    }

                    let inpRecv = document.getElementById(`hidden-recv-tags-${vid}`);
                    if (!inpRecv) {
                        inpRecv = document.createElement('input');
                        inpRecv.type = 'hidden';
                        inpRecv.id = `hidden-recv-tags-${vid}`;
                        inpRecv.name = `items[${vid}][received_tags]`;
                        form.appendChild(inpRecv);
                    }
                    inpRecv.value = vReceivedTags.join(',');

                    let inpDmg = document.getElementById(`hidden-dmg-tags-${vid}`);
                    if (!inpDmg) {
                        inpDmg = document.createElement('input');
                        inpDmg.type = 'hidden';
                        inpDmg.id = `hidden-dmg-tags-${vid}`;
                        inpDmg.name = `items[${vid}][damaged_tags]`;
                        form.appendChild(inpDmg);
                    }
                    inpDmg.value = vDamagedTags.join(',');
                });

                // Clear localStorage scan state since batch is being credited
                try {
                    localStorage.removeItem(SCAN_STORAGE_KEY);
                    localStorage.removeItem(DMG_STORAGE_KEY);
                } catch(e) {}

                form.submit();
            }
        }
    }

    function showScanFeedback(message, type = 'success') {
        const box = document.getElementById('scanAlertMsg');
        if (box) {
            box.className = `alert alert-${type} alert-dismissible fade show mt-3 mb-0 shadow-sm`;
            box.innerHTML = `${message} <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
            box.classList.remove('d-none');
            try {
                box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch(e) {}
        }
        if (typeof showToastMessage === 'function') {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = message;
            const plainText = tempDiv.textContent || tempDiv.innerText || '';
            showToastMessage(plainText);
        }
    }

    function confirmSubmission() {
        let totalScanned = 0;
        let missingCount = 0;
        ITEMS.forEach(item => {
            const vid = item.variant_id;
            const recvInput = document.getElementById(`input-recv-${vid}`);
            const received = recvInput ? (parseInt(recvInput.value) || 0) : 0;
            const expected = parseInt(item.quantity) || 0;
            totalScanned += received;
            if (received < expected) missingCount += (expected - received);
        });

        if (missingCount > 0) {
            return confirm(`⚠️ WARNING: There is a discrepancy of ${missingCount} missing units!\n\nOutward Dispatched: ${TOTAL_EXPECTED} pcs\nActual Inward: ${totalScanned} pcs\n\nAre you sure you want to proceed and log this discrepancy in the ${WAREHOUSE_NAME} (${WAREHOUSE_CODE}) stock ledger?`);
        }
        return confirm(`Confirm inward verification of ${totalScanned} pcs into ${WAREHOUSE_NAME} (${WAREHOUSE_CODE}) warehouse inventory?`);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    // Camera Scanner logic with Html5Qrcode
    let html5QrCode = null;
    function initCameraScanner() {
        const camModal = document.getElementById('cameraScanModal');
        if (!camModal) return;

        camModal.addEventListener('shown.bs.modal', function () {
            if (typeof Html5Qrcode === 'undefined') {
                alert("QR Scanner library loading, please try again in a moment.");
                return;
            }
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("reader");
            }
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    processScanInput(decodedText);
                    const modalInstance = bootstrap.Modal.getInstance(camModal);
                    if (modalInstance) modalInstance.hide();
                },
                (errorMessage) => {}
            ).catch(err => {
                console.error("Camera open failed:", err);
            });
        });

        camModal.addEventListener('hidden.bs.modal', function () {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().catch(err => console.error("Error stopping scanner", err));
            }
        });
    }

    // =========================================================================
    // BATCH HISTORY & TRACEABILITY MODAL LOADER
    // =========================================================================
    const historyModalEl = document.getElementById('batchHistoryModal');
    const historyLoading = document.getElementById('batchHistoryLoading');
    const historyContent = document.getElementById('batchHistoryContent');

    if (historyModalEl) {
        historyModalEl.addEventListener('show.bs.modal', function() {
            loadBatchHistoryDetails(BATCH_ID);
        });
    }

    function loadBatchHistoryDetails(batchId) {
        if (!historyLoading || !historyContent) return;
        historyLoading.classList.remove('d-none');
        historyContent.classList.add('d-none');
        historyContent.innerHTML = '';

        fetch('<?= url("api/warehouse/inward/batch-history/") ?>' + batchId)
            .then(res => res.json())
            .then(res => {
                historyLoading.classList.add('d-none');
                if (!res.success || !res.data) {
                    historyContent.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> ${res.message || 'Failed to load batch history.'}</div>`;
                    historyContent.classList.remove('d-none');
                    return;
                }

                const d = res.data;
                const batch = d.batch || {};
                const prog = d.program || {};
                const rolls = d.rolls || [];
                const cutting = d.cutting || [];
                const stitching = d.stitching || [];
                const qc = d.qc || null;
                const outward = d.outward || null;
                const txns = d.transactions || [];
                const items = d.items || [];

                let html = `
                    <!-- Header Overview Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <span class="text-muted small d-block">Production Batch Number</span>
                                    <h5 class="fw-bold font-monospace text-primary mb-0">${escapeHtml(batch.batch_number || '')}</h5>
                                    <small class="text-muted">Product: <strong>${escapeHtml(batch.product_name || 'N/A')}</strong></small>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                        <small class="text-muted d-block fw-semibold"><i class="bi bi-gear-wide-connected text-warning me-1"></i>Origin Manufacturing Unit:</small>
                                        <strong class="text-dark font-monospace">${escapeHtml(batch.production_unit_name || prog.unit_name || 'Main Production Facility')} (${escapeHtml(batch.production_unit_code || prog.unit_code || 'PU1')})</strong>
                                        <div class="small text-muted">${escapeHtml(batch.production_unit_manager || prog.unit_manager || 'Lead Supervisor')} ${batch.production_unit_phone ? ' &bull; ' + escapeHtml(batch.production_unit_phone) : ''}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 rounded bg-primary bg-opacity-10 border border-primary border-opacity-25">
                                        <small class="text-muted d-block fw-semibold"><i class="bi bi-building text-primary me-1"></i>Destination Warehouse:</small>
                                        <strong class="text-primary font-monospace">${escapeHtml(batch.warehouse_name || '')} (${escapeHtml(batch.warehouse_code || '')})</strong>
                                        <div class="small text-muted">Status: <span class="badge ${batch.status === 'RECEIVED' ? 'bg-success' : 'bg-warning text-dark'} font-monospace">${escapeHtml(batch.status || '')}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visual Timeline -->
                    <div class="timeline-wrapper position-relative ps-4 border-start border-3 border-primary ms-3">
                `;

                // 1. Program & Unit Assignment
                html += `
                    <div class="timeline-step position-relative mb-4">
                        <span class="timeline-icon position-absolute rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                            <i class="bi bi-clipboard-data fs-6"></i>
                        </span>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                <strong class="text-dark"><i class="bi bi-calendar2-check text-primary me-1"></i>Stage 1: Production Program &amp; Facility Assignment</strong>
                                <span class="badge bg-light text-dark border">${prog.created_at || 'Logged'}</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-2 small">
                                    <div class="col-sm-6 col-md-3"><strong>Program #:</strong> <span class="font-monospace text-primary">${escapeHtml(prog.program_number || 'N/A')}</span></div>
                                    <div class="col-sm-6 col-md-3"><strong>Assigned Unit:</strong> <span class="badge bg-warning text-dark">${escapeHtml(prog.unit_name || 'Main Unit')}</span></div>
                                    <div class="col-sm-6 col-md-3"><strong>Planned Target:</strong> ${(parseInt(prog.planned_quantity) > 1 ? prog.planned_quantity : (batch.total_qty || prog.planned_quantity || 0))} pcs (${prog.end_date || prog.target_date || 'N/A'})</div>
                                    <div class="col-sm-6 col-md-3"><strong>Supervisor:</strong> ${escapeHtml(prog.assigned_to_name || prog.unit_manager || 'Lead')}</div>
                                </div>
                                ${rolls.length > 0 ? `
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-muted fw-bold d-block mb-1"><i class="bi bi-layers me-1"></i>Fabric / Material Rolls Consumed (${rolls.length}):</small>
                                        <div class="d-flex flex-wrap gap-2">
                                            ${rolls.map(r => `
                                                <span class="badge bg-light text-dark border font-monospace p-2">
                                                    Roll #${escapeHtml(r.roll_number || 'N/A')} &bull; Color: ${escapeHtml(r.roll_color || r.sr_color || 'Std')} &bull; ${r.roll_weight || r.sr_weight || 0} kg &bull; Supplier: ${escapeHtml(r.seller_name || 'Mill')}
                                                </span>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;

                // 2. Cutting Stage
                if (cutting.length > 0) {
                    html += `
                        <div class="timeline-step position-relative mb-4">
                            <span class="timeline-icon position-absolute rounded-circle bg-info text-white d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                                <i class="bi bi-scissors fs-6"></i>
                            </span>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <strong class="text-dark"><i class="bi bi-scissors text-info me-1"></i>Stage 2: Fabric Cutting &amp; CAD Output</strong>
                                    <span class="badge bg-light text-dark border">Cutting Batches: ${cutting.length}</span>
                                </div>
                                <div class="card-body p-3">
                                    ${cutting.map(c => `
                                        <div class="p-2 rounded bg-light border mb-2 small d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div>
                                                <strong>Batch #${escapeHtml(c.batch_number || '')}</strong> &bull; Cut Pcs: <span class="badge bg-success font-monospace">${c.cut_qty || 0} pcs</span>
                                                <div class="text-muted">Operator: ${escapeHtml(c.operator_name || 'Cutting Master')} &bull; Wastage: ${c.wastage_weight || 0} kg</div>
                                            </div>
                                            <span class="badge bg-secondary font-monospace">${c.status || ''}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `;
                }

                // 3. Stitching Stage
                if (stitching.length > 0) {
                    html += `
                        <div class="timeline-step position-relative mb-4">
                            <span class="timeline-icon position-absolute rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                                <i class="bi bi-thread fs-6"></i>
                            </span>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <strong class="text-dark"><i class="bi bi-thread text-warning me-1"></i>Stage 3: Stitching Line &amp; Tailoring Assembly</strong>
                                    <span class="badge bg-light text-dark border">Stitching Batches: ${stitching.length}</span>
                                </div>
                                <div class="card-body p-3">
                                    ${stitching.map(s => `
                                        <div class="p-2 rounded bg-light border mb-2 small d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div>
                                                <strong>Batch #${escapeHtml(s.batch_number || '')}</strong> &bull; Stitched Pcs: <span class="badge bg-success font-monospace">${s.stitched_qty || 0} pcs</span>
                                                <div class="text-muted">Tailor/Operator: ${escapeHtml(s.operator_name || 'Tailoring Line')} &bull; Defect: ${s.defect_qty || 0} pcs</div>
                                            </div>
                                            <span class="badge bg-secondary font-monospace">${s.status || ''}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `;
                }

                // 4. Quality Control
                if (qc) {
                    const qcStatusRaw = (qc.qc_status || qc.status || 'PASSED').toUpperCase();
                    let qcBadgeClass = 'bg-success';
                    if (qcStatusRaw === 'PENDING') qcBadgeClass = 'bg-warning text-dark';
                    else if (qcStatusRaw === 'REWORK') qcBadgeClass = 'bg-info text-dark';
                    else if (qcStatusRaw === 'REJECTED') qcBadgeClass = 'bg-danger';
                    else if (qcStatusRaw === 'PARTIAL' || qcStatusRaw === 'CONDITIONALLY_PASSED') qcBadgeClass = 'bg-warning text-dark';

                    const qcBatchNum = qc.batch_number || (qc.id ? ('QC #' + qc.id) : (qc.qc_number || 'QC Batch'));
                    const qcInspector = qc.inspector_name || 'System Administrator';
                    const qcDate = qc.inspection_date || qc.updated_at || qc.created_at || 'Inspected';
                    const qcNotesText = qc.remarks || qc.notes || '';

                    html += `
                        <div class="timeline-step position-relative mb-4">
                            <span class="timeline-icon position-absolute rounded-circle bg-success text-white d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                                <i class="bi bi-check2-all fs-6"></i>
                            </span>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <strong class="text-dark"><i class="bi bi-shield-check text-success me-1"></i>Stage 4: Quality Inspection &amp; QC Verdict</strong>
                                    <span class="badge ${qcBadgeClass} font-monospace">${escapeHtml(qcStatusRaw)} &bull; ${qcDate}</span>
                                </div>
                                <div class="card-body p-3 small">
                                    <div class="row g-2">
                                        <div class="col-sm-3"><strong>QC Batch:</strong> <span class="font-monospace text-primary fw-bold">${escapeHtml(qcBatchNum)}</span></div>
                                        <div class="col-sm-3"><strong>Inspector:</strong> <span class="text-dark fw-semibold">${escapeHtml(qcInspector)}</span></div>
                                        <div class="col-sm-3"><strong>Passed Goods:</strong> <span class="badge bg-success font-monospace">${qc.passed_qty || 0} pcs</span></div>
                                        <div class="col-sm-3"><strong>Rejected:</strong> <span class="badge bg-danger font-monospace">${qc.rejected_qty || 0} pcs</span>${parseInt(qc.rework_qty || 0) > 0 ? ' &bull; <strong>Rework:</strong> <span class="badge bg-info text-dark font-monospace">' + qc.rework_qty + ' pcs</span>' : ''}</div>
                                    </div>
                                    ${qcNotesText ? `<div class="mt-2 text-muted"><strong>QC Notes:</strong> ${escapeHtml(qcNotesText)}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }

                // 5. Packaging & Box Prep
                html += `
                    <div class="timeline-step position-relative mb-4">
                        <span class="timeline-icon position-absolute rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                            <i class="bi bi-box-seam fs-6"></i>
                        </span>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                <strong class="text-dark"><i class="bi bi-box-seam text-secondary me-1"></i>Stage 5: Final Packaging &amp; Master Carton Packing</strong>
                                <span class="badge bg-light text-dark border">${batch.created_at || 'Packed'}</span>
                            </div>
                            <div class="card-body p-3 small">
                                <div class="row g-2">
                                    <div class="col-sm-4"><strong>Total Packed Quantity:</strong> <span class="badge bg-dark font-monospace fs-7">${batch.total_qty || 0} pcs</span></div>
                                    <div class="col-sm-4"><strong>Carton Boxes:</strong> <span class="badge bg-light text-dark border font-monospace">${batch.box_count || 1} boxes</span></div>
                                    <div class="col-sm-4"><strong>Prepared By:</strong> ${escapeHtml(batch.prepared_by_name || 'Production Lead')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // 6. Outward Logistics Dispatch
                if (outward) {
                    html += `
                        <div class="timeline-step position-relative mb-4">
                            <span class="timeline-icon position-absolute rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                                <i class="bi bi-truck fs-6"></i>
                            </span>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <strong class="text-dark"><i class="bi bi-truck text-primary me-1"></i>Stage 6: Outward Logistics Dispatch &amp; Gate Pass</strong>
                                    <span class="badge bg-primary font-monospace">${escapeHtml(outward.outward_number || 'OUTWARD')}</span>
                                </div>
                                <div class="card-body p-3 small">
                                    <div class="row g-2">
                                        <div class="col-sm-4"><strong>Gate Pass #:</strong> <span class="font-monospace text-primary fw-bold">${escapeHtml(outward.outward_number || 'N/A')}</span></div>
                                        <div class="col-sm-4"><strong>Vehicle #:</strong> <span class="fw-bold">${escapeHtml(outward.vehicle_number || 'Internal Van')}</span></div>
                                        <div class="col-sm-4"><strong>Driver / Contact:</strong> ${escapeHtml(outward.driver_name || 'Driver')} ${outward.driver_phone ? '(' + escapeHtml(outward.driver_phone) + ')' : ''}</div>
                                        <div class="col-sm-6"><strong>Dispatch Timestamp:</strong> ${outward.dispatch_date || 'N/A'}</div>
                                        <div class="col-sm-6"><strong>Dispatched From Unit:</strong> <span class="badge bg-warning text-dark font-monospace">${escapeHtml(batch.production_unit_name || 'Main Unit')}</span></div>
                                    </div>
                                    ${outward.notes ? `<div class="mt-2 text-muted"><strong>Gate Notes:</strong> ${escapeHtml(outward.notes)}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }

                // 7. Warehouse Inward GRN Receipt & Multi-Session Audit History
                const sessions = d.inward_sessions || [];
                const totalExpected = parseInt(batch.total_qty) || 0;
                const totalReceived = parseInt(batch.received_qty) || 0;
                const totalDamaged = parseInt(batch.damaged_qty) || 0;
                const totalMissing = (batch.missing_qty !== undefined && batch.missing_qty !== null) ? parseInt(batch.missing_qty) : Math.max(0, totalExpected - (totalReceived + totalDamaged));
                const isFullyReceived = (batch.status === 'RECEIVED' && totalMissing === 0);
                const isPartial = (totalReceived > 0 && totalMissing > 0);
                const sessionCount = sessions.length;

                let stage7Badge = '<span class="badge bg-warning text-dark font-monospace"><i class="bi bi-hourglass-split me-1"></i>AWAITING GRN SCAN</span>';
                if (isFullyReceived) {
                    stage7Badge = `<span class="badge bg-success font-monospace"><i class="bi bi-check2-all me-1"></i>RECEIVED &amp; 100% STOCKED (${sessionCount > 1 ? sessionCount + ' SESSIONS' : '1 TIME'})</span>`;
                } else if (isPartial) {
                    stage7Badge = `<span class="badge bg-warning text-dark border border-warning font-monospace"><i class="bi bi-exclamation-triangle me-1"></i>PARTIAL INWARD (${sessionCount} SESSIONS &bull; ${totalMissing} SHORT)</span>`;
                }

                html += `
                    <div class="timeline-step position-relative">
                        <span class="timeline-icon position-absolute rounded-circle ${isFullyReceived ? 'bg-success' : (isPartial ? 'bg-warning' : 'bg-secondary')} text-white d-flex align-items-center justify-content-center shadow-sm" style="width:34px; height:34px; left:-38px; top:0;">
                            <i class="bi ${isFullyReceived ? 'bi-building-check text-white' : (isPartial ? 'bi-hourglass-split text-dark' : 'bi-building text-white')} fs-6"></i>
                        </span>
                        <div class="card border-0 shadow-sm border-start ${isFullyReceived ? 'border-success' : (isPartial ? 'border-warning' : 'border-primary')} border-4">
                            <div class="card-header bg-white py-2.5 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong class="text-dark fs-6"><i class="bi bi-box-arrow-in-down text-primary me-1"></i>Stage 7: Warehouse Inward &amp; Stock Ledger</strong>
                                    <small class="text-muted d-block font-monospace">Inward Verification Frequency: <strong class="text-primary">${sessionCount > 0 ? sessionCount + ' Time(s) Inwarded' : 'Pending Inward'}</strong></small>
                                </div>
                                ${stage7Badge}
                            </div>
                            <div class="card-body p-3">
                                <!-- Top Inward Summary Metric Strip -->
                                <div class="row g-2 mb-3 text-center">
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 rounded bg-light border">
                                            <span class="text-muted small d-block" style="font-size: 0.72rem;">Outward Expected</span>
                                            <h6 class="fw-bold mb-0 text-dark font-monospace mt-0.5">${totalExpected} pcs</h6>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25">
                                            <span class="text-success small d-block fw-semibold" style="font-size: 0.72rem;">Inward Credited</span>
                                            <h6 class="fw-bold mb-0 text-success font-monospace mt-0.5">${totalReceived} pcs</h6>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 rounded ${totalMissing > 0 ? 'bg-warning bg-opacity-10 border border-warning border-opacity-50' : 'bg-light border'}">
                                            <span class="${totalMissing > 0 ? 'text-warning text-dark' : 'text-muted'} small d-block fw-semibold" style="font-size: 0.72rem;">Pending / Missing</span>
                                            <h6 class="fw-bold mb-0 ${totalMissing > 0 ? 'text-danger' : 'text-success'} font-monospace mt-0.5">${totalMissing} pcs</h6>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 rounded bg-primary bg-opacity-10 border border-primary border-opacity-25">
                                            <span class="text-primary small d-block fw-semibold" style="font-size: 0.72rem;">Inward Frequency</span>
                                            <h6 class="fw-bold mb-0 text-primary font-monospace mt-0.5"><i class="bi bi-arrow-repeat me-1"></i>${sessionCount} Times</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2 small mb-3">
                                    <div class="col-sm-6 col-md-6">
                                        <strong>Receiving Warehouse:</strong> 
                                        <span class="badge bg-primary text-white font-monospace ms-1">${escapeHtml(batch.warehouse_name || '')} (${escapeHtml(batch.warehouse_code || 'WH')})</span>
                                    </div>
                                    <div class="col-sm-6 col-md-6">
                                        <strong>Stock Status:</strong> 
                                        ${isFullyReceived ? '<span class="badge bg-success ms-1"><i class="bi bi-check-circle-fill me-1"></i>100% Stock Credited (Available)</span>' : (isPartial ? '<span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle me-1"></i>Partial Inward In Progress</span>' : '<span class="badge bg-secondary ms-1">Awaiting Inward</span>')}
                                    </div>
                                </div>

                                <!-- Inward Sessions History Table -->
                                ${sessions.length > 0 ? `
                                    <div class="p-3 bg-light rounded border mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                            <strong class="text-dark small"><i class="bi bi-clock-history text-primary me-1"></i>Inward Verification Sessions &amp; Incremental Crediting Logs (${sessions.length} Times Logged):</strong>
                                            <span class="badge bg-dark text-warning font-monospace">${sessions.length} Inward Session(s)</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover bg-white rounded border align-middle mb-0 font-monospace" style="font-size: 11px;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Session #</th>
                                                        <th>Timestamp</th>
                                                        <th class="text-center">Added (Delta)</th>
                                                        <th class="text-center">Cumulative Credited</th>
                                                        <th class="text-center">Damaged</th>
                                                        <th class="text-center">Pending Shortage</th>
                                                        <th class="text-center">Match %</th>
                                                        <th>Verified By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${sessions.map(s => `
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-dark text-warning font-monospace px-2 py-1">${escapeHtml(s.session_title || ('Session #' + s.session_num))}</span>
                                                            </td>
                                                            <td class="text-muted font-monospace">${escapeHtml(s.created_at || 'N/A')}</td>
                                                            <td class="text-center">
                                                                <span class="badge bg-success font-monospace">+${s.incremental_qty} pcs</span>
                                                            </td>
                                                            <td class="text-center fw-bold text-dark font-monospace">
                                                                ${s.cumulative_accepted} / ${s.expected_qty} pcs
                                                            </td>
                                                            <td class="text-center">
                                                                ${s.cumulative_damaged > 0 ? `<span class="badge bg-danger">${s.cumulative_damaged} pcs</span>` : '<span class="text-muted">0</span>'}
                                                            </td>
                                                            <td class="text-center">
                                                                ${s.missing_qty > 0 ? `<span class="badge bg-warning text-dark font-monospace">${s.missing_qty} pcs short</span>` : '<span class="badge bg-success font-monospace">0 (Matched)</span>'}
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge ${s.match_pct >= 100 ? 'bg-success' : 'bg-info'} font-monospace">${s.match_pct}%</span>
                                                            </td>
                                                            <td>
                                                                <span class="fw-semibold text-dark"><i class="bi bi-person-check me-1 text-primary"></i>${escapeHtml(s.verified_by || 'Admin')}</span>
                                                            </td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ` : ''}

                                <!-- Stock Ledger Transactions Table -->
                                ${txns.length > 0 ? `
                                    <div class="mt-2 pt-2 border-top">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-muted small"><i class="bi bi-receipt me-1 text-primary"></i>Stock Ledger Postings (${txns.length} Transaction Records):</strong>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered bg-white mb-0 font-monospace" style="font-size: 11px;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Txn #</th>
                                                        <th>Variant SKU</th>
                                                        <th>Stock State</th>
                                                        <th>Warehouse</th>
                                                        <th class="text-end">Qty Added</th>
                                                        <th>Balance After</th>
                                                        <th>Date &amp; Time</th>
                                                        <th>Reference Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${txns.map(t => `
                                                        <tr>
                                                            <td>TXN-${t.id}</td>
                                                            <td class="fw-bold text-primary">${escapeHtml(t.sku || t.variant_sku || 'Item')}</td>
                                                            <td><span class="badge ${t.stock_state === 'DAMAGED' ? 'bg-danger' : 'bg-success'}">${escapeHtml(t.stock_state || 'AVAILABLE')}</span></td>
                                                            <td>${escapeHtml(t.warehouse_name || '')}</td>
                                                            <td class="text-end ${parseFloat(t.quantity) >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'}">${parseFloat(t.quantity) > 0 ? '+' : ''}${parseFloat(t.quantity)} pcs</td>
                                                            <td class="text-end font-monospace">${parseFloat(t.balance_after || 0)} pcs</td>
                                                            <td>${escapeHtml(t.created_at || '')}</td>
                                                            <td class="text-muted text-truncate" style="max-width: 200px;">${escapeHtml(t.notes || '')}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;

                html += `</div>`; // End timeline-wrapper

                historyContent.innerHTML = html;
                historyContent.classList.remove('d-none');
            })
            .catch(err => {
                historyLoading.classList.add('d-none');
                historyContent.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Error communicating with server: ${err.message}</div>`;
                historyContent.classList.remove('d-none');
            });
    }
</script>
