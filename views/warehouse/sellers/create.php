<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-person-plus me-2 text-primary"></i>Register Third-Party Supplier</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('warehouse/sellers/buy') ?>" class="btn btn-outline-success">
            <i class="bi bi-cart-plus me-1"></i> Direct Purchase Hub
        </a>
        <a href="<?= url('warehouse/sellers') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Suppliers Hub
        </a>
    </div>
</div>

<!-- Floating Validation Alert Banner -->
<div id="formValidationAlert" class="alert alert-danger alert-dismissible fade show shadow-lg border-0 d-none position-fixed top-0 start-50 translate-middle-x mt-4" style="z-index: 9999; min-width: 320px; max-width: 600px;" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
        <div>
            <strong class="d-block" id="alertTitle">Missing Required Information</strong>
            <span id="alertMessage" class="small">Please fill in the required fields highlighted in red.</span>
        </div>
    </div>
    <button type="button" class="btn-close" onclick="hideValidationAlert()"></button>
</div>

<form method="POST" action="<?= url('warehouse/sellers/store') ?>" enctype="multipart/form-data" id="supplierRegisterForm" class="needs-validation" novalidate>
    <?= CSRF::input() ?>

    <div class="row g-4 mb-4">
        <!-- ========================================================================= -->
        <!-- LEFT COLUMN: SUPPLIER PROFILE & COMMERCIAL DETAILS -->
        <!-- ========================================================================= -->
        <div class="col-lg-4">
            <!-- Card 1: Supplier Profile -->
            <div class="card border-0 shadow-sm rounded-3 bg-white mb-4" id="supplierProfileCard">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-building-check me-2 text-primary"></i>1. Supplier Commercial Information</h6>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-none" id="card1ErrorBadge">
                        <i class="bi bi-exclamation-circle me-1"></i>Required
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Contact Person / Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="supplierNameInput" class="form-control" placeholder="e.g. Ramesh Kumar / Chennai Wholesalers" required minlength="2" maxlength="150" autocomplete="off">
                            <div class="invalid-feedback">Supplier / Contact Name is required (minimum 2 characters).</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Company / Brand Name</label>
                            <input type="text" name="company" id="supplierCompanyInput" class="form-control" placeholder="e.g. RK Apparels Pvt Ltd" maxlength="200">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Number</label>
                            <input type="tel" name="phone" id="supplierPhoneInput" class="form-control" placeholder="e.g. 9876543210" maxlength="15" autocomplete="tel">
                            <div class="invalid-feedback">Enter a valid 10-digit mobile number.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" id="supplierEmailInput" class="form-control" placeholder="vendor@company.com" maxlength="255" autocomplete="email">
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">GSTIN (15 Digits)</label>
                            <input type="text" name="gst_number" id="supplierGstInput" class="form-control font-monospace" placeholder="33AAAAA0000A1Z5" maxlength="15" style="text-transform: uppercase;">
                            <div class="invalid-feedback">Invalid GSTIN format (15 alphanumeric characters).</div>
                        </div>


                        <div class="col-12">
                            <label class="form-label small fw-semibold">Street Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Office / Warehouse street address..." maxlength="500"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">City</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Tirupur / Surat" maxlength="100">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">State</label>
                            <input type="text" name="state" class="form-control" value="Tamil Nadu" maxlength="100">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Bill & Inward Setup -->
            <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold"><i class="bi bi-receipt-cutoff me-2 text-success"></i>2. Bill &amp; Settlement Details</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Destination Warehouse (Stock Inward)</label>
                            <select name="warehouse_id" class="form-select" id="warehouseSelect" <?= ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || (!empty($warehouses) && count($warehouses) <= 1)) ? 'disabled' : '' ?>>
                                <?php if (!empty($warehouses)): ?>
                                    <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?><?= !empty($wh['code']) ? ' (' . e($wh['code']) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="1">Main Central Warehouse (WH-MAIN)</option>
                                <?php endif; ?>
                            </select>
                            <?php if ((function_exists('currentWarehouseId') && currentWarehouseId() !== null) || (!empty($warehouses) && count($warehouses) <= 1)): ?>
                                <input type="hidden" name="warehouse_id" value="<?= e($warehouses[0]['id'] ?? (function_exists('currentWarehouseId') ? currentWarehouseId() : '1')) ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bill Date</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Payment Mode</label>
                            <select name="payment_method" class="form-select">
                                <option value="CASH" selected>CASH</option>
                                <option value="BANK_TRANSFER">Bank Transfer / NEFT</option>
                                <option value="UPI">UPI / GPay / PhonePe</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="CREDIT">Supplier Credit</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="PAID" selected>PAID</option>
                                <option value="PARTIAL">PARTIAL</option>
                                <option value="PENDING">PENDING</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">GST Regime</label>
                            <select name="gst_type" class="form-select" id="gstTypeSelect" onchange="calculateTotals()">
                                <option value="CGST_SGST" selected>Intra-State (CGST + SGST)</option>
                                <option value="IGST">Inter-State (IGST)</option>
                            </select>
                        </div>

                        <!-- Bill Screenshot Upload Box -->
                        <div class="col-12">
                            <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-camera me-1 text-primary"></i>Upload Bill / Invoice Screenshot</span>
                                <span class="badge bg-light text-muted border">Image / PDF (Max 10MB)</span>
                            </label>
                            <div class="border border-2 border-dashed rounded-3 p-3 text-center bg-light" id="billDropzone">
                                <input type="file" name="bill_image" id="billImageInput" class="d-none" accept="image/*,.pdf" onchange="handleBillImageSelect(event)">
                                <div id="billUploadPlaceholder" role="button" onclick="document.getElementById('billImageInput').click();" class="py-2 cursor-pointer">
                                    <i class="bi bi-cloud-arrow-up fs-2 text-primary d-block mb-1"></i>
                                    <div class="fw-semibold small text-dark">Click or Drag to Upload Bill Screenshot</div>
                                    <div class="text-muted fs-8">Supports JPG, PNG, WEBP, or PDF invoices</div>
                                </div>
                                <div id="billImagePreviewBox" class="d-none mt-2 position-relative">
                                    <img id="billPreviewImg" src="" alt="Bill Screenshot" class="img-fluid rounded border shadow-sm" style="max-height: 160px; object-fit: contain;">
                                    <div class="mt-2 d-flex align-items-center justify-content-center gap-2">
                                        <span id="billFileName" class="badge bg-white text-dark border font-monospace small text-truncate" style="max-width: 220px;"></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeBillImage()" title="Remove Image">
                                            <i class="bi bi-x-lg me-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Notes / Purchase Remarks</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Batch references, transport details, notes..." maxlength="500"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- RIGHT COLUMN: MULTIPLE PRODUCTS BUY LIST (OPTIONAL QUICK ADD) -->
        <!-- ========================================================================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="card-title mb-0 fw-bold"><i class="bi bi-boxes me-2 text-primary"></i>3. Product Buy List (Single Bill Multi-Product Entry)</h6>
                        <small class="text-muted">Enter multiple items on the same bill (e.g. Individual Garments + Small Bundle Packs simultaneously)</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary shadow-sm px-3 fw-semibold" onclick="addProductRow()">
                        <i class="bi bi-plus-circle me-1"></i> Add Another Product Line
                    </button>
                </div>

                <div class="card-body p-3 flex-grow-1">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" id="productsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30px;" class="text-center">#</th>
                                    <th style="min-width: 170px;">Product / Item Name</th>
                                    <th style="min-width: 110px;">Style / SKU</th>
                                    <th style="min-width: 80px;">Color</th>
                                    <th style="min-width: 65px;">Size</th>
                                    <th style="width: 70px;" class="text-center">Qty</th>
                                    <th style="min-width: 180px;">QR Packaging Mode</th>
                                    <th style="width: 100px;" class="text-end">Cost (₹)</th>
                                    <th style="width: 125px;" class="text-center"><i class="bi bi-gift-fill text-primary me-1"></i>Complementary</th>
                                    <th style="width: 110px;" class="text-end">Total (₹)</th>
                                    <th style="width: 35px;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                <!-- First dynamic product row -->
                                <tr class="product-row">
                                    <td class="text-center fw-bold text-muted row-num">1</td>
                                    <td>
                                        <input type="text" name="product_name[]" class="form-control form-control-sm item-name-input" placeholder="e.g. Mens Shirt / Socks Pack" maxlength="200" oninput="handleProductNameInput(this)">
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="product_sku[]" class="form-control form-control-sm font-monospace sku-input" placeholder="e.g. TP-ITEM-01" maxlength="100">
                                            <button type="button" class="btn btn-outline-secondary px-2 py-0" onclick="autoGenerateSku(this)" title="Auto-generate SKU">
                                                <i class="bi bi-magic text-primary"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="product_color[]" class="form-control form-control-sm color-input" placeholder="e.g. Navy" maxlength="50" oninput="handleVariantPropInput(this)">
                                    </td>
                                    <td>
                                        <input type="text" name="product_size[]" class="form-control form-control-sm size-input" placeholder="e.g. L / XL" maxlength="20" oninput="handleVariantPropInput(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control form-control-sm qty-input text-center fw-bold" value="1" min="1" max="100000" step="1" oninput="handleQtyChange(this)">
                                    </td>
                                    <td>
                                        <!-- Per-row QR Packaging Type Selector -->
                                        <select name="product_qr_type[]" class="form-select form-select-sm qr-type-select fw-semibold" onchange="handleRowQRModeChange(this)">
                                            <option value="INDIVIDUAL" selected>🏷️ Individual (1 Pc = 1 QR)</option>
                                            <option value="SERIES_BATCH">📦 Master Series Bundle Pack</option>
                                        </select>
                                        <!-- Dynamic Range Box shown only when Master Series Bundle Pack is chosen -->
                                        <div class="row-series-box d-none mt-1 p-1 bg-light rounded border">
                                            <div class="input-group input-group-sm mb-1">
                                                <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted">From</span>
                                                <input type="text" name="series_start_code[]" class="form-control form-control-sm font-monospace series-start-input" placeholder="SKU-001" oninput="calculateTotals()">
                                            </div>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted">To</span>
                                                <input type="text" name="series_end_code[]" class="form-control form-control-sm font-monospace series-end-input" placeholder="SKU-050" oninput="calculateTotals()">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="unit_price[]" class="form-control form-control-sm price-input text-end fw-semibold" value="0.00" step="0.01" min="0" max="1000000" oninput="calculateTotals()">
                                        <input type="hidden" name="mrp[]" class="mrp-input" value="0.00">
                                        <input type="hidden" name="selling_price[]" class="sell-price-input" value="0.00">
                                        <input type="hidden" name="gst_percentage[]" class="gst-select" value="0">
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="is_complementary[]" value="0" class="comp-hidden-val">
                                        <div class="form-check form-switch d-inline-flex align-items-center justify-content-center m-0">
                                            <input class="form-check-input comp-switch" type="checkbox" role="switch" onchange="this.closest('td').querySelector('.comp-hidden-val').value = this.checked ? '1' : '0'; this.closest('td').querySelector('.comp-label-badge').textContent = this.checked ? 'Complementary' : 'Normal'; this.closest('td').querySelector('.comp-label-badge').className = this.checked ? 'badge bg-primary text-white d-block mt-0.5' : 'badge bg-light text-muted border d-block mt-0.5';" title="Toggle Normal / Complementary product">
                                        </div>
                                        <span class="badge bg-light text-muted border d-block mt-0.5 comp-label-badge" style="font-size: 0.65rem;">Normal</span>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm total-display text-end fw-bold text-success" value="₹0.00" readonly>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeProductRow(this)" title="Remove item">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-muted small">
                        <i class="bi bi-info-circle me-1 text-primary"></i> <strong>Workflow:</strong> Registering the supplier automatically stores products in the <strong>Warehouse Inventory System</strong>, logs purchase bills, and generates QR packaging codes.
                    </div>

                    <!-- Live Inward Products & QR Packaging Identification Manifest -->
                    <div class="mt-4 pt-3 border-top" id="productsManifestBox">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark small">
                                <i class="bi bi-card-checklist me-1 text-primary"></i> Live Inward Products &amp; QR Packaging Breakdown:
                            </h6>
                            <span class="badge bg-light text-secondary border small" id="manifestCountBadge">0 Products Entered</span>
                        </div>
                        
                        <div class="table-responsive border rounded-3 bg-white shadow-xs">
                            <table class="table table-sm table-hover align-middle mb-0 small" id="manifestTable">
                                <thead class="table-light">
                                    <tr class="text-muted text-uppercase fs-8">
                                        <th style="width: 35px;" class="text-center">#</th>
                                        <th>Product / Item</th>
                                        <th>SKU Code</th>
                                        <th>Variant Specs</th>
                                        <th class="text-center">Inward Qty</th>
                                        <th>QR Sticker Packaging Format</th>
                                        <th class="text-end">Cost Rate</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody id="manifestTableBody">
                                    <tr id="manifestEmptyRow">
                                        <td colspan="8" class="text-center py-3 text-muted">
                                            <i class="bi bi-box-arrow-in-down text-secondary me-1"></i> As you enter products above, both individual &amp; bundle items will be itemized here in real-time.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Financial Valuation Summary Footer -->
                <div class="card-footer bg-light p-3 border-top">
                    <div class="row align-items-center g-3">
                        <div class="col-md-7">
                            <div class="d-flex flex-wrap gap-3 small">
                                <div><span class="text-muted">Total Products:</span> <strong id="lblItemCount" class="text-dark">0</strong></div>
                                <div><span class="text-muted">Total Quantity:</span> <strong id="lblTotalQty" class="text-dark">0 pcs</strong></div>
                                <div><span class="text-muted">Total Cost Value:</span> <strong id="lblSubtotal">₹0.00</strong></div>
                            </div>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <div class="fs-5 fw-bold text-success" id="lblTotalBox">
                                <span class="small fs-6 text-muted fw-normal">Total Bill Value:</span> <span id="lblTotal">₹0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Action Bar -->
    <div class="card border-0 shadow-sm rounded-3 bg-white p-3 d-flex flex-row justify-content-between align-items-center">
        <a href="<?= url('warehouse/sellers') ?>" class="btn btn-light px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancel
        </a>
        <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold shadow-sm" id="btnSubmit">
            <i class="bi bi-check-circle me-1"></i> Register Supplier &amp; Store Products
        </button>
    </div>
