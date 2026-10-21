<?php
// views/sales/whatsapp/create.php — WhatsApp Sales Order Creation with exact POS Terminal UI & Inclusive GST
?>
<style>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] {
    -moz-appearance: textfield;
}

/* POS Field & Customer Header Styles */
.pos-field-wrap {
    position: relative;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 7px 12px;
    transition: all 0.2s ease;
}
.pos-field-wrap:focus-within {
    border-color: #198754;
    box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.15);
}
.pos-field-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 2px;
    display: block;
}
.pos-field-wrap .pos-input-group .form-control {
    border: none;
    padding: 2px 0;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: transparent;
    box-shadow: none !important;
}
.pos-field-wrap .pos-input-group .input-group-text {
    border: none;
    background: transparent;
    padding: 0 8px 0 0;
    color: #94a3b8;
    font-size: 14px;
}

/* Explicit Table Editable Inputs */
.item-billed-price-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    width: 125px;
    margin-left: auto;
}
.item-billed-price-wrap .input-group-text {
    background-color: #f8fafc !important;
    border: 1.5px solid #cbd5e1 !important;
    border-right: none !important;
    color: #475569 !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    border-radius: 6px 0 0 6px !important;
    padding: 5px 8px !important;
}
.item-billed-price-input {
    border: 1.5px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    color: #0d6efd !important;
    font-weight: 700 !important;
    font-size: 13.5px !important;
    border-radius: 0 6px 6px 0 !important;
    padding: 5px 8px !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}
.item-billed-price-input:hover {
    border-color: #94a3b8 !important;
}
.item-billed-price-input:focus {
    border-color: #0d6efd !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25) !important;
    outline: none !important;
}

/* POS Table Styles matching Shop POS */
.pos-cart-table-wrapper {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.pos-cart-table-wrapper::-webkit-scrollbar {
    width: 6px;
}
.pos-cart-table-wrapper::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}
.pos-table {
    margin-bottom: 0;
}
.pos-table-head {
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.pos-table-head th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    color: #64748b;
    padding: 11px 12px;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.cart-item-row {
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.15s ease;
}
.cart-item-row:hover {
    background-color: #f8fafc !important;
}
.pos-empty-icon {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background-color: #eff6ff;
    border: 1px solid #dbeafe;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
}
.piece-tag-badge {
    background: #1e293b;
    color: #fbbf24;
    font-family: monospace;
    font-size: 10.5px;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Modifier & Summary Cards */
.pos-modifier-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 12px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.pos-modifier-card:focus-within {
    border-color: #86efac;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.08);
}
.pos-modifier-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #1e293b;
}

/* Net Payable Dark Hero Banner */
.pos-net-payable-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.pos-hero-tag {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.8px;
    color: #94a3b8;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.pos-hero-subtitle {
    font-size: 11px;
}
.pos-hero-total {
    font-size: 28px;
    font-weight: 800;
    color: #38bdf8;
    line-height: 1.1;
    letter-spacing: -0.5px;
}

/* Payment Method Interactive Tiles */
.pos-pay-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 8px;
    border-radius: 12px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center;
}
.pos-pay-tile:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateY(-1px);
}
.pos-pay-icon {
    font-size: 20px;
    margin-bottom: 3px;
    transition: transform 0.2s ease;
}
.pos-pay-name {
    font-size: 12px;
    font-weight: 600;
    color: #334155;
}
.pos-pay-radio:checked + .pos-pay-tile {
    border-color: #198754;
    background: #f0fdf4;
    box-shadow: 0 0 0 1px #198754, 0 4px 12px rgba(25, 135, 84, 0.12);
}
.pos-pay-radio:checked + .pos-pay-tile .pos-pay-name {
    color: #15803d;
    font-weight: 700;
}
.pos-pay-radio:checked + .pos-pay-tile .pos-pay-icon {
    transform: scale(1.12);
}

