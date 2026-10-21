<?php
$canInward = hasPermission('shop_inward', 'verify') || hasPermission('shop_inward', 'receive') || hasPermission('shop_inward', 'create');
$canPrintInward = hasPermission('shop_inward', 'print');
?>
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-box-arrow-in-down me-2 text-primary"></i>Shop Stock Inward &amp; QR Receiving</h2>
        <span class="text-muted">Scan QR codes for instant shop inventory auto-inward or perform manual allocation cross-check</span>
    </div>
    <div class="d-flex gap-2">
        <?php if ($canInward): ?>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cameraScanModal" id="btnOpenCam">
                <i class="bi bi-camera me-1"></i> Camera Scanner
            </button>
        <?php endif; ?>
        <a href="<?= url('shop/inventory') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-shop me-1"></i> Shop Stock Ledger
        </a>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. SUMMARY METRIC CARDS -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Awaiting Shop Inward (Missing Stock)</span>
                        <h3 class="mb-0 fw-bold text-danger mt-1">
                            <?= number_format($pendingInwardsCount ?? 0) ?> <small class="fs-6 text-muted">Batches</small>
                        </h3>
                        <small class="text-danger fw-semibold mt-1 d-block">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= number_format($totalPendingMissingQty ?? 0) ?> pcs pending verification
                        </small>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Received &amp; Stock Credited</span>
                        <h3 class="mb-0 fw-bold text-success mt-1">
                            <?= number_format($receivedInwardsCount ?? 0) ?> <small class="fs-6 text-muted">Batches</small>
                        </h3>
                        <small class="text-success fw-semibold mt-1 d-block">
                            <i class="bi bi-check2-circle me-1"></i><?= number_format($totalReceivedQty ?? 0) ?> pcs in shop inventory
                        </small>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="bi bi-shield-check fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Inward Dispatches</span>
                        <h3 class="mb-0 fw-bold text-primary mt-1">
                            <?= number_format(count($allocations ?? [])) ?> <small class="fs-6 text-muted">Batches</small>
                        </h3>
                        <small class="text-muted mt-1 d-block">
                            <i class="bi bi-truck me-1"></i><?= number_format(($totalPendingMissingQty ?? 0) + ($totalReceivedQty ?? 0)) ?> total dispatched pcs
                        </small>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canInward): ?>
