<?php
    $dispNum = $batch['allocation_number'] ?? $batch['batch_number'] ?? ('ALC-' . str_pad($allocation['id'] ?? 1, 5, '0', STR_PAD_LEFT));
    $displayRec = (int)($totalReceived ?? 0);
    $displayDmg = (int)($totalDamaged ?? 0);
    $displayMis = (int)($totalMissing ?? 0);
    $displayPct = $totalExpected > 0 ? round(($displayRec / $totalExpected) * 100) : 0;
    $isFullyReceived = (in_array(strtoupper($batch['status'] ?? ''), ['COMPLETED', 'RECEIVED']) && $displayMis === 0);
    $canReceive = hasPermission('website_inward', 'receive') || hasPermission('website_inward', 'create');
?>

<?php if (!$canReceive): ?>
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-2 mb-4 p-3 rounded-3">
    <i class="bi bi-shield-lock-fill fs-4 text-warning"></i>
    <div>
        <strong>Read-Only Mode:</strong> You have view-only access to this Inward Batch. Modifying quantities, scanning QR barcodes into inventory, or updating stock records is restricted.
    </div>
</div>
<?php endif; ?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h2 class="mb-0"><i class="bi bi-qr-code-scan me-2 text-primary"></i>Cross-Check Inward Batch #<?= e($dispNum) ?></h2>
            <span class="badge bg-secondary bg-opacity-10 text-dark fs-6 px-3 py-2 border shadow-sm">
                <i class="bi bi-building me-1 text-secondary"></i>From Warehouse: <strong><?= e($allocation['warehouse_name'] ?? 'Main Central Warehouse') ?> (<?= e($allocation['warehouse_code'] ?? 'WH-01') ?>)</strong>
            </span>
            <span class="badge bg-primary bg-opacity-10 text-primary fs-6 px-3 py-2 border border-primary border-opacity-25 shadow-sm">
                <i class="bi bi-globe me-1"></i>Destination: <strong><?= e($allocation['channel_name'] ?? 'Website Channel') ?> (WEB-01)</strong>
            </span>
        </div>
        <span class="text-muted">Origin Warehouse: <strong><?= e($allocation['warehouse_name'] ?? 'Central Warehouse') ?></strong> &rarr; Inward Target: <strong><?= e($allocation['channel_name'] ?? 'Website Channel') ?></strong></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-info fw-semibold" onclick="openBatchHistoryModal(<?= $allocation['id'] ?? 1 ?>)">
            <i class="bi bi-clock-history me-1"></i> Batch Lifecycle &amp; Logs
        </button>
        <button type="button" class="btn btn-outline-primary fw-semibold" onclick="openCrossCheckModal(<?= $allocation['id'] ?? 1 ?>)">
            <i class="bi bi-printer me-1"></i> Print Carton QR Sticker
        </button>
        <a href="<?= url('warehouse/website-orders/inward') ?>" class="btn btn-outline-secondary fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Inward Queue
        </a>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. LIVE SUMMARY METRIC CARDS (5 CARDS) -->
