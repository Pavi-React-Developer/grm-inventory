<?php
/**
 * Master Comprehensive Seeder for All Remaining Modules in GRM Inventory
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

echo "========================================================\n";
echo "  FEEDING ALL REMAINING MODULES IN GRM INVENTORY\n";
echo "========================================================\n\n";

// 1. UNITS
echo "1. Seeding Units of Measure...\n";
$units = [
    [1, 'Pieces', 'PCS'],
    [2, 'Meters', 'MTR'],
    [3, 'Kilograms', 'KG'],
    [4, 'Rolls', 'ROLL'],
    [5, 'Boxes / Cartons', 'BOX'],
    [6, 'Sets', 'SET'],
    [7, 'Packets', 'PKT'],
];
foreach ($units as $u) {
    $pdo->prepare("INSERT INTO units (id, name, abbreviation, is_active, created_at, updated_at) 
                   VALUES (?, ?, ?, 1, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE name=VALUES(name), abbreviation=VALUES(abbreviation)")
        ->execute($u);
}
echo "  -> Units seeded.\n";

// 2. SELLERS & SELLER_ROLLS
echo "2. Seeding Fabric Sellers & Fabric Rolls...\n";
$sellers = [
    [1, 'ARUN TEXTILES', 'ARUN TEXTILE MILLS', '9842100001', 'arun@aruntextiles.com', 'Tirupur', 'Tamil Nadu', '124, Rayapuram Main Rd', '100% Cotton Single Jersey', '33AAACT1111A1Z1', '30 Days Net', 'MATERIAL_SUPPLIER'],
    [2, 'SRI MURUGAN FABRICS', 'SRI MURUGAN DYEING MILLS', '9842100002', 'suresh@srimurugan.in', 'Erode', 'Tamil Nadu', '45, Brough Road', 'Organic Cotton Interlock', '33AAACS2222B1Z2', '15 Days Net', 'MATERIAL_SUPPLIER'],
    [3, 'TEXLINE KNITS', 'TEXLINE KNITTING WORKS', '9842100003', 'karthik@texlineknits.com', 'Coimbatore', 'Tamil Nadu', '88, Trichy Road', 'Liva Rayon Fabric', '33AAACT3333C1Z3', 'Immediate Cash', 'MATERIAL_SUPPLIER'],
];
foreach ($sellers as $s) {
    $pdo->prepare("INSERT INTO sellers (id, name, company, phone, email, city, state, address, material_type, gst_number, payment_terms, seller_type, is_active, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                   ON DUPLICATE KEY UPDATE name=VALUES(name), company=VALUES(company)")
        ->execute($s);
}

$pdo->exec("DELETE FROM seller_rolls");
$rollId = 1;
for ($sId = 1; $sId <= 3; $sId++) {
    for ($r = 1; $r <= 4; $r++) {
        $rNum = "ROL-S" . $sId . "-" . sprintf('%03d', $r);
        $w = rand(20, 35);
        $p = $w * 220;
        $pdo->prepare("INSERT INTO seller_rolls (id, seller_id, roll_number, color, design_name, design_count, quantity, weight, price, notes, created_at)
                       VALUES (?, ?, ?, 'Solid Knit', 'Interlock Plain', 1, 1, ?, ?, 'Inspected OK', NOW())")
            ->execute([$rollId++, $sId, $rNum, $w, $p]);
    }
}
echo "  -> Sellers and rolls seeded.\n";

// 3. ASSET CATEGORIES & ASSETS
echo "3. Seeding Fixed Assets & Machinery...\n";
$assetCats = [
    [1, 'Industrial Sewing Machinery', 'SEW_MACH', 'Single needle, overlock, flatlock stitching machines'],
    [2, 'Fabric Cutting Equipment', 'CUT_EQUIP', 'Straight knife cutters, band knife & lay spreading tables'],
    [3, 'Finishing & Pressing Units', 'FIN_PRESS', 'Industrial steam boilers, vacuum tables, thread suckers'],
    [4, 'Material Handling & Storage', 'MAT_HANDL', 'Hydraulic pallet trucks, stackers, heavy duty warehouse racks'],
    [5, 'Retail POS & IT Infrastructure', 'POS_IT', 'POS terminals, touch screens, thermal receipt printers, QR scanners'],
];
foreach ($assetCats as $ac) {
    $pdo->prepare("INSERT INTO asset_categories (id, name, code, description, is_active, created_at)
                   VALUES (?, ?, ?, ?, 1, NOW())
                   ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)")
        ->execute($ac);
}

$pdo->exec("DELETE FROM assets");
$assetsList = [
    [1, 'Juki DDL-8700 High-Speed Lockstitch Machine', 'AST-SEW-001', 1, 'PRODUCTION', 1, 'Stitching Line A', 'Juki', 'DDL-8700', 'SN-JK-8821', 'QR-AST-001', '2025-03-15', 38500.00, 'Apex Machines', 8, 3850.00, 32000.00, 'IN_USE', 'EXCELLENT', 'Plant Head'],
    [2, 'Siruba 747K 4-Thread Overlock Machine', 'AST-SEW-002', 1, 'PRODUCTION', 1, 'Stitching Line B', 'Siruba', '747K-514M2-24', 'SN-SR-4412', 'QR-AST-002', '2025-03-15', 45000.00, 'Apex Machines', 8, 4500.00, 38000.00, 'IN_USE', 'GOOD', 'Plant Head'],
    [3, 'Eastman 8-Inch Straight Knife Fabric Cutter', 'AST-CUT-001', 2, 'PRODUCTION', 1, 'Cutting Section', 'Eastman', 'Brute 627X', 'SN-EM-1029', 'QR-AST-003', '2025-01-10', 62000.00, 'Eastman India', 10, 6000.00, 52000.00, 'IN_USE', 'EXCELLENT', 'Cutting Master'],
    [4, 'Silver Star Industrial Steam Iron & Boiler', 'AST-FIN-001', 3, 'PRODUCTION', 1, 'Ironing Table 1', 'Silver Star', 'SR-5000', 'SN-SS-9933', 'QR-AST-004', '2025-06-20', 28000.00, 'SteamTech Tirupur', 5, 2000.00, 24000.00, 'IN_USE', 'GOOD', 'Finishing Supervisor'],
    [5, 'Hydraulic Hand Pallet Truck 2.5 Ton', 'AST-MHE-001', 4, 'WAREHOUSE', 1, 'Main Inward Bay', 'Nilkamal', 'HPT-2500', 'SN-NK-7711', 'QR-AST-005', '2025-02-01', 22500.00, 'Nilkamal Materials', 7, 2000.00, 19000.00, 'IN_USE', 'GOOD', 'Warehouse Manager'],
    [6, 'Heavy Duty 4-Tier Pallet Racking System', 'AST-RCK-001', 4, 'WAREHOUSE', 1, 'Aisle 1 to 4', 'Godrej', 'RACK-G4', 'SN-GD-1002', 'QR-AST-006', '2024-11-15', 185000.00, 'Godrej Storage', 15, 15000.00, 160000.00, 'IN_USE', 'EXCELLENT', 'Warehouse Manager'],
    [7, 'Epson OmniLink TM-T88VI Thermal Receipt Printer', 'AST-POS-001', 5, 'SHOP', 1, 'Counter 1 POS Desk', 'Epson', 'TM-T88VI', 'SN-EP-3341', 'QR-AST-007', '2025-08-01', 18500.00, 'POS Solutions', 5, 1500.00, 16500.00, 'IN_USE', 'EXCELLENT', 'Store Manager'],
    [8, 'Honeywell Xenon 1900G 2D Barcode & QR Scanner', 'AST-POS-002', 5, 'SHOP', 1, 'Counter 1 POS Desk', 'Honeywell', 'Xenon 1900G', 'SN-HW-5521', 'QR-AST-008', '2025-08-01', 12000.00, 'POS Solutions', 5, 1000.00, 10500.00, 'IN_USE', 'EXCELLENT', 'Store Manager'],
];

$stmtAsset = $pdo->prepare("INSERT INTO assets (
    id, name, asset_code, category_id, location_type, location_id, floor_area, brand, model_number, serial_number, barcode_qr,
    purchase_date, purchase_cost, vendor_name, useful_life_years, salvage_value, current_book_value, status, condition_state, custodian_name, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
foreach ($assetsList as $a) {
    $stmtAsset->execute($a);
}
echo "  -> Fixed assets seeded.\n";

// 4. CUTTING ITEMS, STITCHING ITEMS, QC ITEMS
echo "4. Seeding Production Batch Detailed Items (Cutting, Stitching, QC)...\n";
$pdo->exec("DELETE FROM cutting_items");
$pdo->exec("DELETE FROM stitching_items");
$pdo->exec("DELETE FROM qc_items");

$cBatches = $pdo->query("SELECT id FROM cutting_batches LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
$allVariants = $pdo->query("SELECT id FROM product_variants LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);

$cItemId = 1;
foreach ($cBatches as $cbId) {
    for ($i = 0; $i < 3; $i++) {
        $vId = $allVariants[array_rand($allVariants)];
        $q = rand(80, 200);
        $pdo->prepare("INSERT INTO cutting_items (id, cutting_batch_id, variant_id, size_name, color_name, planned_qty, cut_qty, rejected_qty, wastage_qty)
                       VALUES (?, ?, ?, 'M', 'Navy Blue', ?, ?, 2, 1)")
            ->execute([$cItemId++, $cbId, $vId, $q, $q]);
    }
}

$sBatches = $pdo->query("SELECT id FROM stitching_batches LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
$sItemId = 1;
foreach ($sBatches as $sbId) {
    for ($i = 0; $i < 3; $i++) {
        $vId = $allVariants[array_rand($allVariants)];
        $q = rand(70, 180);
        $pdo->prepare("INSERT INTO stitching_items (id, stitching_batch_id, variant_id, size_name, color_name, received_qty, stitched_qty, rejected_qty, cut_quantity, stitched_quantity)
                       VALUES (?, ?, ?, 'M', 'Navy Blue', ?, ?, 2, ?, ?)")
            ->execute([$sItemId++, $sbId, $vId, $q, $q, $q, $q]);
    }
}

$qcBatches = $pdo->query("SELECT id FROM qc_batches LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
$qcItemId = 1;
foreach ($qcBatches as $qbId) {
    for ($i = 0; $i < 3; $i++) {
        $vId = $allVariants[array_rand($allVariants)];
        $q = rand(70, 180);
        $pass = $q - rand(0, 3);
        $rej = $q - $pass;
        $pdo->prepare("INSERT INTO qc_items (id, qc_batch_id, variant_id, size_name, color_name, inspected_qty, passed_qty, rejected_qty, action_taken)
                       VALUES (?, ?, ?, 'M', 'Navy Blue', ?, ?, ?, 'PASSED')")
            ->execute([$qcItemId++, $qbId, $vId, $q, $pass, $rej]);
    }
}
echo "  -> Production batch line items seeded.\n";

// 5. EXPENSES (Operational Expenses)
echo "5. Seeding Operational Expenses Table...\n";
$pdo->exec("DELETE FROM expenses");
$expenseRows = [
    ['EXP-2026-001', 'PRODUCTION', 1, 'Unit 1 Main Factory', 'PROD_SPARES', 'Juki Machine Replacement Motors & Needles', 2, 'SET', 2400.00, 0.00, 4800.00, 4800.00, 'PAID', 'UPI', '2026-09-02 11:00:00'],
    ['EXP-2026-002', 'PRODUCTION', 1, 'Unit 1 Main Factory', 'PROD_UTILITIES', 'Factory HT Power Tariff Bill - Sept 2026', 1, 'MONTH', 28500.00, 0.00, 28500.00, 28500.00, 'PAID', 'BANK_TRANSFER', '2026-09-05 14:30:00'],
    ['EXP-2026-003', 'WAREHOUSE', 1, 'Central Distribution Hub', 'WH_PACKAGING', '5-Ply Heavy Export Corrugated Cartons (500 Nos)', 500, 'PCS', 48.00, 2400.00, 26400.00, 26400.00, 'PAID', 'BANK_TRANSFER', '2026-09-10 10:15:00'],
    ['EXP-2026-004', 'WAREHOUSE', 1, 'Central Distribution Hub', 'WH_FREIGHT', 'Local Tempo Freight - WH to Flagship Store', 4, 'TRIP', 1200.00, 0.00, 4800.00, 4800.00, 'PAID', 'CASH', '2026-09-12 16:00:00'],
    ['EXP-2026-005', 'SHOP', 1, 'GRM Retail Flagship Store', 'SHOP_RENT', 'Retail Showroom Monthly Commercial Lease', 1, 'MONTH', 35000.00, 0.00, 35000.00, 35000.00, 'PAID', 'BANK_TRANSFER', '2026-09-01 09:00:00'],
    ['EXP-2026-006', 'SHOP', 1, 'GRM Retail Flagship Store', 'SHOP_PETTY', 'Staff Hospitality, Refreshments & Water Cans', 1, 'LOT', 2400.00, 0.00, 2400.00, 2400.00, 'PAID', 'PETTY_CASH', '2026-09-18 18:30:00'],
    ['EXP-2026-007', 'WHATSAPP', 1, 'WhatsApp B2B Channel', 'CHAN_SOFTWARE', 'WhatsApp Cloud Business API Messaging Fees', 1, 'MONTH', 3200.00, 576.00, 3776.00, 3776.00, 'PAID', 'CARD', '2026-09-15 12:00:00'],
    ['EXP-2026-008', 'WEBSITE', 1, 'eCommerce Website Store', 'CHAN_ADS', 'Meta Social Media Advertising Campaign', 1, 'CAMPAIGN', 15000.00, 2700.00, 17700.00, 17700.00, 'PAID', 'CARD', '2026-09-20 15:45:00'],
    ['EXP-2026-009', 'WEBSITE', 1, 'eCommerce Website Store', 'CHAN_COURIER', 'DTDC & Delhivery E-Commerce Parcel Shipping', 85, 'SHIPMENTS', 75.00, 0.00, 6375.00, 6375.00, 'PAID', 'BANK_TRANSFER', '2026-09-22 17:00:00'],
    ['EXP-2026-010', 'OFFICE', 1, 'Head Office', 'OFFICE_SUPPLIES', 'A4 Printing Paper, POS Thermal Ribbons & Stationery', 1, 'LOT', 4150.00, 0.00, 4150.00, 4150.00, 'PAID', 'UPI', '2026-09-24 11:20:00'],
];

$stmtExp = $pdo->prepare("INSERT INTO expenses (
    expense_number, location_type, location_id, location_name, category, item_name, quantity, unit_type,
    unit_cost, tax_amount, total_cost, paid_amount, payment_status, payment_method, expense_date, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($expenseRows as $er) {
    $stmtExp->execute([$er[0], $er[1], $er[2], $er[3], $er[4], $er[5], $er[6], $er[7], $er[8], $er[9], $er[10], $er[11], $er[12], $er[13], $er[14], $er[14]]);
}
echo "  -> Operational expenses seeded.\n";

// 6. CASH REGISTERS & CASH TRANSACTIONS
echo "6. Seeding POS Cash Registers & Register Shifts...\n";
$pdo->exec("DELETE FROM cash_transactions");
$pdo->exec("DELETE FROM cash_registers");

$stmtReg = $pdo->prepare("INSERT INTO cash_registers (
    id, shop_id, register_number, cashier_id, cashier_name, opening_time, closing_time,
    opening_balance, cash_sales_amount, expected_cash, closing_cash_counted, cash_difference, status, created_at
) VALUES (?, 1, ?, 1, 'Store Manager', ?, ?, ?, ?, ?, ?, 0.00, 'CLOSED', ?)");

$stmtTx = $pdo->prepare("INSERT INTO cash_transactions (
    shop_id, register_id, transaction_number, transaction_type, flow_direction, amount, payment_mode, description, created_at
) VALUES (1, ?, ?, ?, ?, ?, 'CASH', ?, ?)");

$txId = 1;
for ($d = 10; $d <= 24; $d++) {
    $shiftId = $d - 9;
    $regNum = "REG-SH1-" . sprintf('%04d', $shiftId);
    $dateStr = "2026-09-" . sprintf('%02d', $d);
    $openAt = "{$dateStr} 09:30:00";
    $closeAt = "{$dateStr} 21:30:00";
    $openFloat = 2000.00;
    $salesCash = rand(8000, 18000);
    $expCash = rand(200, 800);
    $closeCash = $openFloat + $salesCash - $expCash;

    $stmtReg->execute([$shiftId, $regNum, $openAt, $closeAt, $openFloat, $salesCash, $closeCash, $closeCash, $openAt]);

    $stmtTx->execute([$shiftId, "TXN-" . sprintf('%05d', $txId++), 'OPENING_FLOAT', 'IN', $openFloat, 'Shift opening cash drawer float', $openAt]);
    $stmtTx->execute([$shiftId, "TXN-" . sprintf('%05d', $txId++), 'SALE', 'IN', $salesCash, 'Accumulated cash sales for day', "{$dateStr} 20:00:00"]);
    $stmtTx->execute([$shiftId, "TXN-" . sprintf('%05d', $txId++), 'EXPENSE', 'OUT', $expCash, 'Staff snacks and local transport', "{$dateStr} 15:00:00"]);
}
echo "  -> Cash registers and cash transactions seeded.\n";

// 7. RETURNS & EXCHANGES
echo "7. Seeding Customer Returns & Exchanges...\n";
$pdo->exec("DELETE FROM exchange_items");
$pdo->exec("DELETE FROM exchanges");
$pdo->exec("DELETE FROM return_items");
$pdo->exec("DELETE FROM returns");

$salesList = $pdo->query("SELECT id, invoice_number, customer_id, customer_name, customer_phone, total_amount FROM sales LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
$retId = 1;
$exchId = 1;

$stmtRet = $pdo->prepare("INSERT INTO returns (
    id, return_number, return_type, source_type, sale_id, customer_id, warehouse_id,
    refund_amount, total_amount, refund_mode, refund_status, inspected_by, approved_by, status, created_at
) VALUES (?, ?, 'CUSTOMER_RETURN', 'SHOP', ?, ?, 1, ?, ?, 'CASH', 'COMPLETED', 1, 1, 'APPROVED', ?)");

$stmtRetItem = $pdo->prepare("INSERT INTO return_items (
    return_id, variant_id, quantity, refund_amount, item_condition, destination, reason
) VALUES (?, ?, 1, ?, 'GOOD', 'SHOP_FLOOR', 'Size too small, requested swap')");

$stmtExch = $pdo->prepare("INSERT INTO exchanges (
    id, exchange_number, return_id, source_type, sale_id, original_amount, new_amount, difference_amount,
    settlement_mode, payment_action, status, payment_status, created_at
) VALUES (?, ?, ?, 'SHOP', ?, ?, ?, 0.00, 'NONE', 'NONE', 'COMPLETED', 'PAID', ?)");

$stmtExchItem = $pdo->prepare("INSERT INTO exchange_items (
    exchange_id, item_type, variant_id, quantity, unit_price, total_amount
) VALUES (?, 'NEW', ?, 1, ?, ?)");

foreach ($salesList as $s) {
    $rNum = "RET-2026-" . sprintf('%04d', $retId);
    $exNum = "EXC-2026-" . sprintf('%04d', $exchId);
    $dt = "2026-09-" . sprintf('%02d', rand(5, 23)) . " 16:30:00";
    $amt = 699.00;

    $stmtRet->execute([$retId, $rNum, $s['id'], $s['customer_id'], $amt, $amt, $dt]);
    
    // items
    $v1 = $allVariants[0];
    $v2 = $allVariants[1];
    $stmtRetItem->execute([$retId, $v1, $amt]);

    $stmtExch->execute([$exchId, $exNum, $retId, $s['id'], $amt, $amt, $dt]);
    $stmtExchItem->execute([$exchId, $v2, $amt, $amt]);

    $retId++;
    $exchId++;
}
echo "  -> Returns and exchanges seeded.\n";

// 8. SHOP DAMAGES & CHANNEL DAMAGES
echo "8. Seeding Shop & Digital Channel Damages...\n";
$pdo->exec("DELETE FROM shop_damages");
$pdo->exec("DELETE FROM channel_damages");

$stmtShopDmg = $pdo->prepare("INSERT INTO shop_damages (
    shop_id, damage_number, variant_id, quantity, cost_per_unit, total_loss, damage_reason, status, reported_by, created_at
) VALUES (1, ?, ?, ?, ?, ?, ?, 'LOGGED', 1, ?)");

for ($i = 1; $i <= 10; $i++) {
    $dNum = "SH-DMG-" . sprintf('%03d', $i);
    $vId = $allVariants[array_rand($allVariants)];
    $qty = rand(1, 3);
    $price = 450.00;
    $tot = $qty * $price;
    $dt = "2026-09-" . sprintf('%02d', rand(3, 24)) . " 12:00:00";
    $stmtShopDmg->execute([$dNum, $vId, $qty, $price, $tot, 'Customer trial fitting seam tear / display stain', $dt]);
}

$stmtChanDmg = $pdo->prepare("INSERT INTO channel_damages (
    channel_id, damage_number, variant_id, quantity, cost_per_unit, total_loss, damage_reason, status, reported_by, created_at
) VALUES (?, ?, ?, ?, ?, ?, 'Packaging box torn during courier transit delivery', 'LOGGED', 1, ?)");

for ($i = 1; $i <= 8; $i++) {
    $cId = ($i % 2 == 0) ? 1 : 2;
    $dNum = "CHN-DMG-" . sprintf('%03d', $i);
    $vId = $allVariants[array_rand($allVariants)];
    $qty = rand(1, 2);
    $cost = 320.00;
    $loss = $qty * $cost;
    $dt = "2026-09-" . sprintf('%02d', rand(3, 24)) . " 14:00:00";
    $stmtChanDmg->execute([$cId, $dNum, $vId, $qty, $cost, $loss, $dt]);
}
echo "  -> Shop and channel damages seeded.\n";

// 9. STOCK TRANSACTIONS (Stock Ledger Audit)
echo "9. Seeding Stock Ledger Transactions...\n";
$pdo->exec("DELETE FROM stock_transactions");
$stmtStkTx = $pdo->prepare("INSERT INTO stock_transactions (
    transaction_type, variant_id, location_type, location_id, source_type, source_id,
    dest_type, dest_id, quantity, balance_after, reference_type, reference_id, notes, created_by, created_at
) VALUES (?, ?, ?, 1, ?, 1, ?, 1, ?, ?, ?, 1, ?, 1, ?)");

for ($i = 1; $i <= 50; $i++) {
    $vId = $allVariants[array_rand($allVariants)];
    $q = rand(10, 60);
    $bal = rand(80, 250);
    $dt = "2026-09-" . sprintf('%02d', rand(1, 24)) . " " . sprintf('%02d:00:00', rand(9, 19));
    $stmtStkTx->execute([
        'INWARD', $vId, 'WAREHOUSE', 'SUPPLIER', 'WAREHOUSE',
        $q, $bal, 'PURCHASE_GRN', 'Received stock from garment inward verification', $dt
    ]);
}
echo "  -> Stock ledger transactions seeded.\n";

// 10. SYSTEM NOTIFICATIONS
echo "10. Seeding System Notifications...\n";
$pdo->exec("DELETE FROM notifications");
$notifs = [
    ['admin', 'Low Stock Alert', 'Product variant MAK0101-XL-RED stock has dropped below minimum threshold (5 pcs remaining).', 'WARNING', '/grm_inventory/grm_inventory/public/shop/inventory', '2026-09-24 09:15:00'],
    ['admin', 'QC Batch Clearance', 'Production QC Batch #QC-2026-020 has passed with 99.2% quality yield. Ready for packaging.', 'SUCCESS', '/grm_inventory/grm_inventory/public/production/qc', '2026-09-23 17:40:00'],
    ['admin', 'Store Stock Allocation Dispatched', 'Warehouse Allocation #ALC-2026-0018 has been dispatched via Tempo to Flagship Store.', 'INFO', '/grm_inventory/grm_inventory/public/warehouse/allocations', '2026-09-23 11:20:00'],
    ['admin', 'Daily Register Shift Closed', 'Counter 1 register shift closed with ₹14,850 cash collected. Variance: ₹0.00.', 'SUCCESS', '/grm_inventory/grm_inventory/public/shop/cash', '2026-09-23 21:35:00'],
    ['admin', 'Database Backup Created', 'Automatic system database snapshot completed and backup encrypted.', 'SUCCESS', '/grm_inventory/grm_inventory/public/system/backup', '2026-09-24 00:00:00'],
];

$stmtNotif = $pdo->prepare("INSERT INTO notifications (
    user_id, title, message, type, link, is_read, created_at
) VALUES (1, ?, ?, ?, ?, 0, ?)");
foreach ($notifs as $n) {
    $stmtNotif->execute([$n[1], $n[2], $n[3], $n[4], $n[5]]);
}
echo "  -> System notifications seeded.\n";

// 11. DELIVERIES (For online & WhatsApp orders)
echo "11. Seeding Deliveries...\n";
$pdo->exec("DELETE FROM deliveries");
$onlineSales = $pdo->query("SELECT id, total_amount, customer_name FROM sales WHERE sale_type IN ('WEBSITE', 'WHATSAPP') LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
$stmtDeliv = $pdo->prepare("INSERT INTO deliveries (
    sale_id, delivery_number, status, dispatch_date, delivery_date, address, tracking_number, courier_name, notes, created_at
) VALUES (?, ?, 'DELIVERED', ?, ?, 'Customer Address, Tamil Nadu', ?, 'DTDC Express', 'Delivered safely with OTP verification', ?)");

$delId = 1;
foreach ($onlineSales as $os) {
    $dNum = "DEL-2026-" . sprintf('%04d', $delId);
    $dtStr = "2026-09-" . sprintf('%02d', rand(5, 22));
    $track = "DTDC" . rand(10000000, 99999999);
    $stmtDeliv->execute([$os['id'], $dNum, "{$dtStr} 10:00:00", "{$dtStr} 17:30:00", $track, "{$dtStr} 09:30:00"]);
    $delId++;
}
echo "  -> Deliveries seeded.\n";

// 12. BANK DEPOSITS
echo "12. Seeding Bank Deposits...\n";
$pdo->exec("DELETE FROM bank_deposits");
$stmtBank = $pdo->prepare("INSERT INTO bank_deposits (
    shop_id, register_id, deposit_number, bank_name, account_number, amount, slip_number, deposit_date, deposited_by, status, created_at
) VALUES (1, ?, ?, 'HDFC Bank Ltd', '50200012345678', ?, ?, ?, 1, 'VERIFIED', ?)");

for ($d = 10; $d <= 24; $d++) {
    $shiftId = $d - 9;
    $depNum = "DEP-2026-" . sprintf('%04d', $shiftId);
    $amt = rand(10000, 25000);
    $dateStr = "2026-09-" . sprintf('%02d', $d);
    $slip = "HDFC-SLIP-" . rand(10000, 99999);
    $stmtBank->execute([$shiftId, $depNum, $amt, $slip, $dateStr, "{$dateStr} 11:30:00"]);
}
echo "  -> Bank deposits seeded.\n";

// 13. ALLOCATION OUTWARDS
echo "13. Seeding Allocation Outwards (Gatepasses)...\n";
$pdo->exec("DELETE FROM allocation_outwards");
$allocs = $pdo->query("SELECT id, allocation_number, warehouse_id, shop_id FROM warehouse_allocations LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
$stmtOut = $pdo->prepare("INSERT INTO allocation_outwards (
    outward_number, allocation_id, warehouse_id, destination_type, shop_id, total_boxes, total_quantity,
    vehicle_number, driver_name, driver_contact, transporter_name, lr_number, dispatched_by, status, created_at
) VALUES (?, ?, ?, 'SHOP', ?, 4, 180, 'TN-38-AB-4567', 'K. Murugan', '9842199887', 'GRM Fleet Tempo', ?, 1, 'DELIVERED', ?)");

$outId = 1;
foreach ($allocs as $al) {
    $oNum = "GATE-2026-" . sprintf('%04d', $outId);
    $lr = "LR-" . rand(10000, 99999);
    $dt = "2026-09-" . sprintf('%02d', rand(3, 20)) . " 10:30:00";
    $stmtOut->execute([$oNum, $al['id'], $al['warehouse_id'], $al['shop_id'], $lr, $dt]);
    $outId++;
}
echo "  -> Allocation outwards seeded.\n";

// 14. PAYMENTS
echo "14. Seeding Payments...\n";
$pdo->exec("DELETE FROM payments");
$paidSales = $pdo->query("SELECT id, total_amount, payment_mode, created_at FROM sales LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$stmtPay = $pdo->prepare("INSERT INTO payments (
    sale_id, payment_mode, amount, reference_number, payment_date, status, payment_number, created_at
) VALUES (?, ?, ?, ?, ?, 'COMPLETED', ?, ?)");

$pId = 1;
foreach ($paidSales as $ps) {
    $pNum = "PAY-2026-" . sprintf('%05d', $pId);
    $ref = "TXN-" . rand(100000, 999999);
    $stmtPay->execute([$ps['id'], $ps['payment_mode'] ?: 'UPI', $ps['total_amount'], $ref, $ps['created_at'], $pNum, $ps['created_at']]);
    $pId++;
}
echo "  -> Payments seeded.\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "\n========================================================\n";
echo "SUCCESS: All remaining modules populated with rich data!\n";
echo "========================================================\n";
