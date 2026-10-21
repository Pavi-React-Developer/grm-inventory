<?php
// Production Expenses & Assets Management View (Unit 1 & Unit 2)
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-wallet2 text-primary me-2"></i>Production Expenses &amp; Assets Management
            </h4>
            <span class="text-muted small">Maintain machinery assets, sewing consumables (threads, zips, needles), machine repairs, and costs for Production Unit 1 &amp; Unit 2</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('production/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Production Dashboard
            </a>
            <?php if (hasPermission('production_expenses', 'create')): ?>
            <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#recordExpenseModal">
                <i class="bi bi-plus-circle me-1"></i> Record Production Expense / Asset
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Navigation / Segment Tabs -->
    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted small fw-bold ms-2 me-1 text-uppercase"><i class="bi bi-funnel me-1"></i>Quick View:</span>
                
                <a href="<?= url('production/expenses') ?>" class="btn btn-sm <?= (empty($productionUnit) && empty($category)) ? 'btn-dark shadow-sm' : 'btn-outline-secondary bg-white' ?>">
                    <i class="bi bi-grid me-1"></i>All Expenses
                </a>

                <a href="<?= url('production/expenses?production_unit=PRODUCTION_1') ?>" class="btn btn-sm <?= $productionUnit === 'PRODUCTION_1' ? 'btn-primary shadow-sm' : 'btn-outline-primary bg-white' ?>">
                    <i class="bi bi-building me-1"></i>🏭 Production Unit 1
                </a>

                <a href="<?= url('production/expenses?production_unit=PRODUCTION_2') ?>" class="btn btn-sm <?= $productionUnit === 'PRODUCTION_2' ? 'btn-info text-white shadow-sm' : 'btn-outline-info bg-white' ?>">
                    <i class="bi bi-buildings me-1"></i>🏭 Production Unit 2
                </a>

                <a href="<?= url('production/expenses?category=MACHINERY_ASSET') ?>" class="btn btn-sm <?= $category === 'MACHINERY_ASSET' ? 'btn-warning text-dark shadow-sm' : 'btn-outline-warning text-dark bg-white' ?>">
                    <i class="bi bi-gear-wide-connected me-1"></i>⚙️ Machinery &amp; Equipment
                </a>

                <a href="<?= url('production/expenses?category=SEWING_MATERIALS') ?>" class="btn btn-sm <?= $category === 'SEWING_MATERIALS' ? 'btn-success shadow-sm' : 'btn-outline-success bg-white' ?>">
                    <i class="bi bi-thread me-1"></i>🧵 Sewing Materials (Threads, Zips, Needles)
                </a>

                <a href="<?= url('production/expenses?category=MAINTENANCE_REPAIR') ?>" class="btn btn-sm <?= $category === 'MAINTENANCE_REPAIR' ? 'btn-danger shadow-sm' : 'btn-outline-danger bg-white' ?>">
                    <i class="bi bi-tools me-1"></i>🔧 Maintenance &amp; Servicing
                </a>
            </div>
        </div>
    </div>

    <!-- KPI Summary Analytics Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Production Expense Spend -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Production Spend</span>
                        <div class="p-2 rounded-circle bg-primary-subtle text-primary">
                            <i class="bi bi-currency-rupee fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-primary mb-1">₹ <?= number_format($stats['total_cost'], 2) ?></h3>
                    <div class="small text-muted d-flex justify-content-between align-items-center">
                        <span><?= number_format($stats['total_records']) ?> Transaction(s)</span>
                        <span class="badge bg-light text-dark border">All Units Combined</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Unit 1 vs Unit 2 Spend -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Unit 1 &amp; Unit 2 Costs</span>
                        <div class="p-2 rounded-circle bg-info-subtle text-info">
                            <i class="bi bi-diagram-2 fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div>
                            <span class="badge bg-primary text-white me-1">Unit 1</span>
                            <strong class="text-dark">₹ <?= number_format($stats['unit1_cost'], 2) ?></strong>
                        </div>
                        <div>
                            <span class="badge bg-info text-white me-1">Unit 2</span>
                            <strong class="text-dark">₹ <?= number_format($stats['unit2_cost'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span>Common/Facility: ₹<?= number_format($stats['both_units_cost'], 2) ?></span>
                        <span class="badge bg-info-subtle text-info border">By Unit</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Machinery vs Consumables (Threads/Zips/Needles) -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Machinery &amp; Materials</span>
                        <div class="p-2 rounded-circle bg-success-subtle text-success">
                            <i class="bi bi-scissors fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div>
                            <small class="text-muted d-block">Machinery Assets</small>
                            <strong class="text-warning text-dark">₹<?= number_format($stats['machinery_cost'], 2) ?></strong>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Threads / Zips / Needles</small>
                            <strong class="text-success">₹<?= number_format($stats['sewing_materials_cost'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span>Repairs: ₹<?= number_format($stats['maintenance_cost'], 2) ?></span>
                        <span class="badge bg-success-subtle text-success border">Assets &amp; Trims</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Paid vs Pending Payables -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Settlement &amp; Payables</span>
                        <div class="p-2 rounded-circle bg-danger-subtle text-danger">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div>
                            <small class="text-muted d-block">Paid Settled</small>
                            <strong class="text-success">₹<?= number_format($stats['total_paid'], 2) ?></strong>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Vendor Due</small>
                            <strong class="text-danger">₹<?= number_format($stats['total_pending'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span><?= number_format($stats['count_pending'] + $stats['count_partial']) ?> Unsettled Bills</span>
                        <span class="badge bg-danger-subtle text-danger border">Payables</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('production/expenses') ?>" class="row g-2 align-items-center">
                <!-- Search -->
                <div class="col-lg-3 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="expensesSearchInput" class="form-control form-control-sm border-start-0" placeholder="Search item, vendor, invoice, serial..." value="<?= e($search) ?>" autocomplete="off">
                    </div>
                </div>

                <!-- Unit Filter -->
                <div class="col-lg-2 col-md-3">
                    <select name="production_unit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Production Units --</option>
                        <option value="PRODUCTION_1" <?= $productionUnit === 'PRODUCTION_1' ? 'selected' : '' ?>>Production Unit 1</option>
                        <option value="PRODUCTION_2" <?= $productionUnit === 'PRODUCTION_2' ? 'selected' : '' ?>>Production Unit 2</option>
                        <option value="BOTH_UNITS" <?= $productionUnit === 'BOTH_UNITS' ? 'selected' : '' ?>>Both Units / Common</option>
                        <option value="GENERAL" <?= $productionUnit === 'GENERAL' ? 'selected' : '' ?>>General Facility</option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div class="col-lg-2 col-md-3">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Categories --</option>
                        <option value="MACHINERY_ASSET" <?= $category === 'MACHINERY_ASSET' ? 'selected' : '' ?>>Machinery &amp; Equipment</option>
                        <option value="SEWING_MATERIALS" <?= $category === 'SEWING_MATERIALS' ? 'selected' : '' ?>>Sewing Materials &amp; Trims</option>
                        <option value="MAINTENANCE_REPAIR" <?= $category === 'MAINTENANCE_REPAIR' ? 'selected' : '' ?>>Maintenance &amp; Repairs</option>
                        <option value="OPERATIONAL_UTILITY" <?= $category === 'OPERATIONAL_UTILITY' ? 'selected' : '' ?>>Operations &amp; Utilities</option>
                        <option value="OTHER" <?= $category === 'OTHER' ? 'selected' : '' ?>>Other Expenses</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div class="col-lg-2 col-md-3">
                    <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Payment Statuses --</option>
                        <option value="PAID" <?= $paymentStatus === 'PAID' ? 'selected' : '' ?>>Paid</option>
                        <option value="PARTIAL" <?= $paymentStatus === 'PARTIAL' ? 'selected' : '' ?>>Partial</option>
                        <option value="PENDING" <?= $paymentStatus === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="col-lg-1 col-md-3">
                    <input type="date" name="date_from" class="form-control form-control-sm" title="From Date" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
                </div>
                <div class="col-lg-1 col-md-3">
                    <input type="date" name="date_to" class="form-control form-control-sm" title="To Date" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
                </div>

                <!-- Reset -->
                <div class="col-lg-1 col-md-2 text-end">
                    <a href="<?= url('production/expenses') ?>" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses & Assets Table Log -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-card-list text-primary me-2"></i>Production Expenses &amp; Assets Log (<?= count($expenses) ?>)
            </h6>
            <span class="text-muted small">Unit 1 &amp; Unit 2 asset tracking, material purchases, and cost management</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($expenses)): ?>
                <div class="text-center py-5 text-muted">
                    <div class="d-inline-flex p-3 rounded-circle bg-light mb-2">
                        <i class="bi bi-inbox fs-1 text-secondary"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No Production Expenses or Assets Found</h5>
                    <p class="text-muted small mb-0">No records match your filters. Click "Record Production Expense / Asset" to add a new machinery, material, or maintenance record.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="expensesTable" style="font-size: 13px;">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Expense # &amp; Date</th>
                                <th>Unit &amp; Category</th>
                                <th>Item Name &amp; Serial / Tag</th>
                                <th>Vendor / Supplier</th>
                                <th class="text-end">Quantity &amp; Rate</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-center">Payment Status</th>
                                <th class="text-center">Bill / Photo</th>
                                <th class="text-center" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                                <tr class="expense-row">
                                    <!-- Expense # & Date -->
                                    <td>
                                        <span class="badge bg-dark font-monospace fs-7 px-2 py-1"><?= e($e['expense_number']) ?></span>
                                        <div class="text-muted small mt-1">
                                            <i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($e['expense_date'])) ?>
                                        </div>
                                        <?php if (!empty($e['invoice_number'])): ?>
                                            <small class="text-muted d-block">Bill #: <?= e($e['invoice_number']) ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Unit & Category -->
                                    <td>
                                        <!-- Unit Badge -->
                                        <?php
                                        $unitBadge = match($e['production_unit']) {
                                            'PRODUCTION_1' => 'bg-primary text-white',
                                            'PRODUCTION_2' => 'bg-info text-white',
                                            'BOTH_UNITS'   => 'bg-dark text-white',
                                            default        => 'bg-secondary text-white'
                                        };
                                        $unitLabel = match($e['production_unit']) {
                                            'PRODUCTION_1' => 'Production Unit 1',
                                            'PRODUCTION_2' => 'Production Unit 2',
                                            'BOTH_UNITS'   => 'Both Units / Common',
                                            default        => 'General Facility'
                                        };
                                        ?>
                                        <span class="badge <?= $unitBadge ?> px-2 py-1 mb-1 d-inline-block">
                                            <i class="bi bi-building me-1"></i><?= $unitLabel ?>
                                        </span>

                                        <!-- Category Badge -->
                                        <div>
                                            <?php
                                            $catBadge = match($e['category']) {
                                                'MACHINERY_ASSET'    => 'bg-warning-subtle text-dark border-warning-subtle',
                                                'SEWING_MATERIALS'   => 'bg-success-subtle text-success border-success-subtle',
                                                'MAINTENANCE_REPAIR' => 'bg-danger-subtle text-danger border-danger-subtle',
                                                'OPERATIONAL_UTILITY'=> 'bg-primary-subtle text-primary border-primary-subtle',
                                                default              => 'bg-secondary-subtle text-secondary border-secondary-subtle'
                                            };
                                            $catIcon = match($e['category']) {
                                                'MACHINERY_ASSET'    => 'bi-gear-wide-connected',
                                                'SEWING_MATERIALS'   => 'bi-thread',
                                                'MAINTENANCE_REPAIR' => 'bi-tools',
                                                'OPERATIONAL_UTILITY'=> 'bi-lightning-charge',
                                                default              => 'bi-tag'
                                            };
                                            $catLabel = str_replace('_', ' ', $e['category']);
                                            ?>
                                            <span class="badge <?= $catBadge ?> border small fw-semibold">
                                                <i class="bi <?= $catIcon ?> me-1"></i><?= e($catLabel) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Item Name & Serial / Tag -->
                                    <td>
                                        <strong class="text-dark d-block"><?= e($e['item_name']) ?></strong>
                                        <?php if (!empty($e['asset_tag_or_serial'])): ?>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-upc-scan me-1"></i>Serial: <span class="font-monospace text-dark"><?= e($e['asset_tag_or_serial']) ?></span>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($e['program_number'])): ?>
                                            <small class="text-primary d-block">
                                                <i class="bi bi-diagram-3 me-1"></i>Program: <?= e($e['program_number']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Vendor Details -->
                                    <td>
                                        <strong class="text-dark d-block"><?= e($e['vendor_name']) ?></strong>
                                        <?php if (!empty($e['vendor_company'])): ?>
                                            <span class="text-muted small d-block"><i class="bi bi-building me-1"></i><?= e($e['vendor_company']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($e['vendor_phone'])): ?>
                                            <span class="text-muted small d-block"><i class="bi bi-telephone me-1"></i><?= e($e['vendor_phone']) ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Quantity & Rate -->
                                    <td class="text-end">
                                        <div class="fw-bold text-dark fs-7"><?= number_format($e['quantity'], 2) ?> <?= e($e['unit_type']) ?></div>
                                        <small class="text-muted">@ ₹<?= number_format($e['unit_cost'], 2) ?> / <?= e($e['unit_type']) ?></small>
                                    </td>

                                    <!-- Total Cost -->
                                    <td class="text-end">
                                        <div class="fw-bold text-primary fs-6">₹ <?= number_format($e['total_cost'], 2) ?></div>
                                        <?php if ($e['paid_amount'] > 0 && $e['paid_amount'] < $e['total_cost']): ?>
                                            <small class="text-muted d-block">Paid: ₹<?= number_format($e['paid_amount'], 2) ?></small>
                                            <small class="text-danger fw-semibold d-block">Due: ₹<?= number_format($e['total_cost'] - $e['paid_amount'], 2) ?></small>
                                        <?php elseif ($e['paid_amount'] >= $e['total_cost']): ?>
                                            <small class="text-success fw-semibold"><i class="bi bi-check-all me-1"></i>Settled</small>
                                        <?php else: ?>
                                            <small class="text-warning fw-semibold">Unpaid</small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Payment Status -->
                                    <td class="text-center">
                                        <?php
                                        $payBadge = match($e['payment_status']) {
                                            'PAID'    => 'bg-success text-white',
                                            'PARTIAL' => 'bg-warning text-dark',
                                            default   => 'bg-danger text-white'
                                        };
                                        ?>
                                        <span class="badge <?= $payBadge ?> px-2 py-1"><?= e($e['payment_status']) ?></span>
                                        <div class="small text-muted mt-1"><?= e($e['payment_method']) ?></div>
                                    </td>

                                    <!-- Proof Photo / Bill -->
                                    <td class="text-center">
                                        <?php if (!empty($e['photo_url'])): ?>
                                            <a href="<?= uploadUrl($e['photo_url']) ?>" target="_blank" title="View bill slip or asset photo">
                                                <img src="<?= uploadUrl($e['photo_url']) ?>" alt="Proof" class="rounded border shadow-sm" style="width: 36px; height: 36px; object-fit: cover;">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Print Voucher Button -->
                                            <button type="button" class="btn btn-outline-secondary" title="View & Print Expense Voucher" onclick='openExpenseReceipt(<?= (int)$e['id'] ?>)'>
                                                <i class="bi bi-printer"></i>
                                            </button>

                                            <?php if (hasPermission('production_expenses', 'edit')): ?>
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-outline-primary" title="Edit Expense / Asset Details" onclick='openEditExpenseModal(<?= json_encode($e) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>

                                            <?php if (hasPermission('production_expenses', 'delete')): ?>
                                            <!-- Delete Button -->
                                            <form method="POST" action="<?= url('production/expenses/delete/' . $e['id']) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete Expense #<?= e(addslashes($e['expense_number'])) ?> (<?= e(addslashes($e['item_name'])) ?>)?');">
                                                <?= CSRF::field() ?>
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Expense Record">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Controls Footer -->
                <div class="card-footer bg-white py-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-3" id="expensesPaginationFooter">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="small text-muted fw-medium" id="expensesPageInfo">
                            Showing 1 to 10 of <?= count($expenses) ?> expenses
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0 fw-medium">Show:</label>
                            <select id="expensesPageSizeSelect" class="form-select form-select-sm" style="width: auto;">
                                <option value="10" selected>10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <nav aria-label="Expenses Pagination">
                        <ul class="pagination pagination-modern mb-0" id="expensesPaginationList">
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. RECORD EXPENSE / ASSET MODAL                                           -->
<!-- ========================================================================= -->
<div class="modal fade" id="recordExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-primary">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-plus-circle me-2"></i>Record Production Expense / Asset Purchase
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('production/expenses/store') ?>" enctype="multipart/form-data" id="recordExpenseForm">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <!-- Quick Presets -->
                    <div class="mb-3 p-2 bg-light rounded border">
                        <span class="small fw-bold text-muted d-block mb-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Quick Preset Items (Click to fill):</span>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('⚙️ Juki Stitching Machine DDL-8700', 'MACHINERY_ASSET', 'PCS', 'Fixed Asset Machinery', 'PRODUCTION_1')">⚙️ Stitching Machine</button>
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('🔧 Pegasus Overlock Machine 4-Thread', 'MACHINERY_ASSET', 'PCS', 'Fixed Asset Machinery', 'PRODUCTION_1')">🔧 Overlock Machine</button>
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('🧵 Sewing Thread Cones (40/2 White & Black)', 'SEWING_MATERIALS', 'CONES', 'Sewing Consumable', 'PRODUCTION_1')">🧵 Sewing Threads</button>
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('🤐 YKK Nylon / Metal Zippers (7 & 8 Inch)', 'SEWING_MATERIALS', 'PCS', 'Garment Trims', 'PRODUCTION_1')">🤐 Zips / Zippers</button>
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('🪡 Organ Sewing Needles (DBx1 #14 / #16)', 'SEWING_MATERIALS', 'PACKETS', 'Consumable Tool', 'PRODUCTION_1')">🪡 Sewing Needles</button>
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('🛢️ Machine Oil & Motor Servicing', 'MAINTENANCE_REPAIR', 'LITERS', 'Maintenance & Service', 'PRODUCTION_1')">🛢️ Machine Oil &amp; Service</button>
                            <button type="button" class="btn btn-xs btn-outline-dark bg-white" onclick="applyNewPreset('🔘 Polyester Buttons & Elastic Trims', 'SEWING_MATERIALS', 'BOXES', 'Garment Trims', 'PRODUCTION_1')">🔘 Buttons &amp; Elastic</button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Production Unit Selection -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">
                                <i class="bi bi-building text-primary me-1"></i>Production Unit
                            </label>
                            <select name="production_unit" id="newProductionUnit" class="form-select fw-semibold" required>
                                <option value="PRODUCTION_1" selected>🏭 Production Unit 1</option>
                                <option value="PRODUCTION_2">🏭 Production Unit 2</option>
                                <option value="BOTH_UNITS">🏭 Both Units / Common Facility</option>
                                <option value="GENERAL">🏢 General Production Operations</option>
                            </select>
                        </div>

                        <!-- Expense Category -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">Expense / Asset Category</label>
                            <select name="category" id="newCategory" class="form-select fw-semibold" required>
                                <option value="SEWING_MATERIALS" selected>🧵 Sewing Materials (Threads, Zips, Needles, Trims)</option>
                                <option value="MACHINERY_ASSET">⚙️ Machinery &amp; Equipment (Stitching Machines, etc.)</option>
                                <option value="MAINTENANCE_REPAIR">🔧 Maintenance, Servicing &amp; Machine Oil</option>
                                <option value="OPERATIONAL_UTILITY">⚡ Operating Utilities (Electricity, Labor, Packaging)</option>
                                <option value="OTHER">📦 Other Production Expenses</option>
                            </select>
                        </div>

                        <!-- Item Name -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold required">Item / Expense / Asset Name</label>
                            <input type="text" name="item_name" id="newItemName" class="form-control" placeholder="e.g. Juki Stitching Machine DDL-8700 / Sewing Thread Cones" required>
                        </div>

                        <!-- Asset Tag / Serial # -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Asset Tag / Machine Serial #</label>
                            <input type="text" name="asset_tag_or_serial" id="newAssetTag" class="form-control" placeholder="e.g. MC-STCH-001 / JUK-9812">
                        </div>

                        <!-- Price & Quantity Calculation Box (Manual Entry with Live Two-Way Calculation) -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded border border-primary-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted fw-bold text-uppercase">
                                        <i class="bi bi-calculator me-1 text-primary"></i>Quantity &amp; Cost Calculation (Directly Editable)
                                    </span>
                                    <span class="badge bg-white text-secondary border small">Type Rate or Total Price directly</span>
                                </div>
                                <div class="row g-3">
                                    <!-- Unit Type -->
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold required text-dark">Unit Type</label>
                                        <select name="unit_type" id="newUnitType" class="form-select fw-semibold" onchange="updateNewExpenseUnitLabels()">
                                            <option value="PCS" selected>Pcs (Pieces)</option>
                                            <option value="CONES">Cones (Thread)</option>
                                            <option value="BOXES">Boxes</option>
                                            <option value="PACKETS">Packets</option>
                                            <option value="METERS">Meters</option>
                                            <option value="LITERS">Liters (Oil)</option>
                                            <option value="SET">Set / Kit</option>
                                            <option value="UNITS">Units</option>
                                        </select>
                                    </div>

                                    <!-- Quantity -->
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold required text-dark">Quantity</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0.01" name="quantity" id="newQuantity" class="form-control fw-bold fs-6" value="1.00" required oninput="onNewQuantityChange()">
                                            <span class="input-group-text bg-white" id="newUnitLabel">pcs</span>
                                        </div>
                                    </div>

                                    <!-- Rate / Cost per Unit -->
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold required text-dark">Rate per Unit (<span id="newRateLabel">₹ / pcs</span>)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white fw-bold">₹</span>
                                            <input type="number" step="0.01" min="0" name="unit_cost" id="newUnitCost" class="form-control fw-bold text-primary fs-6" placeholder="0.00" required oninput="onNewUnitCostChange()">
                                        </div>
                                    </div>

                                    <!-- Total Cost (Directly Editable) -->
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-primary">Total Expense Cost (₹) <span class="badge bg-primary-subtle text-primary border ms-1">Editable</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary-subtle text-primary fw-bold">₹</span>
                                            <input type="number" step="0.01" min="0" name="total_cost" id="newTotalCost" class="form-control fw-bold text-primary fs-6" placeholder="0.00" required oninput="onNewTotalCostChange()">
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2" id="newCalcPreview">Calculated: 1.00 PCS × ₹0.00 = ₹0.00</small>
                            </div>
                        </div>

                        <!-- Vendor / Supplier Name -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold required">Supplier / Vendor / Technician</label>
                            <input type="text" name="vendor_name" id="newVendorName" class="form-control" placeholder="e.g. S.K. Sewing Machinery & Spares" required>
                        </div>

                        <!-- Vendor Phone -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor Phone</label>
                            <input type="text" name="vendor_phone" id="newVendorPhone" class="form-control" placeholder="e.g. +91 98450 12345">
                        </div>

                        <!-- Vendor Company -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor Company Name</label>
                            <input type="text" name="vendor_company" id="newVendorCompany" class="form-control" placeholder="e.g. Tirupur Industrial Trims Pvt Ltd">
                        </div>

                        <!-- Payment Information -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Amount Paid (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">₹</span>
                                <input type="number" step="0.01" min="0" name="paid_amount" id="newPaidAmount" class="form-control" placeholder="0.00" oninput="autoCheckNewExpensePaymentStatus()">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Status</label>
                            <select name="payment_status" id="newPaymentStatus" class="form-select">
                                <option value="PENDING">Pending (Unpaid)</option>
                                <option value="PARTIAL">Partial Payment</option>
                                <option value="PAID">Paid in Full</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" id="newPaymentMethod" class="form-select">
                                <option value="CASH">Cash</option>
                                <option value="UPI">UPI / GPay / PhonePe</option>
                                <option value="BANK_TRANSFER">Bank Transfer / NEFT</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="CREDIT">Credit Note / Supplier Account</option>
                            </select>
                        </div>

                        <!-- Date & References -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold required">Expense / Purchase Date</label>
                            <input type="date" name="expense_date" id="newExpenseDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Bill / Invoice #</label>
                            <input type="text" name="invoice_number" id="newInvoiceNo" class="form-control" placeholder="e.g. INV-9821">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Ref / Txn ID</label>
                            <input type="text" name="payment_reference" id="newPaymentRef" class="form-control" placeholder="e.g. UPI-776655">
                        </div>

                        <!-- Linked Program (Optional) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Link Production Program <small class="text-muted">(Optional)</small></label>
                            <select name="program_id" id="newProgramId" class="form-select">
                                <option value="">-- General Factory Expense / No Specific Program --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= e($p['program_number']) ?> (<?= e($p['name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Bill / Asset Photo Upload -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bill Receipt / Warranty / Asset Photo</label>
                            <input type="file" name="photo" id="newPhoto" class="form-control" accept="image/*,application/pdf">
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes &amp; Asset Location / Maintenance Details</label>
                            <textarea name="notes" id="newNotes" class="form-control" rows="2" placeholder="e.g. Installed at Stitching Line 2 in Unit 1, 1-year motor warranty included..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> Save Production Expense / Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. EDIT EXPENSE / ASSET MODAL                                             -->
<!-- ========================================================================= -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-primary">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-pencil-square me-2"></i>Edit Production Expense / Asset Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editExpenseForm" enctype="multipart/form-data">
                <?= CSRF::field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Production Unit -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">Production Unit</label>
                            <select name="production_unit" id="editProductionUnit" class="form-select fw-semibold" required>
                                <option value="PRODUCTION_1">🏭 Production Unit 1</option>
                                <option value="PRODUCTION_2">🏭 Production Unit 2</option>
                                <option value="BOTH_UNITS">🏭 Both Units / Common Facility</option>
                                <option value="GENERAL">🏢 General Production Operations</option>
                            </select>
                        </div>

                        <!-- Expense Category -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold required text-dark">Expense / Asset Category</label>
                            <select name="category" id="editCategory" class="form-select fw-semibold" required>
                                <option value="SEWING_MATERIALS">🧵 Sewing Materials (Threads, Zips, Needles, Trims)</option>
                                <option value="MACHINERY_ASSET">⚙️ Machinery &amp; Equipment (Stitching Machines, etc.)</option>
                                <option value="MAINTENANCE_REPAIR">🔧 Maintenance, Servicing &amp; Machine Oil</option>
                                <option value="OPERATIONAL_UTILITY">⚡ Operating Utilities (Electricity, Labor, Packaging)</option>
                                <option value="OTHER">📦 Other Production Expenses</option>
                            </select>
                        </div>

                        <!-- Item Name -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold required">Item / Expense Name</label>
                            <input type="text" name="item_name" id="editItemName" class="form-control" required>
                        </div>

                        <!-- Asset Tag -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Asset Tag / Serial #</label>
                            <input type="text" name="asset_tag_or_serial" id="editAssetTag" class="form-control">
                        </div>

                        <!-- Cost Box -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded border">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold required">Unit Type</label>
                                        <select name="unit_type" id="editUnitType" class="form-select fw-semibold" onchange="updateEditExpenseUnitLabels()">
                                            <option value="PCS">Pcs (Pieces)</option>
                                            <option value="CONES">Cones (Thread)</option>
                                            <option value="BOXES">Boxes</option>
                                            <option value="PACKETS">Packets</option>
                                            <option value="METERS">Meters</option>
                                            <option value="LITERS">Liters (Oil)</option>
                                            <option value="SET">Set / Kit</option>
                                            <option value="UNITS">Units</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold required">Quantity</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0.01" name="quantity" id="editQuantity" class="form-control fw-bold fs-6" required oninput="onEditQuantityChange()">
                                            <span class="input-group-text bg-white" id="editUnitLabel">pcs</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold required">Rate (<span id="editRateLabel">₹ / pcs</span>)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white fw-bold">₹</span>
                                            <input type="number" step="0.01" min="0" name="unit_cost" id="editUnitCost" class="form-control fw-bold text-primary fs-6" required oninput="onEditUnitCostChange()">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-primary">Total Cost (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary-subtle text-primary fw-bold">₹</span>
                                            <input type="number" step="0.01" min="0" name="total_cost" id="editTotalCost" class="form-control fw-bold text-primary fs-6" required oninput="onEditTotalCostChange()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vendor Details -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold required">Vendor / Supplier</label>
                            <input type="text" name="vendor_name" id="editVendorName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor Phone</label>
                            <input type="text" name="vendor_phone" id="editVendorPhone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor Company</label>
                            <input type="text" name="vendor_company" id="editVendorCompany" class="form-control">
                        </div>

                        <!-- Payment Details -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Amount Paid (₹)</label>
                            <input type="number" step="0.01" min="0" name="paid_amount" id="editPaidAmount" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Status</label>
                            <select name="payment_status" id="editPaymentStatus" class="form-select">
                                <option value="PENDING">Pending</option>
                                <option value="PARTIAL">Partial</option>
                                <option value="PAID">Paid in Full</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" id="editPaymentMethod" class="form-select">
                                <option value="CASH">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="BANK_TRANSFER">Bank Transfer</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="CREDIT">Credit Note</option>
                            </select>
                        </div>

                        <!-- Date & Reference -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold required">Expense Date</label>
                            <input type="date" name="expense_date" id="editExpenseDate" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Invoice / Bill #</label>
                            <input type="text" name="invoice_number" id="editInvoiceNo" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Reference</label>
                            <input type="text" name="payment_reference" id="editPaymentRef" class="form-control">
                        </div>

                        <!-- Linked Program -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Linked Program</label>
                            <select name="program_id" id="editProgramId" class="form-select">
                                <option value="">-- General Factory Expense --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= e($p['program_number']) ?> (<?= e($p['name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Update Photo -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Update Bill / Photo</label>
                            <input type="file" name="photo" id="editPhoto" class="form-control" accept="image/*,application/pdf">
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="bi bi-save me-1"></i> Update Expense Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. PRINTABLE EXPENSE VOUCHER MODAL                                        -->
<!-- ========================================================================= -->
<div class="modal fade" id="expenseReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-1"></i>Production Expense / Asset Voucher
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printableExpenseVoucherArea">
                <div class="text-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark">GRM APPARELS</h5>
                    <small class="text-muted d-block">Production Expense &amp; Asset Voucher</small>
                    <span class="badge bg-light text-dark border mt-1" id="recExpNo">EXP-2026-0001</span>
                </div>
                <hr class="my-2">
                <div class="row g-1 small mb-3">
                    <div class="col-6"><strong>Date:</strong> <span id="recExpDate">-</span></div>
                    <div class="col-6 text-end"><strong>Status:</strong> <span id="recExpStatus">-</span></div>
                    <div class="col-12"><strong>Production Unit:</strong> <span id="recExpUnit">-</span></div>
                    <div class="col-12"><strong>Category:</strong> <span id="recExpCategory">-</span></div>
                    <div class="col-12"><strong>Vendor / Supplier:</strong> <span id="recExpVendor">-</span></div>
                    <div class="col-12" id="recExpCompanyRow"><strong>Company:</strong> <span id="recExpCompany">-</span></div>
                    <div class="col-12" id="recExpSerialRow"><strong>Serial / Asset Tag:</strong> <span id="recExpSerial">-</span></div>
                    <div class="col-12" id="recExpProgramRow"><strong>Linked Program:</strong> <span id="recExpProgram">-</span></div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Description</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Rate</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="recExpItem">-</td>
                                <td class="text-end" id="recExpQty">0.00</td>
                                <td class="text-end" id="recExpRate">₹0.00</td>
                                <td class="text-end fw-bold" id="recExpTotal">₹0.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Expense Cost:</th>
                                <th class="text-end text-primary" id="recExpTotalFooter">₹0.00</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Amount Paid:</th>
                                <th class="text-end text-success" id="recExpPaid">₹0.00</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Balance Due:</th>
                                <th class="text-end text-danger" id="recExpDue">₹0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="small text-muted mb-3" id="recExpNotes"></div>
                <div class="d-flex justify-content-between text-muted small pt-3 border-top">
                    <div>Receiver's Signature</div>
                    <div>Authorized Signatory</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-dark btn-sm px-3" onclick="printExpenseVoucherArea()">
                    <i class="bi bi-printer me-1"></i> Print Voucher
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function applyNewPreset(itemName, category, unitType, itemType, unit) {
    document.getElementById('newItemName').value = itemName;
    document.getElementById('newCategory').value = category;
    document.getElementById('newUnitType').value = unitType;
    document.getElementById('newProductionUnit').value = unit || 'PRODUCTION_1';
    updateNewExpenseUnitLabels();
}

function updateNewExpenseUnitLabels() {
    const unit = document.getElementById('newUnitType').value;
    document.getElementById('newUnitLabel').textContent = unit.toLowerCase();
    document.getElementById('newRateLabel').textContent = `₹ / ${unit.toLowerCase()}`;
    onNewQuantityChange();
}

function onNewQuantityChange() {
    const q = parseFloat(document.getElementById('newQuantity').value) || 0;
    const r = parseFloat(document.getElementById('newUnitCost').value) || 0;
    const t = parseFloat(document.getElementById('newTotalCost').value) || 0;
    const unit = document.getElementById('newUnitType').value;

    if (r > 0) {
        const total = q * r;
        document.getElementById('newTotalCost').value = total > 0 ? total.toFixed(2) : '';
        document.getElementById('newCalcPreview').textContent = `Calculated: ${q.toFixed(2)} ${unit} × ₹${r.toFixed(2)}/${unit} = ₹${total.toFixed(2)}`;
    } else if (t > 0 && q > 0) {
        const rate = t / q;
        document.getElementById('newUnitCost').value = rate.toFixed(2);
        document.getElementById('newCalcPreview').textContent = `Calculated: ${q.toFixed(2)} ${unit} × ₹${rate.toFixed(2)}/${unit} = ₹${t.toFixed(2)}`;
    } else {
        document.getElementById('newCalcPreview').textContent = `Calculated: ${q.toFixed(2)} ${unit} × ₹${r.toFixed(2)}/${unit} = ₹0.00`;
    }
    syncNewExpensePaidAmount();
}

function onNewUnitCostChange() {
    const q = parseFloat(document.getElementById('newQuantity').value) || 0;
    const r = parseFloat(document.getElementById('newUnitCost').value) || 0;
    const unit = document.getElementById('newUnitType').value;
    const total = q * r;
    document.getElementById('newTotalCost').value = total > 0 ? total.toFixed(2) : '';
    document.getElementById('newCalcPreview').textContent = `Calculated: ${q.toFixed(2)} ${unit} × ₹${r.toFixed(2)}/${unit} = ₹${total.toFixed(2)}`;
    syncNewExpensePaidAmount();
}

function onNewTotalCostChange() {
    const q = parseFloat(document.getElementById('newQuantity').value) || 0;
    const t = parseFloat(document.getElementById('newTotalCost').value) || 0;
    const unit = document.getElementById('newUnitType').value;
    if (q > 0 && t > 0) {
        const rate = t / q;
        document.getElementById('newUnitCost').value = rate.toFixed(2);
        document.getElementById('newCalcPreview').textContent = `Calculated: ${q.toFixed(2)} ${unit} × ₹${rate.toFixed(2)}/${unit} = ₹${t.toFixed(2)}`;
    } else {
        document.getElementById('newCalcPreview').textContent = `Total Cost Entered: ₹${t.toFixed(2)}`;
    }
    syncNewExpensePaidAmount();
}

function syncNewExpensePaidAmount() {
    const total = parseFloat(document.getElementById('newTotalCost').value) || 0;
    const paidInput = document.getElementById('newPaidAmount');
    if (!paidInput.value || parseFloat(paidInput.value) === 0) {
        paidInput.value = total > 0 ? total.toFixed(2) : '';
    }
    autoCheckNewExpensePaymentStatus();
}

function autoCheckNewExpensePaymentStatus() {
    const total = parseFloat(document.getElementById('newTotalCost').value) || 0;
    const paid = parseFloat(document.getElementById('newPaidAmount').value) || 0;
    const statusSelect = document.getElementById('newPaymentStatus');

    if (total > 0 && paid >= total) {
        statusSelect.value = 'PAID';
    } else if (paid > 0 && paid < total) {
        statusSelect.value = 'PARTIAL';
    } else {
        statusSelect.value = 'PENDING';
    }
}

function updateEditExpenseUnitLabels() {
    const unit = document.getElementById('editUnitType').value;
    document.getElementById('editUnitLabel').textContent = unit.toLowerCase();
    document.getElementById('editRateLabel').textContent = `₹ / ${unit.toLowerCase()}`;
    onEditQuantityChange();
}

function onEditQuantityChange() {
    const q = parseFloat(document.getElementById('editQuantity').value) || 0;
    const r = parseFloat(document.getElementById('editUnitCost').value) || 0;
    const t = parseFloat(document.getElementById('editTotalCost').value) || 0;

    if (r > 0) {
        const total = q * r;
        document.getElementById('editTotalCost').value = total > 0 ? total.toFixed(2) : '';
    } else if (t > 0 && q > 0) {
        const rate = t / q;
        document.getElementById('editUnitCost').value = rate.toFixed(2);
    }
}

function onEditUnitCostChange() {
    const q = parseFloat(document.getElementById('editQuantity').value) || 0;
    const r = parseFloat(document.getElementById('editUnitCost').value) || 0;
    const total = q * r;
    document.getElementById('editTotalCost').value = total > 0 ? total.toFixed(2) : '';
}

function onEditTotalCostChange() {
    const q = parseFloat(document.getElementById('editQuantity').value) || 0;
    const t = parseFloat(document.getElementById('editTotalCost').value) || 0;
    if (q > 0 && t > 0) {
        const rate = t / q;
        document.getElementById('editUnitCost').value = rate.toFixed(2);
    }
}

function openEditExpenseModal(data) {
    document.getElementById('editExpenseForm').action = '<?= url('production/expenses/update') ?>/' + data.id;
    document.getElementById('editProductionUnit').value = data.production_unit || 'PRODUCTION_1';
    document.getElementById('editCategory').value = data.category || 'SEWING_MATERIALS';
    document.getElementById('editItemName').value = data.item_name || '';
    document.getElementById('editAssetTag').value = data.asset_tag_or_serial || '';
    document.getElementById('editUnitType').value = data.unit_type || 'PCS';
    document.getElementById('editQuantity').value = parseFloat(data.quantity).toFixed(2);
    document.getElementById('editUnitCost').value = parseFloat(data.unit_cost).toFixed(2);
    document.getElementById('editTotalCost').value = parseFloat(data.total_cost).toFixed(2);
    document.getElementById('editVendorName').value = data.vendor_name || '';
    document.getElementById('editVendorPhone').value = data.vendor_phone || '';
    document.getElementById('editVendorCompany').value = data.vendor_company || '';
    document.getElementById('editPaidAmount').value = parseFloat(data.paid_amount || 0).toFixed(2);
    document.getElementById('editPaymentStatus').value = data.payment_status || 'PENDING';
    document.getElementById('editPaymentMethod').value = data.payment_method || 'CASH';
    document.getElementById('editExpenseDate').value = data.expense_date || '';
    document.getElementById('editInvoiceNo').value = data.invoice_number || '';
    document.getElementById('editPaymentRef').value = data.payment_reference || '';
    document.getElementById('editProgramId').value = data.program_id || '';
    document.getElementById('editNotes').value = data.notes || '';

    updateEditExpenseUnitLabels();

    const modal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
    modal.show();
}

function openExpenseReceipt(id) {
    fetch('<?= url('production/expenses/receipt') ?>/' + id)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.expense) {
                const e = res.expense;
                document.getElementById('recExpNo').textContent = e.expense_number;
                document.getElementById('recExpDate').textContent = e.expense_date;
                document.getElementById('recExpStatus').textContent = e.payment_status;
                
                const unitLabels = {
                    'PRODUCTION_1': 'Production Unit 1',
                    'PRODUCTION_2': 'Production Unit 2',
                    'BOTH_UNITS': 'Both Units / Common',
                    'GENERAL': 'General Facility'
                };
                document.getElementById('recExpUnit').textContent = unitLabels[e.production_unit] || e.production_unit;
                document.getElementById('recExpCategory').textContent = (e.category || '').replace(/_/g, ' ');
                document.getElementById('recExpVendor').textContent = e.vendor_name;

                if (e.vendor_company) {
                    document.getElementById('recExpCompanyRow').classList.remove('d-none');
                    document.getElementById('recExpCompany').textContent = e.vendor_company;
                } else {
                    document.getElementById('recExpCompanyRow').classList.add('d-none');
                }

                if (e.asset_tag_or_serial) {
                    document.getElementById('recExpSerialRow').classList.remove('d-none');
                    document.getElementById('recExpSerial').textContent = e.asset_tag_or_serial;
                } else {
                    document.getElementById('recExpSerialRow').classList.add('d-none');
                }

                if (e.program_number) {
                    document.getElementById('recExpProgramRow').classList.remove('d-none');
                    document.getElementById('recExpProgram').textContent = e.program_number + (e.program_name ? ' (' + e.program_name + ')' : '');
                } else {
                    document.getElementById('recExpProgramRow').classList.add('d-none');
                }

                document.getElementById('recExpItem').textContent = e.item_name;
                document.getElementById('recExpQty').textContent = parseFloat(e.quantity).toFixed(2) + ' ' + (e.unit_type || 'PCS');
                document.getElementById('recExpRate').textContent = '₹' + parseFloat(e.unit_cost).toFixed(2);
                document.getElementById('recExpTotal').textContent = '₹' + parseFloat(e.total_cost).toFixed(2);
                document.getElementById('recExpTotalFooter').textContent = '₹' + parseFloat(e.total_cost).toFixed(2);
                document.getElementById('recExpPaid').textContent = '₹' + parseFloat(e.paid_amount).toFixed(2);
                const due = Math.max(0, parseFloat(e.total_cost) - parseFloat(e.paid_amount));
                document.getElementById('recExpDue').textContent = '₹' + due.toFixed(2);
                document.getElementById('recExpNotes').textContent = e.notes ? ('Remarks: ' + e.notes) : '';

                const modal = new bootstrap.Modal(document.getElementById('expenseReceiptModal'));
                modal.show();
            }
        })
        .catch(err => {
            console.error('Error fetching expense voucher data:', err);
            alert('Failed to load expense voucher.');
        });
}

function printExpenseVoucherArea() {
    const printContents = document.getElementById('printableExpenseVoucherArea').innerHTML;
    const origContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = origContents;
    window.location.reload();
}

// Initialize Expenses & Assets Table Pagination
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initClientPagination === 'function' && document.getElementById('expensesTable')) {
        const expensesPagination = window.initClientPagination({
            container: '#expensesTable tbody',
            itemSelector: 'tr.expense-row',
            footer: '#expensesPaginationFooter',
            infoEl: '#expensesPageInfo',
            paginationList: '#expensesPaginationList',
            pageSizeSelect: '#expensesPageSizeSelect',
            pageSize: 10,
            itemName: 'expenses'
        });

        const searchInput = document.getElementById('expensesSearchInput');
        if (searchInput && expensesPagination) {
            let debounceTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const q = searchInput.value.trim().toLowerCase();
                    expensesPagination.filter(row => {
                        if (!q) return true;
                        const text = (row.textContent || '').toLowerCase();
                        return text.includes(q);
                    });
                }, 150);
            });
        }
    }
});
</script>