<!-- ========================================================================= -->
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
                <small class="text-danger d-block mt-1 font-monospace" style="font-size: 0.72rem;"><?= $displayDmg ?> quarantined units</small>
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
                    <div id="statProgressBar" class="progress-bar <?= $displayPct >= 100 ? 'bg-success' : 'bg-info' ?>" role="progressbar" style="width: <?= $displayPct ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- LEFT COLUMN -->
    <div class="col-xl-4 col-lg-5">
        <!-- Scannable Batch QR Card -->
        <div class="card border-0 shadow-sm mb-4 text-center">
            <div class="card-header bg-white py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-qr-code me-2 text-primary"></i>Batch Carton QR Code</h6>
            </div>
            <div class="card-body p-4">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode($dispNum) ?>" alt="QR Code" class="img-fluid mb-2 border p-2 rounded bg-light">
                <div class="font-monospace fw-bold text-primary fs-5"><?= e($dispNum) ?></div>
                <small class="text-muted d-block mt-1">Scan this master code to auto-verify the entire carton box.</small>
            </div>
        </div>

        <!-- Outward Dispatch Details Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Outward Dispatch Details</h6>
                <button type="button" class="btn btn-xs btn-outline-info py-1 px-2 shadow-sm" onclick="openBatchHistoryModal(<?= $allocation['id'] ?>)">
                    <i class="bi bi-clock-history me-1"></i> Traceability
                </button>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Outward Gate Pass:</span>
                        <span class="fw-bold text-primary font-monospace"><?= e($allocation['outward_number'] ?? $dispNum) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Allocation Ref #:</span>
                        <span class="fw-bold font-monospace text-dark"><?= e($dispNum) ?></span>
                    </li>
                    <li class="list-group-item py-2">
                        <div class="text-muted small mb-1">Product / Style:</div>
                        <div class="fw-bold text-dark text-truncate"><?= e($batch['product_name'] ?? 'Assorted Garments & Merchandise') ?></div>
                    </li>
                    <li class="list-group-item bg-light rounded p-2 my-2 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-dark fw-bold small"><i class="bi bi-building me-1 text-secondary"></i>Origin Warehouse:</span>
                            <span class="badge bg-secondary text-white font-monospace"><?= e($allocation['warehouse_code'] ?? 'WH-01') ?></span>
                        </div>
                        <div class="fw-bold text-dark small"><?= e($allocation['warehouse_name'] ?? 'Main Central Warehouse') ?></div>
                    </li>
                    <li class="list-group-item bg-primary bg-opacity-10 rounded p-2 my-2 border border-primary border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-primary fw-bold small"><i class="bi bi-globe me-1 text-primary"></i>Receiving Channel:</span>
                            <span class="badge bg-primary font-monospace">WEB-01</span>
                        </div>
                        <div class="fw-bold text-primary small"><?= e($allocation['channel_name'] ?? 'Website Channel Stock') ?></div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Carton Box Count:</span>
                        <span class="badge bg-light text-dark border font-monospace"><?= (int)($allocation['total_boxes'] ?? 1) ?> Boxes</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Vehicle / Carrier:</span>
                        <span class="fw-semibold small font-monospace"><?= e($allocation['vehicle_number'] ?? 'Direct Transfer') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Dispatch Date:</span>
                        <span class="small"><?= !empty($allocation['created_at']) ? date('d M Y, h:i A', strtotime($allocation['created_at'])) : date('d M Y, h:i A') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Current Status:</span>
                        <?php if (in_array(strtoupper($allocation['status'] ?? ''), ['COMPLETED', 'RECEIVED', 'DELIVERED'])): ?>
                            <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>Received &amp; Stocked</span>
                        <?php else: ?>
                            <span class="badge bg-primary"><i class="bi bi-truck me-1"></i>Awaiting Inward (<?= $totalExpected ?> pcs)</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-xl-8 col-lg-7">
        <?php if ($canReceive): ?>
        <!-- RESTORED SCAN DRAFT ALERT BANNER -->
        <div id="draftRestoredBanner" class="alert alert-info alert-dismissible fade show d-none mb-4 border-0 shadow-sm rounded-3" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history fs-4 text-primary"></i>
                    <div>
                        <strong class="text-dark">Draft Progress Restored:</strong>
                        <span id="draftScannedCountText" class="fw-bold text-primary">0 pcs</span> scanned in draft were preserved from your previous session. You can safely switch sections anytime.
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger shadow-xs fw-bold" onclick="clearInwardDraft(true);" title="Clear all saved progress and reset counts to 0">
                    <i class="bi bi-trash me-1"></i> Clear Draft &amp; Reset
                </button>
            </div>
        </div>

        <?php if (!$isFullyReceived): ?>
        <!-- Live Cross-Check Scanner Card -->
        <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <label class="form-label fw-bold text-dark mb-0 fs-6">
                            <i class="bi bi-upc-scan text-primary me-1"></i> Live Product &amp; QR Cross-Check Scanner
                        </label>
                        <small class="text-muted d-block">Toggle mode below to scan intact goods or quarantine damaged pieces</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="draftSaveIndicator" class="badge bg-light text-muted border small d-none">
                            <i class="bi bi-cloud-check text-success me-1"></i><span id="draftSaveText">Progress Auto-Saved</span>
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 fs-7">
                            <i class="bi bi-building me-1"></i>Inward to: <strong>Website Channel Stock</strong>
                        </span>
                    </div>
                </div>

                <!-- Scan Mode Selector -->
                <div class="mb-3">
                    <label class="small fw-bold text-dark d-block mb-2"><i class="bi bi-sliders me-1"></i>Scanner Mode (Click to Switch):</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="radio" class="btn-check" name="scanModeToggle" id="modeGood" value="good" checked autocomplete="off">
                        <label class="btn btn-outline-success fw-bold py-2 fs-6 flex-grow-1" for="modeGood">
                            <i class="bi bi-check-circle-fill me-1"></i> 🟢 Good / Intact Mode
                        </label>

                        <input type="radio" class="btn-check" name="scanModeToggle" id="modeDamage" value="damage" autocomplete="off">
                        <label class="btn btn-outline-danger fw-bold py-2 fs-6 flex-grow-1" for="modeDamage">
                            <i class="bi bi-slash-circle-fill me-1"></i> 🔴 Damaged / Inactivate Mode
                        </label>
                    </div>
                </div>

                <div class="input-group input-group-lg mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-qr-code text-primary"></i></span>
                    <input type="text" id="liveScannerInput" class="form-control font-monospace" placeholder="Scan Variant QR / Barcode / SKU / Master Carton..." autofocus autocomplete="off">
                    <button class="btn btn-primary px-4 fw-semibold" type="button" onclick="triggerScan()">
                        <i class="bi bi-plus-circle me-1"></i> Scan
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
                    <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Physical USB scanner guns will instantly cross-check on scan.</span>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-bold" onclick="quickFillAll()">
                        <i class="bi bi-magic me-1"></i> Quick Fill All Expected (Master Scan)
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Missing / Unscanned Products Table -->
        <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="card-title mb-0 fw-bold text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Missing / Unscanned Products &amp; QR Codes (Profile-Wise)
                    </h6>
                    <small class="text-muted">Shows the full product variant profile first, followed by pending missing pieces</small>
                </div>
                <span class="badge bg-danger rounded-pill px-3 py-2 fs-7"><?= count($items) ?> SKU(s) in batch</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product Variant Profile</th>
                                <th>QR Code / Tag Range</th>
                                <th>Size &amp; Color</th>
                                <th class="text-center">Expected</th>
                                <th class="text-center">Scanned</th>
                                <th class="text-end">Missing Qty</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $it): 
                                    $expQty = (int)$it['quantity'];
                                    $recQty = isset($it['received_qty']) && $it['received_qty'] !== null ? (int)$it['received_qty'] : 0;
                                    $dmgQty = isset($it['damaged_qty']) && $it['damaged_qty'] !== null ? (int)$it['damaged_qty'] : 0;
                                    $initRecv = $recQty > 0 ? $recQty : ($allocation['status'] === 'DELIVERED' || $allocation['status'] === 'RECEIVED' ? $expQty : 0);
                                    $totalProc = $initRecv + $dmgQty;
                                    $diff = $expQty - $totalProc;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="p-1 border rounded bg-light">
                                                    <i class="bi bi-box-seam text-secondary fs-5"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= e($it['product_name']) ?></div>
                                                    <span class="badge bg-dark font-monospace fs-9"><?= e($it['sku']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark font-monospace p-2"><?= e($it['qr_range'] ?? $it['sku']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e($it['size_name'] ?? 'Free Size') ?></span>
                                            <span class="badge bg-secondary bg-opacity-10 text-dark border"><?= e($it['color_name'] ?? 'Standard') ?></span>
                                        </td>
                                        <td class="text-center fw-bold"><?= $expQty ?></td>
                                        <td class="text-center fw-bold text-success" id="scanned_qty_<?= $it['id'] ?>"><?= $initRecv ?><?= $dmgQty > 0 ? " ({$dmgQty} dmg)" : "" ?></td>
                                        <td class="text-end fw-bold <?= $diff > 0 && $totalProc > 0 ? 'text-danger' : ($diff === 0 ? 'text-success' : 'text-muted') ?>" id="missing_qty_<?= $it['id'] ?>">
                                            <?= $diff > 0 && $totalProc > 0 ? '-' . $diff . ' pcs' : ($diff === 0 ? '0 pcs' : $expQty . ' pending') ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPieceInspectionModal(<?= $it['id'] ?>)">
                                                <i class="bi bi-eye me-1"></i> View Pieces
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Matrix Table Form -->
        <form method="POST" action="<?= url('warehouse/website-orders/inward/receive/' . $allocation['id']) ?>" onsubmit="try { localStorage.removeItem(DRAFT_STORAGE_KEY); } catch(e){}">
            <?= CSRF::field() ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="card-title mb-0 fw-bold"><i class="bi bi-table me-2 text-primary"></i>Outward vs Inward Cross-Check Matrix</h6>
                        <small class="text-muted">Scan QR or verify quantities. Damaged items are quarantined upon receipt.</small>
                    </div>
                    <div>
                        <?php if ($canReceive): ?>
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold" onclick="quickFillAll()">
                            <i class="bi bi-check2-all me-1"></i> Fill All Full (Intact)
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 font-monospace">
                            <thead class="table-light fs-8">
                                <tr>
                                    <th>#</th>
                                    <th>SKU &amp; QR RANGE</th>
                                    <th>SIZE &amp; COLOR</th>
                                    <th class="text-center">EXPECTED</th>
                                    <th class="text-center">INWARD RECV</th>
                                    <th class="text-center">DAMAGED / INACTIVE</th>
                                    <th class="text-center">DIFFERENCE</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $idx => $it): 
                                        $expQty = (int)$it['quantity'];
                                        $recQty = isset($it['received_qty']) && $it['received_qty'] !== null ? (int)$it['received_qty'] : 0;
                                        $dmgQty = isset($it['damaged_qty']) && $it['damaged_qty'] !== null ? (int)$it['damaged_qty'] : 0;
                                        $initInputVal = $recQty > 0 ? $recQty : ($allocation['status'] === 'DELIVERED' || $allocation['status'] === 'RECEIVED' ? $expQty : 0);
                                        $totalProc = $initInputVal + $dmgQty;
                                        $diff = $expQty - $totalProc;
                                    ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= e($it['sku']) ?></div>
                                                <small class="text-muted fs-8 font-monospace"><?= e($it['qr_range'] ?? $it['sku']) ?></small>
                                            </td>
                                            <td><?= e($it['size_name'] ?? 'Free Size') ?> / <?= e($it['color_name'] ?? 'Standard') ?></td>
                                            <td class="text-center fw-bold" id="exp_qty_<?= $it['id'] ?>" data-expected="<?= $expQty ?>"><?= $expQty ?> pcs</td>
                                            <td class="text-center"><input type="number" name="received_qty[<?= $it['id'] ?>]" id="input_recv_<?= $it['id'] ?>" class="form-control form-control-sm text-center font-monospace fw-bold" value="<?= $initInputVal ?>" min="0" max="<?= $expQty ?>" style="width: 70px; margin: 0 auto;" oninput="updateMatrixRow(<?= $it['id'] ?>)" onchange="updateMatrixRow(<?= $it['id'] ?>)" <?= !$canReceive ? 'readonly disabled' : '' ?>></td>
                                            <td class="text-center"><input type="number" name="damaged_qty[<?= $it['id'] ?>]" id="input_dmg_<?= $it['id'] ?>" class="form-control form-control-sm text-center font-monospace fw-bold text-danger" value="<?= $dmgQty ?>" min="0" max="<?= $expQty ?>" style="width: 70px; margin: 0 auto;" oninput="updateMatrixRow(<?= $it['id'] ?>)" onchange="updateMatrixRow(<?= $it['id'] ?>)" <?= !$canReceive ? 'readonly disabled' : '' ?>></td>
                                            <td class="text-center fw-bold <?= $diff > 0 && $totalProc > 0 ? 'text-danger' : ($diff === 0 ? 'text-success' : 'text-muted') ?>" id="diff_col_<?= $it['id'] ?>">
                                                <?php if ($diff > 0): ?>
                                                    <?= $totalProc > 0 ? '-' . $diff . ' pcs' : $expQty . ' pending' ?>
                                                <?php elseif ($diff === 0): ?>
                                                    0 pcs
                                                <?php else: ?>
                                                    +<?= abs($diff) ?> excess
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" id="status_col_<?= $it['id'] ?>">
                                                <?php if ($totalProc === 0): ?>
                                                    <span class="badge bg-primary">AWAITING</span>
                                                <?php elseif ($diff === 0): ?>
                                                    <?php if ($dmgQty > 0): ?>
                                                        <span class="badge bg-info text-dark"><i class="bi bi-check2 me-1"></i>VERIFIED (<?= $dmgQty ?> DMG)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>VERIFIED</span>
                                                    <?php endif; ?>
                                                <?php elseif ($diff > 0): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>PENDING (<?= $diff ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">EXCESS (+<?= abs($diff) ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openPieceInspectionModal(<?= $it['id'] ?>)">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="flex-grow-1 me-3">
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Scanned remaining pieces via QR gun. Carton completed." <?= !$canReceive ? 'readonly disabled' : '' ?>>
                    </div>
                    <?php if ($canReceive): ?>
                    <button type="submit" class="btn btn-success fw-bold px-4 py-2">
                        <i class="bi bi-check-circle-fill me-1"></i> Update / Credit Inward Stock to Website Inventory
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-secondary fw-bold px-4 py-2" disabled>
                        <i class="bi bi-lock me-1"></i> Inward Receive (Read-Only)
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: MISSED / PENDING PIECES INSPECTION (DYNAMIC EXACT PIECES MATCH) -->
<!-- ========================================================================= -->
<div class="modal fade" id="pieceInspectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-qr-code-scan fs-4 text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Allocated Stock Piece QR Codes &amp; Inspection</h5>
                        <small class="text-white-50">Piece-by-piece serialized QR barcode verification &amp; damage report</small>
                    </div>
                </div>
                <span class="badge bg-white text-primary ms-auto me-3 font-monospace fw-bold">Website Channel Stock</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Product Profile Header Box -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 border border-primary border-opacity-25">
                                <i class="bi bi-box-seam fs-1"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <h5 class="fw-bold text-dark mb-0" id="inspProdName">Product Name</h5>
                                    <span class="badge bg-info text-white" id="inspCategory">Category</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-dark font-monospace" id="inspSku">SKU</span>
                                    <span class="badge bg-light text-dark border">Size: <strong id="inspSize">-</strong></span>
                                    <span class="badge bg-light text-dark border">Color: <strong id="inspColor">-</strong></span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Price: <strong id="inspPrice">-</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Stat Summary Cards -->
                <div class="row g-3 mb-4 text-center">
                    <div class="col-3">
                        <div class="card border-0 shadow-sm p-3 bg-white">
                            <small class="text-muted fw-bold text-uppercase fs-9">EXPECTED</small>
                            <h3 class="fw-bold text-dark mb-0" id="inspExpected">0</h3>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card border-0 shadow-sm p-3 bg-white">
                            <small class="text-success fw-bold text-uppercase fs-9">GOOD PRODUCTS (INTACT)</small>
                            <h3 class="fw-bold text-success mb-0" id="inspGood">0</h3>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card border-0 shadow-sm p-3 bg-white">
                            <small class="text-danger fw-bold text-uppercase fs-9">DAMAGE PRODUCTS (QUARANTINED)</small>
                            <h3 class="fw-bold text-danger mb-0" id="inspDamaged">0</h3>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card border-0 shadow-sm p-3 bg-white">
                            <small class="text-warning fw-bold text-uppercase fs-9">PENDING MISSING</small>
                            <h3 class="fw-bold text-warning mb-0" id="inspMissing">0</h3>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- Left Column Scanner & Tags -->
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm p-4 text-center bg-white h-100">
                            <div class="p-3 border border-dashed rounded bg-light mb-3">
                                <img id="inspMainQrImg" src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=PROD" alt="QR Code" class="img-fluid mb-2" style="max-height: 150px;">
                                <div class="badge bg-primary font-monospace fs-8 px-3 py-1" id="inspMainQrCaption">Exact Piece QR</div>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold text-muted d-block text-start mb-1 fs-9">PRODUCT EXACT SKU / IDENTIFIER:</label>
                                <div class="input-group">
                                    <input type="text" id="inspExactSkuInput" class="form-control font-monospace fw-bold text-primary fs-8" value="" readonly>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('inspExactSkuInput').value)"><i class="bi bi-copy me-1"></i>Copy</button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-dark text-white font-monospace fs-8 mt-2">
                                <span>NEXT SEQUENTIAL TAG:</span>
                                <span class="badge bg-warning text-dark font-monospace fs-7" id="inspNextTag">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column Scanner Actions -->
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-4 bg-white h-100">
                            <label class="small fw-bold text-dark mb-2"><i class="bi bi-sliders me-1"></i>Scanner Mode (Click to Switch):</label>
                            <div class="d-flex gap-2 mb-3">
                                <button type="button" id="btnModeGood" class="btn btn-success fw-bold py-2 flex-grow-1 shadow-sm" onclick="setInspectionMode('good')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 🟢 Good / Intact Mode
                                </button>
                                <button type="button" id="btnModeDamage" class="btn btn-outline-danger fw-bold py-2 flex-grow-1" onclick="setInspectionMode('damage')">
                                    <i class="bi bi-slash-circle-fill me-1"></i> 🔴 Damaged / Inactivate Mode
                                </button>
                            </div>
                            <div id="inspModeAlert" class="alert alert-success py-2 text-center fs-8 fw-bold mb-3">
                                <i class="bi bi-check2-all me-1"></i> Currently scanning verified sellable stock
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" id="btnInwardNextPiece" class="btn btn-success btn-lg fw-bold py-3" onclick="inwardNextPiece()">
                                    <i class="bi bi-check-circle me-1"></i> <span id="btnInwardText">Inward Next Piece (Good Product)</span>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-lg fw-bold py-2" onclick="quickFillAllGood()">
                                    <i class="bi bi-check2-all me-1"></i> Quick All Good Products
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetInspection()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Pieces
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Piece Cards Grid -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="card-title mb-0 fw-bold text-primary" id="inspFilterTitle">
                                <i class="bi bi-qr-code me-2"></i>Piece QR Badges &amp; Inspection
                            </h6>
                            <small class="text-muted">Click 🟢 Good Product to verify or 🔴 Damage Product to report damage</small>
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" id="btnFilterPending" class="btn btn-outline-danger" onclick="filterPieceCards('pending')">Pending (<span id="cntFilterPending">0</span>)</button>
                            <button type="button" id="btnFilterAll" class="btn btn-primary active" onclick="filterPieceCards('all')">All Pieces (<span id="cntFilterAll">0</span>)</button>
                            <button type="button" id="btnFilterVerified" class="btn btn-outline-success" onclick="filterPieceCards('verified')">Verified (<span id="cntFilterVerified">0</span>)</button>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3" id="inspPiecesGrid">
                            <!-- Populated dynamically with unique QR codes per piece from Warehouse allocation -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Done &amp; Close</button>
            </div>
        </div>
    </div>
