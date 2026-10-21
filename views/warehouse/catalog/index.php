<?php
// views/warehouse/catalog/index.php
// Warehouse Product Catalog & Master Styles List View
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-journal-bookmark-fill me-2 text-primary"></i>Warehouse Product Catalog</h2>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (hasPermission('wh_catalog', 'create')): ?>
        <a href="<?= url('warehouse/catalog/create' . (!empty($selectedSellerId) ? '?seller_id=' . (int)$selectedSellerId : '')) ?>" class="btn btn-primary shadow-sm fw-semibold">
            <i class="bi bi-plus-circle-fill me-1"></i> Add Product Style
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================= -->
<!-- QUICK SUPPLIER SELECTOR / FILTER PILLS -->
<!-- ========================================================================= -->
<?php if (!empty($suppliers)): ?>
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <span class="fw-bold small text-dark"><i class="bi bi-shop text-primary me-1"></i>Filter by Supplier</span>
                <span class="text-muted small">Click a supplier to immediately show all saved products for that vendor</span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('warehouse/catalog' . (!empty($categoryId) ? '?category_id=' . (int)$categoryId : '')) ?>" 
                   class="btn btn-sm <?= empty($selectedSellerId) ? 'btn-primary shadow-sm fw-bold' : 'btn-outline-secondary' ?> rounded-pill px-3">
                    <i class="bi bi-grid-fill me-1"></i> All Suppliers
                </a>
                <?php foreach ($suppliers as $sup): ?>
                    <?php 
                        $isActiveSup = ($selectedSellerId == $sup['id']); 
                        $supLabel = !empty($sup['company']) ? $sup['company'] : $sup['name'];
                    ?>
                    <a href="<?= url('warehouse/catalog?seller_id=' . $sup['id'] . (!empty($categoryId) ? '&category_id=' . (int)$categoryId : '')) ?>" 
                       class="btn btn-sm <?= $isActiveSup ? 'btn-primary shadow-sm fw-bold' : 'btn-outline-primary' ?> rounded-pill px-3">
                        <i class="bi bi-building me-1"></i> <?= e($supLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- ACTIVE SUPPLIER BANNER (When a supplier is actively filtered) -->
<!-- ========================================================================= -->
<?php if (!empty($selectedSellerId)): 
    $activeSeller = null;
    if (!empty($suppliers)) {
        foreach ($suppliers as $s) {
            if ($s['id'] == $selectedSellerId) {
                $activeSeller = $s;
                break;
            }
        }
    }
?>
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-primary bg-primary-subtle">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary px-2.5 py-1"><i class="bi bi-shop me-1"></i>Supplier Active Filter</span>
                    <h5 class="fw-bold text-dark mb-0"><?= e($activeSeller['company'] ?? $activeSeller['name'] ?? 'Selected Supplier') ?></h5>
                    <?php if (!empty($activeSeller['company']) && !empty($activeSeller['name']) && $activeSeller['company'] !== $activeSeller['name']): ?>
                        <span class="text-muted small">(Contact: <?= e($activeSeller['name']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small d-flex align-items-center gap-3 flex-wrap">
                    <?php if (!empty($activeSeller['phone'])): ?>
                        <span><i class="bi bi-telephone text-primary me-1"></i><?= e($activeSeller['phone']) ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-journal-bookmark text-success me-1"></i>Showing <strong><?= count($products ?? []) ?></strong> Saved Product Style(s)</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('warehouse/catalog/create?seller_id=' . (int)$selectedSellerId) ?>" class="btn btn-sm btn-primary fw-semibold shadow-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add Product for this Supplier
                </a>
                <a href="<?= url('warehouse/labels?source_type=tp_purchase&seller_id=' . (int)$selectedSellerId) ?>" class="btn btn-sm btn-dark fw-semibold shadow-sm">
                    <i class="bi bi-qr-code-scan me-1"></i> Generate QR Labels
                </a>
                <a href="<?= url('warehouse/catalog') ?>" class="btn btn-sm btn-outline-secondary" title="Clear Filter">
                    <i class="bi bi-x-lg me-1"></i> Clear Filter
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Search & Filter Bar -->
<div class="card mb-4 shadow-sm border-0 bg-white">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('warehouse/catalog') ?>" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by style code, SKU, product name, or material..." value="<?= e($search ?? '') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="seller_id" class="form-select form-select-sm">
                    <option value="">-- All Suppliers --</option>
                    <?php if (!empty($suppliers)): ?>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($selectedSellerId == $s['id']) ? 'selected' : '' ?>>
                                <?= e($s['name']) ?><?= !empty($s['company']) ? ' (' . e($s['company']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">-- All Categories --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($categoryId ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold"><i class="bi bi-filter me-1"></i> Filter</button>
                <?php if (!empty($search) || !empty($categoryId) || !empty($selectedSellerId)): ?>
                    <a href="<?= url('warehouse/catalog') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Products List Table -->
<div class="card shadow-sm border-0 bg-white rounded-3 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-list-columns-reverse text-primary me-2"></i>Product Styles Catalog List
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                <?= count($products ?? []) ?> Style(s)
            </span>
        </div>
        
    </div>
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-journal-plus fs-1 text-primary p-3 bg-primary-subtle rounded-circle d-inline-block"></i>
                </div>
                <h4 class="fw-bold mb-1">No Product Styles Found in Catalog</h4>
                <p class="text-muted mb-4">Start by adding your third-party vendor products or garment styles with 3-tier categories and SKU variants.</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= url('warehouse/catalog/create' . (!empty($selectedSellerId) ? '?seller_id=' . (int)$selectedSellerId : '')) ?>" class="btn btn-primary px-4 py-2 fw-semibold">
                        <i class="bi bi-plus-circle me-1"></i> Create Product Style
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr>
                            <th style="width: 70px;" class="text-center">Image</th>
                            <th>Product Style &amp; Code</th>
                            <th>Category &amp; Hierarchy</th>
                            <th>Supplier &amp; Source</th>
                            <th>Variants (Sizes / Colors)</th>
                            <th class="text-end">MRP (₹)</th>
                            <th class="text-end">Cost Price (₹)</th>
                            <th class="text-center">Live Stock</th>
                            <th class="text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): 
                            $firstVarId = !empty($p['variants'][0]['id']) ? $p['variants'][0]['id'] : null;
                        ?>
                            <tr>
                                <!-- Thumbnail Image -->
                                <td class="text-center p-2">
                                    <?php if (!empty($p['image_url'])): ?>
                                        <img src="<?= e($p['image_url']) ?>" 
                                             alt="<?= e($p['name']) ?>" 
                                             class="rounded border shadow-xs" 
                                             style="width: 52px; height: 52px; object-fit: cover;"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="bg-light border rounded d-inline-flex align-items-center justify-content-center text-muted" 
                                             style="width: 52px; height: 52px;">
                                             <i class="bi bi-image text-secondary fs-5 opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Product Style & Code -->
                                <td>
                                    <div class="fw-bold text-dark fs-6">
                                        <a href="<?= url('warehouse/catalog/view/' . $p['id']) ?>" class="text-dark text-decoration-none hover-primary">
                                            <?= e($p['name']) ?>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 mt-1 flex-wrap font-monospace">
                                        <span class="badge bg-dark text-white" style="font-size: 0.72rem;">
                                            <?= e($p['code']) ?>
                                        </span>
                                        <?php if (!empty($p['catalog_code'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.72rem;" title="Catalog Code">
                                                <i class="bi bi-upc-scan me-1"></i><?= e($p['catalog_code']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['is_complementary'])): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.72rem;" title="Complementary Product (Direct Live Warehouse Stock)">
                                                <i class="bi bi-gift-fill me-1"></i>Complementary
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['material'])): ?>
                                            <span class="badge bg-light text-secondary border" style="font-size: 0.72rem;">
                                                <?= e($p['material']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['mfg_date'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.70rem;" title="Manufacture Date">
                                                <i class="bi bi-calendar-check me-1"></i>MFG: <?= date('d/m/Y', strtotime($p['mfg_date'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['expiry_date'])): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.70rem;" title="Expiry Date">
                                                <i class="bi bi-calendar-x me-1"></i>EXP: <?= date('d/m/Y', strtotime($p['expiry_date'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Category & Hierarchy -->
                                <td>
                                    <div class="text-muted small">
                                        <i class="bi bi-folder2 me-1 text-primary"></i><?= e($p['category_name'] ?? 'General') ?>
                                        <?php if (!empty($p['subcategory_name'])): ?>
                                            <i class="bi bi-chevron-right text-muted small" style="font-size: 0.65rem;"></i> <?= e($p['subcategory_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($p['sub_subcategory_name'])): ?>
                                            <i class="bi bi-chevron-right text-muted small" style="font-size: 0.65rem;"></i> <?= e($p['sub_subcategory_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                        <?php if (!empty($p['brand_name'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.7rem;">
                                                <?= e($p['brand_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Supplier & Source -->
                                <td>
                                    <?php if (!empty($p['seller_name']) || !empty($p['seller_company'])): ?>
                                        <a href="<?= url('warehouse/catalog?seller_id=' . (int)($p['warehouse_supplier_id'] ?? 0)) ?>" 
                                           class="badge bg-info-subtle text-info-emphasis border text-decoration-none d-inline-flex align-items-center gap-1 mb-1" 
                                           title="Click to filter all products for this supplier" style="font-size: 0.75rem;">
                                            <i class="bi bi-shop"></i> <?= e($p['seller_company'] ?: $p['seller_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <div>
                                        <?php if ($p['source_type'] === 'PURCHASED' || $p['source_type'] === 'EXTERNAL_PURCHASE'): ?>
                                            <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size: 0.68rem;">
                                                <i class="bi bi-bag-check me-1"></i>Third-Party
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">
                                                <i class="bi bi-gear-fill me-1"></i>In-House
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Variants breakdown -->
                                <td>
                                    <div class="d-flex align-items-center gap-1 flex-wrap" style="max-width: 260px;">
                                        <span class="badge bg-primary text-white mb-1">
                                            <?= (int)$p['variant_count'] ?> Variant(s)
                                        </span>
                                        <?php if (!empty($p['variants'])): ?>
                                            <?php foreach (array_slice($p['variants'], 0, 4) as $v): ?>
                                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;" title="<?= e($v['sku']) ?>">
                                                    <?= e($v['size_code'] ?? $v['size_name'] ?? '') ?>
                                                    <?php if (!empty($v['color_hex'])): ?>
                                                        <span class="rounded-circle d-inline-block border ms-1" style="width:8px; height:8px; background-color:<?= e($v['color_hex']) ?>; vertical-align: middle;"></span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($p['variants']) > 4): ?>
                                                <span class="badge bg-light text-muted border" style="font-size: 0.68rem;">+<?= count($p['variants']) - 4 ?> more</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- MRP -->
                                <td class="text-end">
                                    <span class="fw-semibold text-success">
                                        <?php 
                                            $minMrp = (float)($p['min_mrp'] ?? 0);
                                            $maxMrp = (float)($p['max_mrp'] ?? 0);
                                            if ($minMrp > 0) {
                                                echo ($minMrp === $maxMrp) ? '₹' . number_format($minMrp, 2) : '₹' . number_format($minMrp, 2) . ' – ' . number_format($maxMrp, 2);
                                            } else {
                                                echo '<span class="text-muted">—</span>';
                                            }
                                        ?>
                                    </span>
                                </td>

                                <!-- Cost Price -->
                                <td class="text-end">
                                    <span class="fw-semibold text-primary">
                                        <?php 
                                            $minCost = (float)($p['min_cost_price'] ?? 0);
                                            $maxCost = (float)($p['max_cost_price'] ?? 0);
                                            if ($minCost > 0) {
                                                echo ($minCost === $maxCost) ? '₹' . number_format($minCost, 2) : '₹' . number_format($minCost, 2) . ' – ' . number_format($maxCost, 2);
                                            } else {
                                                echo '<span class="text-muted">—</span>';
                                            }
                                        ?>
                                    </span>
                                </td>

                                <!-- Live Warehouse Stock -->
                                <td class="text-center">
                                    <?php $stock = (int)($p['total_warehouse_stock'] ?? 0); ?>
                                    <span class="badge <?= $stock > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> px-2 py-1 fs-7">
                                        <i class="bi bi-boxes me-1"></i><?= number_format($stock) ?> pcs
                                    </span>
                                </td>

                                <!-- Action Buttons -->
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= url('warehouse/catalog/view/' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Style Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (hasPermission('wh_catalog', 'edit')): ?>
                                        <a href="<?= url('warehouse/catalog/edit/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Style">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (hasPermission('wh_catalog', 'delete')): ?>
                                        <form method="POST" action="<?= url('warehouse/catalog/delete/' . $p['id']) ?>" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to delete product style &quot;<?= e(addslashes($p['name'])) ?>&quot;?');">
                                            <?= CSRF::field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Style">
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
        <?php endif; ?>
    </div>
</div>
