<?php
/**
 * SalesController
 * Handles Website and WhatsApp sales channel orders creation, viewing, and processing.
 */

require_once BASE_PATH . '/app/Services/StockService.php';
require_once BASE_PATH . '/app/Services/CloudinaryService.php';

class SalesController {

    public function websiteOrderList(): void {
        Response::redirect(url('warehouse/website-orders'));
    }

    private function enhanceVariantsWithDynamicTokens(array $variants): array {
        $allocTags = Database::fetchAll(
            "SELECT ai.variant_id, ai.selected_qr_codes
             FROM allocation_items ai
             JOIN warehouse_allocations wa ON ai.allocation_id = wa.id
             WHERE ai.selected_qr_codes IS NOT NULL AND ai.selected_qr_codes != ''"
        );

        $variantTagsMap = [];
        foreach ($allocTags as $at) {
            $vid = (int)$at['variant_id'];
            if (!isset($variantTagsMap[$vid])) {
                $variantTagsMap[$vid] = [];
            }
            $tags = explode(',', $at['selected_qr_codes']);
            foreach ($tags as $t) {
                $trimmed = trim($t);
                if ($trimmed && !in_array($trimmed, $variantTagsMap[$vid])) {
                    $variantTagsMap[$vid][] = $trimmed;
                }
            }
        }

        // Group variants by product_id to calculate sequence offset for deterministic piece tags
        $productVariantGroups = [];
        foreach ($variants as $vItem) {
            $pId = (int)($vItem['product_id'] ?? 0);
            $productVariantGroups[$pId][] = (int)$vItem['id'];
        }

        $nowMonth = sprintf('%02d', date('n'));
        $nowYear  = date('y');

        foreach ($variants as &$v) {
            $vid = (int)$v['id'];
            $pId = (int)($v['product_id'] ?? 0);
            $tagsList = $variantTagsMap[$vid] ?? [];

            // Dynamic Prefix from category/subcategory/sub-subcategory initials (e.g. Girls + Tops -> GT)
            $c1 = !empty($v['category_name']) ? trim($v['category_name']) : (!empty($v['category_code']) ? trim($v['category_code']) : '');
            $c2 = !empty($v['subcategory_name']) ? trim($v['subcategory_name']) : (!empty($v['subcategory_code']) ? trim($v['subcategory_code']) : '');
            $c3 = !empty($v['sub_subcategory_name']) ? trim($v['sub_subcategory_name']) : (!empty($v['sub_subcategory_code']) ? trim($v['sub_subcategory_code']) : '');

            $catPrefix = '';
            if ($c1 !== '') $catPrefix .= strtoupper($c1[0]);
            if ($c2 !== '') $catPrefix .= strtoupper($c2[0]);
            if ($c3 !== '') $catPrefix .= strtoupper($c3[0]);
            if (strlen($catPrefix) < 2 && !empty($v['qr_code_prefix'])) {
                $clean = preg_replace('/[^A-Za-z0-9]/', '', $v['qr_code_prefix']);
                $catPrefix = strtoupper(substr($clean, 0, 3));
            }
            if (empty($catPrefix)) {
                $catPrefix = 'TTS';
            }
            $v['dynamic_prefix'] = strtolower($catPrefix);

            // Compute sequential piece tags for this variant matching the controller offset logic
            $siblingIds = $productVariantGroups[$pId] ?? [$vid];
            $varIndex = array_search($vid, $siblingIds);
            if ($varIndex === false) $varIndex = 0;
            $varOffset = $varIndex * 20;

            for ($seq = 1; $seq <= 20; $seq++) {
                $pTag = $catPrefix . $nowMonth . $nowYear . sprintf("%02d", $varOffset + $seq);
                if (!in_array($pTag, $tagsList)) {
                    $tagsList[] = $pTag;
                }
            }

            $v['piece_tags'] = $tagsList;

            $tokens = [];
            if (!empty($v['sku'])) {
                $tokens[] = strtolower($v['sku']);
                $cleanSku = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $v['sku']));
                if ($cleanSku) $tokens[] = $cleanSku;
            }
            if (!empty($v['qr_code'])) {
                $tokens[] = strtolower($v['qr_code']);
                $cleanQr = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $v['qr_code']));
                if ($cleanQr) $tokens[] = $cleanQr;
            }
            if (!empty($v['product_code'])) {
                $tokens[] = strtolower($v['product_code']);
                $cleanPc = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $v['product_code']));
                if ($cleanPc) $tokens[] = $cleanPc;
            }
            if (!empty($v['category_name'])) $tokens[] = strtolower($v['category_name']);
            if (!empty($v['category_code'])) $tokens[] = strtolower($v['category_code']);
            if (!empty($v['subcategory_name'])) $tokens[] = strtolower($v['subcategory_name']);
            if (!empty($v['subcategory_code'])) $tokens[] = strtolower($v['subcategory_code']);
            if (!empty($v['sub_subcategory_name'])) $tokens[] = strtolower($v['sub_subcategory_name']);
            if (!empty($v['sub_subcategory_code'])) $tokens[] = strtolower($v['sub_subcategory_code']);
            if (!empty($v['prod_category_code'])) $tokens[] = strtolower($v['prod_category_code']);
            if (!empty($v['qr_code_prefix'])) $tokens[] = strtolower($v['qr_code_prefix']);
            if (!empty($v['size_name'])) $tokens[] = strtolower($v['size_name']);
            if (!empty($v['color_name'])) $tokens[] = strtolower($v['color_name']);

            if (!empty($catPrefix)) {
                $tokens[] = strtolower($catPrefix);
            }
            if ($c1 !== '' && $c2 !== '') {
                $tokens[] = strtolower($c1[0] . $c2[0]);
            }

            $concatCatCode = strtolower(($v['category_code'] ?? '') . ($v['subcategory_code'] ?? '') . ($v['sub_subcategory_code'] ?? ''));
            if (!empty($concatCatCode)) $tokens[] = $concatCatCode;
            $concatProdCatCode = strtolower(($v['prod_category_code'] ?? '') . ($v['subcategory_code'] ?? '') . ($v['sub_subcategory_code'] ?? ''));
            if (!empty($concatProdCatCode)) $tokens[] = $concatProdCatCode;
            
            $words = array_filter(preg_split('/[^a-zA-Z0-9]+/', strtolower(($v['product_name'] ?? '') . ' ' . ($v['category_name'] ?? ''))));
            $initials = '';
            foreach ($words as $w) {
                if (strlen($w) > 0) {
                    $initials .= $w[0];
                    $tokens[] = $w;
                    if (strlen($w) >= 2) $tokens[] = substr($w, 0, 2);
                    if (strlen($w) >= 3) $tokens[] = substr($w, 0, 3);
                    if (strlen($w) >= 4) $tokens[] = substr($w, 0, 4);
                }
            }
            if (!empty($initials)) {
                $tokens[] = $initials;
                for ($len = 1; $len <= strlen($initials); $len++) {
                    $tokens[] = substr($initials, 0, $len);
                }
            }

            $tokens[] = 'wmf';
            $tokens[] = 'tp';
            $tokens[] = 'ret';

            // Also add all piece tags directly into search tokens for instant lookup
            if (!empty($v['piece_tags'])) {
                foreach ($v['piece_tags'] as $pt) {
                    $tokens[] = strtolower($pt);
                    $tokens[] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $pt));
                }
            }

            $v['search_tokens'] = array_values(array_unique(array_filter($tokens)));
        }
        unset($v);
        return $variants;
    }

    public function createWebsiteOrder(): void {
        if (!hasPermission('website_orders', 'create') && !hasPermission('website', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create website orders.');
            Response::redirect(url('warehouse/website-orders/history'));
            return;
        }

        $customers = Database::fetchAll("SELECT * FROM customers ORDER BY name ASC");
        $users = Database::fetchAll("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name ASC");
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.product_id, pv.sku, pv.qr_code, pv.mrp, pv.selling_price, p.code as product_code, p.name as product_name,
                    COALESCE(c.name, 'General') as category_name,
                    c.code as category_code,
                    COALESCE(sc.name, '') as subcategory_name,
                    sc.code as subcategory_code,
                    COALESCE(ssc.name, '') as sub_subcategory_name,
                    ssc.code as sub_subcategory_code,
                    p.category_code as prod_category_code,
                    p.qr_code_prefix,
                    COALESCE(sz.name, 'Standard') as size_name,
                    COALESCE(cl.name, '') as color_name,
                    COALESCE(p.gst_percentage, 0.00) as gst_percentage
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
             LEFT JOIN sub_subcategories ssc ON p.sub_subcategory_id = ssc.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );
        $variants = $this->enhanceVariantsWithDynamicTokens($variants);

        $currentUserId = Session::get('user_id');
        $currentUser = $currentUserId ? Database::fetchOne("SELECT full_name FROM users WHERE id = ?", [$currentUserId]) : null;
        $defaultBillerName = $currentUser['full_name'] ?? 'System Administrator';

        Response::view('sales.website.create', [
            'pageTitle'         => 'Create Website Order',
            'activeModule'      => 'website',
            'activeMenu'        => 'wh-website',
            'breadcrumb'        => [
                ['label' => 'Website Orders', 'url' => url('warehouse/website-orders')],
                ['label' => 'Create Website Order']
            ],
            'customers'         => $customers,
            'users'             => $users,
            'variants'          => $variants,
            'defaultBillerName' => $defaultBillerName,
            'currentUserId'     => $currentUserId,
        ]);
    }

    public function storeWebsiteOrder(): void {
        if (!hasPermission('website_orders', 'create') && !hasPermission('website', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create website orders.');
            Response::redirect(url('warehouse/website-orders/history'));
            return;
        }

        CSRF::verify();

        $customerId      = (int)($_POST['customer_id'] ?? 0);
        $customerName    = trim($_POST['customer_name'] ?? '');
        $customerPhone   = trim($_POST['customer_phone'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $customerState   = trim($_POST['customer_state'] ?? '');
        $customerPincode = trim($_POST['customer_pincode'] ?? '');
        $employeeName    = trim($_POST['employee_name'] ?? '');
        $employeeCode    = trim($_POST['employee_code'] ?? '');
        $billerName      = trim($_POST['biller_name'] ?? '');
        $billingStaffId  = (int)($_POST['billing_staff_id'] ?? 0);
        $billerId        = $billingStaffId ?: (int)($_POST['biller_id'] ?? Session::get('user_id') ?? 0);
        $paymentMode     = trim($_POST['payment_mode'] ?? 'ONLINE');
        $paymentStatus   = trim($_POST['payment_status'] ?? 'PAID');
        $shippingCost    = (float)($_POST['shipping_cost'] ?? 0);
        $discountAmount  = max(0.0, (float)($_POST['discount_amount'] ?? 0));
        $gstPercentage   = (float)($_POST['gst_percentage'] ?? 5.0);
        $notes           = trim($_POST['notes'] ?? 'Website E-Commerce Order');
        $items           = $_POST['items'] ?? [];

        if (empty($customerName) || empty($customerPhone) || empty($customerAddress)) {
            Session::flash('error', 'Customer Name, Mobile Number, and Customer Address are required.');
            Response::redirect(url('sales/website/create'));
            return;
        }

        if (!preg_match('/^[a-zA-Z\s.]+$/', $customerName)) {
            Session::flash('error', 'Customer Name must contain only alphabets.');
            Response::redirect(url('sales/website/create'));
            return;
        }

        if (!preg_match('/^\d{10}$/', $customerPhone)) {
            Session::flash('error', 'Customer Phone Number must contain only numbers and be exactly 10 digits.');
            Response::redirect(url('sales/website/create'));
            return;
        }

        if (!empty($customerPincode) && !preg_match('/^\d{6}$/', $customerPincode)) {
            Session::flash('error', 'Pincode must contain only numbers and be exactly 6 digits.');
            Response::redirect(url('sales/website/create'));
            return;
        }

        if (empty($items) || !is_array($items)) {
            Session::flash('error', 'Please select at least one item for the order.');
            Response::redirect(url('sales/website/create'));
            return;
        }

        if (!$customerId && !empty($customerName) && !empty($customerPhone)) {
            Database::execute(
                "INSERT INTO customers (name, phone, address, state, pincode, source_channel, created_at) VALUES (?, ?, ?, ?, ?, 'WEBSITE', NOW())",
                [$customerName, $customerPhone, $customerAddress, $customerState, $customerPincode]
            );
            $customerId = (int)Database::lastInsertId();
        } elseif ($customerId > 0) {
            Database::execute(
                "UPDATE customers SET address = ?, state = ?, pincode = ? WHERE id = ?",
                [$customerAddress, $customerState, $customerPincode, $customerId]
            );
        }

        $fullAddress = $customerAddress;
        if (!empty($customerState)) $fullAddress .= ', ' . $customerState;
        if (!empty($customerPincode)) $fullAddress .= ' - ' . $customerPincode;

        // Upload payment screenshot to Cloudinary only
        $screenshotUrl = null;
        if (isset($_FILES['payment_screenshot']) && ($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $screenshotUrl = CloudinaryService::upload(
                $_FILES['payment_screenshot'],
                'grm_inventory/website_sales/screenshots'
            );
        }

        // Build payment notes & split details
        $paymentDetails = [];
        if ($paymentMode === 'CASH_UPI') {
            $cashAmt = (float)($_POST['cash_amount'] ?? 0);
            $upiAmt  = (float)($_POST['upi_amount'] ?? 0);
            $upiRef  = trim($_POST['upi_reference'] ?? '');
            $paymentDetails[] = "Split Payment [Cash: ₹{$cashAmt}, UPI: ₹{$upiAmt}" . ($upiRef ? ", Ref: {$upiRef}" : "") . "]";
        }
        if (!empty($screenshotUrl)) {
            $paymentDetails[] = "Screenshot (Cloudinary): {$screenshotUrl}";
        }
        if (!empty($paymentDetails)) {
            $notes = implode(" | ", $paymentDetails) . (!empty($notes) ? " -- {$notes}" : "");
        }

        $prefix = 'WEB-' . date('Ymd') . '-';
        $last = Database::fetchOne("SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
        $seq = $last ? ((int)substr($last['invoice_number'], -4) + 1) : 1;
        $invoiceNumber = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $grossTotal = 0.0;
        $totalMrp = 0.0;
        $orderItems = [];

        foreach ($items as $itm) {
            $variantId = (int)($itm['variant_id'] ?? 0);
            $qty       = (int)($itm['quantity'] ?? 0);
            $price     = (float)($itm['unit_price'] ?? 0);
            $scannedQrs = trim($itm['scanned_qr_codes'] ?? '');

            if ($variantId > 0 && $qty > 0) {
                $lineGross = $qty * $price;
                $grossTotal += $lineGross;
                $mrp = (float)($itm['mrp'] ?? $price);
                if ($mrp <= 0) $mrp = $price;
                $totalMrp += ($qty * $mrp);

                // Line-level inclusive tax calculation
                $lineTaxable = round($lineGross / (1 + ($gstPercentage / 100)), 2);
                $lineTax = round($lineGross - $lineTaxable, 2);
                $lineCgst = round($lineTax / 2, 2);
                $lineSgst = round($lineTax - $lineCgst, 2);

                $orderItems[] = [
                    'variant_id'       => $variantId,
                    'quantity'         => $qty,
                    'unit_price'       => $price,
                    'mrp'              => $mrp,
                    'total'            => $lineGross,
                    'taxable_amount'   => $lineTaxable,
                    'cgst_amount'      => $lineCgst,
                    'sgst_amount'      => $lineSgst,
                    'scanned_qr_codes' => !empty($scannedQrs) ? $scannedQrs : null,
                ];
            }
        }

        $calcDiscount = max(0.0, $totalMrp - $grossTotal);
        $discountAmount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : $calcDiscount;
        if ($discountAmount <= 0 && $calcDiscount > 0) {
            $discountAmount = $calcDiscount;
        }

        // Inclusive GST Calculation matching Shop POS: Taxable Base = Billed Gross / (1 + GST%)
        $taxableSubtotal = round($grossTotal / (1 + ($gstPercentage / 100)), 2);
        $gstAmount = round($grossTotal - $taxableSubtotal, 2);
        $cgstAmount = round($gstAmount / 2, 2);
        $sgstAmount = round($gstAmount - $cgstAmount, 2);
        $grandTotalAmount = (float)ceil($grossTotal + $shippingCost);

        $shop = Database::fetchOne("SELECT id FROM shops LIMIT 1");
        $shopId = $shop ? (int)$shop['id'] : null;

        Database::execute(
            "INSERT INTO sales (invoice_number, sale_type, shop_id, customer_id, customer_address, subtotal, discount_amount, taxable_amount, gst_percentage, gst_amount, cgst_amount, sgst_amount, shipping_cost, total_amount, paid_amount, payment_mode, payment_status, status, sales_channel, biller_id, billing_staff_id, employee_name, employee_code, biller_name, notes, created_at)
             VALUES (?, 'WEBSITE', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DELIVERED', 'WEBSITE', ?, ?, ?, ?, ?, ?, NOW())",
            [$invoiceNumber, $shopId, $customerId ?: null, $fullAddress, $taxableSubtotal, $discountAmount, $taxableSubtotal, $gstPercentage, $gstAmount, $cgstAmount, $sgstAmount, $shippingCost, $grandTotalAmount, ($paymentStatus === 'PAID' ? $grandTotalAmount : 0.0), $paymentMode, $paymentStatus, $billerId ?: null, $billingStaffId ?: null, $employeeName, $employeeCode, $billerName, $notes]
        );
        $saleId = (int)Database::lastInsertId();

        foreach ($orderItems as $oi) {
            Database::execute(
                "INSERT INTO sale_items (sale_id, variant_id, quantity, unit_price, mrp, gst_percentage, cgst_amount, sgst_amount, total_amount, scanned_qr_codes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$saleId, $oi['variant_id'], $oi['quantity'], $oi['unit_price'], $oi['mrp'], $gstPercentage, $oi['cgst_amount'], $oi['sgst_amount'], $oi['total'], $oi['scanned_qr_codes']]
            );

            // Automatically deduct stock from Central Inventory with auto-sync fallback
            $tagNote = !empty($oi['scanned_qr_codes']) ? " [Piece Tags: {$oi['scanned_qr_codes']}]" : "";
            if ($shopId) {
                try {
                    StockService::decrease(
                        $oi['variant_id'],
                        'SHOP',
                        $shopId,
                        'AVAILABLE',
                        (float)$oi['quantity'],
                        'SALE',
                        'sales',
                        $saleId,
                        $billerId ?: 1,
                        "Website Sale: {$invoiceNumber}{$tagNote}"
                    );
                } catch (\Throwable $ex) {
                    try {
                        StockService::increase(
                            $oi['variant_id'],
                            'SHOP',
                            $shopId,
                            'AVAILABLE',
                            (float)$oi['quantity'],
                            'ADJUSTMENT',
                            'sales',
                            $saleId,
                            $billerId ?: 1,
                            "Auto-stock sync for Website sale {$invoiceNumber}"
                        );
                        StockService::decrease(
                            $oi['variant_id'],
                            'SHOP',
                            $shopId,
                            'AVAILABLE',
                            (float)$oi['quantity'],
                            'SALE',
                            'sales',
                            $saleId,
                            $billerId ?: 1,
                            "Website Sale: {$invoiceNumber}{$tagNote}"
                        );
                    } catch (\Throwable $ex2) {
                        error_log("Website sale stock deduction fallback: " . $ex2->getMessage());
                    }
                }
            }
        }

        Session::flash('success', "Website Order #{$invoiceNumber} created successfully.");
        Response::redirect(url('sales/website/view/' . $saleId));
    }

    public function viewWebsiteOrder(int $id): void {
        if (!hasPermission('website_orders', 'view') && !hasPermission('website', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view website orders.');
            Response::redirect(url('warehouse/website-orders'));
            return;
        }

        $sale = Database::fetchOne(
            "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_db_address
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE s.id = ?",
            [$id]
        );

        if (!$sale) {
            Session::flash('error', 'Website order not found.');
            Response::redirect(url('warehouse/website-orders'));
            return;
        }

        $items = Database::fetchAll(
            "SELECT si.*, pv.sku, pv.qr_code, p.name as product_name,
                    COALESCE(c.name, 'General') as category_name,
                    COALESCE(sz.name, 'Standard') as size_name
             FROM sale_items si
             JOIN product_variants pv ON si.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             WHERE si.sale_id = ?",
            [$id]
        );

        Response::view('sales.website.view', [
            'pageTitle'    => 'Website Order: ' . $sale['invoice_number'],
            'activeModule' => 'website',
            'activeMenu'   => 'wh-website',
            'breadcrumb'   => [
                ['label' => 'Website Orders', 'url' => url('warehouse/website-orders')],
                ['label' => $sale['invoice_number']]
            ],
            'sale'         => $sale,
            'items'        => $items,
        ]);
    }

    public function processWebsiteOrder(int $id): void {
        if (!hasPermission('website_orders', 'edit') && !hasPermission('website_orders', 'process') && !hasPermission('website', 'edit')) {
            Session::flash('error', 'Access denied: You do not have permission to process or edit website orders.');
            Response::redirect(url('sales/website/view/' . $id));
            return;
        }

        CSRF::verify();
        $status = trim($_POST['status'] ?? 'COMPLETED');
        Database::execute("UPDATE sales SET status = ?, payment_status = ? WHERE id = ?", [$status, $status === 'CANCELLED' ? 'REFUNDED' : 'PAID', $id]);
        Session::flash('success', "Website Order updated to {$status}.");
        Response::redirect(url('sales/website/view/' . $id));
    }

    // ==========================================
    // WHATSAPP SALES ORDERS
    // ==========================================

    public function whatsappOrderList(): void {
        Response::redirect(url('warehouse/whatsapp-orders'));
    }

    public function createWhatsappOrder(): void {
        if (!hasPermission('whatsapp_orders', 'create') && !hasPermission('whatsapp', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create WhatsApp orders.');
            Response::redirect(url('warehouse/whatsapp-orders/history'));
            return;
        }

        $customers = Database::fetchAll("SELECT * FROM customers ORDER BY name ASC");
        $users = Database::fetchAll("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name ASC");
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.product_id, pv.sku, pv.qr_code, pv.mrp, pv.selling_price, p.code as product_code, p.name as product_name,
                    COALESCE(c.name, 'General') as category_name,
                    c.code as category_code,
                    COALESCE(sc.name, '') as subcategory_name,
                    sc.code as subcategory_code,
                    COALESCE(ssc.name, '') as sub_subcategory_name,
                    ssc.code as sub_subcategory_code,
                    p.category_code as prod_category_code,
                    p.qr_code_prefix,
                    COALESCE(sz.name, 'Standard') as size_name,
                    COALESCE(cl.name, '') as color_name,
                    COALESCE(p.gst_percentage, 0.00) as gst_percentage
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
             LEFT JOIN sub_subcategories ssc ON p.sub_subcategory_id = ssc.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );
        $variants = $this->enhanceVariantsWithDynamicTokens($variants);

        $currentUserId = Session::get('user_id');
        $currentUser = $currentUserId ? Database::fetchOne("SELECT full_name FROM users WHERE id = ?", [$currentUserId]) : null;
        $defaultBillerName = $currentUser['full_name'] ?? 'System Administrator';

        Response::view('sales.whatsapp.create', [
            'pageTitle'         => 'Create WhatsApp Order',
            'activeModule'      => 'whatsapp',
            'activeMenu'        => 'wh-whatsapp',
            'breadcrumb'        => [
                ['label' => 'WhatsApp Orders', 'url' => url('warehouse/whatsapp-orders')],
                ['label' => 'Create WhatsApp Order']
            ],
            'customers'         => $customers,
            'users'             => $users,
            'variants'          => $variants,
            'defaultBillerName' => $defaultBillerName,
            'currentUserId'     => $currentUserId,
        ]);
    }

    public function storeWhatsappOrder(): void {
        if (!hasPermission('whatsapp_orders', 'create') && !hasPermission('whatsapp', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create WhatsApp orders.');
            Response::redirect(url('warehouse/whatsapp-orders/history'));
            return;
        }

        CSRF::verify();

        $customerId      = (int)($_POST['customer_id'] ?? 0);
        $customerName    = trim($_POST['customer_name'] ?? '');
        $customerPhone   = trim($_POST['customer_phone'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $customerState   = trim($_POST['customer_state'] ?? '');
        $customerPincode = trim($_POST['customer_pincode'] ?? '');
        $employeeName    = trim($_POST['employee_name'] ?? '');
        $employeeCode    = trim($_POST['employee_code'] ?? '');
        $billerName      = trim($_POST['biller_name'] ?? '');
        $billingStaffId  = (int)($_POST['billing_staff_id'] ?? 0);
        $billerId        = $billingStaffId ?: (int)($_POST['biller_id'] ?? Session::get('user_id') ?? 0);
        $paymentMode     = trim($_POST['payment_mode'] ?? 'UPI');
        $paymentStatus   = trim($_POST['payment_status'] ?? 'PAID');
        $shippingCost    = (float)($_POST['shipping_cost'] ?? 0);
        $gstPercentage   = (float)($_POST['gst_percentage'] ?? 0);
        $gstAmount       = (float)($_POST['gst_amount'] ?? 0);
        $notes           = trim($_POST['notes'] ?? 'WhatsApp Order');
        $items           = $_POST['items'] ?? [];

        if (empty($customerName) || empty($customerPhone) || empty($customerAddress)) {
            Session::flash('error', 'Customer Name, Mobile Number, and Customer Address are required.');
            Response::redirect(url('sales/whatsapp/create'));
            return;
        }

        if (!preg_match('/^[a-zA-Z\s.]+$/', $customerName)) {
            Session::flash('error', 'Customer Name must contain only alphabets.');
            Response::redirect(url('sales/whatsapp/create'));
            return;
        }

        if (!preg_match('/^\d{10}$/', $customerPhone)) {
            Session::flash('error', 'Customer Phone Number must contain only numbers and be exactly 10 digits.');
            Response::redirect(url('sales/whatsapp/create'));
            return;
        }

        if (!empty($customerPincode) && !preg_match('/^\d{6}$/', $customerPincode)) {
            Session::flash('error', 'Pincode must contain only numbers and be exactly 6 digits.');
            Response::redirect(url('sales/whatsapp/create'));
            return;
        }

        if (empty($items) || !is_array($items)) {
            Session::flash('error', 'Please select at least one item for the order.');
            Response::redirect(url('sales/whatsapp/create'));
            return;
        }

        if (!$customerId && !empty($customerName) && !empty($customerPhone)) {
            Database::execute(
                "INSERT INTO customers (name, phone, address, state, pincode, source_channel, created_at) VALUES (?, ?, ?, ?, ?, 'WHATSAPP', NOW())",
                [$customerName, $customerPhone, $customerAddress, $customerState, $customerPincode]
            );
            $customerId = (int)Database::lastInsertId();
        } elseif ($customerId > 0) {
            Database::execute(
                "UPDATE customers SET address = ?, state = ?, pincode = ? WHERE id = ?",
                [$customerAddress, $customerState, $customerPincode, $customerId]
            );
        }

        $fullAddress = $customerAddress;
        if (!empty($customerState)) $fullAddress .= ', ' . $customerState;
        if (!empty($customerPincode)) $fullAddress .= ' - ' . $customerPincode;

        // Upload payment screenshot to Cloudinary only
        $screenshotUrl = null;
        if (isset($_FILES['payment_screenshot']) && ($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $screenshotUrl = CloudinaryService::upload(
                $_FILES['payment_screenshot'],
                'grm_inventory/whatsapp_sales/screenshots'
            );
        }

        // Build payment notes & split details
        $paymentDetails = [];
        if ($paymentMode === 'CASH_UPI') {
            $cashAmt = (float)($_POST['cash_amount'] ?? 0);
            $upiAmt  = (float)($_POST['upi_amount'] ?? 0);
            $upiRef  = trim($_POST['upi_reference'] ?? '');
            $paymentDetails[] = "Split Payment [Cash: ₹{$cashAmt}, UPI: ₹{$upiAmt}" . ($upiRef ? ", Ref: {$upiRef}" : "") . "]";
        }
        if (!empty($screenshotUrl)) {
            $paymentDetails[] = "Screenshot (Cloudinary): {$screenshotUrl}";
        }
        if (!empty($paymentDetails)) {
            $notes = implode(" | ", $paymentDetails) . (!empty($notes) ? " -- {$notes}" : "");
        }

        $prefix = 'WA-' . date('Ymd') . '-';
        $last = Database::fetchOne("SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
        $seq = $last ? ((int)substr($last['invoice_number'], -4) + 1) : 1;
        $invoiceNumber = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $grossTotal = 0.0;
        $totalMrp = 0.0;
        $orderItems = [];

        foreach ($items as $itm) {
            $variantId = (int)($itm['variant_id'] ?? 0);
            $qty       = (int)($itm['quantity'] ?? 0);
            $price     = (float)($itm['unit_price'] ?? 0);
            $scannedQrs = trim($itm['scanned_qr_codes'] ?? '');

            if ($variantId > 0 && $qty > 0) {
                $sub = $qty * $price;
                $grossTotal += $sub;

                $mrp = (float)($itm['mrp'] ?? $price);
                if ($mrp <= 0) $mrp = $price;
                $totalMrp += ($qty * $mrp);

                $orderItems[] = [
                    'variant_id'       => $variantId,
                    'quantity'         => $qty,
                    'unit_price'       => $price,
                    'mrp'              => $mrp,
                    'total'            => $sub,
                    'scanned_qr_codes' => !empty($scannedQrs) ? $scannedQrs : null,
                ];
            }
        }

        $calcDiscount = max(0.0, $totalMrp - $grossTotal);
        $discountAmount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : $calcDiscount;
        if ($discountAmount <= 0 && $calcDiscount > 0) {
            $discountAmount = $calcDiscount;
        }

        // Inclusive GST Calculation matching Shop POS: Taxable Base = Billed Gross / (1 + GST%)
        $taxableSubtotal = round($grossTotal / (1 + ($gstPercentage / 100)), 2);
        $gstAmount = round($grossTotal - $taxableSubtotal, 2);
        $cgstAmount = round($gstAmount / 2, 2);
        $sgstAmount = round($gstAmount - $cgstAmount, 2);
        $grandTotalAmount = (float)ceil($grossTotal + $shippingCost);

        $shop = Database::fetchOne("SELECT id FROM shops LIMIT 1");
        $shopId = $shop ? (int)$shop['id'] : null;

        Database::execute(
            "INSERT INTO sales (invoice_number, sale_type, shop_id, customer_id, customer_address, subtotal, discount_amount, taxable_amount, gst_percentage, gst_amount, cgst_amount, sgst_amount, shipping_cost, total_amount, paid_amount, payment_mode, payment_status, status, sales_channel, biller_id, billing_staff_id, employee_name, employee_code, biller_name, notes, created_at)
             VALUES (?, 'WHATSAPP', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DELIVERED', 'WHATSAPP', ?, ?, ?, ?, ?, ?, NOW())",
            [$invoiceNumber, $shopId, $customerId ?: null, $fullAddress, $taxableSubtotal, $discountAmount, $taxableSubtotal, $gstPercentage, $gstAmount, $cgstAmount, $sgstAmount, $shippingCost, $grandTotalAmount, ($paymentStatus === 'PAID' ? $grandTotalAmount : 0.0), $paymentMode, $paymentStatus, $billerId ?: null, $billingStaffId ?: null, $employeeName, $employeeCode, $billerName, $notes]
        );
        $saleId = (int)Database::lastInsertId();

        foreach ($orderItems as $oi) {
            Database::execute(
                "INSERT INTO sale_items (sale_id, variant_id, quantity, unit_price, mrp, total_amount, scanned_qr_codes) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$saleId, $oi['variant_id'], $oi['quantity'], $oi['unit_price'], $oi['mrp'], $oi['total'], $oi['scanned_qr_codes']]
            );

            // Automatically deduct stock from WhatsApp Channel inventory & Shop Inventory
            $tagNote = !empty($oi['scanned_qr_codes']) ? " [Piece Tags: {$oi['scanned_qr_codes']}]" : "";
            try {
                StockService::decrease(
                    $oi['variant_id'],
                    'CHANNEL',
                    1,
                    'AVAILABLE',
                    (float)$oi['quantity'],
                    'SALE',
                    'sales',
                    $saleId,
                    $billerId ?: 1,
                    "WhatsApp Sale: {$invoiceNumber}{$tagNote}"
                );
            } catch (\Throwable $ex) {
                // If channel record had no balance, sync or ignore
            }

            if ($shopId) {
                try {
                    StockService::decrease(
                        $oi['variant_id'],
                        'SHOP',
                        $shopId,
                        'AVAILABLE',
                        (float)$oi['quantity'],
                        'SALE',
                        'sales',
                        $saleId,
                        $billerId ?: 1,
                        "WhatsApp Sale: {$invoiceNumber}{$tagNote}"
                    );
                } catch (\Throwable $ex) {
                    // Fallback
                }
            }
        }

        Session::flash('success', "WhatsApp Order #{$invoiceNumber} created successfully.");
        Response::redirect(url('sales/whatsapp/view/' . $saleId));
    }

    public function viewWhatsappOrder(int $id): void {
        if (!hasPermission('whatsapp_orders', 'view') && !hasPermission('whatsapp', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view WhatsApp orders.');
            Response::redirect(url('warehouse/whatsapp-orders'));
            return;
        }

        $sale = Database::fetchOne(
            "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_db_address
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE s.id = ?",
            [$id]
        );

        if (!$sale) {
            Session::flash('error', 'WhatsApp order not found.');
            Response::redirect(url('warehouse/whatsapp-orders'));
            return;
        }

        $items = Database::fetchAll(
            "SELECT si.*, pv.sku, pv.qr_code, p.name as product_name,
                    COALESCE(c.name, 'General') as category_name,
                    COALESCE(sz.name, 'Standard') as size_name
             FROM sale_items si
             JOIN product_variants pv ON si.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             WHERE si.sale_id = ?",
            [$id]
        );

        Response::view('sales.whatsapp.view', [
            'pageTitle'    => 'WhatsApp Order: ' . $sale['invoice_number'],
            'activeModule' => 'whatsapp',
            'activeMenu'   => 'wh-whatsapp',
            'breadcrumb'   => [
                ['label' => 'WhatsApp Orders', 'url' => url('warehouse/whatsapp-orders')],
                ['label' => $sale['invoice_number']]
            ],
            'sale'         => $sale,
            'items'        => $items,
        ]);
    }

    public function processWhatsappOrder(int $id): void {
        if (!hasPermission('whatsapp_orders', 'edit') && !hasPermission('whatsapp_orders', 'process') && !hasPermission('whatsapp', 'edit')) {
            Session::flash('error', 'Access denied: You do not have permission to process or edit WhatsApp orders.');
            Response::redirect(url('sales/whatsapp/view/' . $id));
            return;
        }

        CSRF::verify();
        $status = trim($_POST['status'] ?? 'DELIVERED');
        if ($status === 'COMPLETED') $status = 'DELIVERED';
        Database::execute("UPDATE sales SET status = ?, payment_status = ? WHERE id = ?", [$status, $status === 'CANCELLED' ? 'REFUNDED' : 'PAID', $id]);
        Session::flash('success', "WhatsApp Order updated to {$status}.");
        Response::redirect(url('sales/whatsapp/view/' . $id));
    }
}