</div>

<?php
    $itemPiecesMap = [];
    foreach ($items as $it) {
        $itemPiecesMap[$it['id']] = $it['piece_qrs'] ?? [];
    }
?>
<script>
const ALLOCATION_ID = <?= (int)($allocation['id'] ?? 1) ?>;
const DRAFT_STORAGE_KEY = 'grm_website_inward_draft_' + ALLOCATION_ID;
const IS_COMPLETED = <?= $isFullyReceived ? 'true' : 'false' ?>;

const ITEM_PIECES_MAP = <?= json_encode($itemPiecesMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const ALL_PIECE_QRS = <?= json_encode($pieceQRs ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

// Global piece status tracking cache: itemId -> array of piece status objects
let SAVED_PIECE_STATUSES = {};

let inspState = {
    itemId: 0,
    sku: '',
    prodName: '',
    category: '',
    size: '',
    color: '',
    mrp: '',
    disc: '',
    totalQty: 0,
    goodCount: 0,
    damagedCount: 0,
    mode: 'good',
    activeFilter: 'all',
    pieces: []
};

function saveInwardDraft() {
    if (IS_COMPLETED) return;

    const draftData = {
        allocationId: ALLOCATION_ID,
        receivedMap: {},
        damagedMap: {},
        pieceStatuses: SAVED_PIECE_STATUSES,
        notes: (document.querySelector('input[name="notes"]')?.value || '').trim(),
        timestamp: Date.now(),
        savedAtFormatted: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    };

    let totalRecv = 0;
    let totalDmg = 0;

    for (const itemId of Object.keys(ITEM_PIECES_MAP)) {
        const recvInput = document.getElementById('input_recv_' + itemId);
        const dmgInput = document.getElementById('input_dmg_' + itemId);
        const r = recvInput ? (parseInt(recvInput.value) || 0) : 0;
        const d = dmgInput ? (parseInt(dmgInput.value) || 0) : 0;
        draftData.receivedMap[itemId] = r;
        draftData.damagedMap[itemId] = d;
        totalRecv += r;
        totalDmg += d;
    }

    try {
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draftData));
    } catch(e) {
        console.warn("LocalStorage save error:", e);
    }

    const saveIndicator = document.getElementById('draftSaveIndicator');
    const saveText = document.getElementById('draftSaveText');
    if (saveIndicator && saveText) {
        saveIndicator.classList.remove('d-none');
        saveText.textContent = `Auto-saved ${draftData.savedAtFormatted}`;
    }
}