</form>

<template id="productRowTemplate">
    <tr class="product-row">
        <td class="text-center fw-bold text-muted row-num">1</td>
        <td>
            <input type="text" name="product_name[]" class="form-control form-control-sm item-name-input" placeholder="e.g. Mens Shirt / Socks Pack" maxlength="200" oninput="handleProductNameInput(this)">
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="text" name="product_sku[]" class="form-control form-control-sm font-monospace sku-input" placeholder="e.g. TP-ITEM-01" maxlength="100">
                <button type="button" class="btn btn-outline-secondary px-2 py-0" onclick="autoGenerateSku(this)" title="Auto-generate SKU">
                    <i class="bi bi-magic text-primary"></i>
                </button>
            </div>
        </td>
        <td>
            <input type="text" name="product_color[]" class="form-control form-control-sm color-input" placeholder="e.g. Navy" maxlength="50" oninput="handleVariantPropInput(this)">
        </td>
        <td>
            <input type="text" name="product_size[]" class="form-control form-control-sm size-input" placeholder="e.g. L / XL" maxlength="20" oninput="handleVariantPropInput(this)">
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm qty-input text-center fw-bold" value="1" min="1" max="100000" step="1" oninput="handleQtyChange(this)">
        </td>
        <td>
            <!-- Per-row QR Packaging Type Selector -->
            <select name="product_qr_type[]" class="form-select form-select-sm qr-type-select fw-semibold" onchange="handleRowQRModeChange(this)">
                <option value="INDIVIDUAL" selected>🏷️ Individual (1 Pc = 1 QR)</option>
                <option value="SERIES_BATCH">📦 Master Series Bundle Pack</option>
            </select>
            <!-- Dynamic Range Box shown only when Master Series Bundle Pack is chosen -->
            <div class="row-series-box d-none mt-1 p-1 bg-light rounded border">
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted">From</span>
                    <input type="text" name="series_start_code[]" class="form-control form-control-sm font-monospace series-start-input" placeholder="SKU-001" oninput="calculateTotals()">
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text py-0 px-1 font-monospace fs-8 text-muted">To</span>
                    <input type="text" name="series_end_code[]" class="form-control form-control-sm font-monospace series-end-input" placeholder="SKU-050" oninput="calculateTotals()">
                </div>
            </div>
        </td>
        <td>
            <input type="number" name="unit_price[]" class="form-control form-control-sm price-input text-end fw-semibold" value="0.00" step="0.01" min="0" max="1000000" oninput="calculateTotals()">
            <input type="hidden" name="mrp[]" class="mrp-input" value="0.00">
            <input type="hidden" name="selling_price[]" class="sell-price-input" value="0.00">
            <input type="hidden" name="gst_percentage[]" class="gst-select" value="0">
        </td>
        <td class="text-center">
            <input type="hidden" name="is_complementary[]" value="0" class="comp-hidden-val">
            <div class="form-check form-switch d-inline-flex align-items-center justify-content-center m-0">
                <input class="form-check-input comp-switch" type="checkbox" role="switch" onchange="this.closest('td').querySelector('.comp-hidden-val').value = this.checked ? '1' : '0'; this.closest('td').querySelector('.comp-label-badge').textContent = this.checked ? 'Complementary' : 'Normal'; this.closest('td').querySelector('.comp-label-badge').className = this.checked ? 'badge bg-primary text-white d-block mt-0.5' : 'badge bg-light text-muted border d-block mt-0.5';" title="Toggle Normal / Complementary product">
            </div>
            <span class="badge bg-light text-muted border d-block mt-0.5 comp-label-badge" style="font-size: 0.65rem;">Normal</span>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm total-display text-end fw-bold text-success" value="₹0.00" readonly>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeProductRow(this)" title="Remove item">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