/* Checkout Button */
.btn-pos-checkout {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border: none;
    color: #ffffff;
    font-size: 15px;
    letter-spacing: 0.3px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
}
.btn-pos-checkout:hover:not(:disabled) {
    background: linear-gradient(135deg, #15803d 0%, #166534 100%);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(22, 163, 74, 0.45);
}
.btn-pos-checkout:disabled {
    background: #cbd5e1;
    border-color: #cbd5e1;
    color: #64748b;
    box-shadow: none;
    cursor: not-allowed;
    opacity: 0.7;
}
</style>

<div class="container-fluid py-3">
    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center">
            <a href="<?= url('warehouse/whatsapp-orders') ?>" class="btn btn-sm btn-light border rounded-circle me-3" title="Back to WhatsApp Channel">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Order Creation</h4>
                <small class="text-muted"><i class="bi bi-person-check me-1"></i>Biller: <?= htmlspecialchars($defaultBillerName ?? 'System Administrator') ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill"><i class="bi bi-circle-fill me-1 small"></i> QR Scanner Active</span>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="clearCart()">
                <i class="bi bi-trash me-1"></i> Clear Cart
            </button>
        </div>
    </div>

    <form method="POST" action="<?= url('sales/whatsapp/store') ?>" id="whatsappOrderForm" enctype="multipart/form-data" onsubmit="return validateWhatsappForm(event)">
        <?= CSRF::field() ?>
        <input type="hidden" name="customer_id" id="customerIdInput" value="0">
        <input type="hidden" name="biller_name" id="billerNameInput" value="<?= htmlspecialchars($defaultBillerName ?? 'System Administrator') ?>">
        <input type="hidden" name="billing_staff_id" id="billingStaffIdInput" value="<?= htmlspecialchars((string)($currentUserId ?? '1')) ?>">
        <input type="hidden" name="discount_amount" id="formDiscountAmount" value="0.00">
        <input type="hidden" name="taxable_amount" id="formTaxableAmount" value="0.00">
        <input type="hidden" name="gst_percentage" id="formGstPercentage" value="5.00">
        <input type="hidden" name="gst_amount" id="formGstAmount" value="0.00">
        <input type="hidden" name="total_amount" id="formTotalAmount" value="0.00">

        <div class="row g-3">
            <!-- LEFT COLUMN: QR Scanner & Scanned Product Stream -->
            <div class="col-lg-5">
                <!-- QR Scanner Box Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-3 bg-white border-top border-4 border-success">
                    <div class="card-body p-4">
                        <label class="form-label fw-bold text-dark fs-5 mb-2">
                            <i class="bi bi-upc-scan text-success me-2"></i>Scan Product QR Code
                        </label>
                        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden mb-2">
                            <span class="input-group-text bg-success text-white border-0"><i class="bi bi-qr-code fs-4"></i></span>
                            <input type="text" id="qrInput" class="form-control border-0 bg-light font-monospace fw-bold" 
                                   placeholder="Point QR scanner or type code & hit Enter..." autofocus autocomplete="off"
                                   onkeydown="if(event.key==='Enter'){ event.preventDefault(); processManualScan(); }">
                            <button class="btn btn-success px-4 fw-semibold" id="btnManualScan" type="button" onclick="processManualScan()">
                                <i class="bi bi-arrow-right-circle me-1"></i> Add
                            </button>
                        </div>

                        <!-- Live Scan Feedback Alert Bar -->
                        <div id="scanFeedbackToast" class="alert border-0 shadow-sm rounded-3 py-2 px-3 mb-2 d-none align-items-center gap-2" style="background-color:#d1e7dd; color:#0f5132;">
                            <i class="bi bi-check-circle-fill fs-5" id="scanFeedbackIcon"></i>
                            <div id="scanFeedbackMsg" class="fw-semibold small"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Each scanned piece is added one by one.</small>
                            <small class="text-success fw-semibold" id="scanStatus">Ready for next scan...</small>
                        </div>
                    </div>
                </div>

                <!-- Last Scanned Product Details Card -->
                <div class="card border-0 shadow-sm rounded-4 bg-white mb-3" id="lastScannedCard" style="display:none;">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-success mb-0"><i class="bi bi-check-circle-fill me-2"></i>Last Scanned Piece</h6>
                        <span id="lastScanTagBadge"></span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex align-items-center">
                            <div class="rounded-3 bg-light border me-3 d-flex align-items-center justify-content-center overflow-hidden" style="width:70px; height:70px; min-width:70px;">
                                <i class="bi bi-bag-check text-success fs-2"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold text-dark mb-1" id="lastScanName">Product Name</h5>
                                <div class="d-flex flex-wrap gap-2 small text-muted mb-1">
                                    <span class="badge bg-light text-dark border">SKU: <span id="lastScanSku"></span></span>
                                    <span class="badge bg-light text-dark border">Size: <span id="lastScanSize"></span></span>
                                    <span class="badge bg-light text-dark border">Color: <span id="lastScanColor"></span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fs-5 fw-bold text-success">₹<span id="lastScanPrice">0.00</span></div>
                                    <span class="badge bg-success-subtle text-success" id="lastScanStock">Stock Available</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shortcuts Card -->
                <div class="card border-0 bg-light rounded-4 p-3">
                    <div class="d-flex align-items-center text-muted small">
                        <i class="bi bi-keyboard fs-4 me-3 text-secondary"></i>
                        <div>
                            <strong>Fast WhatsApp Shortcuts:</strong> Press <code>Enter</code> to Add piece • Enter customer details and click Save to create order.
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Customer Details & Scanned Items Table -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100 d-flex flex-column">
                    <!-- Customer & Staff Information Header (3x3 Grid of 9 Aligned Fields) -->
                    <div class="p-3 border-bottom bg-light-subtle rounded-top-4">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle bg-success-subtle text-success p-2 d-inline-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                    <i class="bi bi-person-bounding-box"></i>
                                </span>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 13.5px; line-height: 1.2;">Customer &amp; Staff Details</div>
                                    <div class="text-muted" style="font-size: 11px;">Mandatory fields marked with <span class="text-danger fw-bold">*</span></div>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1" style="font-size: 10.5px;">
                                    <i class="bi bi-asterisk me-1"></i>Name, Phone, Address Required
                                </span>
                            </div>
                        </div>

                        <!-- ROW 1: Customer Name, Phone No, Address (3 Cols) -->
                        <div class="row g-2 mb-2">
                            <!-- 1. Customer Name -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="custName">
                                        <i class="bi bi-person text-success me-1"></i>Customer Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input type="text" name="customer_name" id="custName" class="form-control" placeholder="Alphabets only *" required pattern="[A-Za-z\s.]+" oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Phone No -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="custPhone">
                                        <i class="bi bi-telephone text-success me-1"></i>Phone No <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-whatsapp text-success"></i></span>
                                        <input type="tel" name="customer_phone" id="custPhone" class="form-control font-monospace" placeholder="10-digit number only *" required maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Address -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="custAddress">
                                        <i class="bi bi-geo-alt text-success me-1"></i>Address <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                                        <input type="text" name="customer_address" id="custAddress" class="form-control" placeholder="Billing address *" required autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 2: State, Pincode, Order Take Staff ID (3 Cols) -->
                        <div class="row g-2 mb-2">
                            <!-- 4. State -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="custState">
                                        <i class="bi bi-map text-success me-1"></i>State
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-map-fill"></i></span>
                                        <input type="text" name="customer_state" id="custState" class="form-control" placeholder="State (e.g. Tamil Nadu)" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Pincode -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="custPincode">
                                        <i class="bi bi-pin-map text-success me-1"></i>Pincode
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-pin-map-fill"></i></span>
                                        <input type="text" name="customer_pincode" id="custPincode" class="form-control font-monospace" placeholder="6-digit pincode" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 6)" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Order Take Staff ID -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="employeeCodeInput">
                                        <i class="bi bi-card-heading text-success me-1"></i>Order Take Staff ID
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                                        <input type="text" name="employee_code" id="employeeCodeInput" class="form-control" placeholder="Staff ID (e.g. EMP-101)" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 3: Order Take Staff Name, Billing Staff ID, Billing Staff Name (3 Cols) -->
                        <div class="row g-2">
                            <!-- 7. Order Take Staff Name -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="employeeNameInput">
                                        <i class="bi bi-person-workspace text-success me-1"></i>Order Take Staff Name
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill-gear"></i></span>
                                        <input type="text" name="employee_name" id="employeeNameInput" class="form-control" placeholder="Staff full name" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <!-- 8. Billing By Staff ID -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="staffIdInput">
                                        <i class="bi bi-person-badge text-success me-1"></i>Billing By Staff ID
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-person-badge-fill"></i></span>
                                        <input type="text" id="staffIdInput" class="form-control fw-semibold" 
                                               value="<?= htmlspecialchars((string)($currentUserId ?? '1')) ?>" 
                                               placeholder="Staff ID (e.g. 4)" autocomplete="off" oninput="document.getElementById('billingStaffIdInput').value = this.value">
                                    </div>
                                </div>
                            </div>

                            <!-- 9. Billing By Staff Name -->
                            <div class="col-12 col-md-4">
                                <div class="pos-field-wrap">
                                    <label class="pos-field-label" for="staffNameInput">
                                        <i class="bi bi-person-check text-success me-1"></i>Billing By Staff Name
                                    </label>
                                    <div class="input-group input-group-sm pos-input-group">
                                        <span class="input-group-text"><i class="bi bi-check-circle-fill text-success"></i></span>
                                        <input type="text" id="staffNameInput" class="form-control fw-semibold" 
                                               value="<?= htmlspecialchars($defaultBillerName ?? 'System Administrator') ?>" 
                                               placeholder="Biller Name" autocomplete="off" oninput="document.getElementById('billerNameInput').value = this.value">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Items Table (Exact Match to POS Terminal) -->
                    <div class="card-body p-0 flex-grow-1 table-responsive pos-cart-table-wrapper" style="max-height: 380px; min-height: 220px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 pos-table" id="cartTable">
                            <thead class="pos-table-head sticky-top">
                                <tr>
                                    <th class="ps-3 text-center" style="width: 38px;">#</th>
                                    <th>ITEM DETAILS</th>
                                    <th class="text-end" style="width: 100px;">BASE PRICE</th>
                                    <th class="text-end" style="width: 90px;">GST</th>
                                    <th class="text-end" style="width: 95px;">DISCOUNT</th>
                                    <th class="text-end" style="width: 130px;">BILLED PRICE</th>
                                    <th class="text-center" style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cartBody">
                                <tr id="emptyCartRow">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <div class="py-4">
                                            <div class="pos-empty-icon mb-3">
                                                <i class="bi bi-bag-plus text-success"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Billing Cart is Empty</h6>
                                            <p class="text-muted small mb-0">Point your scanner at garment QR codes or type SKU on the left to start billing.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cart Summary & Payment Footer (Exact Match to POS Terminal) -->
                    <div class="p-3 border-top bg-light-subtle rounded-bottom-4 pos-billing-footer">
                        <!-- Subtotal & Scanned Badge Bar -->
                        <div class="d-flex justify-content-between align-items-center mb-2.5 pb-2 border-bottom border-light-subtle">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" id="cartItemCountBadge">
                                <i class="bi bi-bag-check-fill"></i> 0 Items Scanned
                            </span>
                            <div class="d-flex align-items-baseline gap-2">
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">TAXABLE BASE SUBTOTAL</span>
                                <span class="fw-bold text-dark fs-5 font-monospace" id="displaySubtotal">₹0.00</span>
                            </div>
                        </div>

                        <!-- GST Breakdown & Discount Summary Grid (2 Columns) -->
                        <div class="row g-2.5 mb-3">
                            <!-- 1. GST Breakdown Summary (Inclusive) -->
                            <div class="col-12 col-md-6">
                                <div class="pos-modifier-card h-100 p-2.5 bg-white rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="pos-modifier-label mb-0">
                                            <i class="bi bi-receipt text-primary me-1"></i>GST (Inclusive)
                                        </span>
                                        <span class="badge bg-success-subtle text-success fw-bold font-monospace px-2 py-0.5" id="displayTax">Incl. ₹0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1 text-muted px-1" style="font-size: 11.5px;">
                                        <span>CGST: <strong id="displayCgst" class="text-dark font-monospace">₹0.00</strong></span>
                                        <span>SGST: <strong id="displaySgst" class="text-dark font-monospace">₹0.00</strong></span>
                                    </div>
                                    <div class="text-muted mt-1 px-1" style="font-size: 10px;">
                                        <i class="bi bi-info-circle me-1"></i>Auto-calculated from product GST %
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Total Discount Applied Summary -->
                            <div class="col-12 col-md-6">
                                <div class="pos-modifier-card h-100 p-2.5 bg-white rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="pos-modifier-label mb-0">
                                            <i class="bi bi-tags-fill text-danger me-1"></i>Total Discount Given
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger fw-bold font-monospace px-2 py-0.5" id="displayDiscount">-₹0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1 text-muted px-1" style="font-size: 11.5px;">
                                        <span>Total Customer Savings:</span>
                                        <strong id="displayDiscountDetail" class="text-danger font-monospace">₹0.00</strong>
                                    </div>
                                    <div class="text-muted mt-1 px-1" style="font-size: 10px;">
                                        <i class="bi bi-check2-all text-success me-1"></i>Calculated from given billing prices
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Net Payable Hero Card (Dark Navy Slate) -->
                        <div class="pos-net-payable-hero rounded-4 p-3 mb-3 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="pos-hero-tag"><i class="bi bi-receipt-cutoff me-1"></i>NET PAYABLE AMOUNT</div>
                                    <div class="pos-hero-subtitle text-white-50">
                                        <i class="bi bi-shield-check text-info me-1"></i>Includes GST &amp; item discounts
                                        <span id="shippingBadge" class="badge bg-info-subtle text-info ms-1 font-monospace" style="font-size: 10px; display: none;"></span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="pos-hero-total font-monospace" id="displayTotal">₹0.00</div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Mode Interactive Tiles (4 Options) -->
                        <div class="mb-3">
                            <label class="pos-field-label mb-2 d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-wallet2 text-primary me-1"></i>Payment Method</span>
                                <span class="text-muted" style="font-size: 10.5px;">Select customer payment mode</span>
                            </label>
                            <div class="row g-2">
                                <!-- Cash Option -->
                                <div class="col-6 col-sm-3">
                                    <input type="radio" class="btn-check pos-pay-radio" name="payment_mode" id="payCash" value="CASH" checked onchange="onPaymentModeChange(this.value)">
                                    <label class="pos-pay-tile w-100 h-100" for="payCash">
                                        <i class="bi bi-cash-stack pos-pay-icon text-success"></i>
                                        <span class="pos-pay-name">Cash</span>
                                    </label>
                                </div>
                                <!-- UPI / QR Option -->
                                <div class="col-6 col-sm-3">
                                    <input type="radio" class="btn-check pos-pay-radio" name="payment_mode" id="payUPI" value="UPI" onchange="onPaymentModeChange(this.value)">
                                    <label class="pos-pay-tile w-100 h-100" for="payUPI">
                                        <i class="bi bi-qr-code-scan pos-pay-icon text-primary"></i>
                                        <span class="pos-pay-name">UPI / QR</span>
                                    </label>
                                </div>
                                <!-- Card Option -->
                                <div class="col-6 col-sm-3">
                                    <input type="radio" class="btn-check pos-pay-radio" name="payment_mode" id="payCard" value="CARD" onchange="onPaymentModeChange(this.value)">
                                    <label class="pos-pay-tile w-100 h-100" for="payCard">
                                        <i class="bi bi-credit-card-2-front pos-pay-icon text-warning"></i>
                                        <span class="pos-pay-name">Card / POS</span>
                                    </label>
                                </div>
                                <!-- Cash + UPI (Split Payment) Option -->
                                <div class="col-6 col-sm-3">
                                    <input type="radio" class="btn-check pos-pay-radio" name="payment_mode" id="payCashUPI" value="CASH_UPI" onchange="onPaymentModeChange(this.value)">
                                    <label class="pos-pay-tile w-100 h-100" for="payCashUPI">
                                        <i class="bi bi-pie-chart-fill pos-pay-icon text-info"></i>
                                        <span class="pos-pay-name">Cash + UPI</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Split Payment Container (Cash + UPI) -->
                        <div id="splitPaymentSection" class="p-3 rounded-3 mb-3 border d-none" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark small">
                                    <i class="bi bi-wallet2 text-success me-1"></i>Cash + UPI Split Breakdown
                                </span>
                                <button type="button" class="btn btn-xs btn-outline-success py-0.5 px-2 rounded-pill fw-semibold" id="btnAutoBalanceSplit" onclick="autoBalanceSplit()" title="Auto-balance remainder into UPI" style="font-size: 11px;">
                                    <i class="bi bi-magic me-1"></i>Auto-Balance
                                </button>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label text-secondary mb-1" style="font-size: 0.76rem; font-weight: 600;">
                                        <i class="bi bi-cash text-success me-1"></i>Cash Amount (₹)
                                    </label>
                                    <input type="number" step="0.01" min="0" name="cash_amount" id="splitCashInput" class="form-control form-control-sm text-end fw-bold font-monospace bg-white" placeholder="0.00" oninput="onSplitCashChange()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary mb-1" style="font-size: 0.76rem; font-weight: 600;">
                                        <i class="bi bi-qr-code text-primary me-1"></i>UPI Amount (₹)
                                    </label>
                                    <input type="number" step="0.01" min="0" name="upi_amount" id="splitUpiInput" class="form-control form-control-sm text-end fw-bold font-monospace bg-white" placeholder="0.00" oninput="updateSplitSummary()">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label text-secondary mb-1" style="font-size: 0.76rem; font-weight: 600;">
                                    <i class="bi bi-receipt text-muted me-1"></i>UPI Ref / Transaction ID / App
                                </label>
                                <input type="text" name="upi_reference" id="splitUpiRef" class="form-control form-control-sm font-monospace bg-white" placeholder="e.g. GPay / PhonePe / Ref #402918237" autocomplete="off">
                            </div>
                            <div class="d-flex align-items-center justify-content-between small pt-2 border-top" style="border-color: #dcfce7 !important; font-size: 0.78rem;">
                                <span class="text-muted">Split Total: <strong id="lblSplitSum" class="text-dark font-monospace">₹0.00</strong></span>
                                <span id="lblSplitDiff" class="badge bg-success-subtle text-success border border-success-subtle">Balanced (₹0.00)</span>
                            </div>
                        </div>

                        <!-- Extra Delivery & Screenshot Row -->
                        <div class="row g-2 mb-3" id="deliveryAndPaymentRow">
                            <!-- Shipping Cost -->
                            <div class="col-6 col-md-6" id="shippingCol">
                                <div class="bg-white p-2 rounded-3 border h-100 d-flex flex-column justify-content-between">
                                    <label class="pos-field-label mb-1"><i class="bi bi-truck text-primary me-1"></i>Shipping (₹)</label>
                                    <input type="number" step="0.01" min="0" name="shipping_cost" id="shippingCostInput" class="form-control form-control-sm text-end fw-bold font-monospace" value="0.00" oninput="updateSummaryTotals()">
                                </div>
                            </div>
                            <!-- Payment Status -->
                            <div class="col-6 col-md-6" id="paymentStatusCol">
                                <div class="bg-white p-2 rounded-3 border h-100 d-flex flex-column justify-content-between">
                                    <label class="pos-field-label mb-1"><i class="bi bi-check2-circle text-success me-1"></i>Payment Status</label>
                                    <select name="payment_status" class="form-select form-select-sm fw-bold font-monospace border-0 bg-light">
                                        <option value="PAID" selected>Paid</option>
                                        <option value="UNPAID">Unpaid / Pending</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Upload Screenshot (Cloudinary) - Hidden on Cash, Enabled on Others -->
                            <div class="col-12 col-md-4 d-none" id="screenshotCol">
                                <div class="bg-white p-2 rounded-3 border h-100 d-flex flex-column justify-content-between">
                                    <label class="pos-field-label mb-1"><i class="bi bi-camera text-info me-1"></i>Payment Screenshot</label>
                                    <input type="file" name="payment_screenshot" id="paymentScreenshotInput" accept="image/*" class="form-control form-control-sm" onchange="previewPaymentScreenshot(this)">
                                </div>
                            </div>
                        </div>

                        <!-- Screenshot Preview -->
                        <div id="screenshotPreviewBox" class="mb-3 d-none align-items-center gap-2 p-2 bg-white rounded-3 border">
                            <img id="screenshotPreviewImg" src="" alt="Screenshot" class="rounded border" style="max-height: 48px; object-fit: contain;">
                            <div>
                                <div class="fw-semibold text-dark small" id="screenshotFileName">filename.png</div>
                                <small class="text-success"><i class="bi bi-cloud-check-fill me-1"></i>Stored securely on Cloudinary</small>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <button type="submit" class="btn btn-pos-checkout btn-lg w-100 rounded-pill py-3 fw-bold shadow-lg position-relative overflow-hidden" id="btnCheckout" disabled>
                            <span class="d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-check-circle-fill fs-5"></i>
                                <span>Save &amp; Create WhatsApp Order</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const variantsList = <?= json_encode($variants ?? []) ?>;