function loadInwardDraft() {
    if (IS_COMPLETED) {
        try { localStorage.removeItem(DRAFT_STORAGE_KEY); } catch(e) {}
        return;
    }

    let draft = null;
    try {
        const raw = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (raw) {
            draft = JSON.parse(raw);
        }
    } catch(e) {
        console.warn("Draft parse error:", e);
    }

    if (!draft) return;

    let hasScannedData = false;
    let totalRestoredRecv = 0;
    let totalRestoredDmg = 0;

    if (draft.pieceStatuses && typeof draft.pieceStatuses === 'object') {
        SAVED_PIECE_STATUSES = draft.pieceStatuses;
    }

    for (const itemId of Object.keys(ITEM_PIECES_MAP)) {
        const recvInput = document.getElementById('input_recv_' + itemId);
        const dmgInput = document.getElementById('input_dmg_' + itemId);

        if (draft.receivedMap && draft.receivedMap[itemId] !== undefined) {
            const qty = parseInt(draft.receivedMap[itemId]) || 0;
            if (recvInput) recvInput.value = qty;
            totalRestoredRecv += qty;
            if (qty > 0) hasScannedData = true;
        }

        if (draft.damagedMap && draft.damagedMap[itemId] !== undefined) {
            const dmg = parseInt(draft.damagedMap[itemId]) || 0;
            if (dmgInput) dmgInput.value = dmg;
            totalRestoredDmg += dmg;
            if (dmg > 0) hasScannedData = true;
        }
    }

    const notesInput = document.querySelector('input[name="notes"]');
    if (notesInput && draft.notes) {
        notesInput.value = draft.notes;
    }

    updateAllMatrixRows(false);

    if (hasScannedData) {
        const banner = document.getElementById('draftRestoredBanner');
        const countText = document.getElementById('draftScannedCountText');
        if (banner) {
            banner.classList.remove('d-none');
        }
        if (countText) {
            const total = totalRestoredRecv + totalRestoredDmg;
            const dmgText = totalRestoredDmg > 0 ? ` (${totalRestoredRecv} good + ${totalRestoredDmg} damaged)` : '';
            countText.textContent = `${total} pcs${dmgText}`;
        }

        const saveIndicator = document.getElementById('draftSaveIndicator');
        const saveText = document.getElementById('draftSaveText');
        if (saveIndicator && saveText) {
            saveIndicator.classList.remove('d-none');
            saveText.textContent = 'Progress Preserved & Restored';
        }
    }
}