<style>
/* Real-time inline validation feedback styling */
.invalid-feedback {
    display: none;
    font-size: 0.76rem;
    font-weight: 500;
    margin-top: 3px;
    color: #dc3545;
}
.valid-feedback {
    display: none;
    font-size: 0.76rem;
    font-weight: 500;
    margin-top: 3px;
    color: #198754;
}
.form-control.is-invalid, .form-select.is-invalid {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right calc(0.375em + 0.1875rem) center !important;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
    padding-right: calc(1.5em + 0.75rem);
}
.form-control.is-valid, .form-select.is-valid {
    border-color: #198754 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73.6 5l.7-.71 1 1 3.5-3.5.71.71-4.21 4.23z'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right calc(0.375em + 0.1875rem) center !important;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
    padding-right: calc(1.5em + 0.75rem);
}
.is-invalid ~ .invalid-feedback,
.is-invalid + .invalid-feedback {
    display: block !important;
}
.is-valid ~ .valid-feedback,
.is-valid + .valid-feedback {
    display: block !important;
}
#productsTable td {
    vertical-align: top;
    padding: 0.5rem 0.35rem;
}
#productsTable .invalid-feedback {
    font-size: 0.70rem;
    line-height: 1.1;
    white-space: normal;
    margin-top: 2px;
}
@keyframes pulseFieldHighlight {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.6); }
    70% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