const cart = [];
const scannedPieceTags = new Set();

const qrInput = document.getElementById('qrInput');
const scanStatus = document.getElementById('scanStatus');
const cartBody = document.getElementById('cartBody');
const displaySubtotal = document.getElementById('displaySubtotal');
const displayTax = document.getElementById('displayTax');
const displayCgst = document.getElementById('displayCgst');
const displaySgst = document.getElementById('displaySgst');
const displayDiscount = document.getElementById('displayDiscount');
const displayDiscountDetail = document.getElementById('displayDiscountDetail');
const displayTotal = document.getElementById('displayTotal');
const btnCheckout = document.getElementById('btnCheckout');
const cartItemCountBadge = document.getElementById('cartItemCountBadge');
const orderDiscountInput = document.getElementById('orderDiscountInput');

// Split Payment elements
const splitPaymentSection = document.getElementById('splitPaymentSection');
const splitCashInput = document.getElementById('splitCashInput');
const splitUpiInput = document.getElementById('splitUpiInput');
const lblSplitSum = document.getElementById('lblSplitSum');
const lblSplitDiff = document.getElementById('lblSplitDiff');

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function focusQR() {
    if (qrInput) qrInput.focus();
}

function processManualScan() {
    const rawCode = (qrInput.value || '').trim();
    if (!rawCode) return;

    const matched = findVariantFromCode(rawCode);
    if (!matched) {
        showScanToast(`Product QR Code / SKU <strong>"${escapeHtml(rawCode)}"</strong> not found in active catalog.`, 'danger');
        qrInput.value = '';
        return;
    }

    let pieceTag = rawCode.includes('|') ? rawCode.split('|')[0].trim() : rawCode.trim();
    pieceTag = pieceTag.toUpperCase();

    // Check duplicate piece tag in active cart
    if (pieceTag && scannedPieceTags.has(pieceTag)) {
        showScanToast(`Piece Tag <code class="text-danger fw-bold">${escapeHtml(pieceTag)}</code> is already in the order! Each physical garment has a unique serial QR.`, 'warning');
        flashCartItemRow(pieceTag);
        qrInput.value = '';
        return;
    }

    addItemToCart(matched, pieceTag);
    showLastScanned(matched, pieceTag);
    showScanToast(`Added: <strong>${escapeHtml(matched.product_name)}</strong> (${escapeHtml(matched.size_name || 'Free')}) [Tag: ${escapeHtml(pieceTag)}]`, 'success');

    qrInput.value = '';
    focusQR();
}