function clearInwardDraft(confirmPrompt = false) {
    if (confirmPrompt && !confirm('Are you sure you want to clear your saved scan progress and reset all counts to 0?')) {
        return;
    }

    try {
        localStorage.removeItem(DRAFT_STORAGE_KEY);
    } catch(e) {}

    SAVED_PIECE_STATUSES = {};

    const banner = document.getElementById('draftRestoredBanner');
    if (banner) banner.classList.add('d-none');

    const saveIndicator = document.getElementById('draftSaveIndicator');
    if (saveIndicator) saveIndicator.classList.add('d-none');

    for (const itemId of Object.keys(ITEM_PIECES_MAP)) {
        const recvInput = document.getElementById('input_recv_' + itemId);
        const dmgInput = document.getElementById('input_dmg_' + itemId);
        if (recvInput) recvInput.value = 0;
        if (dmgInput) dmgInput.value = 0;
    }

    const notesInput = document.querySelector('input[name="notes"]');
    if (notesInput) notesInput.value = '';

    updateAllMatrixRows(false);
}

function openPieceInspectionModal(itemId) {
    const rawPieces = ITEM_PIECES_MAP[itemId] || [];
    if (rawPieces.length === 0) {
        alert('No piece QR records assigned for this item.');
        return;
    }

    const first = rawPieces[0];
    inspState.itemId = itemId;
    inspState.sku = first.sku || '';
    inspState.prodName = first.product_name || 'Garment Item';
    inspState.category = first.category_name || 'Apparel';
    inspState.size = first.size_name || 'Free';
    inspState.color = first.color_name || 'Std';
    inspState.mrp = first.mrp || '999';
    inspState.disc = first.disc || '500';
    inspState.totalQty = rawPieces.length;
    inspState.mode = 'good';
    inspState.activeFilter = 'all';

    const curRecv = parseInt(document.getElementById('input_recv_' + itemId)?.value) || 0;
    const curDmg = parseInt(document.getElementById('input_dmg_' + itemId)?.value) || 0;

    // Check if we have cached piece statuses from draft or previous session
    const cachedPieces = SAVED_PIECE_STATUSES[itemId];

    if (cachedPieces && Array.isArray(cachedPieces) && cachedPieces.length === rawPieces.length) {
        let goodCount = 0;
        let damagedCount = 0;
        inspState.pieces = rawPieces.map((p, idx) => {
            const cached = cachedPieces[idx] || {};
            const st = cached.status || 'pending';
            if (st === 'good') goodCount++;
            else if (st === 'damaged') damagedCount++;
            return {
                num: p.piece_no || (idx + 1),
                tag: p.tag_code,
                sku: p.sku,
                prodName: p.product_name,
                size: p.size_name,
                color: p.color_name,
                mrp: p.mrp,
                disc: p.disc,
                selling_price: p.selling_price,
                qrPayload: p.qr_payload || (p.tag_code + '|' + p.sku),
                status: st,
                scannedAt: cached.scannedAt || (st !== 'pending' ? 'Verified' : null)
            };
        });
        inspState.goodCount = goodCount;
        inspState.damagedCount = damagedCount;
    } else {
        inspState.pieces = rawPieces.map((p, idx) => {
            let st = 'pending';
            let scannedTime = null;
            if (idx < curRecv) {
                st = 'good';
                scannedTime = 'Verified';
            } else if (idx < (curRecv + curDmg)) {
                st = 'damaged';
                scannedTime = 'Quarantined';
            }
            return {
                num: p.piece_no || (idx + 1),
                tag: p.tag_code,
                sku: p.sku,
                prodName: p.product_name,
                size: p.size_name,
                color: p.color_name,
                mrp: p.mrp,
                disc: p.disc,
                selling_price: p.selling_price,
                qrPayload: p.qr_payload || (p.tag_code + '|' + p.sku),
                status: st,
                scannedAt: scannedTime
            };
        });
        inspState.goodCount = curRecv;
        inspState.damagedCount = curDmg;
    }

    document.getElementById('inspProdName').textContent = inspState.prodName;
    document.getElementById('inspCategory').textContent = inspState.category;
    document.getElementById('inspSku').textContent = inspState.sku;
    document.getElementById('inspSize').textContent = inspState.size;
    document.getElementById('inspColor').textContent = inspState.color;
    document.getElementById('inspPrice').textContent = 'MRP: ₹' + inspState.mrp + ' | Disc: ₹' + inspState.disc;
    document.getElementById('inspExactSkuInput').value = inspState.sku;
    document.getElementById('inspExpected').textContent = inspState.totalQty;

    document.getElementById('inspMainQrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(first.qr_payload || inspState.sku);
    document.getElementById('inspMainQrCaption').textContent = 'Exact Piece QR: ' + first.tag_code;

    setInspectionMode('good');
    updateInspectionUI();

    const modal = new bootstrap.Modal(document.getElementById('pieceInspectionModal'));
    modal.show();
}

function setInspectionMode(mode) {
    inspState.mode = mode;
    const btnGood = document.getElementById('btnModeGood');
    const btnDamage = document.getElementById('btnModeDamage');
    const alertBox = document.getElementById('inspModeAlert');
    const btnText = document.getElementById('btnInwardText');
    const btnNext = document.getElementById('btnInwardNextPiece');

    if (mode === 'good') {
        btnGood.className = 'btn btn-success fw-bold py-2 flex-grow-1 shadow-sm';
        btnDamage.className = 'btn btn-outline-danger fw-bold py-2 flex-grow-1';
        alertBox.className = 'alert alert-success py-2 text-center fs-8 fw-bold mb-3';
        alertBox.innerHTML = '<i class="bi bi-check2-all me-1"></i> Currently scanning verified sellable stock';
        btnText.textContent = 'Inward Next Piece (Good Product)';
        btnNext.className = 'btn btn-success btn-lg fw-bold py-3';
    } else {
        btnGood.className = 'btn btn-outline-success fw-bold py-2 flex-grow-1';
        btnDamage.className = 'btn btn-danger fw-bold py-2 flex-grow-1 shadow-sm';
        alertBox.className = 'alert alert-danger py-2 text-center fs-8 fw-bold mb-3';
        alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Currently scanning damaged/quarantined stock';
        btnText.textContent = 'Inward Next Piece (Damage Product)';
        btnNext.className = 'btn btn-danger btn-lg fw-bold py-3';
    }
}

function inwardNextPiece() {
    const nextPiece = inspState.pieces.find(p => p.status === 'pending');
    if (!nextPiece) {
        alert('All ' + inspState.totalQty + ' pieces have already been processed!');
        return;
    }

    const now = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    nextPiece.status = inspState.mode === 'good' ? 'good' : 'damaged';
    nextPiece.scannedAt = now;

    if (inspState.mode === 'good') {
        inspState.goodCount++;
    } else {
        inspState.damagedCount++;
    }

    updateInspectionUI();
}

function quickFillAllGood() {
    const now = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    inspState.pieces.forEach(p => {
        if (p.status !== 'good') {
            if (p.status === 'damaged') inspState.damagedCount--;
            p.status = 'good';
            p.scannedAt = now;
            inspState.goodCount++;
        }
    });
    updateInspectionUI();
}

function resetInspection() {
    inspState.goodCount = 0;
    inspState.damagedCount = 0;
    inspState.pieces.forEach(p => {
        p.status = 'pending';
        p.scannedAt = null;
    });
    updateInspectionUI();
}

function filterPieceCards(filter) {
    inspState.activeFilter = filter;
    document.getElementById('btnFilterPending').className = filter === 'pending' ? 'btn btn-danger active' : 'btn btn-outline-danger';
    document.getElementById('btnFilterAll').className = filter === 'all' ? 'btn btn-primary active' : 'btn btn-outline-primary';
    document.getElementById('btnFilterVerified').className = filter === 'verified' ? 'btn btn-success active' : 'btn btn-outline-success';
    renderPieceCards();
}

function updateInspectionUI() {
    const missing = inspState.totalQty - (inspState.goodCount + inspState.damagedCount);
    document.getElementById('inspGood').textContent = inspState.goodCount;
    document.getElementById('inspDamaged').textContent = inspState.damagedCount;
    document.getElementById('inspMissing').textContent = Math.max(0, missing);

    // Sync with parent form inputs in real-time
    const parentRecv = document.getElementById('input_recv_' + inspState.itemId);
    const parentDmg = document.getElementById('input_dmg_' + inspState.itemId);
    if (parentRecv) parentRecv.value = inspState.goodCount;
    if (parentDmg) parentDmg.value = inspState.damagedCount;

    // Cache piece statuses for draft restoration
    SAVED_PIECE_STATUSES[inspState.itemId] = inspState.pieces.map(p => ({
        num: p.num,
        tag: p.tag,
        status: p.status,
        scannedAt: p.scannedAt
    }));

    // Real-time recalculation of Difference and Status on the parent table and save draft
    updateMatrixRow(inspState.itemId, true);

    document.getElementById('cntFilterPending').textContent = Math.max(0, missing);
    document.getElementById('cntFilterAll').textContent = inspState.totalQty;
    document.getElementById('cntFilterVerified').textContent = inspState.goodCount + inspState.damagedCount;

    const nextPiece = inspState.pieces.find(p => p.status === 'pending');
    if (nextPiece) {
        document.getElementById('inspNextTag').textContent = nextPiece.tag + ' (Piece #' + nextPiece.num + ')';
    } else {
        document.getElementById('inspNextTag').textContent = 'ALL PIECES PROCESSED ✔';
    }

    renderPieceCards();
}

function renderPieceCards() {
    const grid = document.getElementById('inspPiecesGrid');
    grid.innerHTML = '';

    const filtered = inspState.pieces.filter(p => {
        if (inspState.activeFilter === 'pending') return p.status === 'pending';
        if (inspState.activeFilter === 'verified') return p.status !== 'pending';
        return true; // all
    });

    if (filtered.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-4 text-muted bg-light rounded border">
                <i class="bi bi-check-circle fs-2 d-block mb-1 text-success"></i>
                No pieces match the selected filter.
            </div>
        `;
        return;
    }

    filtered.forEach(p => {
        let borderClass = 'border';
        let badgeHtml = `<span class="badge bg-dark text-white font-monospace fs-9">${p.tag}</span>`;
        let statusBadge = `<span class="badge bg-warning text-dark font-monospace fs-9">PENDING</span>`;

        const qrColor = p.status === 'good' ? '006600' : (p.status === 'damaged' ? 'cc0000' : '000000');
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&color=' + qrColor + '&data=' + encodeURIComponent(p.qrPayload);

        if (p.status === 'good') {
            borderClass = 'border-2 border-success bg-success bg-opacity-10';
            statusBadge = `<span class="badge bg-success font-monospace fs-9"><i class="bi bi-check2-all me-1"></i>VERIFIED GOOD (${p.scannedAt})</span>`;
        } else if (p.status === 'damaged') {
            borderClass = 'border-2 border-danger bg-danger bg-opacity-10';
            statusBadge = `<span class="badge bg-danger font-monospace fs-9"><i class="bi bi-slash-circle me-1"></i>QUARANTINED DAMAGE (${p.scannedAt})</span>`;
        }

        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4';
        col.innerHTML = `
            <div class="card ${borderClass} shadow-sm p-3 rounded-3 bg-white h-100">
                <div class="d-flex align-items-start gap-3">
                    <div class="text-center flex-shrink-0">
                        <img src="${qrUrl}" alt="QR ${p.tag}" class="img-fluid border p-1 rounded bg-white mb-1" style="width: 75px; height: 75px;">
                        <div>${badgeHtml}</div>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="fw-bold text-dark text-truncate mb-1 fs-8 text-uppercase" title="${escapeHtml(p.prodName)}">${escapeHtml(p.prodName)}</div>
                        <div class="text-muted font-monospace fs-9 text-truncate mb-1">${escapeHtml(p.sku)}</div>
                        <div class="d-flex align-items-center gap-1.5 fs-9 mb-1">
                            <span class="badge bg-light text-dark border">${escapeHtml(p.size)}</span>
                            <span class="badge bg-light text-dark border">${escapeHtml(p.color)}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 fs-9 mb-2">
                            <span class="text-muted text-decoration-line-through">MRP: ₹${p.mrp}</span>
                            <span class="fw-bold text-success">Disc: ₹${p.disc}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                            ${statusBadge}
                            <div class="btn-group btn-group-xs">
                                <button class="btn btn-sm btn-outline-success py-0 px-2 fs-9" onclick="markPieceSingle(${p.num}, 'good')">🟢 Good</button>
                                <button class="btn btn-sm btn-outline-danger py-0 px-2 fs-9" onclick="markPieceSingle(${p.num}, 'damaged')">🔴 Damage</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        grid.appendChild(col);
    });
}