.field-error-pulse {
    animation: pulseFieldHighlight 1.5s infinite;
    border-color: #dc3545 !important;
}
</style>

<script>
// =========================================================================
// ALERT NOTIFICATION HELPER
// =========================================================================
let alertTimer = null;
function showValidationNotice(title, message) {
    const alertBox = document.getElementById('formValidationAlert');
    if (!alertBox) return;
    document.getElementById('alertTitle').textContent = title || 'Validation Notice';
    document.getElementById('alertMessage').textContent = message || 'Please check the highlighted fields.';
    alertBox.classList.remove('d-none');
    alertBox.classList.add('show');
    
    if (alertTimer) clearTimeout(alertTimer);
    alertTimer = setTimeout(() => {
        hideValidationAlert();
    }, 6000);
}

function hideValidationAlert() {
    const alertBox = document.getElementById('formValidationAlert');
    if (alertBox) {
        alertBox.classList.add('d-none');
        alertBox.classList.remove('show');
    }
}

// =========================================================================
// BILL IMAGE PREVIEW & REMOVAL
// =========================================================================
function handleBillImageSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    document.getElementById('billFileName').textContent = file.name;
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('billPreviewImg').src = e.target.result;
            document.getElementById('billPreviewImg').classList.remove('d-none');
            document.getElementById('billUploadPlaceholder').classList.add('d-none');
            document.getElementById('billImagePreviewBox').classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('billPreviewImg').classList.add('d-none');
        document.getElementById('billUploadPlaceholder').classList.add('d-none');
        document.getElementById('billImagePreviewBox').classList.remove('d-none');
    }
}