function addItemToCart(variant, pieceTag) {
    if (pieceTag) {
        scannedPieceTags.add(pieceTag);
    }

    const cartItemId = 'item_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
    let retailPrice = parseFloat(variant.selling_price) || 0;
    let mrp = parseFloat(variant.mrp) || retailPrice;
    if (retailPrice <= 0 && mrp > 0) retailPrice = mrp;
    if (mrp <= 0 && retailPrice > 0) mrp = retailPrice;
    if (mrp < retailPrice) mrp = retailPrice;
    const gstRate = parseFloat(variant.gst_percentage) || 5.0;

    const discountAmount = mrp > retailPrice ? Math.round((mrp - retailPrice) * 100) / 100 : 0.0;
    const discountPct = mrp > 0 && discountAmount > 0 ? Math.round(((discountAmount / mrp) * 100) * 10) / 10 : 0.0;

    cart.push({
        cart_id: cartItemId,
        variant_id: variant.id,
        product_name: variant.product_name,
        sku: variant.sku || '',
        size_name: variant.size_name || '',
        color_name: variant.color_name || '',
        image: variant.image || null,
        original_price: mrp,
        billed_price: retailPrice,
        mrp: mrp,
        discount_amount: discountAmount,
        discount_percent: discountPct,
        gst_percentage: gstRate,
        quantity: 1,
        scanned_tag: pieceTag
    });

    renderCart();
}