function markPieceSingle(pieceNum, status) {
    const target = inspState.pieces.find(p => p.num === pieceNum);
    if (!target) return;
    const now = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    if (target.status === 'good') inspState.goodCount--;
    if (target.status === 'damaged') inspState.damagedCount--;

    target.status = status;
    target.scannedAt = now;

    if (status === 'good') inspState.goodCount++;
    else if (status === 'damaged') inspState.damagedCount++;

    updateInspectionUI();
}

function escapeHtml(str) {
    return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function updateGlobalSummaryStats() {
    let grandExpected = 0;
    let grandRecv = 0;
    let grandDmg = 0;

    for (const itemId of Object.keys(ITEM_PIECES_MAP)) {
        const expCell = document.getElementById('exp_qty_' + itemId);
        let exp = 0;
        if (expCell && expCell.getAttribute('data-expected')) {
            exp = parseInt(expCell.getAttribute('data-expected')) || 0;
        } else if (ITEM_PIECES_MAP[itemId]) {
            exp = ITEM_PIECES_MAP[itemId].length;
        }
        const recvInput = document.getElementById('input_recv_' + itemId);
        const dmgInput = document.getElementById('input_dmg_' + itemId);
        const r = recvInput ? (parseInt(recvInput.value) || 0) : 0;
        const d = dmgInput ? (parseInt(dmgInput.value) || 0) : 0;

        grandExpected += exp;
        grandRecv += r;
        grandDmg += d;
    }

    const grandProc = grandRecv + grandDmg;
    const grandMissing = Math.max(0, grandExpected - grandProc);
    const pct = grandExpected > 0 ? Math.min(100, Math.round((grandProc / grandExpected) * 100)) : 0;

    const elExp = document.getElementById('statExpected');
    const elScanned = document.getElementById('statScanned');
    const elDamaged = document.getElementById('statDamaged');
    const elMissing = document.getElementById('statMissing');
    const elPct = document.getElementById('statProgressPct');
    const elBar = document.getElementById('statProgressBar');

    if (elExp) elExp.innerHTML = `${grandExpected} <small class="fs-6 text-muted">pcs</small>`;
    if (elScanned) elScanned.innerHTML = `${grandRecv} <small class="fs-6 text-muted">pcs</small>`;
    if (elDamaged) elDamaged.innerHTML = `${grandDmg} <small class="fs-6 text-muted">pcs</small>`;
    if (elMissing) elMissing.innerHTML = `${grandMissing} <small class="fs-6 text-muted">pcs</small>`;
    if (elPct) elPct.textContent = `${pct}%`;
    if (elBar) {
        elBar.style.width = `${pct}%`;
        elBar.className = pct >= 100 ? 'progress-bar bg-success' : 'progress-bar bg-info';
    }
}

function updateMatrixRow(itemId, shouldSaveDraft = true) {
    const recvInput = document.getElementById('input_recv_' + itemId);
    const dmgInput = document.getElementById('input_dmg_' + itemId);
    const diffCol = document.getElementById('diff_col_' + itemId);
    const statusCol = document.getElementById('status_col_' + itemId);
    const scannedCell = document.getElementById('scanned_qty_' + itemId);
    const missingCell = document.getElementById('missing_qty_' + itemId);
    const expCell = document.getElementById('exp_qty_' + itemId);

    if (!recvInput || !dmgInput || !diffCol || !statusCol) return;

    let expected = 0;
    if (expCell && expCell.getAttribute('data-expected')) {
        expected = parseInt(expCell.getAttribute('data-expected')) || 0;
    } else if (ITEM_PIECES_MAP[itemId]) {
        expected = ITEM_PIECES_MAP[itemId].length;
    }

    let recv = parseInt(recvInput.value) || 0;
    let dmg = parseInt(dmgInput.value) || 0;

    if (recv < 0) { recv = 0; recvInput.value = 0; }
    if (dmg < 0) { dmg = 0; dmgInput.value = 0; }

    const totalProcessed = recv + dmg;
    const pending = expected - totalProcessed;

    // 1. Update Upper Missing Table
    if (scannedCell) {
        scannedCell.textContent = recv + (dmg > 0 ? ` (${dmg} dmg)` : '');
    }
    if (missingCell) {
        if (pending > 0) {
            missingCell.className = 'text-end fw-bold ' + (totalProcessed > 0 ? 'text-danger' : 'text-muted');
            missingCell.textContent = totalProcessed > 0 ? `-${pending} pcs` : `${expected} pending`;
        } else if (pending === 0) {
            missingCell.className = 'text-end fw-bold text-success';
            missingCell.textContent = '0 pcs';
        } else {
            missingCell.className = 'text-end fw-bold text-warning';
            missingCell.textContent = `+${Math.abs(pending)} excess`;
        }
    }

    // 2. Update Difference Column in Matrix Table
    if (pending > 0) {
        if (totalProcessed === 0) {
            diffCol.className = 'text-center fw-bold text-muted';
            diffCol.innerHTML = `${expected} pending`;
        } else {
            diffCol.className = 'text-center fw-bold text-danger';
            diffCol.innerHTML = `-${pending} pcs <small class="text-muted d-block fs-9 font-monospace">(${pending} pending)</small>`;
        }
    } else if (pending === 0) {
        diffCol.className = 'text-center fw-bold text-success';
        if (dmg > 0) {
            diffCol.innerHTML = `0 pcs <small class="text-danger d-block fs-9 font-monospace">(${dmg} damaged)</small>`;
        } else {
            diffCol.innerHTML = `0 pcs`;
        }
    } else {
        diffCol.className = 'text-center fw-bold text-warning';
        diffCol.innerHTML = `+${Math.abs(pending)} excess`;
    }

    // 3. Update Status Column in Matrix Table
    if (totalProcessed === 0) {
        statusCol.innerHTML = `<span class="badge bg-primary">AWAITING</span>`;
    } else if (pending > 0) {
        statusCol.innerHTML = `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>PENDING (${pending})</span>`;
    } else if (pending === 0) {
        if (dmg > 0) {
            statusCol.innerHTML = `<span class="badge bg-info text-dark"><i class="bi bi-check2 me-1"></i>VERIFIED (${dmg} DMG)</span>`;
        } else {
            statusCol.innerHTML = `<span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>VERIFIED</span>`;
        }
    } else {
        statusCol.innerHTML = `<span class="badge bg-warning text-dark">EXCESS (+${Math.abs(pending)})</span>`;
    }

    updateGlobalSummaryStats();

    if (shouldSaveDraft) {
        saveInwardDraft();
    }
}

function updateAllMatrixRows(shouldSaveDraft = false) {
    for (const itemId of Object.keys(ITEM_PIECES_MAP)) {
        updateMatrixRow(itemId, shouldSaveDraft);
    }
    updateGlobalSummaryStats();
}

function quickFillAll() {
    <?php if (!empty($items)): ?>
    <?php foreach ($items as $it): ?>
    const el_<?= $it['id'] ?> = document.getElementById('input_recv_<?= $it['id'] ?>');
    if (el_<?= $it['id'] ?>) el_<?= $it['id'] ?>.value = <?= (int)$it['quantity'] ?>;
    const dmg_<?= $it['id'] ?> = document.getElementById('input_dmg_<?= $it['id'] ?>');
    if (dmg_<?= $it['id'] ?>) dmg_<?= $it['id'] ?>.value = 0;
    updateMatrixRow(<?= $it['id'] ?>, false);
    <?php endforeach; ?>
    <?php endif; ?>
    saveInwardDraft();
}

function triggerScan() {
    const input = document.getElementById('liveScannerInput');
    if (!input) return;
    const rawVal = input.value.trim();
    if (!rawVal) return;
    const val = rawVal.toUpperCase();

    const isDamageMode = document.getElementById('modeDamage')?.checked;
    let matched = false;

    // Check matching piece tag code, QR payload, or SKU
    for (const [itemId, pieces] of Object.entries(ITEM_PIECES_MAP)) {
        for (let idx = 0; idx < pieces.length; idx++) {
            const p = pieces[idx];
            const tag = (p.tag_code || '').toUpperCase();
            const sku = (p.sku || '').toUpperCase();
            const payload = (p.qr_payload || '').toUpperCase();

            if (val === tag || val.includes(tag) || val === payload || val === sku || (sku.length > 5 && val.includes(sku))) {
                const recvInput = document.getElementById('input_recv_' + itemId);
                const dmgInput = document.getElementById('input_dmg_' + itemId);
                const maxQty = pieces.length;

                let curRecv = recvInput ? (parseInt(recvInput.value) || 0) : 0;
                let curDmg = dmgInput ? (parseInt(dmgInput.value) || 0) : 0;

                if (curRecv + curDmg < maxQty) {
                    if (isDamageMode) {
                        if (dmgInput) dmgInput.value = curDmg + 1;
                    } else {
                        if (recvInput) recvInput.value = curRecv + 1;
                    }

                    // Update piece status cache if we matched specific piece
                    if (!SAVED_PIECE_STATUSES[itemId]) {
                        SAVED_PIECE_STATUSES[itemId] = pieces.map((pc, i) => ({
                            num: pc.piece_no || (i + 1),
                            tag: pc.tag_code,
                            status: 'pending',
                            scannedAt: null
                        }));
                    }
                    const now = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    if (val === tag || val.includes(tag)) {
                        SAVED_PIECE_STATUSES[itemId][idx].status = isDamageMode ? 'damaged' : 'good';
                        SAVED_PIECE_STATUSES[itemId][idx].scannedAt = now;
                    } else {
                        const pendingPiece = SAVED_PIECE_STATUSES[itemId].find(pc => pc.status === 'pending');
                        if (pendingPiece) {
                            pendingPiece.status = isDamageMode ? 'damaged' : 'good';
                            pendingPiece.scannedAt = now;
                        }
                    }
                }
                matched = true;
                updateMatrixRow(itemId, true);
                break;
            }
        }
        if (matched) break;
    }

    if (!matched && (val === '<?= strtoupper($dispNum) ?>' || val.includes('ALC-') || val.includes('OUT-'))) {
        quickFillAll();
        matched = true;
    }

    input.value = '';
    input.focus();
}

document.addEventListener('DOMContentLoaded', function() {
    updateAllMatrixRows(false);
    loadInwardDraft();

    const scanInput = document.getElementById('liveScannerInput');
    if (scanInput) {
        scanInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                triggerScan();
            }
        });
    }

    const notesInput = document.querySelector('input[name="notes"]');
    if (notesInput) {
        notesInput.addEventListener('input', function() {
            saveInwardDraft();
        });
    }
});
</script>