<!-- ========================================================================= -->
<!-- 2. LIVE QR CODE SCANNER BAR -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4 border-start border-primary border-4">
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-7">
                <label class="form-label fw-bold text-dark mb-1">
                    <i class="bi bi-qr-code-scan me-1 text-primary"></i> Scan Allocation / Outward / Piece QR Code
                </label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-primary"></i></span>
                    <input type="text" id="qrInput" class="form-control font-monospace" placeholder="Scan or type Allocation # (e.g. ALC-20260917-0006 or GIR092601)..." autofocus autocomplete="off">
                    <button class="btn btn-primary px-4 fw-semibold" type="button" id="btnScanSubmit">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Process QR
                    </button>
                </div>
                <small class="text-muted mt-1 d-block">
                    <i class="bi bi-info-circle me-1"></i> Handheld USB/Bluetooth barcode guns will automatically submit upon scan.
                </small>
            </div>
            <div class="col-lg-5">
                <div class="p-3 bg-light rounded border">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="autoReceiveSwitch" checked>
                        <label class="form-check-label fw-bold text-dark" for="autoReceiveSwitch">
                            ⚡ Auto-Inward on Scan
                        </label>
                    </div>
                    <small class="text-muted d-block" id="switchHelpText">
                        <strong class="text-success">ON:</strong> Automatically verifies allocation and credits shop inventory instantly upon scan.
                    </small>
                </div>
            </div>
        </div>

        <!-- Dynamic Scan Result Feedback Alert -->
        <div id="scanFeedback" class="mt-3 d-none"></div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- 3. ALLOCATION-WISE MISSING STOCK & PRODUCT VARIANTS SMALL CARDS UI -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4 border-start border-danger border-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h6 class="card-title mb-0 fw-bold text-danger">
                <i class="bi bi-grid-fill me-2"></i>Allocation-Wise Missing Stock &amp; Variant Overview
            </h6>
            <small class="text-muted">Quick-glance small cards showing missing stock count per allocation with variant details for easy identification</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="batchCardSearch" class="form-control" placeholder="Search variant / SKU...">
            </div>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary active btn-batch-filter" data-filter="all">All</button>
                <button type="button" class="btn btn-outline-danger btn-batch-filter" data-filter="missing">Missing Only (<?= $pendingInwardsCount ?? 0 ?>)</button>
                <button type="button" class="btn btn-outline-success btn-batch-filter" data-filter="stocked">Stocked (<?= $receivedInwardsCount ?? 0 ?>)</button>
            </div>
        </div>
    </div>
    <div class="card-body p-3 bg-light">
        <?php if (empty($allocations)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-1"></i> No allocation records available for this shop.
            </div>
        <?php else: ?>
            <div class="row g-3" id="batchCardsContainer">
                <?php foreach ($allocations as $a): ?>
                    <?php 
                        $isFullyReceived = in_array($a['status'], ['RECEIVED', 'COMPLETED']);
                        $isPartiallyReceived = ($a['status'] === 'PARTIAL_RECEIVED');
                        $isReceived = $isFullyReceived;
                        $totalCredited = (int)($a['total_received_items_qty'] ?? 0);
                        $missingQty = $isFullyReceived ? 0 : max(0, (int)$a['effective_qty'] - $totalCredited);
                        $itemsList = $a['items'] ?? [];
                        $skuSearchTerms = implode(' ', array_map(function($i) { return ($i['sku'] ?? '') . ' ' . ($i['size_name'] ?? '') . ' ' . ($i['color_name'] ?? '') . ' ' . ($i['product_name'] ?? ''); }, $itemsList));
                        $primaryProduct = $itemsList[0]['product_name'] ?? 'Assorted Apparels';
                    ?>
                    <div class="col-md-6 col-xl-4 batch-card-item" data-alloc-id="<?= (int)$a['id'] ?>" data-status="<?= $isFullyReceived ? 'stocked' : 'missing' ?>" data-search="<?= strtolower(e($a['allocation_number'] . ' ' . ($a['outward_number'] ?? '') . ' ' . $a['warehouse_name'] . ' ' . $skuSearchTerms)) ?>">
                        <div class="card border shadow-sm h-100 <?= $isFullyReceived ? 'border-success border-opacity-50' : ($isPartiallyReceived ? 'border-warning border-opacity-75' : 'border-danger border-opacity-50') ?>">
                            <!-- Card Header -->
                            <div class="card-header <?= $isFullyReceived ? 'bg-success bg-opacity-10 border-success border-opacity-25' : ($isPartiallyReceived ? 'bg-warning bg-opacity-15 border-warning border-opacity-25' : 'bg-danger bg-opacity-10 border-danger border-opacity-25') ?> py-2 px-3 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="fw-bold font-monospace text-dark fs-7"><?= e($a['allocation_number']) ?></span>
                                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none btn-show-qr ms-1" data-code="<?= e($a['allocation_number']) ?>" data-product="<?= e($primaryProduct) ?>" title="Show QR">
                                        <i class="bi bi-qr-code text-primary"></i>
                                    </button>
                                </div>
                                <?php if ($isFullyReceived): ?>
                                    <span class="badge bg-success rounded-pill px-2 py-1 fs-8">
                                        <i class="bi bi-check-circle-fill me-1"></i>Stock Credited
                                    </span>
                                <?php elseif ($isPartiallyReceived): ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-2 py-1 fs-8">
                                        <i class="bi bi-pie-chart me-1"></i>Partial (<?= $totalCredited ?> / <?= (int)$a['effective_qty'] ?> pcs)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-2 py-1 fs-8">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i><?= number_format($missingQty) ?> pcs Missing
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 text-truncate" style="max-width: 200px;" title="<?= e($primaryProduct) ?>">
                                            <?= e($primaryProduct) ?><?= count($itemsList) > 1 ? ' +' . (count($itemsList) - 1) . ' more' : '' ?>
                                        </h6>
                                        <small class="text-muted d-block">From: <span class="badge bg-light text-dark border"><?= e($a['warehouse_name']) ?></span></small>
                                    </div>
                                    <span class="badge bg-light text-dark border font-monospace fs-7">
                                        <?= number_format($a['effective_qty']) ?> pcs
                                    </span>
                                </div>

                                <!-- Variant Breakdown Box -->
                                <div class="p-2 rounded bg-light border mb-2" style="max-height: 120px; overflow-y: auto;">
                                    <small class="text-muted fw-semibold d-block mb-1 fs-8 text-uppercase">
                                        <i class="bi bi-tags-fill me-1 text-secondary"></i>Items Breakdown (<?= count($itemsList) ?>):
                                    </small>
                                    <?php if (!empty($itemsList)): ?>
                                        <?php foreach ($itemsList as $v): ?>
                                            <?php 
                                                $vDispatched = (int)($v['dispatched_qty'] > 0 ? $v['dispatched_qty'] : $v['requested_qty']);
                                                $vRecv = (int)($v['received_qty'] ?? 0);
                                                $vDam = (int)($v['damaged_qty'] ?? 0);
                                                $vDone = $isFullyReceived ? true : ($vRecv >= $vDispatched);
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center bg-white px-2 py-1 rounded border mb-1 fs-8">
                                                <span class="font-monospace text-truncate" style="max-width: 170px;" title="<?= e($v['sku']) ?>">
                                                    <strong><?= e($v['sku']) ?></strong> (<?= e($v['size_name'] ?? 'Std') ?>, <?= e($v['color_name'] ?? 'Std') ?>)
                                                </span>
                                                <span class="badge <?= $vDone ? 'bg-success-subtle text-success border border-success-subtle' : ($vRecv > 0 ? 'bg-warning-subtle text-dark border border-warning-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle') ?> font-monospace">
                                                    <?= $isFullyReceived ? $vDispatched . ' pcs' : ($vRecv > 0 ? "$vRecv / $vDispatched pcs" : "$vDispatched pcs") ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">No variant lines logged</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex justify-content-between align-items-center small text-muted">
                                    <span><i class="bi bi-box-seam me-1"></i><?= number_format($a['total_boxes'] ?? 1) ?> Cartons</span>
                                    <span><i class="bi bi-calendar3 me-1"></i><?= !empty($a['dispatch_date']) ? date('d M, h:i A', strtotime($a['dispatch_date'])) : 'Dispatched' ?></span>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center gap-1">
                                <?php if ($canPrintInward): ?>
                                    <a href="<?= url('shop/inward/label/' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Print Carton QR Label">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="<?= url('shop/inward/gatepass/' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-dark py-1 px-2" title="Print Delivery Gate Pass">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($isFullyReceived || !$canInward): ?>
                                    <a href="<?= url('shop/inward/view/' . $a['id']) ?>" class="btn btn-sm btn-outline-primary flex-grow-1 py-1">
                                        <i class="bi bi-eye me-1"></i> View Inward
                                    </a>
                                <?php elseif ($isPartiallyReceived): ?>
                                    <a href="<?= url('shop/inward/view/' . $a['id']) ?>" class="btn btn-sm btn-warning text-dark fw-semibold flex-grow-1 py-1">
                                        <i class="bi bi-qr-code-scan me-1"></i> Continue Inward (<?= $missingQty ?> missing)
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('shop/inward/view/' . $a['id']) ?>" class="btn btn-sm btn-danger fw-semibold flex-grow-1 py-1">
                                        <i class="bi bi-qr-code-scan me-1"></i> Cross-Check &amp; Inward
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="noBatchCardsMatch" class="text-center py-4 d-none text-muted">
                <i class="bi bi-search fs-3 d-block mb-1"></i> No matching allocation cards found for the selected filter or search term.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('shop/inward') ?>" class="row g-3" id="inwardFilterForm" onsubmit="return true;">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Search Filter</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="inwardSearchInput" class="form-control" 
                           placeholder="Search allocation, outward, vehicle, SKU, product..." 
                           value="<?= e($search ?? '') ?>" autocomplete="off">
                    <button type="button" id="btnClearInwardSearch" class="btn btn-outline-secondary <?= empty($search) ? 'd-none' : '' ?>" title="Clear Search" onclick="clearInwardSearch()"><i class="bi bi-x"></i></button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Source Warehouse</label>
                <select name="warehouse_id" id="inwardWarehouseSelect" class="form-select">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= $wh['id'] ?>" <?= ($warehouseId ?? '') == $wh['id'] ? 'selected' : '' ?>>
                            <?= e($wh['name']) ?><?= !empty($wh['code']) ? ' (' . e($wh['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Inward Status</label>
                <select name="status" id="inwardStatusSelect" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="DISPATCHED" <?= ($status ?? '') === 'DISPATCHED' ? 'selected' : '' ?>>DISPATCHED (Awaiting Inward)</option>
                    <option value="RECEIVED" <?= in_array(($status ?? ''), ['RECEIVED', 'COMPLETED', 'DELIVERED']) ? 'selected' : '' ?>>RECEIVED (Stock Credited)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= url('shop/inward') ?>" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Allocation Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="card-title mb-0 fw-bold text-dark"><i class="bi bi-table me-2 text-primary"></i>All Inward Allocations Detailed Ledger</h6>
        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 font-monospace" id="inwardTableCountBadge">
            <?= count($allocations ?? []) ?> Consignments
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($allocations)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                <h5 class="text-muted">No Inward Allocations Found</h5>
                <p class="text-muted small">Consignments dispatched from central warehouse will appear here ready to receive into shop inventory.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="inwardTable">
                    <thead class="table-light">
                        <tr>
                            <th>Allocation #</th>
                            <th>Source Warehouse</th>
                            <th>Items &amp; Products</th>
                            <th>Quantity &amp; Boxes</th>
                            <th>Dispatch Date &amp; Vehicle</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inwardTableBody">
                        <?php foreach ($allocations as $a): ?>
                            <?php 
                                $isRecv = in_array($a['status'], ['RECEIVED', 'COMPLETED', 'DELIVERED']); 
                                $itemsList = $a['items'] ?? [];
                                $skuSearchTerms = implode(' ', array_map(function($i) { return ($i['sku'] ?? '') . ' ' . ($i['size_name'] ?? '') . ' ' . ($i['color_name'] ?? '') . ' ' . ($i['product_name'] ?? ''); }, $itemsList));
                                $searchHaystack = strtolower(e($a['allocation_number'] . ' ' . ($a['outward_number'] ?? '') . ' ' . ($a['vehicle_number'] ?? '') . ' ' . $a['warehouse_name'] . ' ' . $skuSearchTerms));
                            ?>
                            <tr id="row-alloc-<?= $a['id'] ?>" 
                                class="inward-alloc-row" 
                                data-search="<?= $searchHaystack ?>"
                                data-warehouse="<?= (int)($a['warehouse_id'] ?? 0) ?>"
                                data-status="<?= $isRecv ? 'RECEIVED' : 'DISPATCHED' ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-light border p-1 btn-show-qr" data-code="<?= e($a['allocation_number']) ?>" data-product="<?= e($a['items'][0]['product_name'] ?? 'Stock Consignment') ?>" title="Show QR Code">
                                            <i class="bi bi-qr-code text-primary fs-5"></i>
                                        </button>
                                        <div>
                                            <span class="fw-bold font-monospace text-primary"><?= e($a['allocation_number']) ?></span>
                                            <?php if (!empty($a['outward_number'])): ?>
                                                <br><span class="badge bg-light text-dark border"><i class="bi bi-truck me-1"></i><?= e($a['outward_number']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e($a['warehouse_name']) ?> (<?= e($a['warehouse_code']) ?>)</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= e($a['items'][0]['product_name'] ?? 'Assorted Apparels') ?></span>
                                    <?php if (count($a['items']) > 1): ?>
                                        <br><small class="text-muted">+<?= count($a['items']) - 1 ?> other variant(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-success fs-6"><?= number_format($a['effective_qty']) ?> pcs</span>
                                    <br><span class="badge bg-light text-dark border"><?= number_format($a['total_boxes'] ?? 1) ?> Cartons</span>
                                </td>
                                <td>
                                    <?php if (!empty($a['dispatch_date'])): ?>
                                        <small class="text-muted d-block"><?= date('d M Y, h:i A', strtotime($a['dispatch_date'])) ?></small>
                                        <small class="text-secondary"><i class="bi bi-car-front me-1"></i><?= e($a['vehicle_number'] ?? 'Direct') ?></small>
                                    <?php else: ?>
                                        <small class="text-muted d-block"><?= date('d M Y', strtotime($a['created_at'])) ?></small>
                                        <span class="text-muted small">Standard Transport</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$isRecv): ?>
                                        <span class="badge bg-warning text-dark batch-status-badge"><i class="bi bi-hourglass-split me-1"></i>Awaiting Shop Inward</span>
                                    <?php else: ?>
                                        <span class="badge bg-success batch-status-badge"><i class="bi bi-check2-all me-1"></i>Received &amp; Stocked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($canPrintInward): ?>
                                            <a href="<?= url('shop/inward/label/' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Carton QR Label">
                                                <i class="bi bi-qr-code"></i>
                                            </a>
                                            <a href="<?= url('shop/inward/gatepass/' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Print Delivery Gate Pass">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= url('shop/inward/view/' . $a['id']) ?>" class="btn btn-sm <?= (!$isRecv && $canInward) ? 'btn-primary fw-semibold' : 'btn-outline-primary' ?>">
                                            <i class="bi <?= (!$isRecv && $canInward) ? 'bi-qr-code-scan' : 'bi-eye' ?> me-1"></i>
                                            <?= (!$isRecv && $canInward) ? 'Cross-Check & Inward' : 'View Inward' ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canInward): ?>
<!-- ========================================================================= -->
<!-- MODAL: CAMERA QR SCANNER -->
<!-- ========================================================================= -->
<div class="modal fade" id="cameraScanModal" tabindex="-1" aria-labelledby="cameraScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="cameraScanModalLabel">
                    <i class="bi bi-camera me-2 text-primary"></i> Live Camera QR Scanner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btnCloseCam"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="cameraReader" style="width: 100%; min-height: 250px; background:#000; border-radius: 8px;"></div>
                <div class="mt-3">
                    <small class="text-muted">Point your camera at the Allocation, Outward, or Piece QR code.</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL: SHOW QR CODE -->
<!-- ========================================================================= -->
<div class="modal fade" id="qrPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="qrModalTitle">Consignment QR Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="modalQrCode" class="d-flex justify-content-center mb-3"></div>
                <div id="qrModalCodeText" class="font-monospace fw-bold fs-6 text-primary"></div>
                <small id="qrModalProductText" class="text-muted d-block mt-1"></small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const qrInput = document.getElementById('qrInput');
    const btnSubmit = document.getElementById('btnScanSubmit');
    const autoSwitch = document.getElementById('autoReceiveSwitch');
    const switchHelp = document.getElementById('switchHelpText');
    const feedbackBox = document.getElementById('scanFeedback');
    let html5QrScanner = null;

    // Toggle switch text
    autoSwitch.addEventListener('change', function() {
        if (this.checked) {
            switchHelp.innerHTML = '<strong class="text-success">ON:</strong> Automatically verifies allocation and credits shop inventory instantly upon scan.';
        } else {
            switchHelp.innerHTML = '<strong class="text-warning text-dark">OFF (Manual Mode):</strong> Loads allocation inspection details for manual cross-check before stock update.';
        }
    });

    // Handle QR Input Enter key (for barcode/QR handheld guns)
    qrInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            processScannedCode(qrInput.value);
        }
    });

    btnSubmit.addEventListener('click', function() {
        processScannedCode(qrInput.value);
    });

    // Core function to process scanned code via AJAX
    function processScannedCode(code) {
        code = (code || '').trim();
        if (!code) {
            showFeedback('warning', 'Please enter or scan a valid QR code.');
            return;
        }

        const isAuto = autoSwitch.checked;
        showFeedback('info', '<div class="spinner-border spinner-border-sm me-2"></div> Processing QR Code: <strong>' + code + '</strong>...');

        const formData = new FormData();
        formData.append('qr_code', code);
        formData.append('auto_receive', isAuto ? '1' : '0');
        formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        fetch('<?= url("api/shop/inward/scan") ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                playAudioBeep(false);
                showFeedback('danger', '<i class="bi bi-exclamation-triangle-fill me-2"></i> ' + (data.message || 'Consignment not found.'));
                return;
            }

            playAudioBeep(true);
            const alloc = data.data.allocation;

            if (data.data.already_received) {
                showFeedback('warning', '<i class="bi bi-info-circle-fill me-2"></i> ' + data.message + ' <a href="' + data.data.viewUrl + '" class="btn btn-sm btn-outline-dark ms-2">View</a>');
            } else if (data.data.credited) {
                // Auto-received!
                showFeedback('success', '<i class="bi bi-check-circle-fill me-2 fs-5"></i> <strong>SUCCESS!</strong> ' + data.message + ' <a href="' + data.data.viewUrl + '" class="btn btn-sm btn-dark ms-2">View Inward</a>');
                // Update row in table if present
                const row = document.getElementById('row-alloc-' + alloc.id);
                if (row) {
                    const badge = row.querySelector('.batch-status-badge');
                    if (badge) {
                        badge.className = 'badge bg-success batch-status-badge';
                        badge.innerHTML = '<i class="bi bi-check2-all me-1"></i>Received & Stocked';
                    }
                }
            } else {
                // Manual mode: Redirect or prompt view
                showFeedback('info', '<i class="bi bi-eye-fill me-2"></i> Allocation <strong>' + alloc.allocation_number + '</strong> found. <a href="' + data.data.viewUrl + '" class="btn btn-sm btn-success ms-2">Inspect & Inward Now &rarr;</a>');
                setTimeout(() => {
                    window.location.href = data.data.viewUrl;
                }, 800);
            }

            qrInput.value = '';
            qrInput.focus();
        })
        .catch(err => {
            playAudioBeep(false);
            showFeedback('danger', 'Server communication error: ' + err.message);
        });
    }

    function showFeedback(type, html) {
        feedbackBox.className = 'mt-3 alert alert-' + type + ' d-flex align-items-center justify-content-between';
        feedbackBox.innerHTML = '<div>' + html + '</div><button type="button" class="btn-close" onclick="this.parentElement.classList.add(\'d-none\')"></button>';
        feedbackBox.classList.remove('d-none');
    }

    // Audio Beep generator using Web Audio API
    function playAudioBeep(isSuccess) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = isSuccess ? 880 : 330;
            gain.gain.value = 0.15;
            osc.start();
            setTimeout(() => { osc.stop(); ctx.close(); }, isSuccess ? 120 : 300);
        } catch(e) {}
    }

    // Camera QR Scanner Modal
    const camModal = document.getElementById('cameraScanModal');
    camModal.addEventListener('shown.bs.modal', function() {
        if (!html5QrScanner && typeof Html5QrcodeScanner !== 'undefined') {
            html5QrScanner = new Html5QrcodeScanner("cameraReader", { fps: 10, qrbox: 250 });
            html5QrScanner.render(function(decodedText) {
                bootstrap.Modal.getInstance(camModal).hide();
                processScannedCode(decodedText);
            }, function(err) {});
        }
    });

    camModal.addEventListener('hidden.bs.modal', function() {
        if (html5QrScanner) {
            try { html5QrScanner.clear(); } catch(e){}
            html5QrScanner = null;
        }
    });

    // View QR Modal
    document.querySelectorAll('.btn-show-qr').forEach(btn => {
        btn.addEventListener('click', function() {
            const code = this.dataset.code;
            const product = this.dataset.product;
            const modalEl = document.getElementById('qrPreviewModal');
            document.getElementById('modalQrCode').innerHTML = '';
            document.getElementById('qrModalCodeText').textContent = code;
            document.getElementById('qrModalProductText').textContent = product;
            new QRCode(document.getElementById("modalQrCode"), {
                text: code,
                width: 160,
                height: 160
            });
            new bootstrap.Modal(modalEl).show();
        });
    });

    // Interactive Allocation Cards Filter & Search
    const batchFilterBtns = document.querySelectorAll('.btn-batch-filter');
    const batchSearchInput = document.getElementById('batchCardSearch');
    const batchCardItems = document.querySelectorAll('.batch-card-item');
    const noBatchMatch = document.getElementById('noBatchCardsMatch');

    let currentFilter = 'all';

    function filterBatchCards() {
        const query = (batchSearchInput ? batchSearchInput.value : '').toLowerCase().trim();
        let visibleCount = 0;

        batchCardItems.forEach(card => {
            const status = card.getAttribute('data-status');
            const searchData = card.getAttribute('data-search') || '';

            const matchesStatus = (currentFilter === 'all') || (currentFilter === status);
            const matchesSearch = !query || searchData.includes(query);

            if (matchesStatus && matchesSearch) {
                card.classList.remove('d-none');
                visibleCount++;
            } else {
                card.classList.add('d-none');
            }
        });

        if (noBatchMatch) {
            if (visibleCount === 0 && batchCardItems.length > 0) {
                noBatchMatch.classList.remove('d-none');
            } else {
                noBatchMatch.classList.add('d-none');
            }
        }
    }

    if (batchFilterBtns.length > 0) {
        batchFilterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                batchFilterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter');
                filterBatchCards();
            });
        });
    }

    if (batchSearchInput) {
        batchSearchInput.addEventListener('input', filterBatchCards);
        batchSearchInput.addEventListener('keyup', filterBatchCards);
    }

    // Instant Real-Time Letter-by-Letter Table Filter Engine for Allocation Ledger
    const inwardSearchInput = document.getElementById('inwardSearchInput');
    const inwardWhSelect = document.getElementById('inwardWarehouseSelect');
    const inwardStatusSelect = document.getElementById('inwardStatusSelect');
    const inwardTableBody = document.getElementById('inwardTableBody');
    const inwardCountBadge = document.getElementById('inwardTableCountBadge');
    const btnClearInward = document.getElementById('btnClearInwardSearch');
    const allocRows = Array.from(document.querySelectorAll('.inward-alloc-row'));

    window.clearInwardSearch = function() {
        if (inwardSearchInput) {
            inwardSearchInput.value = '';
            if (btnClearInward) btnClearInward.classList.add('d-none');
            if (window.applyInwardFilters) window.applyInwardFilters();
        }
    };

    window.applyInwardFilters = function() {
        if (!allocRows.length) return;

        const query = (inwardSearchInput ? inwardSearchInput.value : '').trim().toLowerCase();
        const whId = inwardWhSelect ? inwardWhSelect.value : '';
        const statusVal = inwardStatusSelect ? inwardStatusSelect.value : '';

        if (btnClearInward) {
            if (query) btnClearInward.classList.remove('d-none');
            else btnClearInward.classList.add('d-none');
        }

        let visibleCount = 0;

        allocRows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const rowWh = row.getAttribute('data-warehouse') || '';
            const rowStatus = row.getAttribute('data-status') || '';

            const matchQuery = !query || searchData.includes(query);
            const matchWh = !whId || rowWh === whId;
            const matchStatus = !statusVal || (statusVal === 'DISPATCHED' && rowStatus === 'DISPATCHED') || (statusVal === 'RECEIVED' && rowStatus === 'RECEIVED');

            if (matchQuery && matchWh && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (inwardCountBadge) {
            inwardCountBadge.textContent = `${visibleCount} Consignments`;
        }

        let emptyRow = document.getElementById('noInwardMatchRow');
        if (visibleCount === 0) {
            if (!emptyRow && inwardTableBody) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'noInwardMatchRow';
                emptyRow.innerHTML = `
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-search fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        <h6 class="fw-bold text-secondary">No Matching Inward Allocations Found</h6>
                        <p class="small text-muted mb-2">No consignments match your live search criteria.</p>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="clearInwardSearch()">
                            Clear Search
                        </button>
                    </td>
                `;
                inwardTableBody.appendChild(emptyRow);
            }
        } else if (emptyRow) {
            emptyRow.remove();
        }
    };

    if (inwardSearchInput) {
        inwardSearchInput.addEventListener('input', applyInwardFilters);
        inwardSearchInput.addEventListener('keyup', applyInwardFilters);
    }
    if (inwardWhSelect) {
        inwardWhSelect.addEventListener('change', applyInwardFilters);
    }
    if (inwardStatusSelect) {
        inwardStatusSelect.addEventListener('change', applyInwardFilters);
    }

    // Check localStorage for any in-progress scan drafts and badge the cards
    try {
        document.querySelectorAll('.batch-card-item[data-alloc-id]').forEach(card => {
            const allocId = card.getAttribute('data-alloc-id');
            const status = card.getAttribute('data-status');
            if (status === 'missing' && allocId) {
                const raw = localStorage.getItem('grm_shop_inward_draft_' + allocId);
                if (raw) {
                    const draft = JSON.parse(raw);
                    let scannedCount = 0;
                    if (draft.receivedMap) {
                        Object.values(draft.receivedMap).forEach(v => scannedCount += (parseInt(v) || 0));
                    }
                    if (scannedCount > 0) {
                        const btn = card.querySelector('a[href*="shop/inward/view/"]');
                        if (btn) {
                            btn.className = 'btn btn-sm btn-warning text-dark fw-bold flex-grow-1 py-1 shadow-xs';
                            btn.innerHTML = `<i class="bi bi-clock-history me-1"></i>Resume Cross-Check (${scannedCount} scanned)`;
                        }
                        const header = card.querySelector('.card-header');
                        if (header) {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-warning text-dark font-monospace px-2 py-1 fs-8 ms-1';
                            badge.innerHTML = `<i class="bi bi-pencil-square me-1"></i>Draft: ${scannedCount} pcs`;
                            header.appendChild(badge);
                        }
                    }
                }
            }
        });
    } catch(e) {
        console.warn("Draft indicator check:", e);
    }
});
</script>