function flashCartItemRow(pieceTag) {
    const row = document.querySelector(`tr[data-piece-tag="${pieceTag}"]`);
    if (row) {
        row.classList.add('table-warning');
        setTimeout(() => row.classList.remove('table-warning'), 1500);
    }
}

function showLastScanned(variant, pieceTag) {
    const card = document.getElementById('lastScannedCard');
    if (!card) return;
    card.style.display = 'block';
    document.getElementById('lastScanName').innerText = variant.product_name;
    document.getElementById('lastScanSku').innerText = variant.sku || '';
    document.getElementById('lastScanSize').innerText = variant.size_name || 'N/A';
    document.getElementById('lastScanColor').innerText = variant.color_name || 'N/A';
    const pVal = parseFloat(variant.selling_price > 0 ? variant.selling_price : (variant.mrp || 0));
    document.getElementById('lastScanPrice').innerText = pVal.toFixed(2);
    document.getElementById('lastScanTagBadge').innerHTML = pieceTag ? `<span class="piece-tag-badge"><i class="bi bi-qr-code"></i>Piece Tag: ${escapeHtml(pieceTag)}</span>` : '';
}

function showScanToast(msg, type = 'success') {
    const toast = document.getElementById('scanFeedbackToast');
    const msgDiv = document.getElementById('scanFeedbackMsg');
    const iconEl = document.getElementById('scanFeedbackIcon');
    if (toast && msgDiv) {
        msgDiv.innerHTML = msg;
        if (type === 'warning') {
            toast.style.backgroundColor = '#fff3cd';
            toast.style.color = '#664d03';
            if (iconEl) iconEl.className = 'bi bi-exclamation-triangle-fill fs-5 text-warning';
        } else if (type === 'danger') {
            toast.style.backgroundColor = '#f8d7da';
            toast.style.color = '#842029';
            if (iconEl) iconEl.className = 'bi bi-x-circle-fill fs-5 text-danger';
        } else {
            toast.style.backgroundColor = '#d1e7dd';
            toast.style.color = '#0f5132';
            if (iconEl) iconEl.className = 'bi bi-check-circle-fill fs-5 text-success';
        }
        toast.classList.remove('d-none');
        toast.classList.add('d-flex');
        clearTimeout(window.scanToastTimer);
        window.scanToastTimer = setTimeout(() => {
            toast.classList.remove('d-flex');
            toast.classList.add('d-none');
        }, 4000);
    }
}

function clearCart() {
    cart.length = 0;
    scannedPieceTags.clear();
    if (orderDiscountInput) orderDiscountInput.value = '0.00';
    renderCart();
    focusQR();
}

function updateSummaryTotals() {
    let subtotalTaxable = 0;
    let totalGst = 0;
    let totalDiscount = 0;
    let totalBilled = 0;
    let mainGstPct = 5.0;

    cart.forEach(item => {
        const orig = parseFloat(item.original_price) || 0;
        const billed = parseFloat(item.billed_price) >= 0 ? parseFloat(item.billed_price) : orig;
        const gstPct = parseFloat(item.gst_percentage) || 5.0;
        mainGstPct = gstPct;

        const lineTaxable = Math.round((billed / (1 + (gstPct / 100))) * 100) / 100;
        const lineGst = Math.round((billed - lineTaxable) * 100) / 100;
        const disc = orig > billed ? Math.round((orig - billed) * 100) / 100 : 0;

        subtotalTaxable += lineTaxable;
        totalGst += lineGst;
        totalDiscount += disc;
        totalBilled += billed;
    });

    const shipping = parseFloat(document.getElementById('shippingCostInput')?.value) || 0;
    const cgstTotal = Math.round((totalGst / 2) * 100) / 100;
    const sgstTotal = Math.round((totalGst - cgstTotal) * 100) / 100;
    const netTotal = Math.ceil(totalBilled + shipping);

    if (displaySubtotal) displaySubtotal.innerText = `₹${subtotalTaxable.toFixed(2)}`;
    if (displayTax) displayTax.innerText = `Incl. ₹${totalGst.toFixed(2)}`;
    if (displayCgst) displayCgst.innerText = `₹${cgstTotal.toFixed(2)}`;
    if (displaySgst) displaySgst.innerText = `₹${sgstTotal.toFixed(2)}`;
    if (displayDiscount) displayDiscount.innerText = `-₹${totalDiscount.toFixed(2)}`;
    if (displayDiscountDetail) displayDiscountDetail.innerText = `₹${totalDiscount.toFixed(2)}`;
    if (displayTotal) displayTotal.innerText = `₹${netTotal.toFixed(2)}`;

    // Update hidden form inputs
    const fDisc = document.getElementById('formDiscountAmount');
    if (fDisc) fDisc.value = totalDiscount.toFixed(2);
    const fTaxable = document.getElementById('formTaxableAmount');
    if (fTaxable) fTaxable.value = subtotalTaxable.toFixed(2);
    const fGstPct = document.getElementById('formGstPercentage');
    if (fGstPct) fGstPct.value = mainGstPct.toFixed(2);
    const fGstAmt = document.getElementById('formGstAmount');
    if (fGstAmt) fGstAmt.value = totalGst.toFixed(2);
    const fTotal = document.getElementById('formTotalAmount');
    if (fTotal) fTotal.value = netTotal.toFixed(2);

    const shipBadge = document.getElementById('shippingBadge');
    if (shipBadge) {
        if (shipping > 0) {
            shipBadge.style.display = 'inline-block';
            shipBadge.innerText = `Shipping: +₹${shipping.toFixed(2)}`;
        } else {
            shipBadge.style.display = 'none';
        }
    }

    if (cartItemCountBadge) {
        cartItemCountBadge.innerHTML = `<i class="bi bi-bag-check-fill"></i> ${cart.length} ${cart.length === 1 ? 'Item' : 'Items'} Scanned`;
    }

    if (btnCheckout) btnCheckout.disabled = cart.length === 0;

    // Update split payment if active
    const activePayMode = document.querySelector('input[name="payment_mode"]:checked')?.value;
    if (activePayMode === 'CASH_UPI') {
        updateSplitSummary();
    }
}

