<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-sliders me-2 text-primary"></i>Stock Adjustments & Audits</h2>
        <span class="text-muted">Reconcile physical inventory counts, record write-offs, and submit audit discrepancy requests</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (hasPermission('stock', 'adjust') || hasPermission('wh_adjustments', 'create') || hasPermission('stock', 'create')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAdjustmentModal">
            <i class="bi bi-plus-circle me-1"></i> Request Adjustment
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('warehouse/adjustments') ?>" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Warehouse</label>
                <select name="warehouse_id" class="form-select" <?= ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1) ? 'disabled' : '' ?>>
                    <?php if (!function_exists('currentWarehouseId') || currentWarehouseId() === null && count($warehouses) > 1): ?>
                        <option value="">All Warehouses</option>
                    <?php endif; ?>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="<?= $wh['id'] ?>" <?= (($warehouseId ?? '') == $wh['id'] || (function_exists('currentWarehouseId') && currentWarehouseId() == $wh['id'])) ? 'selected' : '' ?>>
                            <?= e($wh['name']) ?><?= !empty($wh['code']) ? ' (' . e($wh['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1): ?>
                    <input type="hidden" name="warehouse_id" value="<?= e($warehouseId ?? ($warehouses[0]['id'] ?? '')) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="PENDING" <?= ($status ?? '') === 'PENDING' ? 'selected' : '' ?>>PENDING (Awaiting Approval)</option>
                    <option value="APPROVED" <?= ($status ?? '') === 'APPROVED' ? 'selected' : '' ?>>APPROVED (Stock Updated)</option>
                    <option value="REJECTED" <?= ($status ?? '') === 'REJECTED' ? 'selected' : '' ?>>REJECTED</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Adjustment Type</label>
                <select name="adjustment_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="INCREASE" <?= ($type ?? '') === 'INCREASE' ? 'selected' : '' ?>>INCREASE (+ Stock Found)</option>
                    <option value="DECREASE" <?= ($type ?? '') === 'DECREASE' ? 'selected' : '' ?>>DECREASE (- Discrepancy / Damage)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= url('warehouse/adjustments') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Adjustments Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($adjustments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle fs-1 text-success d-block mb-3"></i>
                <h5 class="text-muted">No Adjustment Requests Found</h5>
                <p class="text-muted small">Submit an adjustment request whenever a physical audit variance is detected.</p>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAdjustmentModal">
                    Create First Adjustment
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Adjustment #</th>
                            <th>Warehouse</th>
                            <th>Product / SKU</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reason & Notes</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adjustments as $adj): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-primary"><?= e($adj['adjustment_number']) ?></span>
                                    <br><small class="text-muted"><?= date('d M Y, h:i A', strtotime($adj['created_at'])) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= e($adj['warehouse_name']) ?></span></td>
                                <td>
                                    <span class="fw-bold font-monospace"><?= e($adj['sku']) ?></span>
                                    <br><small class="text-muted"><?= e($adj['product_name']) ?> (<?= e($adj['size_name']) ?>, <?= e($adj['color_name']) ?>)</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $adj['adjustment_type'] === 'INCREASE' ? 'success' : 'danger' ?>">
                                        <?= e($adj['adjustment_type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold fs-6 <?= $adj['adjustment_type'] === 'INCREASE' ? 'text-success' : 'text-danger' ?>">
                                    <?= ($adj['adjustment_type'] === 'INCREASE' ? '+' : '-') . number_format($adj['quantity']) ?> pcs
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= e($adj['reason']) ?></span>
                                    <?php if (!empty($adj['notes'])): ?>
                                        <br><small class="text-muted"><?= e($adj['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="fw-semibold text-dark d-block"><?= e($adj['requested_by_name'] ?? 'Staff') ?></small>
                                    <?php if (!empty($adj['approved_by_name'])): ?>
                                        <small class="text-muted">Approved by: <?= e($adj['approved_by_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $adj['status'] === 'APPROVED' ? 'success' : ($adj['status'] === 'PENDING' ? 'warning text-dark' : 'danger') ?>">
                                        <?= e($adj['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($adj['status'] === 'PENDING' && (hasPermission('stock', 'adjust') || hasPermission('wh_adjustments', 'approve') || hasPermission('wh_adjustments', 'create'))): ?>
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="<?= url('warehouse/adjustments/approve/' . $adj['id']) ?>" class="d-inline">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="APPROVE">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this stock adjustment and update inventory balance?');">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="<?= url('warehouse/adjustments/approve/' . $adj['id']) ?>" class="d-inline">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="REJECT">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this adjustment request?');">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($adj['status'] === 'PENDING'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Adjustment Request -->
<div class="modal fade" id="newAdjustmentModal" tabindex="-1" aria-labelledby="newAdjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('warehouse/adjustments/store') ?>">
            <?= CSRF::input() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newAdjustmentModalLabel"><i class="bi bi-sliders me-2 text-primary"></i>Request Stock Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required <?= ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1) ? 'disabled' : '' ?>>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?><?= !empty($wh['code']) ? ' (' . e($wh['code']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || count($warehouses) <= 1): ?>
                            <input type="hidden" name="warehouse_id" value="<?= e($warehouses[0]['id'] ?? (function_exists('currentWarehouseId') ? currentWarehouseId() : '')) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Item / Variant (SKU) <span class="text-danger">*</span></label>
                        <select name="variant_id" class="form-select" required>
                            <option value="">Select Variant</option>
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= $v['id'] ?>">
                                    <?= e($v['sku']) ?> — <?= e($v['product_name']) ?> (<?= e($v['size_name']) ?>, <?= e($v['color_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Adjustment Type <span class="text-danger">*</span></label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="INCREASE">INCREASE (+ Found Stock)</option>
                                <option value="DECREASE">DECREASE (- Discrepancy / Damage)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Quantity (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="1" step="1" value="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Primary Reason <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="Physical Count Discrepancy">Physical Count Discrepancy (Stock Audit)</option>
                            <option value="Damaged / Spoiled in Warehouse">Damaged / Spoiled in Warehouse</option>
                            <option value="Lost / Missing Inventory">Lost / Missing Inventory</option>
                            <option value="Inventory Found / Corrected">Inventory Found / Corrected</option>
                            <option value="Sample / Showroom Pull">Sample / Showroom Pull</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Detailed Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Auditor name, audit batch reference..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit for Approval</button>
                </div>
            </div>
        </form>
    </div>
</div>