function removeBillImage() {
    const input = document.getElementById('billImageInput');
    input.value = '';
    document.getElementById('billPreviewImg').src = '';
    document.getElementById('billFileName').textContent = '';
    document.getElementById('billImagePreviewBox').classList.add('d-none');
    document.getElementById('billUploadPlaceholder').classList.remove('d-none');
}

// Drag & drop for bill upload
const dropzone = document.getElementById('billDropzone');
if (dropzone) {
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('border-primary', 'bg-primary-subtle');
        }, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-primary', 'bg-primary-subtle');
        }, false);
    });
    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            const fileInput = document.getElementById('billImageInput');
            fileInput.files = files;
            handleBillImageSelect({ target: { files: files } });
        }
    });
}

// =========================================================================
// MULTI-PRODUCT DYNAMIC ROW MANAGEMENT & CALCULATIONS
// =========================================================================
function addProductRow() {
    const template = document.getElementById('productRowTemplate');
    const tbody = document.getElementById('productsTableBody');
    const clone = template.content.cloneNode(true);
    tbody.appendChild(clone);
    const newRow = tbody.lastElementChild;
    const isSeries = document.getElementById('qrTypeSeriesBatch')?.checked;
    if (isSeries && newRow) {
        newRow.querySelectorAll('.series-col').forEach(el => el.classList.remove('d-none'));
    }
    updateRowNumbers();
    calculateTotals();
}

function removeProductRow(btn) {
    const tbody = document.getElementById('productsTableBody');
    if (tbody.querySelectorAll('tr.product-row').length > 1) {
        btn.closest('tr').remove();
        updateRowNumbers();
    } else {
        const row = btn.closest('tr');
        row.querySelector('.item-name-input').value = '';
        row.querySelectorAll('input[type="text"], input[type="number"]').forEach(inp => {
            if (inp.classList.contains('qty-input')) inp.value = '1';
            else if (inp.classList.contains('price-input') || inp.classList.contains('mrp-input') || inp.classList.contains('sell-price-input')) inp.value = '0.00';
            else inp.value = '';
            inp.classList.remove('is-invalid', 'is-valid', 'field-error-pulse');
        });
        row.querySelectorAll('.invalid-feedback').forEach(fb => fb.style.display = 'none');
        row.querySelector('.total-display').value = '₹0.00';
    }
    calculateTotals();
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('#productsTableBody tr.product-row');
    rows.forEach((row, idx) => {
        const numEl = row.querySelector('.row-num');
        if (numEl) numEl.textContent = idx + 1;
    });
}