function renderCart() {
    if (cart.length === 0) {
        cartBody.innerHTML = `
            <tr id="emptyCartRow">
                <td colspan="7" class="text-center py-5 text-muted">
                    <div class="py-4">
                        <div class="pos-empty-icon mb-3">
                            <i class="bi bi-bag-plus text-success"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Billing Cart is Empty</h6>
                        <p class="text-muted small mb-0">Point your scanner at garment QR codes or type SKU on the left to start billing.</p>
                    </div>
                </td>
            </tr>`;
        if (btnCheckout) btnCheckout.disabled = true;
        if (displaySubtotal) displaySubtotal.innerText = '₹0.00';
        if (displayTax) displayTax.innerText = 'Incl. ₹0.00';
        if (displayCgst) displayCgst.innerText = '₹0.00';
        if (displaySgst) displaySgst.innerText = '₹0.00';
        if (displayDiscount) displayDiscount.innerText = '-₹0.00';
        if (displayDiscountDetail) displayDiscountDetail.innerText = '₹0.00';
        if (displayTotal) displayTotal.innerText = '₹0.00';
        if (cartItemCountBadge) cartItemCountBadge.innerHTML = '<i class="bi bi-bag-check-fill"></i> 0 Items Scanned';
        return;
    }

    let html = '';
    cart.forEach((item, index) => {
        const orig = parseFloat(item.original_price) || 0;
        const billed = parseFloat(item.billed_price) >= 0 ? parseFloat(item.billed_price) : orig;
        const gstPercentage = parseFloat(item.gst_percentage) || 5.0;

        const lineTaxable = Math.round((billed / (1 + (gstPercentage / 100))) * 100) / 100;
        const lineGst = Math.round((billed - lineTaxable) * 100) / 100;
        const discountAmount = orig > billed ? Math.round((orig - billed) * 100) / 100 : 0;
        const discountPct = orig > 0 && discountAmount > 0 ? Math.round(((discountAmount / orig) * 100) * 10) / 10 : 0;

        const itemImgHtml = item.image 
            ? `<img src="${escapeHtml(item.image)}" alt="" class="rounded-3 border me-2.5 shadow-sm" style="width:38px; height:38px; object-fit: cover;">`
            : `<div class="rounded-3 bg-light border me-2.5 d-flex align-items-center justify-content-center text-success" style="width:38px; height:38px; min-width:38px;"><i class="bi bi-bag-check fs-5"></i></div>`;

        const tagHtml = item.scanned_tag
            ? `<span class="badge bg-dark-subtle text-dark border border-secondary-subtle font-monospace mt-0.5 d-inline-block" style="font-size:10px;"><i class="bi bi-qr-code text-success me-1"></i>Piece Tag: ${escapeHtml(item.scanned_tag)}</span>`
            : `<span class="badge bg-light text-dark border font-monospace mt-0.5 d-inline-block" style="font-size:10px;"><i class="bi bi-qr-code text-success me-1"></i>QR: ${escapeHtml(item.sku)}</span>`;

        const discountBadgeHtml = discountAmount > 0
            ? `<span class="badge bg-danger-subtle text-danger fw-bold font-monospace" style="font-size: 11px;">-₹${discountAmount.toFixed(2)}</span><div class="text-muted font-monospace mt-0.5" style="font-size: 9.5px;">(${discountPct}% off)</div>`
            : `<span class="badge bg-light text-muted font-monospace" style="font-size: 10.5px;">₹0.00</span>`;

        html += `
            <tr data-cart-id="${item.cart_id}" data-piece-tag="${escapeHtml(item.scanned_tag || '')}" class="cart-item-row align-middle">
                <td class="ps-3 text-center" style="width: 38px;">
                    <span class="badge bg-light text-secondary rounded-pill px-2 py-1 font-monospace" style="font-size: 11px;">${index + 1}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        ${itemImgHtml}
                        <div class="overflow-hidden flex-grow-1">
                            <div class="fw-bold text-dark small text-truncate" title="${escapeHtml(item.product_name)}">${escapeHtml(item.product_name)}</div>
                            <div class="d-flex flex-wrap align-items-center gap-1 mt-0.5" style="font-size:11px;">
                                <span class="badge bg-light text-dark border font-monospace" style="font-size:10px;">${escapeHtml(item.sku)}</span> 
                                ${item.size_name ? `<span class="badge bg-primary-subtle text-primary" style="font-size:10px;">${escapeHtml(item.size_name)}</span>` : ''} 
                                ${item.color_name ? `<span class="badge bg-secondary-subtle text-secondary" style="font-size:10px;">${escapeHtml(item.color_name)}</span>` : ''}
                                <span class="badge bg-light text-secondary border font-monospace item-mrp-badge" style="font-size:10px;" title="Original MRP">MRP: ₹${orig.toFixed(2)}</span>
                            </div>
                            ${tagHtml}
                        </div>
                    </div>
                    <!-- Hidden Form Submissions -->
                    <input type="hidden" name="items[${index}][variant_id]" value="${item.variant_id}">
                    <input type="hidden" name="items[${index}][quantity]" value="1">
                    <input type="hidden" name="items[${index}][unit_price]" class="item-hidden-price" value="${billed.toFixed(2)}">
                    <input type="hidden" name="items[${index}][mrp]" value="${orig.toFixed(2)}">
                    <input type="hidden" name="items[${index}][gst_percentage]" value="${gstPercentage}">
                    <input type="hidden" name="items[${index}][scanned_qr_codes]" value="${escapeHtml(item.scanned_tag || '')}">
                </td>
                <td class="text-end fw-semibold text-secondary font-monospace item-base-price-cell" style="font-size: 12.5px;">
                    ₹${lineTaxable.toFixed(2)}
                </td>
                <td class="text-end item-gst-cell">
                    <span class="badge bg-info-subtle text-info-emphasis font-monospace" style="font-size: 10px;">${gstPercentage}%</span>
                    <div class="text-muted font-monospace mt-0.5 item-gst-val" style="font-size: 10px;">+₹${lineGst.toFixed(2)}</div>
                </td>
                <td class="text-end item-discount-cell" style="width: 105px;">
                    <span class="badge bg-danger-subtle text-danger fw-bold font-monospace item-discount-badge" style="font-size: 11px;">-₹${discountAmount.toFixed(2)}</span>
                    <div class="text-muted font-monospace mt-0.5 item-discount-pct text-end" style="font-size: 9.5px;">(${discountPct}% off)</div>
                </td>
                <td class="text-end" style="width: 140px;">
                    <div class="item-billed-price-wrap">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="any" min="0" 
                               class="form-control form-control-sm text-end fw-bold font-monospace item-billed-price-input" 
                               data-cart-id="${item.cart_id}" 
                               value="${billed.toFixed(2)}" 
                               title="Click to edit Billed Price">
                    </div>
                </td>
                <td class="text-center pe-3" style="width: 40px;">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle p-0 btn-remove-item" data-idx="${index}" title="Remove Item" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </td>
            </tr>`;
    });

    cartBody.innerHTML = html;
    attachCartRowEvents();
    updateSummaryTotals();
}

function attachCartRowEvents() {
    // Live Input on Item Billed Price (Row-level)
    document.querySelectorAll('.item-billed-price-input').forEach(inp => {
        inp.addEventListener('input', function() {
            const cartId = this.getAttribute('data-cart-id');
            const item = cart.find(it => it.cart_id === cartId);
            if (!item) return;

            let enteredVal = parseFloat(this.value);
            if (isNaN(enteredVal) || enteredVal < 0) {
                enteredVal = 0;
            }
            item.billed_price = enteredVal;
            if (item.original_price <= 0 && enteredVal > 0) {
                item.original_price = enteredVal;
                item.mrp = enteredVal;
            }

            const orig = parseFloat(item.original_price) || (parseFloat(item.mrp) || 0);
            if (orig > 0 && enteredVal < orig) {
                item.discount_amount = Math.round((orig - enteredVal) * 100) / 100;
                item.discount_percent = Math.round(((item.discount_amount / orig) * 100) * 10) / 10;
            } else {
                item.discount_amount = 0;
                item.discount_percent = 0;
            }

            const gstPct = parseFloat(item.gst_percentage) || 5.0;
            const lineTaxable = Math.round((enteredVal / (1 + (gstPct / 100))) * 100) / 100;
            const lineGst = Math.round((enteredVal - lineTaxable) * 100) / 100;

            // Direct DOM updates for instant responsiveness without losing focus
            const row = this.closest('tr');
            if (row) {
                const baseCell = row.querySelector('.item-base-price-cell');
                const gstCell = row.querySelector('.item-gst-cell');
                const discBadge = row.querySelector('.item-discount-badge');
                const discPct = row.querySelector('.item-discount-pct');
                const mrpBadge = row.querySelector('.item-mrp-badge');
                const hiddenPrice = row.querySelector('.item-hidden-price');

                if (baseCell) baseCell.innerText = `₹${lineTaxable.toFixed(2)}`;
                if (gstCell) {
                    gstCell.innerHTML = `<span class="badge bg-info-subtle text-info-emphasis font-monospace" style="font-size: 10px;">${gstPct}%</span><div class="text-muted font-monospace mt-0.5 item-gst-val" style="font-size: 10px;">+₹${lineGst.toFixed(2)}</div>`;
                }
                if (discBadge) {
                    discBadge.innerText = `-₹${item.discount_amount.toFixed(2)}`;
                    discBadge.className = item.discount_amount > 0 ? 'badge bg-danger-subtle text-danger fw-bold font-monospace item-discount-badge' : 'badge bg-light text-muted font-monospace item-discount-badge';
                }
                if (discPct) discPct.innerText = `(${item.discount_percent}% off)`;
                if (mrpBadge) mrpBadge.innerText = `MRP: ₹${(parseFloat(item.mrp) || orig).toFixed(2)}`;
                if (hiddenPrice) hiddenPrice.value = enteredVal.toFixed(2);
            }

            updateSummaryTotals();
        });
    });

    // 2. Remove buttons
    document.querySelectorAll('.btn-remove-item').forEach(b => {
        b.addEventListener('click', function() {
            const idx = parseInt(this.getAttribute('data-idx'));
            const item = cart[idx];
            if (item && item.scanned_tag) {
                scannedPieceTags.delete(item.scanned_tag);
            }
            cart.splice(idx, 1);
            renderCart();
            focusQR();
        });
    });
}

function onPaymentModeChange(mode) {
    const screenshotCol = document.getElementById('screenshotCol');
    const screenshotBox = document.getElementById('screenshotPreviewBox');
    const screenshotInput = document.getElementById('paymentScreenshotInput');
    const shippingCol = document.getElementById('shippingCol');
    const paymentStatusCol = document.getElementById('paymentStatusCol');

    if (mode === 'CASH') {
        if (screenshotCol) screenshotCol.classList.add('d-none');
        if (screenshotBox) screenshotBox.classList.add('d-none');
        if (screenshotInput) screenshotInput.value = '';
        if (shippingCol) { shippingCol.className = 'col-6 col-md-6'; }
        if (paymentStatusCol) { paymentStatusCol.className = 'col-6 col-md-6'; }
        if (splitPaymentSection) splitPaymentSection.classList.add('d-none');
    } else {
        if (screenshotCol) screenshotCol.classList.remove('d-none');
        if (shippingCol) { shippingCol.className = 'col-6 col-md-4'; }
        if (paymentStatusCol) { paymentStatusCol.className = 'col-6 col-md-4'; }

        if (mode === 'CASH_UPI') {
            if (splitPaymentSection) splitPaymentSection.classList.remove('d-none');
            autoBalanceSplit();
        } else {
            if (splitPaymentSection) splitPaymentSection.classList.add('d-none');
        }
    }
}

function autoBalanceSplit() {
    const netTotal = parseFloat(document.getElementById('formTotalAmount')?.value) || 0;
    if (!splitCashInput || !splitUpiInput) return;
    const cashVal = parseFloat(splitCashInput.value) || 0;
    if (cashVal > 0 && cashVal < netTotal) {
        splitUpiInput.value = (netTotal - cashVal).toFixed(2);
    } else {
        const half = (netTotal / 2).toFixed(2);
        splitCashInput.value = half;
        splitUpiInput.value = (netTotal - parseFloat(half)).toFixed(2);
    }
    updateSplitSummary();
}

function onSplitCashChange() {
    const netTotal = parseFloat(document.getElementById('formTotalAmount')?.value) || 0;
    const cashVal = parseFloat(splitCashInput ? splitCashInput.value : 0) || 0;
    if (splitUpiInput) {
        splitUpiInput.value = Math.max(0, netTotal - cashVal).toFixed(2);
    }
    updateSplitSummary();
}

function updateSplitSummary() {
    const netTotal = parseFloat(document.getElementById('formTotalAmount')?.value) || 0;
    const cashVal = parseFloat(splitCashInput ? splitCashInput.value : 0) || 0;
    const upiVal = parseFloat(splitUpiInput ? splitUpiInput.value : 0) || 0;
    const splitSum = cashVal + upiVal;
    const diff = netTotal - splitSum;

    if (lblSplitSum) lblSplitSum.textContent = '₹' + splitSum.toFixed(2);
    if (lblSplitDiff) {
        if (Math.abs(diff) < 0.01) {
            lblSplitDiff.className = 'badge bg-success-subtle text-success border border-success-subtle';
            lblSplitDiff.textContent = 'Balanced (₹0.00)';
        } else if (diff > 0) {
            lblSplitDiff.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle';
            lblSplitDiff.textContent = 'Remaining: ₹' + diff.toFixed(2);
        } else {
            lblSplitDiff.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
            lblSplitDiff.textContent = 'Excess: +₹' + Math.abs(diff).toFixed(2);
        }
    }
}

function previewPaymentScreenshot(input) {
    const box = document.getElementById('screenshotPreviewBox');
    const img = document.getElementById('screenshotPreviewImg');
    const nameEl = document.getElementById('screenshotFileName');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (nameEl) nameEl.textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            if (img) img.src = e.target.result;
            if (box) {
                box.classList.remove('d-none');
                box.classList.add('d-flex');
            }
        };
        reader.readAsDataURL(file);
    } else if (box) {
        box.classList.remove('d-flex');
        box.classList.add('d-none');
    }
}