function calculateTotals() {
    let grossSubtotal = 0;
    let totalTax = 0;
    let grandTotal = 0;
    let totalUnits = 0;
    let activeItemCount = 0;

    const rows = document.querySelectorAll('#productsTableBody tr.product-row');
    const isSeriesGlobal = document.getElementById('qrTypeSeriesBatch')?.checked;
    const manifestRows = [];

    rows.forEach((row, idx) => {
        const name = row.querySelector('.item-name-input').value.trim();
        const sku = row.querySelector('.sku-input')?.value.trim() || 'Auto';
        const color = row.querySelector('.color-input')?.value.trim() || '-';
        const size = row.querySelector('.size-input')?.value.trim() || '-';
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const gstPct = parseFloat(row.querySelector('.gst-select').value) || 0;
        const startCode = row.querySelector('.series-start-input')?.value.trim() || '';
        const endCode = row.querySelector('.series-end-input')?.value.trim() || '';

        const lineGross = qty * price;
        const lineTax = (lineGross * gstPct) / 100;
        const lineTotal = lineGross + lineTax;

        if (name !== '' || price > 0) {
            activeItemCount++;
            totalUnits += qty;

            // Determine packaging badge per row
            const qrType = row.querySelector('.qr-type-select')?.value || 'INDIVIDUAL';
            let qrBadgeHtml = '';
            if (qrType === 'SERIES_BATCH' || startCode !== '' || endCode !== '') {
                const rangeText = (startCode && endCode) ? `${startCode} ➔ ${endCode}` : `${sku}-001 ➔ ${sku}-${String(qty).padStart(3, '0')}`;
                qrBadgeHtml = `<span class="badge bg-primary text-white me-1"><i class="bi bi-collection me-1"></i>Master Series Bundle QR</span> <span class="font-monospace text-dark fw-bold">${rangeText}</span> <small class="text-muted">(${qty} pcs pack)</small>`;
            } else {
                qrBadgeHtml = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-tag me-1"></i>Individual Piece QR</span> <small class="text-muted ms-1">(${qty} separate stickers)</small>`;
            }

            manifestRows.push(`
                <tr>
                    <td class="text-center fw-bold text-muted">${activeItemCount}</td>
                    <td><strong class="text-dark">${escapeHtml(name || sku || 'Item Line')}</strong></td>
                    <td><code class="text-primary fw-semibold">${escapeHtml(sku)}</code></td>
                    <td>
                        <span class="badge bg-light text-dark border">${escapeHtml(color)}</span>
                        <span class="badge bg-light text-dark border">${escapeHtml(size)}</span>
                    </td>
                    <td class="text-center"><span class="badge bg-primary text-white fw-bold px-2">${qty} pcs</span></td>
                    <td>${qrBadgeHtml}</td>
                    <td class="text-end fw-semibold">₹${price.toFixed(2)}</td>
                    <td class="text-end fw-bold text-success">₹${lineTotal.toFixed(2)}</td>
                </tr>
            `);
        }

        grossSubtotal += lineGross;
        totalTax += lineTax;
        grandTotal += lineTotal;

        row.querySelector('.total-display').value = '₹' + lineTotal.toFixed(2);
    });

    if (document.getElementById('lblItemCount')) document.getElementById('lblItemCount').textContent = activeItemCount;
    if (document.getElementById('lblTotalQty')) document.getElementById('lblTotalQty').textContent = totalUnits + ' pcs';
    if (document.getElementById('lblSubtotal')) document.getElementById('lblSubtotal').textContent = '₹' + grossSubtotal.toFixed(2);
    if (document.getElementById('lblTax')) document.getElementById('lblTax').textContent = '₹' + totalTax.toFixed(2);
    if (document.getElementById('lblTotal')) document.getElementById('lblTotal').textContent = '₹' + grandTotal.toFixed(2);

    // Update manifest table
    const manifestTbody = document.getElementById('manifestTableBody');
    const manifestBadge = document.getElementById('manifestCountBadge');
    if (manifestTbody) {
        if (manifestRows.length > 0) {
            manifestTbody.innerHTML = manifestRows.join('');
            if (manifestBadge) {
                manifestBadge.textContent = `${activeItemCount} Product${activeItemCount > 1 ? 's' : ''} Ready for Inward`;
                manifestBadge.className = 'badge bg-success-subtle text-success border border-success-subtle small';
            }
        } else {
            manifestTbody.innerHTML = `
                <tr id="manifestEmptyRow">
                    <td colspan="8" class="text-center py-3 text-muted">
                        <i class="bi bi-box-arrow-in-down text-secondary me-1"></i> As you enter products above, both individual &amp; bundle items will be itemized here in real-time.
                    </td>
                </tr>`;
            if (manifestBadge) {
                manifestBadge.textContent = '0 Products Entered';
                manifestBadge.className = 'badge bg-light text-secondary border small';
            }
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[tag] || tag));
}

// =========================================================================
// SKU AUTO GENERATION HELPER
// =========================================================================
function generateCleanSku(name, color, size) {
    name = (name || '').trim();
    color = (color || '').trim();
    size = (size || '').trim();

    const cleanName = name.replace(/[^A-Za-z0-9]/g, '').substring(0, 5).toUpperCase() || 'ITEM';
    const cleanColor = color ? '-' + color.replace(/[^A-Za-z0-9]/g, '').substring(0, 3).toUpperCase() : '';
    const cleanSize = size ? '-' + size.replace(/[^A-Za-z0-9]/g, '').substring(0, 4).toUpperCase() : '';
    const rand = Math.floor(100 + Math.random() * 900);

    return 'TP-' + cleanName + cleanColor + cleanSize + '-' + rand;
}