function findVariantFromCode(rawCode) {
    if (!rawCode) return null;
    const clean = rawCode.trim();
    const lower = clean.toLowerCase();
    const cleanAlpha = clean.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();

    // 1. Pipe-separated payload (e.g. "KB102612|BCT0303-12Y-BLK|MRP:3000|Price:2500")
    if (clean.includes('|')) {
        const parts = clean.split('|').map(p => p.trim());
        for (let part of parts) {
            const pLower = part.toLowerCase();
            const pAlpha = part.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
            const exact = variantsList.find(v => 
                (v.sku && (v.sku.toLowerCase() === pLower || v.sku.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === pAlpha)) || 
                (v.qr_code && (v.qr_code.toLowerCase() === pLower || v.qr_code.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === pAlpha)) || 
                (v.product_code && (v.product_code.toLowerCase() === pLower || v.product_code.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === pAlpha)) ||
                (v.piece_tags && Array.isArray(v.piece_tags) && v.piece_tags.some(t => t.toLowerCase() === pLower || t.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === pAlpha))
            );
            if (exact) return exact;
        }
        for (let part of parts) {
            if (part.startsWith('MRP:') || part.startsWith('Price:') || part.startsWith('Disc:') || part.startsWith('Sell:')) continue;
            const m = findVariantFromCode(part);
            if (m) return m;
        }
    }

    // 2. Exact match on Piece Tag (e.g. "GT102621", "KB102612")
    let matched = variantsList.find(v => 
        (v.piece_tags && Array.isArray(v.piece_tags) && v.piece_tags.some(t => t.toLowerCase() === lower || t.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanAlpha))
    );
    if (matched) return matched;

    // 3. Exact match on SKU (with or without hyphens), QR Code, Product Code, or Variant ID
    matched = variantsList.find(v => 
        (v.sku && (v.sku.toLowerCase() === lower || v.sku.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanAlpha)) || 
        (v.qr_code && (v.qr_code.toLowerCase() === lower || v.qr_code.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanAlpha)) || 
        (v.product_code && (v.product_code.toLowerCase() === lower || v.product_code.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanAlpha)) ||
        (v.id && v.id.toString() === clean)
    );
    if (matched) return matched;

    // 4. Match specific variant containing clean SKU or size/color parts (e.g. "34Y", "LAV", "GFF040434YLAV")
    if (cleanAlpha.length >= 3) {
        matched = variantsList.find(v => {
            const vSkuClean = (v.sku || '').replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
            return vSkuClean === cleanAlpha || vSkuClean.endsWith(cleanAlpha) || (cleanAlpha.length >= 6 && vSkuClean.includes(cleanAlpha));
        });
        if (matched) return matched;
    }

    // 5. Piece Tag Prefix match (e.g. "KB102612", "GT102621")
    const tagMatch = clean.match(/^([a-zA-Z]+)(\d+)$/);
    if (tagMatch) {
        const alphaPrefix = tagMatch[1].toLowerCase();
        const prefixMatches = variantsList.filter(v => {
            if (v.dynamic_prefix && v.dynamic_prefix.toLowerCase() === alphaPrefix) return true;
            if (v.qr_code_prefix && v.qr_code_prefix.toLowerCase() === alphaPrefix) return true;
            if (v.search_tokens && Array.isArray(v.search_tokens) && v.search_tokens.includes(alphaPrefix)) return true;
            if (v.category_code && v.category_code.toLowerCase() === alphaPrefix) return true;
            if (v.prod_category_code && v.prod_category_code.toLowerCase() === alphaPrefix) return true;
            if (v.sku && v.sku.toLowerCase().startsWith(alphaPrefix)) return true;
            if (v.product_code && v.product_code.toLowerCase().startsWith(alphaPrefix)) return true;
            return false;
        });

        if (prefixMatches.length > 0) {
            // First check if any variant's piece_tags matches the specific tag
            const pieceMatch = prefixMatches.find(v => v.piece_tags && v.piece_tags.some(t => t.toLowerCase() === lower || t.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanAlpha));
            if (pieceMatch) return pieceMatch;
            return prefixMatches[0];
        }
    }

    // 6. Substring / Token match
    matched = variantsList.find(v => 
        (v.dynamic_prefix && lower.startsWith(v.dynamic_prefix.toLowerCase())) ||
        (v.qr_code_prefix && lower.startsWith(v.qr_code_prefix.toLowerCase())) ||
        (v.sku && (v.sku.toLowerCase().includes(lower) || lower.includes(v.sku.toLowerCase()))) ||
        (v.product_name && v.product_name.toLowerCase().includes(lower))
    );

    return matched || null;
}

function validateWhatsappForm(e) {
    const name = document.getElementById('custName').value.trim();
    const phone = document.getElementById('custPhone').value.trim();
    const address = document.getElementById('custAddress').value.trim();
    const pincode = (document.getElementById('custPincode')?.value || '').trim();

    // 1. Customer Name Validation (Only Alphabets, spaces, dots)
    if (!name) {
        alert('Customer Name is required.');
        document.getElementById('custName').focus();
        e.preventDefault();
        return false;
    }
    if (!/^[a-zA-Z\s.]+$/.test(name)) {
        alert('Customer Name must contain ONLY alphabets (letters).');
        document.getElementById('custName').focus();
        e.preventDefault();
        return false;
    }

    // 2. Phone Number Validation (Only Numbers, Exactly 10 Digits)
    if (!phone) {
        alert('Customer Mobile Number is required.');
        document.getElementById('custPhone').focus();
        e.preventDefault();
        return false;
    }
    if (!/^\d{10}$/.test(phone)) {
        alert('Customer Phone Number must contain ONLY numbers and be EXACTLY 10 digits.');
        document.getElementById('custPhone').focus();
        e.preventDefault();
        return false;
    }

    // 3. Address Validation
    if (!address) {
        alert('Customer Address is required.');
        document.getElementById('custAddress').focus();
        e.preventDefault();
        return false;
    }

    // 4. Pincode Validation (If entered, Only Numbers, Exactly 6 Digits)
    if (pincode && !/^\d{6}$/.test(pincode)) {
        alert('Pincode must contain ONLY numbers and be EXACTLY 6 digits.');
        document.getElementById('custPincode').focus();
        e.preventDefault();
        return false;
    }

    // 5. Cart Items Validation
    if (cart.length === 0) {
        alert('Please scan at least one item into the order.');
        focusQR();
        e.preventDefault();
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    focusQR();
    const initMode = document.querySelector('input[name="payment_mode"]:checked')?.value || 'CASH';
    onPaymentModeChange(initMode);

    // Auto-prefill variant from URL parameters if opened from product inventory
    const urlParams = new URLSearchParams(window.location.search);
    const preVariantId = urlParams.get('variant_id');
    const preSku = urlParams.get('sku');
    const preTag = urlParams.get('tag') || urlParams.get('tag_code');
    const lookupKey = preVariantId || preSku || preTag;
    if (lookupKey) {
        const matched = findVariantFromCode(lookupKey);
        if (matched) {
            addItemToCart(matched, preTag || matched.sku);
            showLastScanned(matched, preTag || matched.sku);
        }
    }
});
</script>