function autoGenerateSku(btn) {
    const row = btn.closest('tr');
    const name = row.querySelector('.item-name-input')?.value || '';
    const color = row.querySelector('.color-input')?.value || '';
    const size = row.querySelector('.size-input')?.value || '';
    const skuInput = row.querySelector('.sku-input');

    if (skuInput) {
        skuInput.value = generateCleanSku(name, color, size);
    }
}

// =========================================================================
// PER-ROW QR PACKAGING MODE HANDLERS
// =========================================================================
function handleRowQRModeChange(select) {
    const row = select.closest('tr');
    if (!row) return;
    const seriesBox = row.querySelector('.row-series-box');
    const isSeries = select.value === 'SERIES_BATCH';
    
    if (isSeries) {
        if (seriesBox) seriesBox.classList.remove('d-none');
        const skuInput = row.querySelector('.sku-input');
        const qtyInput = row.querySelector('.qty-input');
        const startInput = row.querySelector('.series-start-input');
        const endInput = row.querySelector('.series-end-input');
        
        let sku = (skuInput?.value || '').trim();
        if (!sku) {
            const name = row.querySelector('.item-name-input')?.value || 'ITEM';
            const color = row.querySelector('.color-input')?.value || '';
            const size = row.querySelector('.size-input')?.value || '';
            sku = generateCleanSku(name, color, size);
            if (skuInput) skuInput.value = sku;
        }
        
        const qty = parseInt(qtyInput?.value) || 1;
        if (startInput && !startInput.value.trim()) {
            startInput.value = sku + '-001';
        }
        if (endInput && !endInput.value.trim()) {
            endInput.value = sku + '-' + String(qty).padStart(3, '0');
        }
    } else {
        if (seriesBox) seriesBox.classList.add('d-none');
        const startInput = row.querySelector('.series-start-input');
        const endInput = row.querySelector('.series-end-input');
        if (startInput) startInput.value = '';
        if (endInput) endInput.value = '';
    }
    
    calculateTotals();
}

function handleQtyChange(input) {
    calculateTotals();
    const row = input.closest('tr');
    if (!row) return;
    const qrSelect = row.querySelector('.qr-type-select');
    if (qrSelect && qrSelect.value === 'SERIES_BATCH') {
        const sku = row.querySelector('.sku-input')?.value || 'ITEM';
        const qty = parseInt(input.value) || 1;
        const startInput = row.querySelector('.series-start-input');
        const endInput = row.querySelector('.series-end-input');
        if (startInput && !startInput.value.trim()) {
            startInput.value = sku + '-001';
        }
        if (endInput) {
            endInput.value = sku + '-' + String(qty).padStart(3, '0');
        }
    }
}

function handleProductNameInput(input) {
    calculateTotals();
    const row = input.closest('tr');
    const skuInput = row.querySelector('.sku-input');
    if (skuInput && !skuInput.value.trim()) {
        const color = row.querySelector('.color-input')?.value || '';
        const size = row.querySelector('.size-input')?.value || '';
        const newSku = generateCleanSku(input.value, color, size);
        skuInput.value = newSku;
        
        const qrSelect = row.querySelector('.qr-type-select');
        if (qrSelect && qrSelect.value === 'SERIES_BATCH') {
            const qty = parseInt(row.querySelector('.qty-input')?.value) || 1;
            const startInput = row.querySelector('.series-start-input');
            const endInput = row.querySelector('.series-end-input');
            if (startInput) startInput.value = newSku + '-001';
            if (endInput) endInput.value = newSku + '-' + String(qty).padStart(3, '0');
        }
    }
}

function handleVariantPropInput(input) {
    calculateTotals();
    const row = input.closest('tr');
    const skuInput = row.querySelector('.sku-input');
    if (skuInput && (!skuInput.value.trim() || skuInput.value.startsWith('TP-'))) {
        const name = row.querySelector('.item-name-input')?.value || document.getElementById('supplierCompanyInput')?.value || document.getElementById('supplierNameInput')?.value || 'ITEM';
        const color = row.querySelector('.color-input')?.value || '';
        const size = row.querySelector('.size-input')?.value || '';
        const newSku = generateCleanSku(name, color, size);
        skuInput.value = newSku;
        const qrSelect = row.querySelector('.qr-type-select');
        if (qrSelect && qrSelect.value === 'SERIES_BATCH') {
            const qty = parseInt(row.querySelector('.qty-input')?.value) || 1;
            const startInput = row.querySelector('.series-start-input');
            const endInput = row.querySelector('.series-end-input');
            if (startInput) startInput.value = newSku + '-001';
            if (endInput) endInput.value = newSku + '-' + String(qty).padStart(3, '0');
        }
    }
}

// =========================================================================
// REAL-TIME INLINE FIELD VALIDATION
// =========================================================================
function findFeedbackElement(input) {
    if (!input) return null;
    let parent = input.parentElement;
    let fb = parent.querySelector('.invalid-feedback');
    if (fb) return fb;

    if (parent.classList.contains('input-group') && parent.parentElement) {
        fb = parent.parentElement.querySelector('.invalid-feedback');
        if (fb) return fb;
    }

    const cell = input.closest('td') || input.closest('.col-12, .col-md-6, .mb-3');
    if (cell) {
        fb = cell.querySelector('.invalid-feedback');
        if (fb) return fb;
    }

    return null;
}

function validateField(input) {
    if (!input || input.disabled || input.type === 'hidden' || input.type === 'submit' || input.type === 'button') return true;

    let isValid = true;
    let customError = '';

    const name = input.name || '';
    const val = (input.value || '').trim();

    if (name === 'name' || input.id === 'supplierNameInput') {
        if (!val || val.length < 2) {
            isValid = false;
            customError = 'Supplier name is required (min 2 characters).';
        }
    } else if ((name === 'phone' || input.id === 'supplierPhoneInput') && val) {
        const cleanedPhone = val.replace(/[^0-9]/g, '');
        if (cleanedPhone.length > 0 && cleanedPhone.length < 10) {
            isValid = false;
            customError = 'Mobile number should be at least 10 digits.';
        }
    } else if ((name === 'email' || input.id === 'supplierEmailInput') && val) {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            isValid = false;
            customError = 'Enter a valid email address.';
        }
    } else if ((name === 'gst_number' || input.id === 'supplierGstInput') && val) {
        if (val.length !== 15) {
            isValid = false;
            customError = 'GSTIN must be 15 characters.';
        }
    }

    const feedback = findFeedbackElement(input);

    if (!isValid) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');

        if (feedback) {
            if (customError) feedback.textContent = customError;
            feedback.style.display = 'block';
        }
    } else {
        input.classList.remove('is-invalid', 'field-error-pulse');
        if (val !== '' && (name === 'name' || name === 'phone' || name === 'email' || name === 'gst_number')) {
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
        }

        if (feedback) {
            feedback.style.display = 'none';
        }
    }

    return isValid;
}

function attachLiveValidation() {
    const inputs = document.querySelectorAll('#supplierNameInput, #supplierPhoneInput, #supplierEmailInput, #supplierGstInput');
    inputs.forEach(input => {
        ['input', 'blur', 'change', 'keyup', 'paste'].forEach(evt => {
            input.addEventListener(evt, function() {
                validateField(this);
                // Clean GSTIN to uppercase
                if (this.id === 'supplierGstInput') {
                    this.value = this.value.toUpperCase();
                }
            });
        });
    });
}

// Form submission validation & UI loading state
(function() {
    'use strict';
    const form = document.getElementById('supplierRegisterForm');
    const btnSubmit = document.getElementById('btnSubmit');

    if (form) {
        form.addEventListener('submit', function(event) {
            let formValid = true;
            let firstInvalid = null;
            let errorMessage = '';

            const nameInput = document.getElementById('supplierNameInput') || form.querySelector('input[name="name"]');
            const phoneInput = document.getElementById('supplierPhoneInput') || form.querySelector('input[name="phone"]');
            const emailInput = document.getElementById('supplierEmailInput') || form.querySelector('input[name="email"]');
            const gstInput = document.getElementById('supplierGstInput') || form.querySelector('input[name="gst_number"]');

            if (nameInput && !validateField(nameInput)) {
                formValid = false;
                if (!firstInvalid) {
                    firstInvalid = nameInput;
                    errorMessage = 'Supplier / Contact Name is required (min 2 characters).';
                }
            }

            if (phoneInput && !validateField(phoneInput)) {
                formValid = false;
                if (!firstInvalid) {
                    firstInvalid = phoneInput;
                    errorMessage = 'Please enter a valid 10-digit mobile number.';
                }
            }

            if (emailInput && !validateField(emailInput)) {
                formValid = false;
                if (!firstInvalid) {
                    firstInvalid = emailInput;
                    errorMessage = 'Please enter a valid email address.';
                }
            }

            if (gstInput && !validateField(gstInput)) {
                formValid = false;
                if (!firstInvalid) {
                    firstInvalid = gstInput;
                    errorMessage = 'Please enter a valid 15-character GSTIN.';
                }
            }

            if (!formValid) {
                event.preventDefault();
                event.stopPropagation();

                // Show visual alert and highlight
                showValidationNotice('Supplier Information Required', errorMessage);

                const cardBadge = document.getElementById('card1ErrorBadge');
                if (cardBadge) cardBadge.classList.remove('d-none');

                if (firstInvalid) {
                    firstInvalid.classList.add('field-error-pulse');
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        firstInvalid.focus();
                    }, 400);
                }
                form.classList.add('was-validated');
                return false;
            }

            // Valid form -> change button to loading state to prevent double submit
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving Supplier & Moving to Catalog...';
            }

            // Let form submit naturally
            return true;
        }, false);
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    attachLiveValidation();
    calculateTotals();
});
</script>

