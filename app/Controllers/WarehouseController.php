<?php
/**
 * Warehouse Controller
 * Unified Controller combining Person 1 (Inward GRN, Stock Ledger, Purchases, Adjustments)
 * and Person 2 (Allocations, Transfers, Returns & QC, Labels, Delivery Tracking).
 */

require_once BASE_PATH . '/app/Services/WarehouseService.php';
require_once BASE_PATH . '/app/Services/StockService.php';
require_once BASE_PATH . '/app/Services/AuditService.php';
require_once BASE_PATH . '/app/Models/Warehouse.php';
require_once BASE_PATH . '/app/Models/Location.php';
require_once BASE_PATH . '/app/Models/WarehouseAllocation.php';
require_once BASE_PATH . '/app/Models/AllocationItem.php';
require_once BASE_PATH . '/app/Models/WarehouseTransfer.php';
require_once BASE_PATH . '/app/Models/TransferItem.php';
require_once BASE_PATH . '/app/Models/WarehouseReturn.php';
require_once BASE_PATH . '/app/Models/WarehouseReturnItem.php';
require_once BASE_PATH . '/app/Models/Shop.php';
require_once BASE_PATH . '/app/Models/Delivery.php';
require_once BASE_PATH . '/app/Models/StockBalance.php';
require_once BASE_PATH . '/app/Models/PurchaseOrder.php';
require_once BASE_PATH . '/app/Models/PurchaseItem.php';
require_once BASE_PATH . '/app/Models/PurchaseInward.php';
require_once BASE_PATH . '/app/Models/PurchaseInwardItem.php';
require_once BASE_PATH . '/app/Models/StockAdjustment.php';
require_once BASE_PATH . '/app/Models/ProductionUnit.php';
require_once BASE_PATH . '/app/Models/ProductionBatch.php';
require_once BASE_PATH . '/app/Models/ProductionOutward.php';
require_once BASE_PATH . '/app/Models/WarehouseDamage.php';
require_once BASE_PATH . '/app/Services/CloudinaryService.php';

class WarehouseController {

    // =========================================================================
    // 1. DASHBOARD
    // =========================================================================

    public function dashboard(): void {
        if (!hasPermission('warehouse', 'view') && !hasPermission('stock', 'view') && !hasPermission('inward', 'view') && !hasPermission('allocation', 'view') && !hasPermission('purchases', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view the warehouse module.');
            Response::redirect(url('admin/dashboard'));
            return;
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $stats = WarehouseService::getDashboardStats($scopedWarehouseId);

        // Also fetch Person 2 outbound stats & recent records for full dashboard compatibility
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1");
        $whStockClause = $scopedWarehouseId ? " AND location_id = $scopedWarehouseId" : "";
        $whAllocClause = $scopedWarehouseId ? " AND warehouse_id = $scopedWarehouseId" : "";
        $whOutClause = $scopedWarehouseId ? " AND ao.warehouse_id = $scopedWarehouseId" : "";
        $whRetClause = $scopedWarehouseId ? " AND warehouse_id = $scopedWarehouseId" : "";

        $person2Stats = [
            'total_stock_units'    => Database::fetchOne("SELECT COALESCE(SUM(quantity), 0) as total FROM stock_balances WHERE stock_state = 'AVAILABLE'$whStockClause")['total'] ?? 0,
            'reserved_stock_units' => Database::fetchOne("SELECT COALESCE(SUM(quantity), 0) as total FROM stock_balances WHERE stock_state = 'RESERVED'$whStockClause")['total'] ?? 0,
            'active_allocations'   => Database::fetchOne("SELECT COUNT(*) as cnt FROM warehouse_allocations WHERE status IN ('REQUESTED', 'APPROVED', 'PICKING', 'PACKING')$whAllocClause")['cnt'] ?? 0,
            'in_transit_transfers' => Database::fetchOne("SELECT COUNT(*) as cnt FROM allocation_outwards ao WHERE 1=1$whOutClause")['cnt'] ?? 0,
            'total_outwards'       => Database::fetchOne("SELECT COUNT(*) as cnt FROM allocation_outwards ao WHERE 1=1$whOutClause")['cnt'] ?? 0,
            'pending_returns'      => Database::fetchOne("SELECT COUNT(*) as cnt FROM warehouse_returns WHERE status IN ('RECEIVED', 'INSPECTING')$whRetClause")['cnt'] ?? 0,
            'dispatches_today'     => Database::fetchOne("SELECT COUNT(*) as cnt FROM warehouse_allocations WHERE status = 'DISPATCHED' AND DATE(updated_at) = CURDATE()$whAllocClause")['cnt'] ?? 0,
        ];

        $recentAllocations = WarehouseAllocation::getAll(array_merge(['limit' => 5], $scopedWarehouseId ? ['warehouse_id' => $scopedWarehouseId] : []));
        $recentOutwards    = Database::fetchAll("SELECT ao.*, wa.allocation_number, 
                                                        COALESCE(NULLIF(ao.total_quantity, 0), (SELECT COALESCE(SUM(dispatched_qty), SUM(requested_qty), 0) FROM allocation_items WHERE allocation_id = wa.id), 0) as real_total_pcs,
                                                        w.name as warehouse_name, s.name as shop_name, sc.name as channel_name 
                                                 FROM allocation_outwards ao 
                                                 JOIN warehouse_allocations wa ON ao.allocation_id = wa.id 
                                                 JOIN warehouses w ON ao.warehouse_id = w.id 
                                                 LEFT JOIN shops s ON ao.shop_id = s.id 
                                                 LEFT JOIN sales_channels sc ON ao.channel_id = sc.id 
                                                 WHERE 1=1$whOutClause
                                                 ORDER BY ao.id DESC LIMIT 5");
        $recentTransfers   = WarehouseTransfer::getAll(array_merge(['limit' => 5], $scopedWarehouseId ? ['warehouse_id' => $scopedWarehouseId] : []));
        $recentReturns     = WarehouseReturn::getAll(array_merge(['limit' => 5], $scopedWarehouseId ? ['warehouse_id' => $scopedWarehouseId] : []));

        Response::view('warehouse.dashboard', array_merge($stats, $person2Stats, [
            'pageTitle'         => 'Warehouse Operations Dashboard',
            'activeModule'      => 'warehouse',
            'activeMenu'        => 'wh-dashboard',
            'warehouses'        => $warehouses,
            'scopedWarehouseId' => $scopedWarehouseId,
            'recentAllocations' => $recentAllocations,
            'recentOutwards'    => $recentOutwards,
            'recentTransfers'   => $recentTransfers,
            'recentReturns'     => $recentReturns,
            'breadcrumb'        => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Dashboard'],
            ],
        ]));
    }

    // =========================================================================
    // 2. INWARD / GRN (Production Finished Goods Receipt) — Person 1
    // =========================================================================

    public function inwardList(): void {
        if (!hasPermission('inward', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view inward shipments.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $scopedUnitId = function_exists('currentProductionUnitId') ? currentProductionUnitId() : null;

        $warehouseId = $scopedWarehouseId ? (string)$scopedWarehouseId : ($_GET['warehouse_id'] ?? '');
        $unitId = $scopedUnitId ? (string)$scopedUnitId : ($_GET['production_unit_id'] ?? $_GET['unit_id'] ?? '');
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT pb.*, 
                       pp.program_number, pp.target_date, pp.name as program_name, pp.production_unit_id as program_unit_id,
                       p.name as product_name, p.code as product_code,
                       w.name as warehouse_name, w.code as warehouse_code,
                       po.outward_number, po.dispatch_date, po.vehicle_number, po.driver_name, po.notes as outward_notes,
                       po.production_unit_id as outward_unit_id,
                       COALESCE(pu_out.name, pu_prog.name, 'Main Production Facility') as production_unit_name,
                       COALESCE(pu_out.code, pu_prog.code, 'PU1') as production_unit_code,
                       COALESCE(pu_out.manager_name, pu_prog.manager_name, '') as production_unit_manager,
                       COALESCE(pu_out.phone, pu_prog.phone, '') as production_unit_phone,
                       COALESCE(pu_out.address, pu_prog.address, '') as production_unit_address,
                       u_prep.full_name as prepared_by_name
                FROM production_batches pb
                JOIN production_programs pp ON pb.program_id = pp.id
                JOIN products p ON pp.product_id = p.id
                JOIN warehouses w ON pb.destination_warehouse_id = w.id
                LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
                LEFT JOIN production_units pu_prog ON pp.production_unit_id = pu_prog.id
                LEFT JOIN production_units pu_out ON po.production_unit_id = pu_out.id
                LEFT JOIN users u_prep ON pb.prepared_by = u_prep.id
                WHERE pb.status IN ('DISPATCHED', 'RECEIVED')";
        $params = [];

        if (!empty($warehouseId)) {
            $sql .= " AND pb.destination_warehouse_id = ?";
            $params[] = (int)$warehouseId;
        }

        if (!empty($unitId)) {
            $sql .= " AND (pp.production_unit_id = ? OR po.production_unit_id = ?)";
            $params[] = (int)$unitId;
            $params[] = (int)$unitId;
        }

        if (!empty($status)) {
            $sql .= " AND pb.status = ?";
            $params[] = $status;
        }

        if (!empty($search)) {
            $sql .= " AND (
                pb.batch_number LIKE ? 
                OR pp.program_number LIKE ? 
                OR po.outward_number LIKE ? 
                OR p.name LIKE ? 
                OR po.vehicle_number LIKE ?
                OR pu_prog.name LIKE ?
                OR pu_out.name LIKE ?
                OR pu_prog.code LIKE ?
                OR pu_out.code LIKE ?
            )";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s, $s]);
        }

        $sql .= " ORDER BY pb.id DESC";
        $batches = Database::fetchAll($sql, $params);
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $productionUnits = function_exists('getScopedProductionUnits') ? getScopedProductionUnits() : ProductionUnit::getAll(['is_active' => 1]);

        // Fetch variant items for all retrieved batches for rich batch-wise card cards UI
        $batchIds = array_column($batches, 'id');
        $batchItemsMap = [];
        if (!empty($batchIds)) {
            $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
            $itemsSql = "SELECT pbi.*, pv.sku, pv.qr_code, pv.size_id, pv.color_id, pv.selling_price,
                                s.name as size_name, c.name as color_name, c.hex_code as color_hex,
                                p.name as product_name, p.image_url as product_image, cat.name as category_name
                         FROM production_batch_items pbi
                         JOIN product_variants pv ON pbi.variant_id = pv.id
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors c ON pv.color_id = c.id
                         WHERE pbi.production_batch_id IN ($placeholders)
                         ORDER BY pbi.id ASC";
            $rawItems = Database::fetchAll($itemsSql, $batchIds);
            foreach ($rawItems as $row) {
                $batchItemsMap[$row['production_batch_id']][] = $row;
            }
        }

        $pendingBatchesCount = 0;
        $totalPendingMissingQty = 0;
        $receivedBatchesCount = 0;
        $totalReceivedQty = 0;

        foreach ($batches as &$b) {
            $b['items'] = $batchItemsMap[$b['id']] ?? [];
            $b['total_variants'] = count($b['items']);

            $batchExpected = (int)($b['total_qty'] ?? $b['quantity'] ?? 0);
            $itemsExpected = 0;
            $itemsReceived = 0;
            $itemsDamaged = 0;
            $itemsMissing = 0;

            foreach ($b['items'] as &$item) {
                $iExp = (int)($item['quantity'] ?? 0);
                $iRec = (int)($item['received_qty'] ?? 0);
                $iDmg = (int)($item['damaged_qty'] ?? 0);
                $iMis = isset($item['missing_qty']) ? (int)$item['missing_qty'] : max(0, $iExp - ($iRec + $iDmg));

                if ($b['status'] === 'RECEIVED' && $iRec === 0 && $iDmg === 0 && $iMis === 0) {
                    $iRec = $iExp;
                    $iMis = 0;
                    $item['received_qty'] = $iRec;
                    $item['missing_qty'] = 0;
                }

                $itemsExpected += $iExp;
                $itemsReceived += $iRec;
                $itemsDamaged += $iDmg;
                $itemsMissing += $iMis;
            }
            unset($item);

            if ($itemsExpected > 0) {
                $batchExpected = $itemsExpected;
                $b['total_qty'] = $itemsExpected;
            }

            if ($b['status'] === 'RECEIVED') {
                $b['received_qty'] = isset($b['received_qty']) && (int)$b['received_qty'] > 0 ? (int)$b['received_qty'] : $itemsReceived;
                $b['damaged_qty'] = isset($b['damaged_qty']) ? (int)$b['damaged_qty'] : $itemsDamaged;
                $b['missing_qty'] = isset($b['missing_qty']) ? (int)$b['missing_qty'] : max(0, $batchExpected - ($b['received_qty'] + $b['damaged_qty']));

                $receivedBatchesCount++;
                $totalReceivedQty += $b['received_qty'];
                if ($b['missing_qty'] > 0) {
                    $totalPendingMissingQty += $b['missing_qty'];
                }
            } else {
                $b['received_qty'] = $itemsReceived;
                $b['damaged_qty'] = $itemsDamaged;
                $b['missing_qty'] = max(0, $batchExpected - ($itemsReceived + $itemsDamaged));
                $pendingBatchesCount++;
                $totalPendingMissingQty += $b['missing_qty'];
            }
        }
        unset($b);

        Response::view('warehouse.inward.index', [
            'pageTitle'              => 'Production Inward (GRN)',
            'activeModule'           => 'warehouse',
            'activeMenu'             => 'wh-inward',
            'batches'                => $batches,
            'warehouses'             => $warehouses,
            'productionUnits'        => $productionUnits,
            'warehouseId'            => $warehouseId,
            'scopedWarehouseId'      => $scopedWarehouseId,
            'unitId'                 => $unitId,
            'status'                 => $status,
            'search'                 => $search,
            'pendingBatchesCount'    => $pendingBatchesCount,
            'totalPendingMissingQty' => $totalPendingMissingQty,
            'receivedBatchesCount'   => $receivedBatchesCount,
            'totalReceivedQty'       => $totalReceivedQty,
            'breadcrumb'             => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Inward / GRN'],
            ],
        ]);
    }

    /**
     * AJAX endpoint to retrieve full batch lifecycle history and traceability logs.
     */
    public function ajaxBatchHistory(string $id): void {
        if (!hasPermission('inward', 'view') && !hasPermission('warehouse', 'view')) {
            Response::jsonError('Access denied: You do not have permission to view batch history.', [], 403);
            return;
        }
        $history = ProductionBatch::getLifecycleHistory($id);
        if (!$history) {
            Response::jsonError('Batch not found or no traceability history recorded.', [], 404);
            return;
        }
        Response::jsonSuccess('Batch lifecycle history loaded successfully', $history);
    }

    public function createInward(): void {
        if (!hasPermission('inward', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create inward shipments.');
            Response::redirect(url('warehouse/inward'));
            return;
        }
        $this->inwardList();
    }

    public function storeInward(): void {
        if (!hasPermission('inward', 'create') && !hasPermission('inward', 'verify')) {
            Session::flash('error', 'Access denied: You do not have permission to process inward shipments.');
            Response::redirect(url('warehouse/inward'));
            return;
        }
        $batchId = (int)($_POST['production_batch_id'] ?? 0);
        $this->verifyInward((string)$batchId);
    }

    public function viewInward(string $id): void {
        if (!hasPermission('inward', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view inward shipments.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $batchId = (int)$id;
        $batch = ProductionBatch::find($batchId);
        if (!$batch) {
            Response::redirect(url('warehouse/inward'), 'error', 'Production batch not found.');
        }

        $outward = ProductionOutward::findByBatchId($batchId);
        $items = ProductionBatch::getItems($batchId);
        $lifecycle = ProductionBatch::getLifecycleHistory($batchId);
        $damages = WarehouseDamage::getByBatchId($batchId);

        $totalExpected = 0;
        $totalReceived = 0;
        $totalDamaged = 0;
        $totalMissing = 0;
        foreach ($items as &$item) {
            $exp = (int)($item['quantity'] ?? 0);
            $rec = (int)($item['received_qty'] ?? 0);
            $dmg = (int)($item['damaged_qty'] ?? 0);
            $mis = isset($item['missing_qty']) ? (int)$item['missing_qty'] : max(0, $exp - ($rec + $dmg));

            if ($batch['status'] === 'RECEIVED' && $rec === 0 && $dmg === 0 && $mis === 0) {
                $rec = $exp;
                $mis = 0;
                $item['received_qty'] = $rec;
                $item['missing_qty'] = 0;
            }

            $totalExpected += $exp;
            $totalReceived += $rec;
            $totalDamaged += $dmg;
            $totalMissing += $mis;
        }
        unset($item);

        if ($batch['status'] === 'RECEIVED') {
            if (isset($batch['received_qty']) && (int)$batch['received_qty'] > 0) {
                $totalReceived = (int)$batch['received_qty'];
            }
            if (isset($batch['damaged_qty'])) {
                $totalDamaged = (int)$batch['damaged_qty'];
            }
            if (isset($batch['missing_qty'])) {
                $totalMissing = (int)$batch['missing_qty'];
            }
        }

        Response::view('warehouse.inward.view', [
            'pageTitle'     => 'Cross-Check Inward Batch #' . $batch['batch_number'],
            'activeModule'  => 'warehouse',
            'activeMenu'    => 'wh-inward',
            'batch'         => $batch,
            'outward'       => $outward,
            'items'         => $items,
            'lifecycle'     => $lifecycle,
            'damages'       => $damages,
            'totalExpected' => $totalExpected,
            'totalReceived' => $totalReceived,
            'totalDamaged'  => $totalDamaged,
            'totalMissing'  => $totalMissing,
            'breadcrumb'    => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Inward / GRN', 'url' => url('warehouse/inward')],
                ['label' => 'Cross-Check Batch #' . $batch['batch_number']],
            ],
        ]);
    }

    public function verifyInward(string $id): void {
        $batchId = (int)$id;
        if (!hasPermission('inward', 'verify') && !hasPermission('inward', 'create') && !hasPermission('inward', 'edit')) {
            Session::flash('error', 'Access denied: You do not have permission to verify or inward stock.');
            Response::redirect(url('warehouse/inward/view/' . $batchId));
            return;
        }
        $notes = trim($_POST['notes'] ?? '');

        $receivedItems = null;
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            $receivedItems = [];
            foreach ($_POST['items'] as $varId => $data) {
                $receivedItems[(int)$varId] = [
                    'received_qty' => (float)($data['received_qty'] ?? 0),
                    'damaged_qty'  => (float)($data['damaged_qty'] ?? 0),
                    'notes'        => trim($data['notes'] ?? ''),
                ];
            }
        }

        try {
            $userId = Session::userId() ?? 1;
            WarehouseService::receiveProductionBatch($batchId, $userId, $notes, $receivedItems);

            $batch = ProductionBatch::find($batchId);
            $batchNum = $batch['batch_number'] ?? "#$batchId";
            Response::redirect(url('warehouse/inward/view/' . $batchId), 'success', "Batch $batchNum successfully verified and credited to warehouse inventory!");
        } catch (Exception $e) {
            Response::redirect(url('warehouse/inward/view/' . $batchId), 'error', "Inward verification failed: " . $e->getMessage());
        }
    }

    public function printBatchQRLabel(string $id): void {
        if (!hasPermission('inward', 'print') && !hasPermission('inward', 'view') && !hasPermission('wh_labels', 'print') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to print batch labels.');
            Response::redirect(url('warehouse/inward'));
            return;
        }
        $batchId = (int)$id;
        $batch = ProductionBatch::find($batchId);
        if (!$batch) {
            Response::error(404, 'Production batch not found.');
            return;
        }
        $items = ProductionBatch::getItems($batchId);
        Response::viewOnly('warehouse.inward.label', [
            'batch' => $batch,
            'items' => $items,
        ]);
    }

    public function printOutwardGatePass(string $id): void {
        if (!hasPermission('inward', 'print') && !hasPermission('inward', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to print outward gatepasses.');
            Response::redirect(url('warehouse/inward'));
            return;
        }
        $batchId = (int)$id;
        $batch = ProductionBatch::find($batchId);
        if (!$batch) {
            Response::error(404, 'Production batch not found.');
            return;
        }
        $outward = ProductionOutward::findByBatchId($batchId);
        $items = ProductionBatch::getItems($batchId);
        Response::viewOnly('warehouse.inward.gatepass', [
            'batch'   => $batch,
            'outward' => $outward,
            'items'   => $items,
        ]);
    }

    public function ajaxCrossCheckScan(): void {
        if (!hasPermission('inward', 'verify') && !hasPermission('inward', 'create') && !hasPermission('inward', 'edit')) {
            Response::jsonError('Access denied: You do not have permission to verify inward scans.', [], 403);
            return;
        }
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $scannedCode = trim($_POST['qr_code'] ?? '');
        $rawScannedMap = $_POST['scanned_map'] ?? [];

        if (is_string($rawScannedMap)) {
            $scannedMap = json_decode($rawScannedMap, true) ?? [];
        } else {
            $scannedMap = (array)$rawScannedMap;
        }

        if ($batchId <= 0) {
            Response::jsonError("Invalid batch identifier.");
            return;
        }

        if (empty($scannedCode)) {
            Response::jsonError("QR code or SKU cannot be empty.");
            return;
        }

        if (str_starts_with($scannedCode, '{') && str_ends_with($scannedCode, '}')) {
            $json = json_decode($scannedCode, true);
            if (!empty($json['sku'])) {
                $scannedCode = $json['sku'];
            } elseif (!empty($json['qr_code'])) {
                $scannedCode = $json['qr_code'];
            } elseif (!empty($json['batch_number'])) {
                $scannedCode = $json['batch_number'];
            }
        }

        try {
            $result = WarehouseService::processCrossCheckScan($batchId, $scannedCode, $scannedMap);
            if (!$result['success']) {
                Response::jsonError($result['message'], $result, 404);
                return;
            }
            Response::jsonSuccess($result['message'], $result);
        } catch (Exception $e) {
            Response::jsonError("Cross-check scan error: " . $e->getMessage());
        }
    }

    public function ajaxLookupBatch(string $code): void {
        if (!hasPermission('inward', 'view') && !hasPermission('warehouse', 'view')) {
            Response::jsonError('Access denied: You do not have permission to look up batches.', [], 403);
            return;
        }
        $code = trim($code);
        $batch = Database::fetchOne(
            "SELECT pb.*, pp.program_number, COALESCE(p.name, pp.name, 'General Apparel') as product_name, p.code as product_code,
                    w.name as warehouse_name, w.code as warehouse_code,
                    po.outward_number, po.dispatch_date, po.vehicle_number
             FROM production_batches pb
             JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN products p ON pp.product_id = p.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
             WHERE pb.batch_number = ? OR po.outward_number = ?",
            [$code, $code]
        );

        if (!$batch) {
            Response::jsonError("No batch found matching '$code'. Please check QR/Batch code.", [], 404);
            return;
        }

        $items = ProductionBatch::getItems((int)$batch['id']);
        Response::jsonSuccess("Batch found.", [
            'batch'    => $batch,
            'items'    => $items,
            'viewUrl'  => url('warehouse/inward/view/' . $batch['id']),
            'labelUrl' => url('warehouse/inward/label/' . $batch['id']),
        ]);
    }

    public function ajaxScanInward(): void {
        if (!hasPermission('inward', 'verify') && !hasPermission('inward', 'create') && !hasPermission('inward', 'edit')) {
            Response::jsonError('Access denied: You do not have permission to receive or inward stock via scan.', [], 403);
            return;
        }
        $code = trim($_POST['qr_code'] ?? '');
        $autoReceive = isset($_POST['auto_receive']) && ($_POST['auto_receive'] === '1' || $_POST['auto_receive'] === 'true');
        $notes = trim($_POST['notes'] ?? 'Scanned & Received via Inward QR Scanner');

        if (empty($code)) {
            Response::jsonError("QR code or batch identifier cannot be empty.");
            return;
        }

        // Clean code and handle JSON / Pipe formats
        $parsedCode = $code;
        $extractedSku = '';
        $extractedTag = '';
        $isSeriesBatch = false;
        $seriesPackQty = 0;
        $seriesStart = '';
        $seriesEnd = '';

        if (str_starts_with($code, '{') && str_ends_with($code, '}')) {
            $json = json_decode($code, true);
            if (!empty($json['type']) && $json['type'] === 'SERIES_BATCH') {
                $isSeriesBatch = true;
                $extractedSku = trim($json['sku'] ?? '');
                $seriesPackQty = (int)($json['qty'] ?? 0);
                $seriesStart = trim($json['start'] ?? '');
                $seriesEnd = trim($json['end'] ?? '');
                $parsedCode = $extractedSku;
            } elseif (!empty($json['batch_number'])) {
                $parsedCode = $json['batch_number'];
            } elseif (!empty($json['outward_number'])) {
                $parsedCode = $json['outward_number'];
            } elseif (!empty($json['sku'])) {
                $extractedSku = trim($json['sku']);
            }
        } elseif (str_contains($code, '|')) {
            $parts = explode('|', $code);
            if (strtoupper($parts[0]) === 'SERIES') {
                $isSeriesBatch = true;
                foreach ($parts as $part) {
                    $partTrim = trim($part);
                    if (str_starts_with($partTrim, 'QTY:')) $seriesPackQty = (int)substr($partTrim, 4);
                    elseif (str_starts_with($partTrim, 'START:')) $seriesStart = trim(substr($partTrim, 6));
                    elseif (str_starts_with($partTrim, 'END:')) $seriesEnd = trim(substr($partTrim, 4));
                    elseif (!str_contains($partTrim, ':') && strtoupper($partTrim) !== 'SERIES') $extractedSku = $partTrim;
                }
                $parsedCode = $extractedSku;
            } else {
                $extractedTag = trim($parts[0]);
                if (!empty($parts[1])) {
                    $extractedSku = trim($parts[1]);
                }
            }
        }

        // Search for matching batch: by batch_number, outward_number, variant SKU, product code, or size-color combination
        $batch = Database::fetchOne(
            "SELECT pb.*, pp.program_number, p.name as product_name, w.name as warehouse_name, w.code as warehouse_code
             FROM production_batches pb
             JOIN production_programs pp ON pb.program_id = pp.id
             JOIN products p ON pp.product_id = p.id
             JOIN warehouses w ON pb.destination_warehouse_id = w.id
             LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
             LEFT JOIN production_batch_items pbi ON pbi.production_batch_id = pb.id
             LEFT JOIN product_variants pv ON pbi.variant_id = pv.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pb.batch_number = ? 
                OR po.outward_number = ?
                OR (? != '' AND pv.sku = ?)
                OR (? != '' AND pv.sku = ?)
                OR (? != '' AND CONCAT(s.name, '-', c.name) = ?)
                OR (? != '' AND CONCAT(s.code, '-', c.name) = ?)
                OR (? != '' AND ? LIKE CONCAT('%', pv.sku, '%'))
                OR (? != '' AND ? LIKE CONCAT('%', pb.batch_number, '%'))
             ORDER BY (pb.status = 'DISPATCHED') DESC, pb.id DESC
             LIMIT 1",
            [
                $parsedCode,
                $parsedCode,
                $extractedSku, $extractedSku,
                $parsedCode, $parsedCode,
                $extractedSku, $extractedSku,
                $extractedSku, $extractedSku,
                $code, $code,
                $code, $code
            ]
        );

        if (!$batch) {
            // Check warehouse_allocations (Channel / Warehouse Stock Allocation)
            $allocation = Database::fetchOne(
                "SELECT wa.*, COALESCE(sc.name, 'Sales Channel') as channel_name, COALESCE(w.name, 'Main Warehouse') as warehouse_name
                 FROM warehouse_allocations wa
                 LEFT JOIN sales_channels sc ON wa.channel_id = sc.id
                 LEFT JOIN warehouses w ON wa.warehouse_id = w.id
                 WHERE wa.allocation_number = ? OR (? REGEXP '^[0-9]+$' AND wa.id = ?) OR (? != '' AND wa.allocation_number LIKE CONCAT('%', ?, '%'))
                 ORDER BY wa.id DESC LIMIT 1",
                [$parsedCode, $parsedCode, (int)$parsedCode, $parsedCode, $parsedCode]
            );

            if ($allocation) {
                $allocId = (int)$allocation['id'];
                $totalQty = (int)Database::fetchColumn(
                    "SELECT COALESCE(SUM(GREATEST(dispatched_qty, requested_qty)), 0) FROM allocation_items WHERE allocation_id = ?",
                    [$allocId]
                );

                if (in_array(strtoupper($allocation['status']), ['COMPLETED', 'RECEIVED'])) {
                    Response::jsonSuccess("Stock Allocation #{$allocation['allocation_number']} is ALREADY received & inwarded into {$allocation['channel_name']}.", [
                        'batch'            => [
                            'id' => $allocation['id'],
                            'batch_number' => $allocation['allocation_number'],
                            'warehouse_name' => $allocation['channel_name'],
                            'total_qty' => $totalQty
                        ],
                        'already_received' => true,
                        'viewUrl'          => url('warehouse/whatsapp-orders/inward')
                    ]);
                    return;
                }

                if ($autoReceive) {
                    Database::execute("UPDATE warehouse_allocations SET status = 'COMPLETED', updated_at = NOW() WHERE id = ?", [$allocId]);
                    Database::execute("UPDATE allocation_outwards SET status = 'DELIVERED', updated_at = NOW() WHERE allocation_id = ?", [$allocId]);
                    $items = Database::fetchAll("SELECT * FROM allocation_items WHERE allocation_id = ?", [$allocId]);
                    foreach ($items as $item) {
                        $qty = (int)($item['dispatched_qty'] > 0 ? $item['dispatched_qty'] : $item['requested_qty']);
                        AllocationItem::updateQuantities($item['id'], ['received_qty' => $qty]);
                        if ($qty > 0 && !empty($item['variant_id'])) {
                            Database::execute(
                                "INSERT INTO stock_balances (variant_id, location_type, location_id, stock_state, quantity, created_at, updated_at)
                                 VALUES (?, 'CHANNEL', ?, 'AVAILABLE', ?, NOW(), NOW())
                                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()",
                                [$item['variant_id'], $allocation['channel_id'] ?? 2, $qty]
                            );
                        }
                    }

                    Response::jsonSuccess("Allocation #{$allocation['allocation_number']} ({$totalQty} pcs) received and inwarded to {$allocation['channel_name']} successfully!", [
                        'batch'    => [
                            'id' => $allocation['id'],
                            'batch_number' => $allocation['allocation_number'],
                            'warehouse_name' => $allocation['channel_name'],
                            'total_qty' => $totalQty
                        ],
                        'credited' => true,
                        'viewUrl'  => url('warehouse/whatsapp-orders/inward')
                    ]);
                    return;
                } else {
                    Response::jsonSuccess("Allocation #{$allocation['allocation_number']} found ({$totalQty} pcs). Destination: {$allocation['channel_name']}.", [
                        'batch'    => [
                            'id' => $allocation['id'],
                            'batch_number' => $allocation['allocation_number'],
                            'warehouse_name' => $allocation['channel_name'],
                            'total_qty' => $totalQty
                        ],
                        'credited' => false,
                        'viewUrl'  => url('warehouse/whatsapp-orders/inward')
                    ]);
                    return;
                }
            }

            // Check direct Product Variant / Series Master QR
            $variant = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code,
                        sz.name as size_name, col.name as color_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 WHERE pv.sku = ? OR pv.qr_code = ? OR pv.series_start_code = ? OR pv.series_end_code = ?
                    OR (? != '' AND pv.sku = ?)
                 LIMIT 1",
                [$parsedCode, $parsedCode, $parsedCode, $parsedCode, $extractedSku, $extractedSku]
            );

            if ($variant) {
                $qtyToCredit = $seriesPackQty > 0 ? $seriesPackQty : ($variant['pack_quantity'] > 0 ? (int)$variant['pack_quantity'] : 1);
                $scopedWhId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
                $selectedWhId = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null;
                $targetWhId = $scopedWhId ?: ($selectedWhId ?: null);
                if ($targetWhId) {
                    $whRow = Database::fetchOne("SELECT id, name, code FROM warehouses WHERE id = ?", [$targetWhId]);
                } else {
                    $whRow = Database::fetchOne("SELECT id, name, code FROM warehouses WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
                }
                $targetWhId = $whRow ? (int)$whRow['id'] : 1;
                $whLabel = ($whRow['name'] ?? 'Main Warehouse') . (!empty($whRow['code']) ? " ({$whRow['code']})" : '');

                if ($autoReceive) {
                    $userId = Session::get('user_id') ?? 1;
                    $seriesInfo = $variant['qr_type'] === 'SERIES_BATCH' || $isSeriesBatch ? " [Master Series Range: " . ($seriesStart ?: $variant['series_start_code']) . " ➔ " . ($seriesEnd ?: $variant['series_end_code']) . "]" : "";
                    StockService::increase(
                        (int)$variant['id'],
                        'WAREHOUSE',
                        $targetWhId,
                        'AVAILABLE',
                        $qtyToCredit,
                        'INWARD_SCAN',
                        'products',
                        (int)$variant['product_id'],
                        $userId,
                        "Inward scanned via Master Series QR ({$qtyToCredit} pcs){$seriesInfo}"
                    );

                    Response::jsonSuccess("Master Series Pack for SKU {$variant['sku']} ({$qtyToCredit} pcs) credited to {$whLabel}!", [
                        'variant'          => $variant,
                        'is_series_master' => true,
                        'pack_quantity'    => $qtyToCredit,
                        'series_range'     => ($seriesStart ?: $variant['series_start_code']) . ' ➔ ' . ($seriesEnd ?: $variant['series_end_code']),
                        'credited'         => true,
                        'viewUrl'          => url('warehouse/catalog/view/' . $variant['product_id'])
                    ]);
                    return;
                } else {
                    Response::jsonSuccess("Master Series Variant {$variant['sku']} ({$qtyToCredit} pcs) loaded.", [
                        'variant'          => $variant,
                        'is_series_master' => true,
                        'pack_quantity'    => $qtyToCredit,
                        'series_range'     => ($seriesStart ?: $variant['series_start_code']) . ' ➔ ' . ($seriesEnd ?: $variant['series_end_code']),
                        'credited'         => false,
                        'viewUrl'          => url('warehouse/catalog/view/' . $variant['product_id'])
                    ]);
                    return;
                }
            }

            Response::jsonError("Batch or product matching '$code' not found in system.");
            return;
        }

        $batchId = (int)$batch['id'];
        $whLabel = $batch['warehouse_name'] . (!empty($batch['warehouse_code']) ? " ({$batch['warehouse_code']})" : "");

        if ($batch['status'] === 'RECEIVED') {
            Response::jsonSuccess("Batch #{$batch['batch_number']} is ALREADY received & stocked in {$whLabel}.", [
                'batch'            => $batch,
                'already_received' => true,
                'viewUrl'          => url('warehouse/inward/view/' . $batchId)
            ]);
            return;
        }

        if ($autoReceive) {
            try {
                $userId = Session::userId() ?? 1;
                WarehouseService::receiveProductionBatch($batchId, $userId, $notes);

                Response::jsonSuccess("Batch #{$batch['batch_number']} ({$batch['total_qty']} pcs) verified and stock credited to {$whLabel}!", [
                    'batch'    => $batch,
                    'credited' => true,
                    'viewUrl'  => url('warehouse/inward/view/' . $batchId)
                ]);
            } catch (Exception $e) {
                Response::jsonError("Auto-inward failed: " . $e->getMessage());
            }
        } else {
            $items = ProductionBatch::getItems($batchId);
            Response::jsonSuccess("Batch #{$batch['batch_number']} destined for {$whLabel} loaded for inspection.", [
                'batch'    => $batch,
                'items'    => $items,
                'credited' => false,
                'viewUrl'  => url('warehouse/inward/view/' . $batchId)
            ]);
        }
    }

    // =========================================================================
    // 3. WAREHOUSE STOCK LEDGER & REAL-TIME BALANCES — Person 1
    // =========================================================================

    public function stockList(): void {
        if (!hasPermission('stock', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view warehouse stock.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $warehouseId = $_GET['warehouse_id'] ?? '';
        $stockState = $_GET['stock_state'] ?? 'AVAILABLE';
        $categoryId = $_GET['category_id'] ?? '';
        $search = $_GET['search'] ?? '';
        $activeTab = $_GET['tab'] ?? 'production'; // Default to Production Inward tab

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        if ($scopedWarehouseId) {
            $selectedWarehouseId = $scopedWarehouseId;
        } else {
            $selectedWarehouseId = !empty($warehouseId) ? (int)$warehouseId : ($warehouses[0]['id'] ?? 1);
        }

        $currentWarehouse = null;
        foreach ($warehouses as $wh) {
            if ((int)$wh['id'] === (int)$selectedWarehouseId) {
                $currentWarehouse = $wh;
                break;
            }
        }
        if (!$currentWarehouse && !empty($warehouses)) {
            $currentWarehouse = $warehouses[0];
        }

        $sql = "SELECT sb.*, 
                       pv.sku, pv.qr_code, pv.selling_price, pv.mrp, pv.production_cost, pv.purchase_price, pv.is_complementary as variant_is_complementary,
                       p.id as product_id, p.name as product_name, p.code as product_code, p.source_type, p.min_stock, p.material, p.image_url, p.is_complementary,
                       p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                       c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name,
                       s.name as size_name, s.code as size_code,
                       col.name as color_name, col.hex_code as color_hex,
                       w.name as warehouse_name, w.code as warehouse_code,
                       sel.name as seller_name, sel.company as seller_company,
                       b.name as brand_name
                FROM stock_balances sb
                JOIN product_variants pv ON sb.variant_id = pv.id
                JOIN products p ON pv.product_id = p.id
                 LEFT JOIN warehouse_categories c ON p.warehouse_category_id = c.id
                 LEFT JOIN warehouse_subcategories sc ON p.warehouse_subcategory_id = sc.id
                 LEFT JOIN warehouse_sub_subcategories ssc ON p.warehouse_sub_subcategory_id = ssc.id
                LEFT JOIN sizes s ON pv.size_id = s.id
                LEFT JOIN colors col ON pv.color_id = col.id
                 LEFT JOIN warehouse_suppliers sel ON p.warehouse_supplier_id = sel.id
                LEFT JOIN brands b ON p.brand_id = b.id
                JOIN warehouses w ON sb.location_id = w.id
                WHERE sb.location_type = 'WAREHOUSE'";
        $params = [];

        if (!empty($selectedWarehouseId)) {
            $sql .= " AND sb.location_id = ?";
            $params[] = $selectedWarehouseId;
        }

        if (!empty($stockState)) {
            $sql .= " AND sb.stock_state = ?";
            $params[] = $stockState;
        }

        if (!empty($categoryId)) {
            $sql .= " AND p.warehouse_category_id = ?";
            $params[] = (int)$categoryId;
        }

        if (!empty($search)) {
            $sql .= " AND (p.name LIKE ? OR pv.sku LIKE ? OR p.code LIKE ? OR sel.name LIKE ? OR b.name LIKE ? OR pv.qr_code LIKE ? OR p.qr_code_prefix LIKE ? OR s.name LIKE ? OR col.name LIKE ? OR p.material LIKE ?)";
            $s = '%' . $search . '%';
            $params = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s, $s, $s]);
        }

        $sql .= " ORDER BY p.name ASC, pv.sku ASC";
        $stockItems = Database::fetchAll($sql, $params);

        // Cross-warehouse search detection: if search is set, find if matching items exist in other warehouses (unscoped admins only)
        $otherWarehouseMatches = [];
        if (!empty($search) && empty($scopedWarehouseId)) {
            $s = '%' . $search . '%';
            $otherSql = "SELECT sb.location_id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
                                p.id as product_id, p.name as product_name, p.code as product_code, pv.sku, p.source_type,
                                SUM(sb.quantity) as total_qty, sb.stock_state
                         FROM stock_balances sb
                         JOIN product_variants pv ON sb.variant_id = pv.id
                         JOIN products p ON pv.product_id = p.id
                         JOIN warehouses w ON sb.location_id = w.id
                         LEFT JOIN warehouse_suppliers sel ON p.warehouse_supplier_id = sel.id
                         LEFT JOIN brands b ON p.brand_id = b.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         WHERE sb.location_type = 'WAREHOUSE'
                           AND sb.location_id != ?
                           AND (p.name LIKE ? OR pv.sku LIKE ? OR p.code LIKE ? OR sel.name LIKE ? OR b.name LIKE ? OR pv.qr_code LIKE ? OR p.qr_code_prefix LIKE ? OR s.name LIKE ? OR col.name LIKE ? OR p.material LIKE ?)
                         GROUP BY sb.location_id, w.name, w.code, p.id, p.name, p.code, pv.id, pv.sku, p.source_type, sb.stock_state
                         HAVING total_qty > 0
                         ORDER BY w.name ASC, p.name ASC";
            $otherWarehouseMatches = Database::fetchAll($otherSql, [$selectedWarehouseId, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s]);
        }

        // Separate items by source: Production vs Third-Party vs Complementary
        $productionItems = [];
        $thirdPartyItems = [];
        $complementaryItems = [];
        $prodAvailableQty = 0;
        $prodValuation = 0.0;
        $tpAvailableQty = 0;
        $tpValuation = 0.0;
        $compAvailableQty = 0;
        $compValuation = 0.0;

        foreach ($stockItems as $item) {
            $isComp = (!empty($item['is_complementary']) || !empty($item['variant_is_complementary']));
            $isProd = ($item['source_type'] === 'OWN_PRODUCTION');
            $purchasePrice = (float)($item['purchase_price'] ?? 0);
            $productionCost = (float)($item['production_cost'] ?? 0);
            $rawSelling = (float)($item['selling_price'] ?? 0);
            $rawMrp = (float)($item['mrp'] ?? 0);

            if ($rawMrp > 0 && $rawSelling > 0) {
                $item['display_mrp'] = max($rawMrp, $rawSelling);
                $item['display_selling_price'] = min($rawMrp, $rawSelling);
            } elseif ($rawMrp > 0) {
                $item['display_mrp'] = $rawMrp;
                $item['display_selling_price'] = $rawMrp;
            } elseif ($rawSelling > 0) {
                $item['display_mrp'] = $rawSelling;
                $item['display_selling_price'] = $rawSelling;
            } else {
                $item['display_mrp'] = 0.0;
                $item['display_selling_price'] = 0.0;
            }

            $costRate = $purchasePrice > 0 ? $purchasePrice : ($productionCost > 0 ? $productionCost : $item['display_selling_price']);
            $qty = (float)($item['quantity'] ?? 0);
            $itemSellingPrice = $item['display_selling_price'] > 0 ? $item['display_selling_price'] : ($rawMrp > 0 ? $rawMrp : $costRate);
            $valuation = $isComp ? ($qty * $itemSellingPrice) : ($qty * $costRate);
            $item['cost_rate'] = $costRate;
            $item['valuation'] = $valuation;

            if ($isComp) {
                $complementaryItems[] = $item;
                if ($item['stock_state'] === 'AVAILABLE') {
                    $compAvailableQty += $qty;
                    $compValuation += $valuation;
                }
            } elseif ($isProd) {
                $productionItems[] = $item;
                if ($item['stock_state'] === 'AVAILABLE') {
                    $prodAvailableQty += $qty;
                    $prodValuation += $valuation;
                }
            } else {
                $thirdPartyItems[] = $item;
                if ($item['stock_state'] === 'AVAILABLE') {
                    $tpAvailableQty += $qty;
                    $tpValuation += $valuation;
                }
            }
        }

        // Complementary Sales (Realized sales from sale_items)
        $compSales = Database::fetchOne(
            "SELECT COALESCE(SUM(si.quantity), 0) as total_sold_qty,
                    COALESCE(SUM(si.total_amount), 0) as total_sold_revenue,
                    COUNT(DISTINCT si.sale_id) as total_orders
             FROM sale_items si
             JOIN product_variants pv ON si.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             WHERE (p.is_complementary = 1 OR pv.is_complementary = 1)"
        );
        $compSoldQty = (int)($compSales['total_sold_qty'] ?? 0);
        $compSoldRevenue = (float)($compSales['total_sold_revenue'] ?? 0.0);

        $warehouseSummary = Database::fetchOne(
            "SELECT 
                COALESCE(SUM(CASE WHEN stock_state = 'AVAILABLE' THEN quantity ELSE 0 END), 0) as available_qty,
                COALESCE(SUM(CASE WHEN stock_state = 'RESERVED' THEN quantity ELSE 0 END), 0) as reserved_qty,
                COALESCE(SUM(CASE WHEN stock_state = 'DAMAGED' THEN quantity ELSE 0 END), 0) as damaged_qty,
                COUNT(DISTINCT variant_id) as unique_variants
             FROM stock_balances
             WHERE location_type = 'WAREHOUSE' AND location_id = ?",
            [$selectedWarehouseId]
        );

        $reworkStats = Database::fetchOne(
            "SELECT COALESCE(SUM(quantity), 0) as rework_qty FROM warehouse_damages WHERE warehouse_id = ? AND status = 'REWORK'",
            [$selectedWarehouseId]
        );
        $warehouseSummary['rework_qty'] = (int)($reworkStats['rework_qty'] ?? 0);

        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");

        Response::view('warehouse.stock.index', [
            'pageTitle'              => 'Warehouse Stock Inventory',
            'activeModule'           => 'warehouse',
            'activeMenu'             => 'wh-stock',
            'stockItems'             => $stockItems,
            'productionItems'        => $productionItems,
            'thirdPartyItems'        => $thirdPartyItems,
            'complementaryItems'     => $complementaryItems,
            'prodAvailableQty'       => $prodAvailableQty,
            'prodValuation'          => $prodValuation,
            'tpAvailableQty'         => $tpAvailableQty,
            'tpValuation'            => $tpValuation,
            'compAvailableQty'       => $compAvailableQty,
            'compValuation'          => $compValuation,
            'compSoldQty'            => $compSoldQty,
            'compSoldRevenue'        => $compSoldRevenue,
            'compSales'              => $compSales,
            'currTab'                => $activeTab,
            'warehouses'             => $warehouses,
            'currentWarehouse'       => $currentWarehouse,
            'otherWarehouseMatches'  => $otherWarehouseMatches,
            'categories'             => $categories,
            'selectedWarehouseId'    => $selectedWarehouseId,
            'scopedWarehouseId'      => $scopedWarehouseId,
            'stockState'             => $stockState,
            'categoryId'             => $categoryId,
            'search'                 => $search,
            'summary'                => $warehouseSummary,
            'breadcrumb'             => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Inventory Ledger'],
            ],
        ]);
    }

    /**
     * Quick Inward Stock from Warehouse Catalog into Warehouse Inventory.
     */
    public function storeQuickInward(): void {
        if (!hasPermission('inward', 'verify') && !hasPermission('inward', 'create') && !hasPermission('stock', 'adjust')) {
            Session::flash('error', 'Access denied: You do not have permission to inward stock.');
            Response::redirect(url('warehouse/stock'));
            return;
        }
        $productId = (int)($_POST['product_id'] ?? 0);
        $warehouseId = (int)($_POST['warehouse_id'] ?? 1);
        $variantsData = $_POST['variants'] ?? [];

        $product = Database::fetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }

        $totalAddedQty = 0;
        Database::beginTransaction();
        try {
            if (empty($variantsData) || !is_array($variantsData)) {
                throw new Exception('No variant quantity data provided.');
            }

            foreach ($variantsData as $varId => $data) {
                $varId = (int)$varId;
                $qtyToAdd = max(0, (int)($data['quantity'] ?? 0));
                if ($qtyToAdd <= 0) continue;

                $costPrice = isset($data['purchase_price']) && $data['purchase_price'] !== '' ? max(0.0, (float)$data['purchase_price']) : null;
                $mrp = isset($data['mrp']) && $data['mrp'] !== '' ? max(0.0, (float)$data['mrp']) : null;
                $sellingPrice = isset($data['selling_price']) && $data['selling_price'] !== '' ? max(0.0, (float)$data['selling_price']) : null;

                $updates = [];
                $params = [];
                if ($costPrice !== null) {
                    $updates[] = "purchase_price = ?, production_cost = ?";
                    $params[] = $costPrice;
                    $params[] = $costPrice;
                }
                if ($mrp !== null) {
                    $updates[] = "mrp = ?";
                    $params[] = $mrp;
                }
                if ($sellingPrice !== null) {
                    $updates[] = "selling_price = ?";
                    $params[] = $sellingPrice;
                }
                if (!empty($updates)) {
                    $params[] = $varId;
                    Database::execute("UPDATE product_variants SET " . implode(', ', $updates) . " WHERE id = ?", $params);
                }

                // Credit stock balance in warehouse
                StockService::increase(
                    variantId: $varId,
                    locationType: 'WAREHOUSE',
                    locationId: $warehouseId,
                    stockState: 'AVAILABLE',
                    quantity: (float)$qtyToAdd,
                    transactionType: 'PURCHASE_GRN',
                    referenceType: 'products',
                    referenceId: $productId,
                    userId: Session::userId(),
                    notes: "Direct Catalog Inward to Warehouse Inventory for {$product['name']} ({$product['code']})"
                );

                $totalAddedQty += $qtyToAdd;
            }

            if ($totalAddedQty <= 0) {
                throw new Exception('Please specify an inward quantity greater than 0 for at least one size variant.');
            }

            // Sync products total quantity
            Database::execute(
                "UPDATE products SET quantity = COALESCE((
                    SELECT SUM(sb.quantity) FROM stock_balances sb 
                    JOIN product_variants pv ON sb.variant_id = pv.id
                    WHERE pv.product_id = ? AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                ), 0) WHERE id = ?",
                [$productId, $productId]
            );

            AuditService::log('warehouse', 'quick_inward', 'products', $productId, null, [
                'product_name' => $product['name'],
                'warehouse_id' => $warehouseId,
                'total_qty'    => $totalAddedQty,
            ]);

            Database::commit();

            $destTab = ($product['source_type'] === 'OWN_PRODUCTION') ? 'production' : 'third_party';
            Session::flash('success', "Successfully credited +{$totalAddedQty} pcs of '{$product['name']}' to Warehouse " . ($destTab === 'production' ? 'Production' : 'Third-Party') . " Inventory!");
            Response::redirect(url('warehouse/stock?tab=' . $destTab . '&warehouse_id=' . $warehouseId));
        } catch (Exception $e) {
            Database::rollback();
            Session::flash('error', 'Inward failed: ' . $e->getMessage());
            Response::redirect(url('warehouse/catalog'));
        }
    }

    public function stockDetail(string $id): void {
        if (!hasPermission('stock', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view stock details.');
            Response::redirect(url('warehouse/stock'));
            return;
        }
        $variantId = (int)$id;
        $variant = Database::fetchOne(
            "SELECT pv.*, 
                    p.name as product_name, p.code as product_code, p.material, p.hsn_code, p.min_stock,
                    p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                    p.source_type, p.brand_id, p.warehouse_supplier_id as seller_id,
                    COALESCE(pv.is_complementary, p.is_complementary, 0) as is_complementary, p.is_complementary as prod_is_comp,
                    COALESCE(pv.mfg_date, p.mfg_date) as mfg_date,
                    COALESCE(pv.expiry_date, p.expiry_date) as expiry_date,
                    COALESCE(c.name, cat.name) as category_name,
                    COALESCE(subcat.name, sub_cat.name) as subcategory_name,
                    COALESCE(subsubcat.name, sub_sub_cat.name) as sub_subcategory_name,
                    COALESCE(c.code, cat.code, p.category_code) as cat_code,
                    COALESCE(subcat.code, sub_cat.code, p.subcategory_code) as sub_code,
                    COALESCE(subsubcat.code, sub_sub_cat.code, p.sub_subcategory_code) as subsub_code,
                    b.name as brand_name,
                    s.name as seller_name, s.company as seller_company, s.phone as seller_phone, s.city as seller_city, s.gst_number as seller_gstin,
                    s_size.name as size_name, s_size.code as size_code,
                    col.name as color_name, col.hex_code as color_hex
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN warehouse_categories c ON p.warehouse_category_id = c.id
             LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
             LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN subcategories sub_cat ON p.subcategory_id = sub_cat.id
             LEFT JOIN sub_subcategories sub_sub_cat ON p.sub_subcategory_id = sub_sub_cat.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
             LEFT JOIN sizes s_size ON pv.size_id = s_size.id
             LEFT JOIN colors col ON pv.color_id = col.id
             WHERE pv.id = ?",
            [$variantId]
        );

        if (!$variant) {
            Response::redirect(url('warehouse/stock'), 'error', 'Variant not found.');
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $whBalanceClause = $scopedWarehouseId ? " AND sb.location_id = " . (int)$scopedWarehouseId : "";
        $balances = Database::fetchAll(
            "SELECT sb.*, w.name as warehouse_name, w.code as warehouse_code
             FROM stock_balances sb
             JOIN warehouses w ON sb.location_id = w.id
             WHERE sb.variant_id = ? AND sb.location_type = 'WAREHOUSE'$whBalanceClause
             ORDER BY w.name ASC, sb.stock_state ASC",
            [$variantId]
        );

        $transactions = StockService::getTransactionHistory($variantId, ['limit' => 50]);

        $totalAvailable = 0;
        $totalDamaged = 0;
        $totalReserved = 0;
        foreach ($balances as $b) {
            if ($b['stock_state'] === 'AVAILABLE') {
                $totalAvailable += (int)$b['quantity'];
            } elseif ($b['stock_state'] === 'DAMAGED') {
                $totalDamaged += (int)$b['quantity'];
            } elseif ($b['stock_state'] === 'RESERVED') {
                $totalReserved += (int)$b['quantity'];
            }
        }

        $c1 = !empty($variant['category_name']) ? strtoupper(substr(trim($variant['category_name']), 0, 1)) : (!empty($variant['cat_code']) ? strtoupper(substr(trim($variant['cat_code']), 0, 1)) : '');
        $c2 = !empty($variant['subcategory_name']) ? strtoupper(substr(trim($variant['subcategory_name']), 0, 1)) : (!empty($variant['sub_code']) ? strtoupper(substr(trim($variant['sub_code']), 0, 1)) : '');
        $c3 = !empty($variant['sub_subcategory_name']) ? strtoupper(substr(trim($variant['sub_subcategory_name']), 0, 1)) : (!empty($variant['subsub_code']) ? strtoupper(substr(trim($variant['subsub_code']), 0, 1)) : '');
        $catInitials = $c1 . $c2 . $c3;

        $catPrefix = '';
        if (!empty($variant['qr_code_prefix'])) {
            $rawPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $variant['qr_code_prefix']));
            if (strlen($rawPrefix) === 9) {
                // e.g. WOM + MAT + FEE -> W + M + F = WMF
                $catPrefix = $rawPrefix[0] . $rawPrefix[3] . $rawPrefix[6];
            } elseif (strlen($rawPrefix) <= 4) {
                $catPrefix = $rawPrefix;
            } else {
                $catPrefix = substr($rawPrefix, 0, 3);
            }
        }
        if (strlen($catPrefix) < 2 && strlen($catInitials) >= 2) {
            $catPrefix = $catInitials;
        }
        if (strlen($catPrefix) < 2 && !empty($variant['product_code'])) {
            $parts = explode('-', $variant['product_code']);
            $catPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $parts[0]));
        }
        if (strlen($catPrefix) < 2 && !empty($variant['sku'])) {
            $parts = explode('-', $variant['sku']);
            $catPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $parts[0]));
        }
        if (empty($catPrefix)) {
            $catPrefix = 'TTS';
        }
        $catPrefix = strtoupper(substr($catPrefix, 0, 4));

        $mfgTime = !empty($variant['mfg_date']) ? strtotime($variant['mfg_date']) : time();
        $monthStr = sprintf("%02d", date('n', $mfgTime)); // 2-digit month: 09
        $year2Digit = date('y', $mfgTime); // 2-digit year: 26

        // Fetch already allocated/dispatched piece tags for this variant to skip them in active inventory
        $allocatedRows = Database::fetchAll(
            "SELECT ai.selected_qr_codes 
             FROM allocation_items ai
             JOIN warehouse_allocations wa ON ai.allocation_id = wa.id
             WHERE ai.variant_id = ? AND wa.status IN ('REQUESTED', 'APPROVED', 'PICKING', 'PACKING', 'DISPATCHED', 'DELIVERED', 'RECEIVED')",
            [$variantId]
        );
        $usedTags = [];
        foreach ($allocatedRows as $ar) {
            if (!empty($ar['selected_qr_codes'])) {
                $spl = explode(',', $ar['selected_qr_codes']);
                foreach ($spl as $st) {
                    $t = strtoupper(trim($st));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
        }

        // Fetch active damaged / quarantined piece tags for this variant to skip them in active inventory
        $damagedRows = Database::fetchAll(
            "SELECT tag_code, quantity, defect_notes FROM warehouse_damages WHERE variant_id = ? AND status NOT IN ('RESTOCKED', 'REPAIRED')",
            [$variantId]
        );
        foreach ($damagedRows as $dr) {
            if (!empty($dr['tag_code'])) {
                $spl = explode(',', $dr['tag_code']);
                foreach ($spl as $st) {
                    $t = strtoupper(trim($st));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
            if (!empty($dr['defect_notes']) && preg_match_all('/([A-Z0-9]{8,14})/i', $dr['defect_notes'], $mTags)) {
                foreach ($mTags[1] as $mt) {
                    $usedTags[strtoupper(trim($mt))] = true;
                }
            }
        }

        $shopDamagedRows = Database::fetchAll(
            "SELECT tag_code FROM shop_damages WHERE variant_id = ?",
            [$variantId]
        );
        foreach ($shopDamagedRows as $sdr) {
            if (!empty($sdr['tag_code'])) {
                $spl = explode(',', $sdr['tag_code']);
                foreach ($spl as $st) {
                    $t = strtoupper(trim($st));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
        }

        $channelDamagedRows = Database::fetchAll(
            "SELECT tag_code FROM channel_damages WHERE variant_id = ?",
            [$variantId]
        );
        foreach ($channelDamagedRows as $cdr) {
            if (!empty($cdr['tag_code'])) {
                $spl = explode(',', $cdr['tag_code']);
                foreach ($spl as $st) {
                    $t = strtoupper(trim($st));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
        }

        // Fetch QC rejected / rework tags from associated production batches
        try {
            $qcBatches = Database::fetchAll(
                "SELECT qb.pieces_data 
                 FROM qc_batches qb
                 JOIN production_batches pb ON pb.qc_batch_id = qb.id
                 JOIN production_batch_items pbi ON pbi.production_batch_id = pb.id
                 WHERE pbi.variant_id = ?",
                [$variantId]
            );
            foreach ($qcBatches as $qb) {
                if (!empty($qb['pieces_data'])) {
                    $pd = json_decode($qb['pieces_data'], true);
                    if (is_array($pd)) {
                        foreach ($pd as $p) {
                            if (($p['status'] ?? '') === 'REJECTED' || ($p['status'] ?? '') === 'REWORK') {
                                if (!empty($p['tag_code'])) {
                                    $usedTags[strtoupper(trim($p['tag_code']))] = true;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {}

        // Fetch piece tags from direct customer purchases for this variant to skip sold pieces in active inventory
        $purchasedRows = Database::fetchAll(
            "SELECT pi.batch_lot_no, pi.notes 
             FROM purchase_items pi
             JOIN purchase_orders po ON pi.purchase_order_id = po.id
             WHERE pi.variant_id = ? AND po.status NOT IN ('CANCELLED', 'VOID')",
            [$variantId]
        );
        foreach ($purchasedRows as $pr) {
            if (!empty($pr['batch_lot_no'])) {
                $spl = explode(',', $pr['batch_lot_no']);
                foreach ($spl as $st) {
                    $t = strtoupper(trim($st));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
            if (!empty($pr['notes']) && preg_match_all('/([A-Z0-9]{8,14})/i', $pr['notes'], $mTags)) {
                foreach ($mTags[1] as $mt) {
                    $t = strtoupper(trim($mt));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
        }

        // Fetch exchanged OUT piece tags (replacement pieces handed to customer) - these must be excluded/hidden from available inventory!
        $exchangeOutRows = Database::fetchAll(
            "SELECT ei.piece_tag 
             FROM exchange_items ei
             JOIN exchanges e ON ei.exchange_id = e.id
             WHERE ei.variant_id = ? AND ei.item_type = 'NEW' AND e.status != 'CANCELLED'",
            [$variantId]
        );
        foreach ($exchangeOutRows as $er) {
            if (!empty($er['piece_tag'])) {
                $spl = explode(',', $er['piece_tag']);
                foreach ($spl as $st) {
                    $t = strtoupper(trim($st));
                    if ($t !== '') $usedTags[$t] = true;
                }
            }
        }

        // Fetch returned GOOD piece tags (restocked to available warehouse stock) - these must be UN-HIDDEN / AVAILABLE in active inventory!
        $returnedGoodRows = Database::fetchAll(
            "SELECT ri.reason 
             FROM return_items ri
             JOIN returns r ON ri.return_id = r.id
             WHERE ri.variant_id = ? AND ri.item_condition = 'GOOD' AND r.status != 'CANCELLED'",
            [$variantId]
        );
        $restockedTags = [];
        foreach ($returnedGoodRows as $rr) {
            if (!empty($rr['reason']) && preg_match_all('/([A-Z0-9]{8,14})/i', $rr['reason'], $mTags)) {
                foreach ($mTags[1] as $mt) {
                    $t = strtoupper(trim($mt));
                    if ($t !== '') {
                        $restockedTags[$t] = true;
                        unset($usedTags[$t]); // Returned good pieces become AVAILABLE again!
                    }
                }
            }
        }

        // Build piece-by-piece QR code records for available stock (skipping allocated/dispatched/sold/damaged tags)
        $pieceQRs = [];
        $displayBrand = !empty($variant['brand_name']) ? $variant['brand_name'] : (!empty($variant['seller_name']) ? $variant['seller_name'] : 'GRM WAREHOUSE');

        $rawMrp = (float)($variant['mrp'] ?? 0);
        $rawSelling = (float)($variant['selling_price'] ?? 0);
        $costPrice = (float)($variant['purchase_price'] > 0 ? $variant['purchase_price'] : ($variant['production_cost'] ?? 0));

        if ($rawMrp > 0 && $rawSelling > 0) {
            $basePrice = max($rawMrp, $rawSelling);
            $sellingPrice = min($rawMrp, $rawSelling);
        } elseif ($rawMrp > 0) {
            $basePrice = $rawMrp;
            $sellingPrice = $rawMrp;
        } elseif ($rawSelling > 0) {
            $basePrice = $rawSelling;
            $sellingPrice = $rawSelling;
        } else {
            $basePrice = 0.0;
            $sellingPrice = 0.0;
        }

        // Also normalize variant mrp and selling_price for view header/card display
        $variant['display_mrp'] = $basePrice;
        $variant['display_selling_price'] = $sellingPrice;
        $variant['cost_price'] = $costPrice;

        $isSeriesBatch = (($variant['qr_type'] ?? '') === 'SERIES_BATCH');
        $isComplementary = (!empty($variant['is_complementary']) || !empty($variant['prod_is_comp']));

        $categoryHierarchy = trim(
            ($variant['category_name'] ?? '') .
            (!empty($variant['subcategory_name']) ? ' › ' . $variant['subcategory_name'] : '') .
            (!empty($variant['sub_subcategory_name']) ? ' › ' . $variant['sub_subcategory_name'] : ''),
            ' ›'
        );

        if ($isSeriesBatch || $isComplementary) {
            $packQty = (int)$totalAvailable;
            if ($packQty <= 0) $packQty = max(1, (int)(!empty($variant['pack_quantity']) ? $variant['pack_quantity'] : 1));

            // Standard Category-derived single master QR code (e.g. WMF102601 or SSSC102601) - no number series range
            $properCatCode = "{$catPrefix}{$monthStr}{$year2Digit}01";
            if (!empty($variant['series_start_code']) && !preg_match('/^\d+$/', $variant['series_start_code'])) {
                $singleMasterCode = $variant['series_start_code'];
            } else {
                $singleMasterCode = $properCatCode;
            }

            $tagCode = $singleMasterCode;

            $fmtMrp = ($basePrice == (int)$basePrice) ? (int)$basePrice : number_format($basePrice, 2, '.', '');
            $fmtSelling = ($sellingPrice == (int)$sellingPrice) ? (int)$sellingPrice : number_format($sellingPrice, 2, '.', '');

            $qrPayload = "SERIES|{$displayBrand}|{$variant['sku']}|{$singleMasterCode}|QTY:{$packQty}|MRP:{$fmtMrp}|Sell:{$fmtSelling}" . ($isComplementary ? "|COMP:1" : "");

            if ($totalAvailable > 0) {
                $pieceQRs = [
                    [
                        'piece_no'              => 1,
                        'total_count'           => 1,
                        'tag_code'              => $tagCode,
                        'sku'                   => $variant['sku'],
                        'brand_name'            => $displayBrand,
                        'cost_price'            => $costPrice,
                        'mrp'                   => $basePrice,
                        'selling_price'         => $sellingPrice,
                        'size_name'             => $variant['size_name'] ?? 'Free',
                        'color_name'            => $variant['color_name'] ?? 'Std',
                        'is_series'             => true,
                        'is_complementary'      => $isComplementary,
                        'pack_quantity'         => $packQty,
                        'series_start_code'     => $singleMasterCode,
                        'series_end_code'       => $singleMasterCode,
                        'qr_payload'            => $qrPayload,
                        'category_name'         => $variant['category_name'] ?? '',
                        'subcategory_name'      => $variant['subcategory_name'] ?? '',
                        'sub_subcategory_name'  => $variant['sub_subcategory_name'] ?? '',
                        'category_hierarchy'    => $categoryHierarchy,
                        'source_label'          => ($isComplementary ? 'Complementary Pack' : 'Master Series Pack') . " ({$packQty} Pcs)",
                    ]
                ];
            } else {
                $pieceQRs = [];
            }
        } else {
            // First, add all explicitly restocked returned tags into available piece list!
            foreach (array_keys($restockedTags) as $rTag) {
                if (!isset($usedTags[$rTag]) && count($pieceQRs) < $totalAvailable) {
                    $pieceQRs[] = [
                        'piece_no'           => count($pieceQRs) + 1,
                        'tag_code'           => $rTag,
                        'sku'                => $variant['sku'],
                        'brand_name'         => $displayBrand,
                        'cost_price'         => $costPrice,
                        'mrp'                => $basePrice,
                        'selling_price'      => $sellingPrice,
                        'size_name'          => $variant['size_name'] ?? 'Free',
                        'color_name'         => $variant['color_name'] ?? 'Std',
                        'category_name'      => $variant['category_name'] ?? '',
                        'category_hierarchy' => $categoryHierarchy,
                    ];
                }
            }

            $currSeq = 1;
            while (count($pieceQRs) < $totalAvailable) {
                $pieceSeq = sprintf("%02d", $currSeq);
                $tagCode = "{$catPrefix}{$monthStr}{$year2Digit}{$pieceSeq}"; // e.g. MTP092601

                if (!isset($usedTags[$tagCode]) && !isset($restockedTags[$tagCode])) {
                    $pieceQRs[] = [
                        'piece_no'           => count($pieceQRs) + 1,
                        'tag_code'           => $tagCode,
                        'sku'                => $variant['sku'],
                        'brand_name'         => $displayBrand,
                        'cost_price'         => $costPrice,
                        'mrp'                => $basePrice,
                        'selling_price'      => $sellingPrice,
                        'size_name'          => $variant['size_name'] ?? 'Free',
                        'color_name'         => $variant['color_name'] ?? 'Std',
                        'category_name'      => $variant['category_name'] ?? '',
                        'category_hierarchy' => $categoryHierarchy,
                    ];
                }
                $currSeq++;
                if ($currSeq > 9999) break;
            }
        }

        $warehouses = Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC");

        // Fetch batch-wise added stock history specifically for this variant including damage tracking
        $variantBatchHistory = Database::fetchAll(
            "SELECT pbi.quantity as batch_item_expected_qty,
                    COALESCE(pbi.received_qty, pbi.quantity) as batch_item_qty,
                    pbi.damaged_qty as item_damaged_qty,
                    pbi.missing_qty as item_missing_qty,
                    pb.id as batch_id, pb.batch_number, pb.status as batch_status, pb.created_at as batch_created_at, pb.box_count, pb.total_qty as batch_total_qty,
                    pp.id as program_id, pp.program_number, pp.name as program_name,
                    pu.name as unit_name, pu.code as unit_code, pu.manager_name as unit_manager, pu.phone as unit_phone,
                    po.outward_number, po.dispatch_date as outward_dispatch_date, po.vehicle_number, po.driver_name,
                    w.id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
                    COALESCE(MAX(st.created_at), po.dispatch_date, pb.created_at) as inward_received_at,
                    wd.id as damage_id, wd.damage_number, wd.quantity as damage_qty, wd.tag_code as damage_tags, wd.status as damage_status
             FROM production_batch_items pbi
             JOIN production_batches pb ON pbi.production_batch_id = pb.id
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN production_units pu ON pp.production_unit_id = pu.id
             LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
             LEFT JOIN warehouses w ON po.warehouse_id = w.id
             LEFT JOIN stock_transactions st ON (st.variant_id = pbi.variant_id AND st.reference_type = 'production_batch' AND st.reference_id = pb.id)
             LEFT JOIN warehouse_damages wd ON (wd.reference_id = pb.id AND wd.variant_id = pbi.variant_id)
             WHERE pbi.variant_id = ?
             GROUP BY pb.id, pbi.id, wd.id
             ORDER BY pb.id DESC",
            [$variantId]
        );

        // Also fetch style-level production batches for parent product visibility
        $productId = (int)$variant['product_id'];
        $productBatchHistory = Database::fetchAll(
            "SELECT pb.id as batch_id, pb.batch_number, pb.status as batch_status, pb.created_at as batch_created_at, pb.box_count, pb.total_qty as batch_total_qty,
                    pp.id as program_id, pp.program_number, pp.name as program_name,
                    pu.name as unit_name, pu.code as unit_code, pu.manager_name as unit_manager, pu.phone as unit_phone,
                    po.outward_number, po.dispatch_date as outward_dispatch_date, po.vehicle_number, po.driver_name,
                    w.id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
                    COALESCE(po.dispatch_date, pb.created_at) as inward_received_at,
                    SUM(pbi.quantity) as style_batch_qty,
                    COUNT(DISTINCT pbi.variant_id) as variant_count
             FROM production_batches pb
             JOIN production_batch_items pbi ON pbi.production_batch_id = pb.id
             JOIN product_variants pv ON pbi.variant_id = pv.id
             LEFT JOIN production_programs pp ON pb.program_id = pp.id
             LEFT JOIN production_units pu ON pp.production_unit_id = pu.id
             LEFT JOIN production_outwards po ON po.production_batch_id = pb.id
             LEFT JOIN warehouses w ON po.warehouse_id = w.id
             WHERE pv.product_id = ?
             GROUP BY pb.id, pb.batch_number, pb.status, pb.created_at, pb.box_count, pb.total_qty,
                      pp.id, pp.program_number, pp.name,
                      pu.name, pu.code, pu.manager_name, pu.phone,
                      po.outward_number, po.dispatch_date, po.vehicle_number, po.driver_name,
                      w.id, w.name, w.code
             ORDER BY pb.id DESC",
            [$productId]
        );

        // Fetch all damaged / quarantined records and individual piece tags for this variant
        $variantDamages = Database::fetchAll(
            "SELECT wd.*, w.name as warehouse_name, w.code as warehouse_code,
                    pb.batch_number, u.full_name as reported_by_name
             FROM warehouse_damages wd
             LEFT JOIN warehouses w ON wd.warehouse_id = w.id
             LEFT JOIN production_batches pb ON (wd.source_type IN ('PRODUCTION_INWARD', 'INWARD_GRN', 'PRODUCTION_BATCH', 'INWARD_TRANSIT') AND wd.reference_id = pb.id)
             LEFT JOIN users u ON wd.reported_by = u.id
             WHERE wd.variant_id = ?
             ORDER BY wd.id DESC",
            [$variantId]
        );

        $damagedPieceList = [];
        $dPieceNum = 1;
        foreach ($variantDamages as $vd) {
            $tags = [];
            if (!empty($vd['tag_code'])) {
                $tags = array_filter(array_map('trim', explode(',', $vd['tag_code'])));
            }
            if (empty($tags)) {
                $tags = ['DMG-' . str_pad($vd['id'], 4, '0', STR_PAD_LEFT)];
            }
            foreach ($tags as $t) {
                $damagedPieceList[] = [
                    'piece_no'      => $dPieceNum++,
                    'damage_id'     => $vd['id'],
                    'damage_number' => $vd['damage_number'],
                    'tag_code'      => $t,
                    'sku'           => $variant['sku'] ?? '',
                    'product_name'  => $variant['product_name'] ?? 'Garment Item',
                    'brand_name'    => $variant['brand_name'] ?? 'GRM WAREHOUSE',
                    'size_name'     => $variant['size_name'] ?? 'Std',
                    'color_name'    => $variant['color_name'] ?? 'Std',
                    'mrp'           => (float)($variant['mrp'] > 0 ? $variant['mrp'] : $variant['selling_price']),
                    'selling_price' => (float)($variant['selling_price'] > 0 ? $variant['selling_price'] : $variant['mrp']),
                    'source_type'   => $vd['source_type'] ?? 'WAREHOUSE_FOUND',
                    'damage_reason' => $vd['damage_reason'] ?? ($vd['reason_type'] ?? 'Defective'),
                    'status'        => $vd['status'] ?? 'REPORTED',
                    'reported_at'   => $vd['reported_at'] ?? ($vd['created_at'] ?? null),
                    'warehouse_name'=> $vd['warehouse_name'] ?? 'Central WH',
                    'cost_per_unit' => $vd['cost_per_unit'] ?? 0,
                    'batch_number'  => $vd['batch_number'] ?? null,
                    'defect_notes'  => $vd['defect_notes'] ?? ($vd['notes'] ?? null),
                ];
            }
        }

        // Fetch all direct customer purchase / sold piece records for this variant
        $variantPurchases = Database::fetchAll(
            "SELECT pi.*, po.po_number, po.order_date, po.status as po_status, po.payment_status, po.payment_method,
                    c.name as customer_name, c.phone as customer_phone, w.name as warehouse_name
             FROM purchase_items pi
             JOIN purchase_orders po ON pi.purchase_order_id = po.id
             LEFT JOIN customers c ON po.customer_id = c.id
             LEFT JOIN warehouses w ON po.warehouse_id = w.id
             WHERE pi.variant_id = ? AND po.status NOT IN ('CANCELLED', 'VOID')
             ORDER BY po.id DESC, pi.id DESC",
            [$variantId]
        );

        $soldPieceList = [];
        $sPieceNum = 1;
        foreach ($variantPurchases as $vp) {
            $tags = [];
            if (!empty($vp['batch_lot_no'])) {
                $tags = array_filter(array_map('trim', explode(',', $vp['batch_lot_no'])));
            }
            if (empty($tags) && !empty($vp['notes']) && preg_match_all('/([A-Z0-9]{8,14})/i', $vp['notes'], $mTags)) {
                $tags = $mTags[1];
            }
            if (empty($tags)) {
                $tags = ['SOLD-' . str_pad($vp['id'], 4, '0', STR_PAD_LEFT)];
            }
            foreach ($tags as $t) {
                $cleanT = strtoupper(trim($t));
                $isReturned = isset($restockedTags[$cleanT]);
                if ($isReturned) {
                    continue; // Returned pieces are displayed in the Customer Returns & Exchanges tab
                }
                $soldPieceList[] = [
                    'piece_no'       => $sPieceNum++,
                    'purchase_id'    => $vp['purchase_order_id'],
                    'po_number'      => $vp['po_number'],
                    'tag_code'       => $cleanT,
                    'sku'            => $variant['sku'] ?? '',
                    'product_name'   => $variant['product_name'] ?? 'Garment Item',
                    'customer_name'  => $vp['customer_name'] ?? 'Direct Customer',
                    'customer_phone' => $vp['customer_phone'] ?? '',
                    'selling_price'  => (float)($vp['unit_price'] ?? $variant['selling_price']),
                    'status'         => $vp['po_status'] ?? 'COMPLETED',
                    'sold_at'        => $vp['order_date'] ?? null,
                    'warehouse_name' => $vp['warehouse_name'] ?? 'Central WH',
                ];
            }
        }

        // Also append exchanged-out replacement pieces to the sold/outward list
        $variantExchanges = Database::fetchAll(
            "SELECT ei.*, e.exchange_number, e.created_at as exchange_date, e.status as exchange_status,
                    r.return_number, po.po_number, c.name as customer_name, c.phone as customer_phone, w.name as warehouse_name
             FROM exchange_items ei
             JOIN exchanges e ON ei.exchange_id = e.id
             JOIN returns r ON e.return_id = r.id
             LEFT JOIN purchase_orders po ON e.purchase_order_id = po.id
             LEFT JOIN customers c ON po.customer_id = c.id
             LEFT JOIN warehouses w ON r.warehouse_id = w.id
             WHERE ei.variant_id = ? AND ei.item_type = 'NEW' AND e.status != 'CANCELLED'
             ORDER BY ei.id DESC",
            [$variantId]
        );
        foreach ($variantExchanges as $ve) {
            $tags = [];
            if (!empty($ve['piece_tag'])) {
                $tags = array_filter(array_map('trim', explode(',', $ve['piece_tag'])));
            }
            if (empty($tags)) {
                $tags = ['EXC-' . str_pad($ve['id'], 4, '0', STR_PAD_LEFT)];
            }
            foreach ($tags as $t) {
                $soldPieceList[] = [
                    'piece_no'       => $sPieceNum++,
                    'purchase_id'    => $ve['purchase_order_id'] ?? 0,
                    'po_number'      => $ve['exchange_number'] . ' (Exchanged Out)',
                    'tag_code'       => strtoupper(trim($t)),
                    'sku'            => $variant['sku'] ?? '',
                    'product_name'   => $variant['product_name'] ?? 'Garment Item',
                    'customer_name'  => $ve['customer_name'] ?? 'Direct Customer',
                    'customer_phone' => $ve['customer_phone'] ?? '',
                    'selling_price'  => (float)($ve['unit_price'] ?? $variant['selling_price']),
                    'status'         => 'EXCHANGED_OUT',
                    'sold_at'        => $ve['exchange_date'] ?? null,
                    'warehouse_name' => $ve['warehouse_name'] ?? 'Central WH',
                ];
            }
        }

        // Fetch all customer return and exchange records for this variant
        $variantReturns = Database::fetchAll(
            "SELECT ri.*, r.return_number, r.return_type, r.reason as return_reason, r.refund_amount as total_refund, r.created_at as return_date,
                    r.refund_mode, r.status as return_status, r.notes as return_notes, po.po_number,
                    c.name as customer_name, c.phone as customer_phone, w.name as warehouse_name
             FROM return_items ri
             JOIN returns r ON ri.return_id = r.id
             LEFT JOIN purchase_orders po ON r.purchase_order_id = po.id
             LEFT JOIN customers c ON r.customer_id = c.id
             LEFT JOIN warehouses w ON r.warehouse_id = w.id
             WHERE ri.variant_id = ? AND r.status != 'CANCELLED'
             ORDER BY r.id DESC, ri.id DESC",
            [$variantId]
        );

        $returnedPieceList = [];
        $rPieceNum = 1;
        foreach ($variantReturns as $vr) {
            $tags = [];
            if (!empty($vr['reason']) && preg_match_all('/([A-Z0-9]{8,14})/i', $vr['reason'], $mTags)) {
                $tags = $mTags[1];
            }
            if (empty($tags)) {
                $tags = ['RET-' . str_pad($vr['id'], 4, '0', STR_PAD_LEFT)];
            }
            foreach ($tags as $t) {
                $returnedPieceList[] = [
                    'piece_no'        => $rPieceNum++,
                    'return_id'       => $vr['return_id'],
                    'return_number'   => $vr['return_number'],
                    'return_type'     => $vr['return_type'],
                    'po_number'       => $vr['po_number'] ?? 'Direct Purchase',
                    'tag_code'        => strtoupper(trim($t)),
                    'sku'             => $variant['sku'] ?? '',
                    'product_name'    => $variant['product_name'] ?? 'Garment Item',
                    'brand_name'      => $variant['brand_name'] ?? 'GRM WAREHOUSE',
                    'size_name'       => $variant['size_name'] ?? 'Std',
                    'color_name'      => $variant['color_name'] ?? 'Std',
                    'mrp'             => (float)($variant['mrp'] > 0 ? $variant['mrp'] : $variant['selling_price']),
                    'selling_price'   => (float)($variant['selling_price'] > 0 ? $variant['selling_price'] : $variant['mrp']),
                    'customer_name'   => $vr['customer_name'] ?? 'Direct Customer',
                    'customer_phone'  => $vr['customer_phone'] ?? '',
                    'item_condition'  => $vr['item_condition'] ?? 'GOOD',
                    'destination'     => $vr['destination'] ?? 'AVAILABLE',
                    'refund_amount'   => (float)($vr['refund_amount'] ?? 0),
                    'status'          => $vr['return_status'] ?? 'COMPLETED',
                    'returned_at'     => $vr['return_date'] ?? null,
                    'warehouse_name'  => $vr['warehouse_name'] ?? 'Central WH',
                ];
            }
        }

        $priceLogs = [];
        try {
            $priceLogs = Database::fetchAll("SELECT * FROM product_price_logs WHERE variant_id = ? ORDER BY created_at DESC", [$variantId]);
        } catch (Exception $e) {}

        Response::view('warehouse.stock.detail', [
            'pageTitle'           => 'Stock Detail: ' . $variant['sku'],
            'activeModule'        => 'warehouse',
            'activeMenu'          => 'wh-stock',
            'variant'             => $variant,
            'balances'            => $balances,
            'transactions'        => $transactions,
            'priceLogs'           => $priceLogs,
            'totalAvailable'      => $totalAvailable,
            'totalDamaged'        => $totalDamaged,
            'totalReserved'       => $totalReserved,
            'pieceQRs'            => $pieceQRs,
            'variantBatchHistory' => $variantBatchHistory,
            'productBatchHistory' => $productBatchHistory,
            'variantDamages'      => $variantDamages,
            'damagedPieceList'    => $damagedPieceList,
            'variantPurchases'    => $variantPurchases,
            'soldPieceList'       => $soldPieceList,
            'variantReturns'      => $variantReturns,
            'returnedPieceList'   => $returnedPieceList,
            'warehouses'          => $warehouses,
            'breadcrumb'          => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Stock Ledger', 'url' => url('warehouse/stock')],
                ['label' => $variant['sku']],
            ],
        ]);
    }

    /**
     * AJAX Edit Product Price (Selling Price & MRP) with Margin Logging & Live Update
     */
    public function ajaxUpdateProductPrice(): void {
        header('Content-Type: application/json');

        if (!hasPermission('catalog', 'edit') && !hasPermission('stock', 'edit') && !hasPermission('warehouse', 'edit') && (Session::get('role') !== 'ADMIN')) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Only administrators or authorized staff can edit product prices.']);
            exit;
        }

        $input = $_POST;
        if (empty($input)) {
            $rawJson = file_get_contents('php://input');
            if (!empty($rawJson)) {
                $input = json_decode($rawJson, true) ?? [];
            }
        }

        $variantIds = [];
        if (!empty($input['variant_ids'])) {
            if (is_array($input['variant_ids'])) {
                $variantIds = array_map('intval', $input['variant_ids']);
            } else {
                $variantIds = array_map('intval', explode(',', (string)$input['variant_ids']));
            }
        } elseif (!empty($input['variant_id'])) {
            $variantIds = [(int)$input['variant_id']];
        }
        $variantIds = array_values(array_unique(array_filter($variantIds, fn($id) => $id > 0)));

        if (empty($variantIds)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one product variant.']);
            exit;
        }

        $newSellingPrice = (float)($input['selling_price'] ?? 0);
        $newMrp = (float)($input['mrp'] ?? 0);
        $notes = trim($input['revision_notes'] ?? '');

        if ($newSellingPrice <= 0 && $newMrp <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please specify a valid selling price or MRP.']);
            exit;
        }

        if ($newMrp < $newSellingPrice) {
            $newMrp = $newSellingPrice;
        }

        $userId = Session::get('user_id') ?: 1;
        $userName = Session::get('user_name') ?: (Session::get('full_name') ?: 'Admin');

        $updatedVariants = [];
        $affectedProductIds = [];

        foreach ($variantIds as $vId) {
            $variant = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, p.id as p_id 
                 FROM product_variants pv 
                 JOIN products p ON pv.product_id = p.id 
                 WHERE pv.id = ?",
                [$vId]
            );
            if (!$variant) continue;

            $oldSellingPrice = (float)($variant['selling_price'] ?? 0);
            $oldMrp = (float)($variant['mrp'] ?? 0);
            $costPrice = (float)(
                (!empty($variant['purchase_price']) && (float)$variant['purchase_price'] > 0) ? $variant['purchase_price'] :
                ((!empty($variant['production_cost']) && (float)$variant['production_cost'] > 0) ? $variant['production_cost'] : 0.0)
            );

            $markupPercentage = $costPrice > 0 ? (($newSellingPrice - $costPrice) / $costPrice) * 100 : null;
            $discountPercentage = $newMrp > 0 ? (($newMrp - $newSellingPrice) / $newMrp) * 100 : null;

            // Update product_variants
            Database::execute(
                "UPDATE product_variants 
                 SET selling_price = ?, mrp = ?, updated_at = NOW() 
                 WHERE id = ?",
                [$newSellingPrice, $newMrp, $vId]
            );

            $affectedProductIds[(int)$variant['product_id']] = true;

            // Log into product_price_logs
            try {
                Database::execute(
                    "INSERT INTO product_price_logs 
                     (variant_id, product_id, sku, old_selling_price, new_selling_price, old_mrp, new_mrp, cost_price, markup_percentage, discount_percentage, revision_notes, user_id, user_name, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $vId,
                        (int)$variant['product_id'],
                        $variant['sku'],
                        $oldSellingPrice,
                        $newSellingPrice,
                        $oldMrp,
                        $newMrp,
                        $costPrice,
                        $markupPercentage,
                        $discountPercentage,
                        $notes ?: 'Price adjustment via Stock Inventory',
                        $userId,
                        $userName
                    ]
                );
            } catch (Exception $e) {
                // log error if any
            }

            // Audit log
            if (class_exists('AuditService')) {
                try {
                    AuditService::log(
                        'warehouse',
                        'PRICE_UPDATE',
                        'product_variants',
                        (int)$vId,
                        json_encode(['mrp' => $oldMrp, 'selling_price' => $oldSellingPrice]),
                        json_encode(['mrp' => $newMrp, 'selling_price' => $newSellingPrice, 'notes' => $notes]),
                        (int)$userId
                    );
                } catch (\Throwable $e) {}
            }

            $updatedVariants[] = [
                'variant_id'              => $vId,
                'sku'                     => $variant['sku'],
                'new_selling_price'       => $newSellingPrice,
                'new_mrp'                 => $newMrp,
                'cost_price'              => $costPrice,
                'formatted_selling_price' => '₹' . number_format($newSellingPrice, 2),
                'formatted_mrp'           => '₹' . number_format($newMrp, 2),
                'formatted_cost_price'    => '₹' . number_format($costPrice, 2),
                'markup_percentage'       => $markupPercentage !== null ? round($markupPercentage, 1) : null,
                'discount_percentage'     => $discountPercentage !== null ? round($discountPercentage, 1) : null,
            ];
        }

        // Touch products table timestamp
        foreach (array_keys($affectedProductIds) as $pid) {
            Database::execute("UPDATE products SET updated_at = NOW() WHERE id = ?", [$pid]);
        }

        $count = count($updatedVariants);
        $first = $updatedVariants[0] ?? [];

        echo json_encode([
            'success'                 => true,
            'message'                 => $count > 1 ? "Price updated successfully for {$count} products." : ("Price updated successfully for SKU: " . ($first['sku'] ?? '')),
            'count'                   => $count,
            'updated_variants'        => $updatedVariants,
            'variant_ids'             => array_column($updatedVariants, 'variant_id'),
            // Backward compatibility for single variant callers (e.g. stock/detail.php)
            'variant_id'              => $first['variant_id'] ?? 0,
            'sku'                     => $first['sku'] ?? '',
            'new_selling_price'       => $newSellingPrice,
            'new_mrp'                 => $newMrp,
            'cost_price'              => $first['cost_price'] ?? 0,
            'formatted_selling_price' => '₹' . number_format($newSellingPrice, 2),
            'formatted_mrp'           => '₹' . number_format($newMrp, 2),
            'formatted_cost_price'    => $first['formatted_cost_price'] ?? '₹0.00',
            'markup_percentage'       => $first['markup_percentage'] ?? null,
            'discount_percentage'     => $first['discount_percentage'] ?? null,
            'updated_at'              => date('d M Y, h:i A')
        ]);
        exit;
    }

    /**
     * Re-generate QR Code stickers for active/available warehouse stock.
     */
    public function regenerateStockQR(string $id): void {
        if (!hasPermission('wh_labels', 'create') && !hasPermission('wh_labels', 'print') && !hasPermission('stock', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to generate QR tags.');
            Response::redirect(url('warehouse/stock'));
            return;
        }
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $variantId = (int)$id;
        $variant = Database::fetchOne("SELECT * FROM product_variants WHERE id = ?", [$variantId]);
        if (!$variant) {
            Response::redirect(url('warehouse/stock'), 'error', 'Product variant not found.');
        }

        $warehouseId = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 1;
        $quantity = max(1, min(500, (int)($_POST['quantity'] ?? 1)));

        // Verify available stock in selected warehouse (strict check against missing stock)
        $avail = Database::fetchOne(
            "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'",
            [$variantId, $warehouseId]
        );
        $maxAvailable = (int)($avail['quantity'] ?? 0);

        if ($maxAvailable <= 0) {
            Session::flash('error', 'Cannot re-generate QR codes: No available received stock for this variant in the selected warehouse. Missing or short items cannot be re-generated.');
            Response::back();
            return;
        }

        $qtyToPrint = min($quantity, $maxAvailable);
        $userId = Session::userId() ?? 1;

        AuditService::log('warehouse', 'print', 'product_variants', $variantId, null, [
            'action'       => 'REGENERATE_QR',
            'warehouse_id' => $warehouseId,
            'quantity'     => $qtyToPrint,
            'sku'          => $variant['sku']
        ]);

        Session::flash('success', "QR code tags successfully re-generated for {$qtyToPrint} available units of SKU {$variant['sku']}. Ready to print directly below.");
        Response::redirect(url('warehouse/stock/detail/' . $variantId));
    }

    // =========================================================================
    // WAREHOUSE DAMAGE MANAGEMENT SUB-MODULE — Member 1
    // =========================================================================

    public function damageList(): void {
        if (!hasPermission('damage', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view damage records.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? (string)$scopedWarehouseId : ($_GET['warehouse_id'] ?? '');

        $filters = [
            'warehouse_id' => $warehouseId,
            'status'       => $_GET['status'] ?? '',
            'source_type'  => $_GET['source_type'] ?? '',
            'search'       => $_GET['search'] ?? '',
        ];

        $damages = WarehouseDamage::getAll($filters);
        $stats = WarehouseDamage::getSummaryStats($scopedWarehouseId);
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        Response::view('warehouse.damage.index', [
            'pageTitle'          => 'Warehouse Damaged Stock',
            'activeModule'       => 'warehouse',
            'activeMenu'         => 'wh-damage',
            'damages'            => $damages,
            'stats'              => $stats,
            'warehouses'         => $warehouses,
            'filters'            => $filters,
            'scopedWarehouseId'  => $scopedWarehouseId,
            'breadcrumb'         => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Damage Management'],
            ],
        ]);
    }

    public function createDamage(): void {
        if (!hasPermission('damage', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to log damaged stock.');
            Response::redirect(url('warehouse/damage'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $variants = Database::fetchAll(
            "SELECT pv.*, p.name as product_name, p.code as product_code,
                    COALESCE(wc.name, cat.name) as category_name,
                    COALESCE(wsc.name, subcat.name) as subcategory_name,
                    COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name,
                    s.name as size_name, c.name as color_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
             LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
             LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
             LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        Response::view('warehouse.damage.create', [
            'pageTitle'          => 'Log Damaged Stock Entry',
            'activeModule'       => 'warehouse',
            'activeMenu'         => 'wh-damage',
            'warehouses'         => $warehouses,
            'scopedWarehouseId'  => $scopedWarehouseId,
            'variants'           => $variants,
            'breadcrumb'         => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Damage Management', 'url' => url('warehouse/damage')],
                ['label' => 'Log Damaged Stock'],
            ],
        ]);
    }

    public function storeDamage(): void {
        if (!hasPermission('damage', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to log damaged stock.');
            Response::redirect(url('warehouse/damage'));
            return;
        }
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_POST['warehouse_id'] ?? 0);
        $actionType = strtolower(trim($_POST['action_type'] ?? 'damage')); // 'damage' or 'rework'
        $userId = Session::userId() ?? 1;

        if ($warehouseId <= 0) {
            Session::flash('error', 'Please select a valid warehouse.');
            Response::back();
        }

        $rawItems = $_POST['items'] ?? null;
        $items = [];

        if (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        } elseif (is_array($rawItems)) {
            $items = $rawItems;
        }

        // Fallback for single item post
        if (empty($items) && !empty($_POST['variant_id'])) {
            $items[] = [
                'variant_id'    => (int)$_POST['variant_id'],
                'tag_code'      => trim($_POST['tag_code'] ?? ''),
                'quantity'      => max(1, (int)($_POST['quantity'] ?? 1)),
                'cost_per_unit' => max(0.0, (float)($_POST['cost_per_unit'] ?? 0.0)),
                'total_loss'    => max(0.0, (float)($_POST['total_loss'] ?? 0.0)),
                'damage_reason' => trim($_POST['damage_reason'] ?? 'Other Defect'),
                'source_type'   => $_POST['source_type'] ?? 'STORAGE_HANDLING',
                'defect_notes'  => trim($_POST['defect_notes'] ?? ''),
            ];
        }

        if (empty($items)) {
            Session::flash('error', 'Please scan or add at least one product before submitting.');
            Response::back();
        }

        // Handle Photo upload
        $photoUrl = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedImgExt = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, $allowedImgExt, true)) {
                $photoUrl = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/warehouse/damages', 'image');
            }
        }

        $status = ($actionType === 'rework') ? 'REWORK' : 'LOGGED';
        $prefix = ($actionType === 'rework') ? 'RWK-' : 'DMG-';
        $savedCount = 0;
        $totalQty = 0;

        try {
            Database::beginTransaction();

            $baseCount = (int)(Database::fetchOne("SELECT COUNT(*) as c FROM warehouse_damages")['c'] ?? 0);

            foreach ($items as $idx => $it) {
                $variantId = (int)($it['variant_id'] ?? 0);
                if ($variantId <= 0) continue;

                $qty = max(1, (int)($it['quantity'] ?? 1));
                $costPerUnit = max(0.0, (float)($it['cost_per_unit'] ?? 0.0));
                if ($costPerUnit <= 0.0) {
                    $vPrice = Database::fetchOne("SELECT selling_price, mrp, production_cost, purchase_price FROM product_variants WHERE id = ?", [$variantId]);
                    if ($vPrice) {
                        $costPerUnit = (float)(
                            (!empty($vPrice['selling_price']) && (float)$vPrice['selling_price'] > 0) ? $vPrice['selling_price'] :
                            ((!empty($vPrice['mrp']) && (float)$vPrice['mrp'] > 0) ? $vPrice['mrp'] :
                            ((!empty($vPrice['production_cost']) && (float)$vPrice['production_cost'] > 0) ? $vPrice['production_cost'] :
                            ((!empty($vPrice['purchase_price']) && (float)$vPrice['purchase_price'] > 0) ? $vPrice['purchase_price'] : 0.0)))
                        );
                    }
                }
                $totalLoss = max(0.0, (float)($it['total_loss'] ?? ($qty * $costPerUnit)));
                if ($totalLoss <= 0.0 && $costPerUnit > 0.0) {
                    $totalLoss = $qty * $costPerUnit;
                }
                $tagCode = trim($it['tag_code'] ?? '');
                if ($qty > 1) {
                    $tagsArray = array_filter(array_map('trim', explode(',', $tagCode)));
                    if (count($tagsArray) < $qty) {
                        $vInfo = Database::fetchOne("
                            SELECT COALESCE(cat.name, wc.name, 'M') as cat_name,
                                   COALESCE(subcat.name, wsc.name, 'T') as sub_name,
                                   COALESCE(subsubcat.name, wssc.name, 'P') as subsub_name
                            FROM product_variants pv
                            JOIN products p ON pv.product_id = p.id
                            LEFT JOIN categories cat ON p.category_id = cat.id
                            LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                            LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                            LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                            LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                            LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                            WHERE pv.id = ? LIMIT 1
                        ", [$variantId]);
                        $c1 = !empty($vInfo['cat_name']) ? strtoupper($vInfo['cat_name'][0]) : 'M';
                        $c2 = !empty($vInfo['sub_name']) ? strtoupper($vInfo['sub_name'][0]) : 'T';
                        $c3 = !empty($vInfo['subsub_name']) ? strtoupper($vInfo['subsub_name'][0]) : 'P';
                        $tPrefix = $c1 . $c2 . $c3 . date('my');
                        $startNum = 1;
                        if (!empty($tagsArray) && preg_match('/^(.*?)(\d{1,5})$/', $tagsArray[0], $tm)) {
                            $tPrefix = $tm[1];
                            $startNum = (int)$tm[2];
                        }
                        for ($qi = count($tagsArray); $qi < $qty; $qi++) {
                            $tagsArray[] = $tPrefix . str_pad((string)($startNum + $qi), 2, '0', STR_PAD_LEFT);
                        }
                        $tagCode = implode(', ', $tagsArray);
                    }
                }
                $damageReason = trim($it['damage_reason'] ?? 'Defect / Damage');
                $sourceType = !empty($it['source_type']) ? $it['source_type'] : ($_POST['source_type'] ?? 'STORAGE_HANDLING');
                $defectNotes = trim($it['defect_notes'] ?? ($_POST['defect_notes'] ?? ''));

                $damageNumber = $prefix . date('Ymd') . '-' . str_pad((string)($baseCount + $idx + 1), 4, '0', STR_PAD_LEFT);

                $damageId = WarehouseDamage::create([
                    'damage_number' => $damageNumber,
                    'warehouse_id'  => $warehouseId,
                    'variant_id'    => $variantId,
                    'tag_code'      => !empty($tagCode) ? $tagCode : null,
                    'source_type'   => $sourceType,
                    'quantity'      => $qty,
                    'cost_per_unit' => $costPerUnit,
                    'total_loss'    => $totalLoss,
                    'damage_reason' => $damageReason,
                    'defect_notes'  => $defectNotes,
                    'photo_url'     => $photoUrl,
                    'status'        => $status,
                    'reported_by'   => $userId,
                ]);

                if ($actionType === 'rework') {
                    // Deduct from available stock and dispatch out to factory for rework
                    StockService::decrease(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'AVAILABLE',
                        quantity: $qty,
                        transactionType: 'REWORK_OUT',
                        referenceType: 'warehouse_damage',
                        referenceId: $damageId,
                        userId: $userId,
                        notes: "Dispatched to Factory Rework: {$damageNumber} - {$damageReason}" . (!empty($tagCode) ? " [Tag: {$tagCode}]" : "")
                    );

                    // Seamlessly create corresponding record in Production Rework from Warehouse module
                    try {
                        ProductionWarehouseRework::create([
                            'rework_number'       => $damageNumber,
                            'warehouse_id'        => $warehouseId,
                            'variant_id'          => $variantId,
                            'quantity'            => $qty,
                            'defect_type'         => $it['defect_type'] ?? 'OTHER',
                            'damage_reason'       => $damageReason,
                            'defect_notes'        => $defectNotes,
                            'warehouse_damage_id' => $damageId,
                            'received_date'       => date('Y-m-d'),
                            'status'              => 'RECEIVED',
                            'created_by'          => $userId,
                        ]);
                    } catch (Exception $e) {
                        // Keep non-fatal to ensure damage logging succeeds
                    }
                } else {
                    // Adjust stock: Move available stock to damaged state (quarantine)
                    StockService::decrease(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'AVAILABLE',
                        quantity: $qty,
                        transactionType: 'DAMAGE_OUT',
                        referenceType: 'warehouse_damage',
                        referenceId: $damageId,
                        userId: $userId,
                        notes: "Stock damaged: {$damageNumber} - {$damageReason}" . (!empty($tagCode) ? " [Tag: {$tagCode}]" : "")
                    );

                    StockService::increase(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'DAMAGED',
                        quantity: $qty,
                        transactionType: 'DAMAGE_IN',
                        referenceType: 'warehouse_damage',
                        referenceId: $damageId,
                        userId: $userId,
                        notes: "Quarantine damaged stock: {$damageNumber}" . (!empty($tagCode) ? " [Tag: {$tagCode}]" : "")
                    );
                }

                $savedCount++;
                $totalQty += $qty;
            }

            Database::commit();

            if ($actionType === 'rework') {
                Session::flash('success', "Successfully recorded {$savedCount} item(s) ({$totalQty} pcs) dispatched to Factory Rework.");
            } else {
                Session::flash('success', "Successfully recorded and quarantined {$savedCount} damaged item(s) ({$totalQty} pcs) in warehouse inventory.");
            }

            Response::redirect(url('warehouse/damage'));
        } catch (Exception $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            Session::flash('error', 'Error recording entry: ' . $e->getMessage());
            Response::back();
        }
    }

    public function ajaxLookupDamageProduct(): void {
        if (!hasPermission('damage', 'view') && !hasPermission('damage', 'create')) {
            Response::jsonError('Access denied.', [], 403);
            return;
        }
        header('Content-Type: application/json');

        $rawCode = trim($_REQUEST['code'] ?? $_REQUEST['query'] ?? $_REQUEST['search'] ?? '');
        $warehouseId = (int)($_REQUEST['warehouse_id'] ?? 0);

        if (empty($rawCode)) {
            echo json_encode(['success' => false, 'message' => 'No scan code or SKU provided.']);
            exit;
        }

        // Clean raw input (strip surrounding quotes, spaces, control characters)
        $rawCode = trim($rawCode, " \t\n\r\0\x0B\"'");

        $extractedTag = $rawCode;
        $candidateCodes = [$rawCode];

        // 1. If JSON payload (e.g. {"tag":"HHH102605","sku":"..."})
        if (str_starts_with($rawCode, '{') && str_ends_with($rawCode, '}')) {
            $decoded = json_decode($rawCode, true);
            if (is_array($decoded)) {
                $extractedTag = $decoded['tag'] ?? $decoded['tag_code'] ?? $decoded['code'] ?? $rawCode;
                if (!empty($decoded['sku'])) $candidateCodes[] = trim($decoded['sku']);
                if (!empty($decoded['tag'])) $candidateCodes[] = trim($decoded['tag']);
                if (!empty($decoded['tag_code'])) $candidateCodes[] = trim($decoded['tag_code']);
                if (!empty($decoded['code'])) $candidateCodes[] = trim($decoded['code']);
                if (!empty($decoded['qr_code'])) $candidateCodes[] = trim($decoded['qr_code']);
            }
        }
        // 2. If Pipe-delimited QR payload (e.g. "BCT102601|BCT0303-12Y-BLK|MRP:999|Disc:599")
        elseif (strpos($rawCode, '|') !== false) {
            $parts = array_map('trim', explode('|', $rawCode));
            $extractedTag = $parts[0];
            
            foreach ($parts as $part) {
                if (stripos($part, 'MRP:') === 0 || stripos($part, 'Disc:') === 0 || stripos($part, 'Cost:') === 0 || stripos($part, 'Price:') === 0) {
                    continue;
                }
                $candidateCodes[] = $part;
            }

            if (isset($parts[1]) && !empty($parts[1])) {
                $candidateCodes[] = $parts[1];
            }
            if (isset($parts[2]) && !empty($parts[2])) {
                $candidateCodes[] = $parts[2];
            }
        }
        // 3. If URL encoded with query params
        elseif (strpos($rawCode, 'http') === 0 && (strpos($rawCode, '?') !== false || strpos($rawCode, '/') !== false)) {
            $parsedUrl = parse_url($rawCode);
            if (!empty($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                if (!empty($queryParams['code'])) $candidateCodes[] = $queryParams['code'];
                if (!empty($queryParams['sku'])) $candidateCodes[] = $queryParams['sku'];
                if (!empty($queryParams['tag'])) $candidateCodes[] = $queryParams['tag'];
            }
            if (!empty($parsedUrl['path'])) {
                $pathSegments = explode('/', trim($parsedUrl['path'], '/'));
                $candidateCodes[] = end($pathSegments);
            }
        }

        $candidateCodes = array_values(array_unique(array_filter($candidateCodes)));
        $variant = null;

        // Strategy 1: Exact match on candidate codes against product_variants (sku, qr_code, id) or products (code, qr_code_prefix)
        foreach ($candidateCodes as $c) {
            if (empty($c)) continue;
            $variant = Database::fetchOne(
                "SELECT pv.*, 
                        p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                        COALESCE(p.gst_percentage, 5.0) as gst_rate, p.gst_percentage,
                        p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        s.name as size_name, s.code as size_code,
                        col.name as color_name, col.hex_code as color_hex,
                        COALESCE(wc.name, cat.name) as category_name,
                        COALESCE(wsc.name, subcat.name) as subcategory_name,
                        COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                 LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                 LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                 LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                 WHERE pv.sku = ? 
                    OR pv.qr_code = ? 
                    OR p.code = ?
                    OR p.qr_code_prefix = ?
                    OR (pv.id = ? AND ? > 0)
                 LIMIT 1",
                [$c, $c, $c, $c, is_numeric($c) ? (int)$c : 0, is_numeric($c) ? (int)$c : 0]
            );
            if ($variant) break;
        }

        // Strategy 2: Historical Inward GRN / Stock transactions / Purchase Items tag search
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                if (empty($c) || strlen($c) < 3) continue;
                $tx = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM stock_transactions st
                     JOIN product_variants pv ON st.variant_id = pv.id
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . " AND sb.stock_state = 'AVAILABLE'
                     WHERE st.notes LIKE ? OR st.notes LIKE ?
                     ORDER BY sb.quantity DESC, st.id DESC
                     LIMIT 1",
                    ['%' . $c . '%', '%Tags: ' . $c . '%']
                );
                if ($tx) {
                    $variant = $tx;
                    break;
                }

                $pi = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM purchase_items pi
                     JOIN product_variants pv ON pi.variant_id = pv.id
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . " AND sb.stock_state = 'AVAILABLE'
                     WHERE pi.batch_lot_no LIKE ? OR pi.notes LIKE ?
                     ORDER BY sb.quantity DESC, pi.id DESC
                     LIMIT 1",
                    ['%' . $c . '%', '%' . $c . '%']
                );
                if ($pi) {
                    $variant = $pi;
                    break;
                }
            }
        }

        // Strategy 3: Lookup from QC Items recorded tag_code
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                $qc = Database::fetchOne(
                    "SELECT variant_id, tag_code FROM qc_items WHERE tag_code = ? ORDER BY id DESC LIMIT 1",
                    [$c]
                );
                if ($qc && !empty($qc['variant_id'])) {
                    $variant = Database::fetchOne(
                        "SELECT pv.*, 
                                p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                s.name as size_name, s.code as size_code,
                                col.name as color_name, col.hex_code as color_hex,
                                COALESCE(wc.name, cat.name) as category_name,
                                COALESCE(wsc.name, subcat.name) as subcategory_name,
                                COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                         LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                         LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                         LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                         WHERE pv.id = ? LIMIT 1",
                        [$qc['variant_id']]
                    );
                    if ($variant) {
                        $extractedTag = $qc['tag_code'] ?: $extractedTag;
                        break;
                    }
                }
            }
        }

        // Strategy 4: Lookup by Production Batch Number (e.g. PB-2026-001, BAT-20260919-0001)
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                $batchItem = Database::fetchOne(
                    "SELECT pbi.variant_id 
                     FROM production_batches pb 
                     JOIN production_batch_items pbi ON pbi.production_batch_id = pb.id 
                     WHERE pb.batch_number = ? OR pb.batch_number LIKE ? 
                     ORDER BY pbi.id ASC LIMIT 1",
                    [$c, '%' . $c . '%']
                );
                if ($batchItem && !empty($batchItem['variant_id'])) {
                    $variant = Database::fetchOne(
                        "SELECT pv.*, 
                                p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                s.name as size_name, s.code as size_code,
                                col.name as color_name, col.hex_code as color_hex,
                                COALESCE(wc.name, cat.name) as category_name,
                                COALESCE(wsc.name, subcat.name) as subcategory_name,
                                COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                         LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                         LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                         LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                         WHERE pv.id = ? LIMIT 1",
                        [$batchItem['variant_id']]
                    );
                    if ($variant) break;
                }
            }
        }

        // Strategy 5: Lookup from previous damage records with this tag_code
        if (!$variant) {
            $dmgTag = Database::fetchOne(
                "SELECT variant_id FROM warehouse_damages WHERE tag_code = ? OR damage_number = ? ORDER BY id DESC LIMIT 1",
                [$extractedTag, $rawCode]
            );
            if ($dmgTag && !empty($dmgTag['variant_id'])) {
                $variant = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM product_variants pv
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     WHERE pv.id = ? LIMIT 1",
                    [$dmgTag['variant_id']]
                );
            }
        }

        // Strategy 6: Dynamic Piece Tag Decomposition & Reverse Prefix/Sequence Matching
        if (!$variant) {
            if (preg_match('/^([A-Za-z_-]{2,6})(\d{4})(\d{1,5})$/i', $extractedTag, $matches)) {
                $prefix = strtoupper($matches[1]);
                $mfgDigits = $matches[2];
                $seq = (int)$matches[3];
                $mfgMonth = (int)substr($mfgDigits, 0, 2);

                if ($seq >= 1 && $mfgMonth >= 1 && $mfgMonth <= 12) {
                    $allVariants = Database::fetchAll("
                        SELECT pv.*, 
                               p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                               p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                               p.category_id, p.subcategory_id, p.sub_subcategory_id,
                               s.name as size_name, s.code as size_code,
                               col.name as color_name, col.hex_code as color_hex,
                               COALESCE(wc.name, cat.name) as category_name,
                               COALESCE(wsc.name, subcat.name) as subcategory_name,
                               COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name,
                               COALESCE(wc.code, cat.code, p.category_code) as cat_code,
                               COALESCE(wsc.code, subcat.code, p.subcategory_code) as sub_code,
                               COALESCE(wssc.code, subsubcat.code, p.sub_subcategory_code) as subsub_code,
                               COALESCE(sb.quantity, 0) as wh_stock
                        FROM product_variants pv
                        JOIN products p ON pv.product_id = p.id
                        LEFT JOIN sizes s ON pv.size_id = s.id
                        LEFT JOIN colors col ON pv.color_id = col.id
                        LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                        LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                        LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                        LEFT JOIN categories cat ON p.category_id = cat.id
                        LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                        LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                        LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . " AND sb.stock_state = 'AVAILABLE'
                        WHERE pv.is_active = 1
                        ORDER BY wh_stock DESC, pv.id ASC
                    ");

                    $bestMatch = null;
                    $bestScore = 0;

                    foreach ($allVariants as $v) {
                        $prefixes = [];
                        if (!empty($v['qr_code_prefix'])) {
                            $prefixes[] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v['qr_code_prefix']));
                        }
                        if (!empty($v['product_code'])) {
                            $prefixes[] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', explode('-', $v['product_code'])[0]));
                        }
                        if (!empty($v['sku'])) {
                            $prefixes[] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', explode('-', $v['sku'])[0]));
                            $prefixes[] = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $v['sku']), 0, 3));
                        }
                        $c1 = !empty($v['category_name']) ? strtoupper($v['category_name'][0]) : (!empty($v['cat_code']) ? strtoupper($v['cat_code'][0]) : '');
                        $c2 = !empty($v['subcategory_name']) ? strtoupper($v['subcategory_name'][0]) : (!empty($v['sub_code']) ? strtoupper($v['sub_code'][0]) : '');
                        $c3 = !empty($v['sub_subcategory_name']) ? strtoupper($v['sub_subcategory_name'][0]) : (!empty($v['subsub_code']) ? strtoupper($v['subsub_code'][0]) : '');
                        if ($c1 && $c2) {
                            $prefixes[] = $c1 . $c2 . $c3;
                            $prefixes[] = $c1 . $c2;
                            $prefixes[] = 'R' . $c1 . $c2 . $c3;
                        }

                        $stock = (float)$v['wh_stock'];
                        $isExactPrefixMatch = false;
                        $isPrefixStartMatch = false;

                        foreach ($prefixes as $pfx) {
                            if (empty($pfx)) continue;
                            if ($prefix === $pfx) {
                                $isExactPrefixMatch = true;
                                break;
                            } elseif (str_starts_with($prefix, $pfx) || str_starts_with($pfx, $prefix)) {
                                $isPrefixStartMatch = true;
                            }
                        }

                        if ($isExactPrefixMatch) {
                            $score = 300 + min(50, (int)$stock);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestMatch = $v;
                            }
                        } elseif ($isPrefixStartMatch) {
                            $score = 150 + min(50, (int)$stock);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestMatch = $v;
                            }
                        }
                    }

                    if ($bestMatch) {
                        $variant = $bestMatch;
                    }
                }
            }
        }

        // Strategy 7: Fuzzy search LIKE on SKU, Product Name, Code, Color, Size
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                if (strlen($c) < 2) continue;
                $variant = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM product_variants pv
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     WHERE pv.sku LIKE ? OR p.name LIKE ? OR p.code LIKE ? OR col.name LIKE ?
                     LIMIT 1",
                    ['%' . $c . '%', '%' . $c . '%', '%' . $c . '%', '%' . $c . '%']
                );
                if ($variant) break;
            }
        }

        // Also fetch multiple suggestions / matches if searching (for live dropdown)
        $searchTerms = '%' . $rawCode . '%';
        $matchesList = Database::fetchAll(
            "SELECT pv.*, 
                    p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                    s.name as size_name, col.name as color_name,
                    COALESCE(wc.name, cat.name) as category_name,
                    COALESCE(wsc.name, subcat.name) as subcategory_name,
                    COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . "
                          AND sb.stock_state = 'AVAILABLE'
                    ), 0) as available_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors col ON pv.color_id = col.id
             LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
             LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
             LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
             LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
             WHERE pv.sku LIKE ? OR p.name LIKE ? OR p.code LIKE ? OR pv.qr_code LIKE ? OR col.name LIKE ? OR s.name LIKE ?
             ORDER BY available_stock DESC, pv.id ASC
             LIMIT 10",
            [$searchTerms, $searchTerms, $searchTerms, $searchTerms, $searchTerms, $searchTerms]
        );

        $formattedMatches = [];
        foreach ($matchesList as $m) {
            $catH = implode(' › ', array_filter([$m['category_name'] ?? '', $m['subcategory_name'] ?? '', $m['sub_subcategory_name'] ?? '']));
            $mCost = (float)(
                (!empty($m['selling_price']) && (float)$m['selling_price'] > 0) ? $m['selling_price'] :
                ((!empty($m['mrp']) && (float)$m['mrp'] > 0) ? $m['mrp'] :
                ((!empty($m['purchase_price']) && (float)$m['purchase_price'] > 0) ? $m['purchase_price'] :
                ((!empty($m['production_cost']) && (float)$m['production_cost'] > 0) ? $m['production_cost'] : 0.0)))
            );
            $formattedMatches[] = [
                'id'                 => (int)$m['id'],
                'sku'                => $m['sku'],
                'product_name'       => $m['product_name'],
                'product_code'       => $m['product_code'],
                'category_hierarchy' => $catH,
                'size_name'          => $m['size_name'] ?? 'Free Size',
                'color_name'         => $m['color_name'] ?? 'Standard',
                'available_stock'    => (int)($m['available_stock'] ?? 0),
                'cost_per_unit'      => $mCost,
                'mrp'                => (float)($m['mrp'] ?? $m['selling_price'] ?? 0),
                'tag_code'           => $m['sku']
            ];
        }

        if (!$variant) {
            echo json_encode([
                'success' => false,
                'message' => 'No matching product variant found for code "' . htmlspecialchars($rawCode) . '"',
                'matches' => $formattedMatches
            ]);
            exit;
        }

        // Fetch Stock Balances in this warehouse
        $stockSql = "SELECT 
                        COALESCE(SUM(CASE WHEN stock_state = 'AVAILABLE' THEN quantity ELSE 0 END), 0) as available_qty,
                        COALESCE(SUM(CASE WHEN stock_state = 'DAMAGED' THEN quantity ELSE 0 END), 0) as damaged_qty,
                        COALESCE(SUM(CASE WHEN stock_state = 'RESERVED' THEN quantity ELSE 0 END), 0) as reserved_qty
                     FROM stock_balances
                     WHERE variant_id = ? AND location_type = 'WAREHOUSE'";
        $stockParams = [$variant['id']];
        if ($warehouseId > 0) {
            $stockSql .= " AND location_id = ?";
            $stockParams[] = $warehouseId;
        }
        $stock = Database::fetchOne($stockSql, $stockParams);

        $cost = (float)(
            (!empty($variant['selling_price']) && (float)$variant['selling_price'] > 0) ? $variant['selling_price'] :
            ((!empty($variant['mrp']) && (float)$variant['mrp'] > 0) ? $variant['mrp'] :
            ((!empty($variant['purchase_price']) && (float)$variant['purchase_price'] > 0) ? $variant['purchase_price'] :
            ((!empty($variant['production_cost']) && (float)$variant['production_cost'] > 0) ? $variant['production_cost'] : 0.0)))
        );

        $catHierarchy = implode(' › ', array_filter([
            $variant['category_name'] ?? '',
            $variant['subcategory_name'] ?? '',
            $variant['sub_subcategory_name'] ?? ''
        ]));

        echo json_encode([
            'success' => true,
            'variant' => [
                'id'                   => (int)$variant['id'],
                'sku'                  => $variant['sku'],
                'product_id'           => (int)$variant['product_id'],
                'product_name'         => $variant['product_name'],
                'product_code'         => $variant['product_code'],
                'category_id'          => $variant['category_id'] ?? null,
                'subcategory_id'       => $variant['subcategory_id'] ?? null,
                'sub_subcategory_id'   => $variant['sub_subcategory_id'] ?? null,
                'category_name'        => $variant['category_name'] ?? 'General',
                'subcategory_name'     => $variant['subcategory_name'] ?? '',
                'sub_subcategory_name' => $variant['sub_subcategory_name'] ?? '',
                'category_hierarchy'   => $catHierarchy,
                'size_name'            => $variant['size_name'] ?? 'Free Size',
                'color_name'           => $variant['color_name'] ?? 'Standard',
                'color_hex'            => $variant['color_hex'] ?? '#333333',
                'product_image'        => $variant['product_image'] ?? null,
                'cost_per_unit'        => $cost,
                'mrp'                  => (float)($variant['mrp'] ?? $variant['selling_price'] ?? 0),
                'selling_price'        => (float)($variant['selling_price'] ?? 0),
                'tag_code'             => $extractedTag,
                'available_stock'      => (int)($stock['available_qty'] ?? 0),
                'damaged_stock'        => (int)($stock['damaged_qty'] ?? 0),
                'reserved_stock'       => (int)($stock['reserved_qty'] ?? 0),
            ],
            'matches' => $formattedMatches
        ]);
        exit;
    }

    public function viewDamage(string $id): void {
        if (!hasPermission('damage', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view damage details.');
            Response::redirect(url('warehouse/damage'));
            return;
        }
        $damageId = (int)$id;
        $damage = WarehouseDamage::find($damageId);
        if (!$damage) {
            Response::redirect(url('warehouse/damage'), 'error', 'Damage record not found.');
        }

        // Fetch audit logs for this damage case
        $auditLogs = AuditService::getLogsForReference('warehouse_damages', $damageId);

        // If linked to production batch, fetch batch and its audit logs
        $batch = null;
        $batchAuditLogs = [];
        $batchLifecycle = null;
        if (!empty($damage['reference_id']) && in_array($damage['source_type'], ['PRODUCTION_INWARD', 'INWARD_GRN', 'PRODUCTION_BATCH'])) {
            $batch = ProductionBatch::find((int)$damage['reference_id']);
            $batchAuditLogs = AuditService::getLogsForReference('production_batch', (int)$damage['reference_id']);
            $batchLifecycle = ProductionBatch::getLifecycleHistory((int)$damage['reference_id']);
        }

        // Fetch active production units for routing / rework assignment
        $productionUnits = Database::fetchAll("SELECT id, name, code, manager_name, phone FROM production_units WHERE is_active = 1 ORDER BY code ASC, name ASC");

        Response::view('warehouse.damage.view', [
            'pageTitle'       => 'Damage Case: ' . $damage['damage_number'],
            'activeModule'    => 'warehouse',
            'activeMenu'      => 'wh-damage',
            'damage'          => $damage,
            'auditLogs'       => $auditLogs,
            'batch'           => $batch,
            'batchAuditLogs'  => $batchAuditLogs,
            'batchLifecycle'  => $batchLifecycle,
            'productionUnits' => $productionUnits,
            'breadcrumb'      => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Damage Management', 'url' => url('warehouse/damage')],
                ['label' => $damage['damage_number']],
            ],
        ]);
    }

    public function updateDamageStatus(string $id): void {
        if (!hasPermission('damage', 'resolve') && !hasPermission('damage', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to update damage status.');
            Response::redirect(url('warehouse/damage'));
            return;
        }
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::back();
        }

        $damageId = (int)$id;
        $damage = WarehouseDamage::find($damageId);
        if (!$damage) {
            Response::redirect(url('warehouse/damage'), 'error', 'Damage record not found.');
        }

        $status = $_POST['status'] ?? 'LOGGED';
        $notes = trim($_POST['defect_notes'] ?? '');
        $productionUnitId = !empty($_POST['production_unit_id']) ? (int)$_POST['production_unit_id'] : null;
        $userId = Session::userId() ?? 1;

        // Handle Photo upload / update via Cloudinary
        $photoUrl = $damage['photo_url'] ?? null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedImgExt = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, $allowedImgExt, true)) {
                $uploadedUrl = CloudinaryService::upload($_FILES['photo'], 'grm_inventory/warehouse/damages', 'image');
                if ($uploadedUrl) {
                    $photoUrl = $uploadedUrl;
                }
            }
        }

        try {
            $oldStatus = strtoupper(trim($damage['status'] ?? 'LOGGED'));
            $newStatus = strtoupper(trim($status));
            $variantId = (int)$damage['variant_id'];
            $warehouseId = (int)$damage['warehouse_id'];
            $totalDamagedQty = max(1, (int)$damage['quantity']);

            // Parse all tags belonging to this damage record
            $allTags = [];
            if (!empty($damage['tag_code'])) {
                $allTags = array_values(array_filter(array_map('trim', explode(',', $damage['tag_code']))));
            }
            if (empty($allTags)) {
                for ($i = 1; $i <= $totalDamagedQty; $i++) {
                    $allTags[] = 'DMG-' . str_pad($damage['id'], 4, '0', STR_PAD_LEFT) . '-' . $i;
                }
            }

            // Determine user-selected tags for disposition
            $selectedTagsInput = $_POST['selected_tags'] ?? null;
            $selectedTags = !empty($selectedTagsInput) && is_array($selectedTagsInput)
                ? array_values(array_filter(array_map('trim', $selectedTagsInput)))
                : $allTags;

            // Keep only valid tags that belong to this case
            $selectedTags = array_values(array_intersect($selectedTags, $allTags));

            if (empty($selectedTags)) {
                Session::flash('error', 'Please pick at least one damaged piece using the checkboxes to proceed.');
                Response::back();
                return;
            }

            $selectedCount = count($selectedTags);
            $isPartial = ($selectedCount < count($allTags));

            // =========================================================================
            // A. PARTIAL DISPATCH / SPLIT DISPOSITION (e.g. 3 of 10 pcs sent to Rework)
            // =========================================================================
            if ($isPartial) {
                Database::beginTransaction();

                $remainingTags = array_values(array_diff($allTags, $selectedTags));
                $remainingCount = count($remainingTags);
                $unitCost = (float)($damage['cost_per_unit'] > 0 ? $damage['cost_per_unit'] : ($damage['total_loss'] / max(1, $totalDamagedQty)));
                $selectedLoss = round($selectedCount * $unitCost, 2);
                $remainingLoss = round($remainingCount * $unitCost, 2);

                // Generate unique split case number (e.g. DMG-20261013-0001-P1)
                $baseNumber = $damage['damage_number'];
                $pNum = 1;
                do {
                    $newDamageNumber = $baseNumber . '-P' . $pNum;
                    $chk = Database::fetchOne("SELECT id FROM warehouse_damages WHERE damage_number = ?", [$newDamageNumber]);
                    $pNum++;
                } while ($chk);

                // Handle stock balance changes if moving to RESTOCKED/REPAIRED or WRITTEN_OFF
                if (in_array($newStatus, ['RESTOCKED', 'REPAIRED'], true) && $variantId > 0 && $warehouseId > 0) {
                    $currDamagedBalance = (float)(Database::fetchOne(
                        "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'DAMAGED'",
                        [$variantId, $warehouseId]
                    )['quantity'] ?? 0);
                    if ($currDamagedBalance > 0) {
                        $dec = min((float)$selectedCount, $currDamagedBalance);
                        StockService::decrease(
                            variantId: $variantId, locationType: 'WAREHOUSE', locationId: $warehouseId,
                            stockState: 'DAMAGED', quantity: $dec, transactionType: 'DAMAGE_RESTORE_OUT',
                            referenceType: 'warehouse_damage', referenceId: $damageId, userId: $userId,
                            notes: "Restored {$selectedCount} pcs from Damage Case #{$damage['damage_number']} [Status: {$newStatus}]"
                        );
                    }
                    StockService::increase(
                        variantId: $variantId, locationType: 'WAREHOUSE', locationId: $warehouseId,
                        stockState: 'AVAILABLE', quantity: (float)$selectedCount, transactionType: 'RESTOCK_GOOD',
                        referenceType: 'warehouse_damage', referenceId: $damageId, userId: $userId,
                        notes: "Restocked {$selectedCount} picked pcs from Damage Case #{$damage['damage_number']} [Status: {$newStatus}]"
                    );
                } elseif (in_array($newStatus, ['WRITTEN_OFF', 'DISPOSED', 'SCRAPPED'], true) && $variantId > 0 && $warehouseId > 0) {
                    $currDamagedBalance = (float)(Database::fetchOne(
                        "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'DAMAGED'",
                        [$variantId, $warehouseId]
                    )['quantity'] ?? 0);
                    if ($currDamagedBalance > 0) {
                        $dec = min((float)$selectedCount, $currDamagedBalance);
                        StockService::decrease(
                            variantId: $variantId, locationType: 'WAREHOUSE', locationId: $warehouseId,
                            stockState: 'DAMAGED', quantity: $dec, transactionType: 'DAMAGE_DISPOSAL',
                            referenceType: 'warehouse_damage', referenceId: $damageId, userId: $userId,
                            notes: "Written off {$selectedCount} pcs from Damage Case #{$damage['damage_number']}"
                        );
                    }
                }

                // 1. Create new split damage record for the picked pieces
                $newNotes = !empty($notes) ? $notes : "Partial dispatch of {$selectedCount} pieces to {$newStatus}. (Split from Case #{$damage['damage_number']})";
                $newDamageId = WarehouseDamage::create([
                    'damage_number'      => $newDamageNumber,
                    'warehouse_id'       => (int)$damage['warehouse_id'],
                    'variant_id'         => (int)$damage['variant_id'],
                    'tag_code'           => implode(', ', $selectedTags),
                    'source_type'        => $damage['source_type'] ?? 'STORAGE_HANDLING',
                    'reference_id'       => !empty($damage['reference_id']) ? (int)$damage['reference_id'] : null,
                    'quantity'           => $selectedCount,
                    'cost_per_unit'      => $unitCost,
                    'total_loss'         => $selectedLoss,
                    'damage_reason'      => $damage['damage_reason'] ?? 'Defect',
                    'defect_notes'       => $newNotes,
                    'photo_url'          => $photoUrl,
                    'status'             => $newStatus,
                    'reported_by'        => $userId,
                    'production_unit_id' => ($newStatus === 'REWORK' && $productionUnitId) ? $productionUnitId : null,
                ]);

                // 2. Update remaining pieces in original damage record
                $origNotes = trim($damage['defect_notes'] ?? '');
                $origNotes .= "\n[" . date('d M Y, h:i A') . "] Partial dispatch: {$selectedCount} pcs (" . implode(', ', $selectedTags) . ") split and sent to {$newStatus} as Case #{$newDamageNumber}. Remaining: {$remainingCount} pcs in quarantine.";
                WarehouseDamage::update($damageId, [
                    'quantity'     => $remainingCount,
                    'total_loss'   => $remainingLoss,
                    'tag_code'     => implode(', ', $remainingTags),
                    'defect_notes' => $origNotes,
                ]);

                $puInfo = null;
                if ($newStatus === 'REWORK' && $productionUnitId) {
                    $puInfo = Database::fetchOne("SELECT id, name, code, manager_name, phone FROM production_units WHERE id = ?", [$productionUnitId]);
                }
                $puNameStr = $puInfo ? "[{$puInfo['code']}] {$puInfo['name']}" : null;

                AuditService::log('warehouse', 'damage_partial_split', 'warehouse_damages', $damageId, 
                    ['quantity' => $totalDamagedQty, 'tags' => implode(', ', $allTags)],
                    [
                        'remaining_quantity' => $remainingCount,
                        'split_case'         => $newDamageNumber,
                        'split_count'        => $selectedCount,
                        'split_status'       => $newStatus,
                        'production_unit'    => $puNameStr,
                    ],
                    $userId
                );

                AuditService::log('warehouse', 'damage_created_from_split', 'warehouse_damages', $newDamageId,
                    null,
                    [
                        'damage_number'   => $newDamageNumber,
                        'origin_case'     => $damage['damage_number'],
                        'quantity'        => $selectedCount,
                        'tags'            => implode(', ', $selectedTags),
                        'status'          => $newStatus,
                        'production_unit' => $puNameStr,
                    ],
                    $userId
                );

                // Seamlessly dispatch to Production Rework module
                if ($newStatus === 'REWORK') {
                    $batchId = !empty($damage['production_batch_id']) ? (int)$damage['production_batch_id'] : (!empty($damage['reference_id']) && in_array($damage['source_type'] ?? '', ['PRODUCTION_INWARD', 'INWARD_GRN', 'PRODUCTION_BATCH']) ? (int)$damage['reference_id'] : null);
                    $outwardId = null;
                    if ($batchId) {
                        $po = Database::fetchOne("SELECT id FROM production_outwards WHERE production_batch_id = ?", [$batchId]);
                        if ($po) $outwardId = (int)$po['id'];
                    }
                    $reworkNum = str_starts_with($newDamageNumber, 'DMG-') ? str_replace('DMG-', 'RWK-', $newDamageNumber) : ('RWK-' . $newDamageNumber);
                    try {
                        ProductionWarehouseRework::create([
                            'rework_number'          => $reworkNum,
                            'warehouse_id'           => (int)$damage['warehouse_id'],
                            'production_outward_id'  => $outwardId,
                            'production_batch_id'    => $batchId,
                            'warehouse_damage_id'    => $newDamageId,
                            'variant_id'             => (int)$damage['variant_id'],
                            'production_unit_id'     => $productionUnitId ?: null,
                            'quantity'               => $selectedCount,
                            'defect_type'            => $damage['defect_type'] ?? 'OTHER',
                            'damage_reason'          => $damage['damage_reason'] ?? 'Defect',
                            'defect_notes'           => $newNotes,
                            'photo_url'              => $photoUrl ?: ($damage['photo_url'] ?? null),
                            'received_date'          => date('Y-m-d'),
                            'received_by'            => $userId,
                            'status'                 => 'RECEIVED',
                            'created_by'             => $userId,
                        ]);
                    } catch (Exception $e) {
                        error_log('Failed to create ProductionWarehouseRework for split: ' . $e->getMessage());
                    }
                }

                Database::commit();

                $successMsg = "Dispatched {$selectedCount} picked piece" . ($selectedCount > 1 ? 's' : '') . " to {$newStatus}";
                if ($newStatus === 'REWORK' && $puNameStr) {
                    $successMsg .= " at {$puNameStr}";
                }
                $successMsg .= " (New Case #{$newDamageNumber}). Remaining {$remainingCount} piece" . ($remainingCount > 1 ? 's' : '') . " remain quarantined in Case #{$damage['damage_number']}.";

                Session::flash('success', $successMsg);
                Response::redirect(url('warehouse/damage/view/' . $damageId));
                return;
            }

            // =========================================================================
            // B. FULL DISPATCH / ALL PIECES (Existing behavior for all pieces)
            // =========================================================================
            $qty = (float)$damage['quantity'];
            $isOldRestocked = in_array($oldStatus, ['RESTOCKED', 'REPAIRED'], true);
            $isNewRestocked = in_array($newStatus, ['RESTOCKED', 'REPAIRED'], true);

            // 1. Transitioning from Damaged/Quarantine -> Restocked / Repaired (Increase Available, Decrease Damaged)
            if (!$isOldRestocked && $isNewRestocked && $variantId > 0 && $warehouseId > 0 && $qty > 0) {
                // Decrease from DAMAGED balance if available
                $currDamagedBalance = (float)(Database::fetchOne(
                    "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'DAMAGED'",
                    [$variantId, $warehouseId]
                )['quantity'] ?? 0);

                if ($currDamagedBalance > 0) {
                    $decreaseDamagedQty = min($qty, $currDamagedBalance);
                    StockService::decrease(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'DAMAGED',
                        quantity: $decreaseDamagedQty,
                        transactionType: 'DAMAGE_RESTORE_OUT',
                        referenceType: 'warehouse_damage',
                        referenceId: $damageId,
                        userId: $userId,
                        notes: "Restored from quarantine: Case #{$damage['damage_number']} [Status: {$newStatus}]" . (!empty($damage['tag_code']) ? " [Tags: {$damage['tag_code']}]" : "")
                    );
                }

                // Increase AVAILABLE stock balance
                StockService::increase(
                    variantId: $variantId,
                    locationType: 'WAREHOUSE',
                    locationId: $warehouseId,
                    stockState: 'AVAILABLE',
                    quantity: $qty,
                    transactionType: 'RESTOCK_GOOD',
                    referenceType: 'warehouse_damage',
                    referenceId: $damageId,
                    userId: $userId,
                    notes: "Restocked to available inventory from Damage Case #{$damage['damage_number']} [Status: {$newStatus}]" . (!empty($damage['tag_code']) ? " [Tags: {$damage['tag_code']}]" : "")
                );
            }
            // 2. Transitioning from Restocked / Repaired back to Damaged / Logged (Decrease Available, Increase Damaged)
            elseif ($isOldRestocked && !$isNewRestocked && $variantId > 0 && $warehouseId > 0 && $qty > 0) {
                // Decrease from AVAILABLE balance if available
                $currAvailBalance = (float)(Database::fetchOne(
                    "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                    [$variantId, $warehouseId]
                )['quantity'] ?? 0);

                if ($currAvailBalance > 0) {
                    $decreaseAvailQty = min($qty, $currAvailBalance);
                    StockService::decrease(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'AVAILABLE',
                        quantity: $decreaseAvailQty,
                        transactionType: 'DAMAGE_OUT',
                        referenceType: 'warehouse_damage',
                        referenceId: $damageId,
                        userId: $userId,
                        notes: "Re-quarantined item from Damage Case #{$damage['damage_number']} [Status: {$newStatus}]" . (!empty($damage['tag_code']) ? " [Tags: {$damage['tag_code']}]" : "")
                    );
                }

                // Increase DAMAGED stock balance
                StockService::increase(
                    variantId: $variantId,
                    locationType: 'WAREHOUSE',
                    locationId: $warehouseId,
                    stockState: 'DAMAGED',
                    quantity: $qty,
                    transactionType: 'DAMAGE_IN',
                    referenceType: 'warehouse_damage',
                    referenceId: $damageId,
                    userId: $userId,
                    notes: "Quarantined item from Damage Case #{$damage['damage_number']} [Status: {$newStatus}]" . (!empty($damage['tag_code']) ? " [Tags: {$damage['tag_code']}]" : "")
                );
            }
            // 3. Transitioning to WRITTEN_OFF from a damaged quarantine state
            elseif (!$isOldRestocked && in_array($newStatus, ['WRITTEN_OFF', 'DISPOSED', 'SCRAPPED'], true) && $variantId > 0 && $warehouseId > 0 && $qty > 0) {
                $currDamagedBalance = (float)(Database::fetchOne(
                    "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'DAMAGED'",
                    [$variantId, $warehouseId]
                )['quantity'] ?? 0);

                if ($currDamagedBalance > 0) {
                    $decreaseDamagedQty = min($qty, $currDamagedBalance);
                    StockService::decrease(
                        variantId: $variantId,
                        locationType: 'WAREHOUSE',
                        locationId: $warehouseId,
                        stockState: 'DAMAGED',
                        quantity: $decreaseDamagedQty,
                        transactionType: 'DAMAGE_DISPOSAL',
                        referenceType: 'warehouse_damage',
                        referenceId: $damageId,
                        userId: $userId,
                        notes: "Written off/disposed from Damage Case #{$damage['damage_number']}"
                    );
                }
            }

            $updateData = [
                'status'       => $newStatus,
                'defect_notes' => $notes,
                'photo_url'    => $photoUrl,
            ];
            $puInfo = null;
            if ($newStatus === 'REWORK' && $productionUnitId) {
                $updateData['production_unit_id'] = $productionUnitId;
                $puInfo = Database::fetchOne("SELECT id, name, code, manager_name, phone FROM production_units WHERE id = ?", [$productionUnitId]);
            }

            WarehouseDamage::update($damageId, $updateData);

            // Seamlessly dispatch or sync with Production Rework module
            if ($newStatus === 'REWORK') {
                $batchId = !empty($damage['production_batch_id']) ? (int)$damage['production_batch_id'] : (!empty($damage['reference_id']) && in_array($damage['source_type'] ?? '', ['PRODUCTION_INWARD', 'INWARD_GRN', 'PRODUCTION_BATCH']) ? (int)$damage['reference_id'] : null);
                $outwardId = null;
                if ($batchId) {
                    $po = Database::fetchOne("SELECT id FROM production_outwards WHERE production_batch_id = ?", [$batchId]);
                    if ($po) $outwardId = (int)$po['id'];
                }
                $reworkNum = str_starts_with($damage['damage_number'], 'DMG-') ? str_replace('DMG-', 'RWK-', $damage['damage_number']) : ('RWK-' . $damage['damage_number']);

                $existingRework = Database::fetchOne("SELECT id FROM production_warehouse_reworks WHERE warehouse_damage_id = ?", [$damageId]);
                if ($existingRework) {
                    ProductionWarehouseRework::update($existingRework['id'], [
                        'production_unit_id' => $productionUnitId ?: null,
                        'quantity'           => (int)$damage['quantity'],
                        'defect_notes'       => $notes,
                        'status'             => 'RECEIVED',
                    ]);
                } else {
                    try {
                        ProductionWarehouseRework::create([
                            'rework_number'          => $reworkNum,
                            'warehouse_id'           => (int)$damage['warehouse_id'],
                            'production_outward_id'  => $outwardId,
                            'production_batch_id'    => $batchId,
                            'warehouse_damage_id'    => $damageId,
                            'variant_id'             => (int)$damage['variant_id'],
                            'production_unit_id'     => $productionUnitId ?: null,
                            'quantity'               => (int)$damage['quantity'],
                            'defect_type'            => $damage['defect_type'] ?? 'OTHER',
                            'damage_reason'          => $damage['damage_reason'] ?? 'Defect',
                            'defect_notes'           => $notes,
                            'photo_url'              => $photoUrl ?: ($damage['photo_url'] ?? null),
                            'received_date'          => date('Y-m-d'),
                            'received_by'            => $userId,
                            'status'                 => 'RECEIVED',
                            'created_by'             => $userId,
                        ]);
                    } catch (Exception $e) {
                        error_log('Failed to create ProductionWarehouseRework: ' . $e->getMessage());
                    }
                }
            } elseif (in_array($newStatus, ['REPAIRED', 'RESTOCKED'], true)) {
                $existingRework = Database::fetchOne("SELECT id, status FROM production_warehouse_reworks WHERE warehouse_damage_id = ?", [$damageId]);
                if ($existingRework && in_array($existingRework['status'], ['RECEIVED', 'IN_REWORK'])) {
                    ProductionWarehouseRework::update($existingRework['id'], [
                        'status'       => 'REPAIRED',
                        'repaired_qty' => (int)$damage['quantity'],
                        'repaired_at'  => date('Y-m-d H:i:s'),
                        'repair_notes' => $notes ?: 'Restored/Repaired in Warehouse',
                    ]);
                }
            }

            $puNameStr = $puInfo ? "[{$puInfo['code']}] {$puInfo['name']}" : null;
            AuditService::log('warehouse', 'damage_status_updated', 'warehouse_damages', $damageId, 
                ['status' => $oldStatus], 
                [
                    'status'             => $newStatus, 
                    'notes'              => $notes, 
                    'production_unit_id' => $productionUnitId,
                    'production_unit'    => $puNameStr,
                    'photo_url'          => $photoUrl,
                    'damage_number'      => $damage['damage_number'],
                    'batch_number'       => $damage['batch_number'] ?? null,
                ], 
                $userId
            );

            $successMsg = "Damage Case #{$damage['damage_number']} status updated to {$newStatus}.";
            if ($newStatus === 'REWORK' && $puNameStr) {
                $successMsg .= " Items dispatched to {$puNameStr} for rework.";
            } else {
                $successMsg .= " Inventory stock balance and available piece counts updated successfully.";
            }

            Session::flash('success', $successMsg);
            Response::redirect(url('warehouse/damage/view/' . $damageId));
        } catch (Exception $e) {
            Session::flash('error', 'Error updating status: ' . $e->getMessage());
            Response::back();
        }
    }

    // =========================================================================
    // 4. EXTERNAL PURCHASES (Supplier PO & GRN) — Person 1
    // =========================================================================

    public function purchaseList(): void {
        if (!hasPermission('purchases', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view direct purchases.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $status = $_GET['status'] ?? '';
        $warehouseId = $scopedWarehouseId !== null ? (string)$scopedWarehouseId : ($_GET['warehouse_id'] ?? '');
        $customerId = $_GET['customer_id'] ?? '';
        $search = $_GET['search'] ?? '';

        $filters = [
            'only_direct' => true,
        ];
        if (!empty($status)) $filters['status'] = $status;
        if (!empty($warehouseId)) $filters['warehouse_id'] = $warehouseId;
        if (!empty($customerId)) $filters['customer_id'] = $customerId;
        if (!empty($search)) $filters['search'] = $search;

        $purchases = PurchaseOrder::getAll($filters);
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $customers = Database::fetchAll("SELECT * FROM customers WHERE is_active = 1 ORDER BY name ASC");

        // Summary Stats
        $stats = [
            'total_orders'    => count($purchases),
            'total_revenue'   => (float)array_sum(array_column($purchases, 'total_amount')),
            'total_units'     => (int)array_sum(array_column($purchases, 'total_ordered_qty')),
            'paid_revenue'    => (float)array_sum(array_map(fn($p) => ($p['payment_status'] ?? '') === 'PAID' ? (float)$p['total_amount'] : 0, $purchases)),
            'pending_revenue' => (float)array_sum(array_map(fn($p) => ($p['payment_status'] ?? '') !== 'PAID' ? (float)$p['total_amount'] : 0, $purchases)),
        ];

        // Group purchases by customer (One User Only One Time) with separate invoices
        $groupedCustomers = [];
        $unregisteredPurchases = [];

        foreach ($purchases as $po) {
            $custId = !empty($po['customer_id']) ? (int)$po['customer_id'] : 0;
            if ($custId > 0) {
                if (!isset($groupedCustomers[$custId])) {
                    $groupedCustomers[$custId] = [
                        'customer_id'         => $custId,
                        'customer_name'       => $po['customer_name'] ?? $po['display_customer_name'],
                        'customer_phone'      => $po['customer_phone'] ?? $po['display_customer_phone'],
                        'customer_city'       => $po['customer_city'] ?? 'Local',
                        'customer_state'      => $po['customer_state'] ?? '',
                        'customer_gst'        => $po['customer_gst'] ?? '',
                        'total_orders'        => 0,
                        'total_units'         => 0,
                        'total_items'         => 0,
                        'grand_total'         => 0.0,
                        'paid_total'          => 0.0,
                        'pending_total'       => 0.0,
                        'latest_order_date'   => $po['order_date'],
                        'earliest_order_date' => $po['order_date'],
                        'warehouse_name'      => $po['warehouse_name'],
                        'payment_methods'     => [],
                        'status'              => $po['status'],
                        'invoices'            => []
                    ];
                }

                $groupedCustomers[$custId]['total_orders'] += 1;
                $groupedCustomers[$custId]['total_units'] += (int)($po['total_ordered_qty'] ?? 0);
                $groupedCustomers[$custId]['total_items'] += (int)($po['total_items'] ?? 0);
                $amt = (float)($po['total_amount'] ?? 0);
                $groupedCustomers[$custId]['grand_total'] += $amt;
                if (($po['payment_status'] ?? 'PAID') === 'PAID') {
                    $groupedCustomers[$custId]['paid_total'] += $amt;
                } else {
                    $groupedCustomers[$custId]['pending_total'] += $amt;
                }

                if (strtotime($po['order_date']) > strtotime($groupedCustomers[$custId]['latest_order_date'])) {
                    $groupedCustomers[$custId]['latest_order_date'] = $po['order_date'];
                    $groupedCustomers[$custId]['warehouse_name'] = $po['warehouse_name'];
                    $groupedCustomers[$custId]['status'] = $po['status'];
                }

                if (!empty($po['payment_method']) && !in_array($po['payment_method'], $groupedCustomers[$custId]['payment_methods'])) {
                    $groupedCustomers[$custId]['payment_methods'][] = $po['payment_method'];
                }

                $groupedCustomers[$custId]['invoices'][] = [
                    'id'             => (int)$po['id'],
                    'po_number'      => $po['po_number'],
                    'order_date'     => $po['order_date'],
                    'total_amount'   => $amt,
                    'total_units'    => (int)($po['total_ordered_qty'] ?? 0),
                    'total_items'    => (int)($po['total_items'] ?? 0),
                    'payment_status' => $po['payment_status'] ?? 'PAID',
                    'payment_method' => $po['payment_method'] ?? 'CASH',
                    'status'         => $po['status'],
                    'warehouse_name' => $po['warehouse_name']
                ];
            } else {
                $unregisteredPurchases[] = $po;
            }
        }

        // Sort customer profiles by latest order date descending
        uasort($groupedCustomers, function($a, $b) {
            return strtotime($b['latest_order_date']) <=> strtotime($a['latest_order_date']);
        });

        // Separate regular repeat customers (orders > 1) and one-time purchases (orders == 1)
        $regularCustomers = [];
        $oneTimeCustomers = [];

        foreach ($groupedCustomers as $c) {
            if ($c['total_orders'] > 1) {
                $regularCustomers[] = $c;
            } else {
                $oneTimeCustomers[] = $c;
            }
        }

        Response::view('warehouse.purchases.index', [
            'pageTitle'             => 'Warehouse Direct Customer Purchases',
            'activeModule'          => 'warehouse',
            'activeMenu'            => 'purchases',
            'purchases'             => $purchases,
            'regularCustomers'      => $regularCustomers,
            'oneTimeCustomers'      => $oneTimeCustomers,
            'groupedCustomers'      => array_values($groupedCustomers),
            'unregisteredPurchases' => $unregisteredPurchases,
            'warehouses'            => $warehouses,
            'customers'             => $customers,
            'stats'                 => $stats,
            'status'                => $status,
            'warehouseId'           => $warehouseId,
            'scopedWarehouseId'     => $scopedWarehouseId,
            'customerId'            => $customerId,
            'search'                => $search,
            'breadcrumb'            => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Direct Customer Purchases'],
            ],
        ]);
    }

    public function createPurchase(): void {
        if (!hasPermission('purchases', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create purchases.');
            Response::redirect(url('warehouse/purchases'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $customers = Database::fetchAll("SELECT * FROM customers WHERE is_active = 1 ORDER BY name ASC");
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code, pv.qr_type, pv.series_start_code, pv.series_end_code, pv.pack_quantity,
                    pv.selling_price, pv.mrp, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                    p.category_code, p.subcategory_code, p.sub_subcategory_code,
                    s.name as size_name, s.code as size_code, c.name as color_name,
                    COALESCE(cat.name, c_old.name, 'General') as category_name,
                    COALESCE(p.gst_percentage, 5.0) as gst_rate,
                    COALESCE(sb.total_avail, 0) as available_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             LEFT JOIN (
                 SELECT variant_id, SUM(quantity) as total_avail 
                 FROM stock_balances 
                 WHERE stock_state = 'AVAILABLE' AND location_type = 'WAREHOUSE'
                 GROUP BY variant_id
             ) sb ON pv.id = sb.variant_id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        foreach ($variants as &$v) {
            $retailPrice = (float)($v['selling_price'] > 0 ? $v['selling_price'] : $v['mrp']);
            $v['selling_price'] = $retailPrice;
            $v['mrp'] = $retailPrice;

            $c1 = !empty($v['category_code']) ? strtoupper($v['category_code'][0]) : (!empty($v['category_name']) ? strtoupper($v['category_name'][0]) : '');
            $c2 = !empty($v['subcategory_code']) ? strtoupper($v['subcategory_code'][0]) : '';
            $c3 = !empty($v['sub_subcategory_code']) ? strtoupper($v['sub_subcategory_code'][0]) : '';
            $pfx = $c1 . $c2 . $c3;
            if (strlen($pfx) < 2 && !empty($v['qr_code_prefix'])) {
                $cleanP = preg_replace('/[^A-Za-z0-9]/', '', $v['qr_code_prefix']);
                $pfx = strtoupper(substr($cleanP, 0, 3));
            }
            if (empty($pfx)) {
                $pfx = strtoupper(preg_replace('/[^A-Za-z]/', '', $v['product_code']));
            }
            if (empty($pfx)) {
                $pfx = 'TTS';
            }
            $v['tag_prefix'] = $pfx;
        }
        unset($v);

        Response::view('warehouse.purchases.create', [
            'pageTitle'    => 'Create Direct Customer Purchase',
            'activeModule' => 'warehouse',
            'activeMenu'   => 'purchases',
            'customers'    => $customers,
            'warehouses'   => $warehouses,
            'variants'     => $variants,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Direct Purchases', 'url' => url('warehouse/purchases')],
                ['label' => 'New Direct Purchase'],
            ],
        ]);
    }

    public function storePurchase(): void {
        if (!hasPermission('purchases', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create purchases.');
            Response::redirect(url('warehouse/purchases'));
            return;
        }
        try {
            $userId = Session::userId() ?? 1;
            $items = [];

            if (!empty($_POST['variant_id']) && is_array($_POST['variant_id'])) {
                foreach ($_POST['variant_id'] as $idx => $vId) {
                    $qty = (int)($_POST['quantity'][$idx] ?? 1);
                    $price = (float)($_POST['unit_price'][$idx] ?? 0.0);
                    $mrp = (float)($_POST['mrp'][$idx] ?? $price);
                    if ($mrp <= 0) $mrp = $price;
                    if ($price <= 0 && $mrp > 0) $price = $mrp;

                    $discountPct = (float)($_POST['discount_percentage'][$idx] ?? 0.0);
                    if ($price < $mrp) {
                        $discAmt = $mrp - $price;
                        $discountPct = $mrp > 0 ? round(($discAmt / $mrp) * 100, 2) : 0.0;
                    }
                    $discountPct = max(0.0, min(100.0, $discountPct));
                    
                    $gst = (float)($_POST['gst_percentage'][$idx] ?? 5.0);
                    $scannedQr = !empty($_POST['scanned_qr_codes'][$idx]) ? trim($_POST['scanned_qr_codes'][$idx]) : null;
                    if ($vId && $qty > 0) {
                        $items[] = [
                            'variant_id'          => (int)$vId,
                            'quantity'            => $qty,
                            'unit_price'          => $price,
                            'discount_percentage' => $discountPct,
                            'gst_percentage'      => $gst,
                            'mrp'                 => $mrp,
                            'scanned_qr_codes'    => $scannedQr,
                            'batch_lot_no'        => $scannedQr,
                            'notes'               => $scannedQr ? ('QR: ' . $scannedQr) : null,
                        ];
                    }
                }
            }

            // Server-side duplicate piece QR check
            $seenTags = [];
            foreach ($items as $item) {
                if (!empty($item['scanned_qr_codes'])) {
                    $lowerTag = strtolower($item['scanned_qr_codes']);
                    if (in_array($lowerTag, $seenTags)) {
                        Response::redirect(url('warehouse/purchases/create'), 'error', "Duplicate piece QR tag detected: '{$item['scanned_qr_codes']}'. Each piece QR can only be scanned and added once.");
                        return;
                    }
                    $seenTags[] = $lowerTag;
                }
            }

            if (empty($items)) {
                Response::redirect(url('warehouse/purchases/create'), 'error', 'Please add at least one product item to the purchase order.');
            }

            $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $customerPhone = !empty($_POST['customer_phone']) ? preg_replace('/[^0-9]/', '', trim($_POST['customer_phone'])) : '';
            $customerName = !empty($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
            $customerAddress = !empty($_POST['customer_address']) ? trim($_POST['customer_address']) : '';
            $customerCity = !empty($_POST['customer_city']) ? trim($_POST['customer_city']) : '';
            $customerState = !empty($_POST['customer_state']) ? trim($_POST['customer_state']) : 'Tamil Nadu';
            $customerGst = !empty($_POST['customer_gst']) ? strtoupper(trim($_POST['customer_gst'])) : '';

            if (!$customerId) {
                if (!empty($customerPhone)) {
                    $existingCust = Database::fetchOne("SELECT * FROM customers WHERE phone = ? LIMIT 1", [$customerPhone]);
                    if ($existingCust) {
                        $customerId = (int)$existingCust['id'];
                        Database::execute(
                            "UPDATE customers SET 
                                name = CASE WHEN ? != '' THEN ? ELSE name END,
                                address = CASE WHEN ? != '' THEN ? ELSE address END,
                                city = CASE WHEN ? != '' THEN ? ELSE city END,
                                state = CASE WHEN ? != '' THEN ? ELSE state END,
                                gst_number = CASE WHEN ? != '' THEN ? ELSE gst_number END
                             WHERE id = ?",
                            [$customerName, $customerName, $customerAddress, $customerAddress, $customerCity, $customerCity, $customerState, $customerState, $customerGst, $customerGst, $customerId]
                        );
                    } else {
                        Database::execute(
                            "INSERT INTO customers (name, phone, address, city, state, gst_number, source_channel, is_active, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, 'WAREHOUSE', 1, NOW())",
                            [$customerName ?: ('Customer ' . $customerPhone), $customerPhone, $customerAddress ?: null, $customerCity ?: null, $customerState ?: 'Tamil Nadu', $customerGst ?: null]
                        );
                        $customerId = (int)Database::lastInsertId();
                    }
                } elseif (!empty($customerName)) {
                    Database::execute(
                        "INSERT INTO customers (name, phone, address, city, state, gst_number, source_channel, is_active, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 'WAREHOUSE', 1, NOW())",
                        [$customerName, null, $customerAddress ?: null, $customerCity ?: null, $customerState ?: 'Tamil Nadu', $customerGst ?: null]
                    );
                    $customerId = (int)Database::lastInsertId();
                }
            } else {
                Database::execute(
                    "UPDATE customers SET 
                        name = CASE WHEN ? != '' THEN ? ELSE name END,
                        phone = CASE WHEN ? != '' THEN ? ELSE phone END,
                        address = CASE WHEN ? != '' THEN ? ELSE address END,
                        city = CASE WHEN ? != '' THEN ? ELSE city END,
                        state = CASE WHEN ? != '' THEN ? ELSE state END,
                        gst_number = CASE WHEN ? != '' THEN ? ELSE gst_number END
                     WHERE id = ?",
                    [$customerName, $customerName, $customerPhone, $customerPhone, $customerAddress, $customerAddress, $customerCity, $customerCity, $customerState, $customerState, $customerGst, $customerGst, $customerId]
                );
            }

            $paymentMethod = $_POST['payment_method'] ?? 'CASH';
            $notes = trim($_POST['notes'] ?? '');

            if ($paymentMethod === 'CASH_UPI') {
                $cashAmount = (float)($_POST['cash_amount'] ?? 0.0);
                $upiAmount = (float)($_POST['upi_amount'] ?? 0.0);
                $upiRef = trim($_POST['upi_reference'] ?? '');
                
                $splitSummary = "[Split Payment: Cash ₹" . number_format($cashAmount, 2) . " + UPI ₹" . number_format($upiAmount, 2) . ($upiRef !== '' ? " (Ref: " . $upiRef . ")" : "") . "]";
                $notes = $splitSummary . (!empty($notes) ? "\n" . $notes : "");
            }

            $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
            $poData = [
                'customer_id'    => $customerId,
                'warehouse_id'   => $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_POST['warehouse_id'] ?? 1),
                'order_date'     => $_POST['order_date'] ?? date('Y-m-d'),
                'expected_date'  => !empty($_POST['expected_date']) ? $_POST['expected_date'] : date('Y-m-d'),
                'payment_method' => $paymentMethod,
                'payment_status' => $_POST['payment_status'] ?? 'PAID',
                'gst_type'       => $_POST['gst_type'] ?? 'CGST_SGST',
                'status'         => $_POST['status'] ?? 'COMPLETED',
                'notes'          => $notes,
                'created_by'     => $userId,
            ];

            // Handle Payment Screenshot Upload to Cloudinary
            $screenshotUrl = null;
            $screenshotPublicId = null;
            if (!empty($_FILES['payment_screenshot']['name']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
                require_once BASE_PATH . '/app/Services/CloudinaryService.php';
                $uploadedUrl = CloudinaryService::upload(
                    $_FILES['payment_screenshot'],
                    'grm_inventory/payment_screenshots',
                    'image'
                );
                if ($uploadedUrl) {
                    $screenshotUrl = $uploadedUrl;
                    $screenshotPublicId = CloudinaryService::extractPublicId($uploadedUrl) ?? ('pay_' . time());
                }
            }

            $poId = WarehouseService::createPurchaseOrder($poData, $items, $userId);

            // Save screenshot to purchase_orders after PO is created
            if ($poId && $screenshotUrl) {
                Database::execute(
                    "UPDATE purchase_orders SET payment_screenshot_url = ?, payment_screenshot_public_id = ? WHERE id = ?",
                    [$screenshotUrl, $screenshotPublicId, $poId]
                );
            }

            Response::redirect(url('warehouse/purchases/receive/' . $poId), 'success', 'Direct customer purchase recorded successfully! Invoice generated.');
        } catch (Exception $e) {
            Response::redirect(url('warehouse/purchases/create'), 'error', 'Failed to record purchase: ' . $e->getMessage());
        }
    }

    public function editPurchase(string $id): void {
        if (!hasPermission('purchases', 'edit')) {
            Session::flash('error', 'Access denied: You do not have permission to edit purchases.');
            Response::redirect(url('warehouse/purchases'));
            return;
        }
        $poId = (int)$id;
        $po = PurchaseOrder::find($poId);
        if (!$po) {
            Response::redirect(url('warehouse/purchases'), 'error', 'Purchase order not found.');
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $items = PurchaseOrder::getItems($poId);
        $customers = Database::fetchAll("SELECT * FROM customers WHERE is_active = 1 ORDER BY name ASC");
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code, pv.qr_type, pv.series_start_code, pv.series_end_code, pv.pack_quantity,
                    pv.selling_price, pv.mrp, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                    p.category_code, p.subcategory_code, p.sub_subcategory_code,
                    s.name as size_name, s.code as size_code, c.name as color_name,
                    COALESCE(cat.name, c_old.name, 'General') as category_name,
                    COALESCE(p.gst_percentage, 5.0) as gst_rate,
                    COALESCE(sb.total_avail, 0) as available_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             LEFT JOIN (
                 SELECT variant_id, SUM(quantity) as total_avail 
                 FROM stock_balances 
                 WHERE stock_state = 'AVAILABLE' AND location_type = 'WAREHOUSE'
                 GROUP BY variant_id
             ) sb ON pv.id = sb.variant_id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        foreach ($variants as &$v) {
            $retailPrice = (float)($v['selling_price'] > 0 ? $v['selling_price'] : $v['mrp']);
            $v['selling_price'] = $retailPrice;
            $v['mrp'] = $retailPrice;

            $c1 = !empty($v['category_code']) ? strtoupper($v['category_code'][0]) : (!empty($v['category_name']) ? strtoupper($v['category_name'][0]) : '');
            $c2 = !empty($v['subcategory_code']) ? strtoupper($v['subcategory_code'][0]) : '';
            $c3 = !empty($v['sub_subcategory_code']) ? strtoupper($v['sub_subcategory_code'][0]) : '';
            $pfx = $c1 . $c2 . $c3;
            if (strlen($pfx) < 2 && !empty($v['qr_code_prefix'])) {
                $cleanP = preg_replace('/[^A-Za-z0-9]/', '', $v['qr_code_prefix']);
                $pfx = strtoupper(substr($cleanP, 0, 3));
            }
            if (empty($pfx)) {
                $pfx = strtoupper(preg_replace('/[^A-Za-z]/', '', $v['product_code']));
            }
            if (empty($pfx)) {
                $pfx = 'TTS';
            }
            $v['tag_prefix'] = $pfx;
        }
        unset($v);

        Response::view('warehouse.purchases.edit', [
            'pageTitle'    => 'Edit Direct Customer Purchase #' . $po['po_number'],
            'activeModule' => 'warehouse',
            'activeMenu'   => 'purchases',
            'po'           => $po,
            'items'        => $items,
            'customers'    => $customers,
            'warehouses'   => $warehouses,
            'variants'     => $variants,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Direct Purchases', 'url' => url('warehouse/purchases')],
                ['label' => 'Edit #' . $po['po_number']],
            ],
        ]);
    }

    public function updatePurchase(string $id): void {
        if (!hasPermission('purchases', 'edit')) {
            Session::flash('error', 'Access denied: You do not have permission to update purchases.');
            Response::redirect(url('warehouse/purchases'));
            return;
        }
        $poId = (int)$id;
        try {
            $userId = Session::userId() ?? 1;
            $items = [];

            if (!empty($_POST['variant_id']) && is_array($_POST['variant_id'])) {
                foreach ($_POST['variant_id'] as $idx => $vId) {
                    $qty = (int)($_POST['quantity'][$idx] ?? 1);
                    $price = (float)($_POST['unit_price'][$idx] ?? 0.0);
                    $mrp = (float)($_POST['mrp'][$idx] ?? $price);
                    if ($mrp <= 0) $mrp = $price;
                    if ($price <= 0 && $mrp > 0) $price = $mrp;

                    $discountPct = (float)($_POST['discount_percentage'][$idx] ?? 0.0);
                    if ($price < $mrp) {
                        $discAmt = $mrp - $price;
                        $discountPct = $mrp > 0 ? round(($discAmt / $mrp) * 100, 2) : 0.0;
                    }
                    $discountPct = max(0.0, min(100.0, $discountPct));
                    
                    $gst = (float)($_POST['gst_percentage'][$idx] ?? 5.0);
                    $scannedQr = !empty($_POST['scanned_qr_codes'][$idx]) ? trim($_POST['scanned_qr_codes'][$idx]) : null;
                    if ($vId && $qty > 0) {
                        $items[] = [
                            'variant_id'          => (int)$vId,
                            'quantity'            => $qty,
                            'unit_price'          => $price,
                            'discount_percentage' => $discountPct,
                            'gst_percentage'      => $gst,
                            'mrp'                 => $mrp,
                            'scanned_qr_codes'    => $scannedQr,
                            'batch_lot_no'        => $scannedQr,
                            'notes'               => $scannedQr ? ('QR: ' . $scannedQr) : null,
                        ];
                    }
                }
            }

            // Server-side duplicate piece QR check
            $seenTags = [];
            foreach ($items as $item) {
                if (!empty($item['scanned_qr_codes'])) {
                    $lowerTag = strtolower($item['scanned_qr_codes']);
                    if (in_array($lowerTag, $seenTags)) {
                        Response::redirect(url('warehouse/purchases/edit/' . $poId), 'error', "Duplicate piece QR tag detected: '{$item['scanned_qr_codes']}'. Each piece QR can only be added once.");
                        return;
                    }
                    $seenTags[] = $lowerTag;
                }
            }

            if (empty($items)) {
                Response::redirect(url('warehouse/purchases/edit/' . $poId), 'error', 'Please add at least one product item to the purchase order.');
            }

            $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $customerPhone = !empty($_POST['customer_phone']) ? preg_replace('/[^0-9]/', '', trim($_POST['customer_phone'])) : '';
            $customerName = !empty($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
            $customerAddress = !empty($_POST['customer_address']) ? trim($_POST['customer_address']) : '';
            $customerCity = !empty($_POST['customer_city']) ? trim($_POST['customer_city']) : '';
            $customerState = !empty($_POST['customer_state']) ? trim($_POST['customer_state']) : 'Tamil Nadu';
            $customerGst = !empty($_POST['customer_gst']) ? strtoupper(trim($_POST['customer_gst'])) : '';

            if (!$customerId) {
                if (!empty($customerPhone)) {
                    $existingCust = Database::fetchOne("SELECT * FROM customers WHERE phone = ? LIMIT 1", [$customerPhone]);
                    if ($existingCust) {
                        $customerId = (int)$existingCust['id'];
                        Database::execute(
                            "UPDATE customers SET 
                                name = CASE WHEN ? != '' THEN ? ELSE name END,
                                address = CASE WHEN ? != '' THEN ? ELSE address END,
                                city = CASE WHEN ? != '' THEN ? ELSE city END,
                                state = CASE WHEN ? != '' THEN ? ELSE state END,
                                gst_number = CASE WHEN ? != '' THEN ? ELSE gst_number END
                             WHERE id = ?",
                            [$customerName, $customerName, $customerAddress, $customerAddress, $customerCity, $customerCity, $customerState, $customerState, $customerGst, $customerGst, $customerId]
                        );
                    } else {
                        Database::execute(
                            "INSERT INTO customers (name, phone, address, city, state, gst_number, source_channel, is_active, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, 'WAREHOUSE', 1, NOW())",
                            [$customerName ?: ('Customer ' . $customerPhone), $customerPhone, $customerAddress ?: null, $customerCity ?: null, $customerState ?: 'Tamil Nadu', $customerGst ?: null]
                        );
                        $customerId = (int)Database::lastInsertId();
                    }
                } elseif (!empty($customerName)) {
                    Database::execute(
                        "INSERT INTO customers (name, phone, address, city, state, gst_number, source_channel, is_active, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 'WAREHOUSE', 1, NOW())",
                        [$customerName, null, $customerAddress ?: null, $customerCity ?: null, $customerState ?: 'Tamil Nadu', $customerGst ?: null]
                    );
                    $customerId = (int)Database::lastInsertId();
                }
            } else {
                Database::execute(
                    "UPDATE customers SET 
                        name = CASE WHEN ? != '' THEN ? ELSE name END,
                        phone = CASE WHEN ? != '' THEN ? ELSE phone END,
                        address = CASE WHEN ? != '' THEN ? ELSE address END,
                        city = CASE WHEN ? != '' THEN ? ELSE city END,
                        state = CASE WHEN ? != '' THEN ? ELSE state END,
                        gst_number = CASE WHEN ? != '' THEN ? ELSE gst_number END
                     WHERE id = ?",
                    [$customerName, $customerName, $customerPhone, $customerPhone, $customerAddress, $customerAddress, $customerCity, $customerCity, $customerState, $customerState, $customerGst, $customerGst, $customerId]
                );
            }

            $paymentMethod = $_POST['payment_method'] ?? 'CASH';
            $notes = trim($_POST['notes'] ?? '');

            if ($paymentMethod === 'CASH_UPI') {
                $cashAmount = (float)($_POST['cash_amount'] ?? 0.0);
                $upiAmount = (float)($_POST['upi_amount'] ?? 0.0);
                $upiRef = trim($_POST['upi_reference'] ?? '');
                
                $splitSummary = "[Split Payment: Cash ₹" . number_format($cashAmount, 2) . " + UPI ₹" . number_format($upiAmount, 2) . ($upiRef !== '' ? " (Ref: " . $upiRef . ")" : "") . "]";
                if (strpos($notes, '[Split Payment:') !== false) {
                    $notes = preg_replace('/\[Split Payment:.*?\]\r?\n?/', '', $notes);
                    $notes = trim($notes);
                }
                $notes = $splitSummary . (!empty($notes) ? "\n" . $notes : "");
            }

            $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
            $poData = [
                'customer_id'    => $customerId,
                'warehouse_id'   => $scopedWarehouseId !== null ? $scopedWarehouseId : (int)$_POST['warehouse_id'],
                'order_date'     => $_POST['order_date'] ?? date('Y-m-d'),
                'expected_date'  => !empty($_POST['expected_date']) ? $_POST['expected_date'] : null,
                'payment_method' => $paymentMethod,
                'payment_status' => $_POST['payment_status'] ?? 'PAID',
                'status'         => $_POST['status'] ?? 'COMPLETED',
                'notes'          => $notes,
                'gst_type'       => $_POST['gst_type'] ?? 'CGST_SGST',
            ];

            WarehouseService::updatePurchaseOrder($poId, $poData, $items, $userId);
            Response::redirect(url('warehouse/purchases/receive/' . $poId), 'success', 'Direct customer purchase updated successfully!');
        } catch (Exception $e) {
            Response::redirect(url('warehouse/purchases/edit/' . $poId), 'error', 'Error updating purchase: ' . $e->getMessage());
        }
    }

    public function receivePurchase(string $id): void {
        if (!hasPermission('purchases', 'receive') && !hasPermission('purchases', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view invoices.');
            Response::redirect(url('warehouse/purchases'));
            return;
        }
        $poId = (int)$id;
        $po = PurchaseOrder::find($poId);
        if (!$po) {
            Response::redirect(url('warehouse/purchases'), 'error', 'Purchase record not found.');
        }

        $items = PurchaseItem::getByPurchaseOrder($poId);

        Response::view('warehouse.purchases.receive', [
            'pageTitle'    => 'Tax Invoice & Receipt #' . $po['po_number'],
            'activeModule' => 'warehouse',
            'activeMenu'   => 'purchases',
            'po'           => $po,
            'items'        => $items,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Direct Purchases', 'url' => url('warehouse/purchases')],
                ['label' => 'Invoice #' . $po['po_number']],
            ],
        ]);
    }

    public function storeReceivePurchase(string $id): void {
        if (!hasPermission('purchases', 'receive') && !hasPermission('purchases', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to receive purchases.');
            Response::redirect(url('warehouse/purchases'));
            return;
        }
        $poId = (int)$id;
        Response::redirect(url('warehouse/purchases/receive/' . $poId), 'info', 'Invoice viewed.');
    }

    public function ajaxScanPurchaseQr(): void {
        if (!hasPermission('purchases', 'receive') && !hasPermission('purchases', 'view')) {
            Response::jsonError('Access denied.', [], 403);
            return;
        }
        header('Content-Type: application/json');

        $rawCode = trim($_REQUEST['code'] ?? $_REQUEST['query'] ?? $_REQUEST['search'] ?? '');
        if (empty($rawCode)) {
            echo json_encode(['success' => false, 'message' => 'No scan code or SKU provided.']);
            exit;
        }

        $rawCode = trim($rawCode, " \t\n\r\0\x0B\"'");
        $extractedTag = $rawCode;
        $candidateCodes = [$rawCode];

        if (str_starts_with($rawCode, '{') && str_ends_with($rawCode, '}')) {
            $decoded = json_decode($rawCode, true);
            if (is_array($decoded)) {
                $extractedTag = $decoded['tag'] ?? $decoded['tag_code'] ?? $decoded['code'] ?? $rawCode;
                if (!empty($decoded['sku'])) $candidateCodes[] = trim($decoded['sku']);
                if (!empty($decoded['tag'])) $candidateCodes[] = trim($decoded['tag']);
                if (!empty($decoded['tag_code'])) $candidateCodes[] = trim($decoded['tag_code']);
                if (!empty($decoded['code'])) $candidateCodes[] = trim($decoded['code']);
                if (!empty($decoded['qr_code'])) $candidateCodes[] = trim($decoded['qr_code']);
            }
        } elseif (strpos($rawCode, '|') !== false) {
            $parts = array_map('trim', explode('|', $rawCode));
            $extractedTag = $parts[0];
            foreach ($parts as $part) {
                if (stripos($part, 'MRP:') === 0 || stripos($part, 'Disc:') === 0 || stripos($part, 'Cost:') === 0 || stripos($part, 'Price:') === 0) {
                    continue;
                }
                $candidateCodes[] = $part;
            }
            if (isset($parts[1]) && !empty($parts[1])) $candidateCodes[] = $parts[1];
            if (isset($parts[2]) && !empty($parts[2])) $candidateCodes[] = $parts[2];
        }

        $candidateCodes = array_values(array_unique(array_filter($candidateCodes)));
        $variant = null;

        // Strategy 1: Exact match on candidate codes against product_variants or products
        foreach ($candidateCodes as $c) {
            if (empty($c)) continue;
            $variant = Database::fetchOne(
                "SELECT pv.*, 
                        p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                        COALESCE(p.gst_percentage, 5.0) as gst_rate,
                        p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        s.name as size_name, s.code as size_code,
                        col.name as color_name, col.hex_code as color_hex,
                        cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                 LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                 WHERE pv.sku = ? 
                    OR pv.qr_code = ? 
                    OR p.code = ?
                    OR (pv.id = ? AND ? > 0)
                 LIMIT 1",
                [$c, $c, $c, is_numeric($c) ? (int)$c : 0, is_numeric($c) ? (int)$c : 0]
            );
            if ($variant) break;
        }

        // Strategy 2: Lookup from QC Items recorded tag_code
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                $qc = Database::fetchOne(
                    "SELECT variant_id, tag_code FROM qc_items WHERE tag_code = ? ORDER BY id DESC LIMIT 1",
                    [$c]
                );
                if ($qc && !empty($qc['variant_id'])) {
                    $variant = Database::fetchOne(
                        "SELECT pv.*, 
                                p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                COALESCE(p.gst_percentage, 5.0) as gst_rate,
                                p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                s.name as size_name, s.code as size_code,
                                col.name as color_name, col.hex_code as color_hex,
                                cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                         LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                         WHERE pv.id = ? LIMIT 1",
                        [$qc['variant_id']]
                    );
                    if ($variant) {
                        $extractedTag = $qc['tag_code'] ?: $extractedTag;
                        break;
                    }
                }
            }
        }

        // Strategy 3: Lookup by Production Batch Number
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                $batchItem = Database::fetchOne(
                    "SELECT pbi.variant_id 
                     FROM production_batches pb 
                     JOIN production_batch_items pbi ON pbi.production_batch_id = pb.id 
                     WHERE pb.batch_number = ? OR pb.batch_number LIKE ? 
                     ORDER BY pbi.id ASC LIMIT 1",
                    [$c, '%' . $c . '%']
                );
                if ($batchItem && !empty($batchItem['variant_id'])) {
                    $variant = Database::fetchOne(
                        "SELECT pv.*, 
                                p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                COALESCE(p.gst_percentage, 5.0) as gst_rate,
                                p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                s.name as size_name, s.code as size_code,
                                col.name as color_name, col.hex_code as color_hex,
                                cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                         LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                         WHERE pv.id = ? LIMIT 1",
                        [$batchItem['variant_id']]
                    );
                    if ($variant) break;
                }
            }
        }

        // Strategy 4: Lookup by Tag Prefix pattern (e.g. KGF092607, TTS092601)
        if (!$variant) {
            $matchedAlpha = null;
            if (preg_match('/^([A-Za-z_-]+)(\d+)?$/i', $extractedTag, $matches)) {
                $matchedAlpha = $matches[1];
            } elseif (preg_match('/^([A-Za-z0-9_-]+?)(\d{2,12})?$/i', $extractedTag, $matches)) {
                $matchedAlpha = $matches[1];
            }

            if (!empty($matchedAlpha)) {
                $alphaPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $matchedAlpha));
                if (!empty($alphaPrefix)) {
                    $allProducts = Database::fetchAll("
                        SELECT p.id, p.name, p.code, p.qr_code_prefix,
                               p.category_code, p.subcategory_code, p.sub_subcategory_code,
                               cat.name as cat_name, subcat.name as sub_name, subsubcat.name as subsub_name,
                               cat.code as cat_c, subcat.code as sub_c, subsubcat.code as subsub_c
                        FROM products p
                        LEFT JOIN categories cat ON p.category_id = cat.id
                        LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                        LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                    ");

                    $matchedProdIds = [];
                    foreach ($allProducts as $p) {
                        $pCleanPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $p['qr_code_prefix'] ?? ''));
                        $pCleanCode   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $p['code'] ?? ''));
                        
                        $catC = strtoupper(trim($p['cat_c'] ?? ''));
                        $subC = strtoupper(trim($p['sub_c'] ?? ''));
                        $subsubC = strtoupper(trim($p['subsub_c'] ?? ''));

                        $initials1 = ($catC !== '' ? $catC[0] : '') . ($subC !== '' ? $subC[0] : '') . ($subsubC !== '' ? $subsubC[0] : '');
                        $initialsR = 'R' . $initials1;
                        $initials2 = ($catC !== '' ? $catC[0] : '') . ($subC !== '' ? $subC[0] : '');
                        $initialsR2 = 'R' . $initials2;

                        $catN = strtoupper(trim($p['cat_name'] ?? ''));
                        $subN = strtoupper(trim($p['sub_name'] ?? ''));
                        $subsubN = strtoupper(trim($p['subsub_name'] ?? ''));
                        $nameInitials = ($catN !== '' ? $catN[0] : '') . ($subN !== '' ? $subN[0] : '') . ($subsubN !== '' ? $subsubN[0] : '');
                        $nameInitialsR = 'R' . $nameInitials;

                        $pNameParts = preg_split('/[\s\-_]+/', trim($p['name']));
                        $pNameInitials = '';
                        foreach ($pNameParts as $pnp) {
                            if (!empty($pnp)) $pNameInitials .= strtoupper($pnp[0]);
                        }

                        if (
                            $alphaPrefix === $pCleanPrefix ||
                            $alphaPrefix === $pCleanCode ||
                            str_starts_with($pCleanPrefix, $alphaPrefix) ||
                            str_starts_with($alphaPrefix, $pCleanPrefix) ||
                            $alphaPrefix === $initials1 ||
                            $alphaPrefix === $initialsR ||
                            $alphaPrefix === $initials2 ||
                            $alphaPrefix === $initialsR2 ||
                            $alphaPrefix === $nameInitials ||
                            $alphaPrefix === $nameInitialsR ||
                            $alphaPrefix === $pNameInitials ||
                            stripos($p['name'], $alphaPrefix) !== false
                        ) {
                            $matchedProdIds[] = $p['id'];
                        }
                    }

                    if (!empty($matchedProdIds)) {
                        $placeholders = implode(',', array_fill(0, count($matchedProdIds), '?'));
                        $variant = Database::fetchOne(
                            "SELECT pv.*, 
                                    p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                    COALESCE(p.gst_percentage, 5.0) as gst_rate,
                                    p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                    p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                    s.name as size_name, s.code as size_code,
                                    col.name as color_name, col.hex_code as color_hex,
                                    cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name
                             FROM product_variants pv
                             JOIN products p ON pv.product_id = p.id
                             LEFT JOIN sizes s ON pv.size_id = s.id
                             LEFT JOIN colors col ON pv.color_id = col.id
                             LEFT JOIN categories cat ON p.category_id = cat.id
                             LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                             LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                             LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                             WHERE pv.product_id IN ($placeholders) AND pv.is_active = 1
                             ORDER BY COALESCE(sb.quantity, 0) DESC, pv.id ASC
                             LIMIT 1",
                            array_values($matchedProdIds)
                        );
                    }
                }
            }
        }

        // Strategy 5: Fuzzy search LIKE on SKU, Product Name, Code
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                if (strlen($c) < 2) continue;
                $variant = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            COALESCE(p.gst_percentage, 5.0) as gst_rate,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            cat.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name
                     FROM product_variants pv
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                     WHERE (pv.sku LIKE ? OR p.name LIKE ? OR p.code LIKE ? OR col.name LIKE ?) AND pv.is_active = 1
                     ORDER BY COALESCE(sb.quantity, 0) DESC, pv.id ASC
                     LIMIT 1",
                    ['%' . $c . '%', '%' . $c . '%', '%' . $c . '%', '%' . $c . '%']
                );
                if ($variant) break;
            }
        }

        if (!$variant) {
            echo json_encode([
                'success' => false,
                'message' => 'No matching product variant found for scanned code "' . htmlspecialchars($rawCode) . '"'
            ]);
            exit;
        }

        $warehouseId = (int)($_REQUEST['warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            $avail = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                [$variant['id'], $warehouseId]
            );
        } else {
            $avail = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'",
                [$variant['id']]
            );
        }
        $availableStock = (int)($avail['qty'] ?? 0);

        // 1. Warehouse Available Stock Check
        if ($availableStock <= 0) {
            $whLabel = 'Warehouse';
            if ($warehouseId > 0) {
                $whRow = Database::fetchOne("SELECT name, code FROM warehouses WHERE id = ?", [$warehouseId]);
                if ($whRow) {
                    $whLabel = $whRow['name'] . ' (' . $whRow['code'] . ')';
                }
            }
            echo json_encode([
                'success' => false,
                'message' => "🚫 Out of Stock in {$whLabel}: Product '{$variant['product_name']}' ({$variant['sku']}) has 0 pcs available in this warehouse inventory!"
            ]);
            exit;
        }

        // 2. Active Allocation Check (Exclude pieces in active dispatch / gate pass)
        $isAllocated = Database::fetchOne(
            "SELECT wa.allocation_number, wa.status
             FROM allocation_items ai
             JOIN warehouse_allocations wa ON ai.allocation_id = wa.id
             WHERE ai.variant_id = ? 
               AND FIND_IN_SET(?, REPLACE(COALESCE(ai.selected_qr_codes, ''), ' ', '')) > 0
               AND wa.status IN ('REQUESTED', 'APPROVED', 'PICKED', 'PACKED', 'DISPATCHED', 'DELIVERED', 'RECEIVED')
             LIMIT 1",
            [$variant['id'], $extractedTag]
        );
        if ($isAllocated) {
            echo json_encode([
                'success' => false,
                'message' => "🚫 Piece Already Allocated: Tag '{$extractedTag}' is allocated in Pass #{$isAllocated['allocation_number']} ({$isAllocated['status']}). Cannot scan an allocated piece!"
            ]);
            exit;
        }

        // 3. Damaged / Defect Pool Check
        $isDamaged = Database::fetchOne(
            "SELECT d.damage_number, d.status 
             FROM warehouse_damages d 
             WHERE d.variant_id = ? AND (d.tag_code = ? OR d.damage_number = ?)
             LIMIT 1",
            [$variant['id'], $extractedTag, $extractedTag]
        );
        if ($isDamaged) {
            echo json_encode([
                'success' => false,
                'message' => "🚫 Piece Marked Damaged: Tag '{$extractedTag}' is marked as Defective (Ref: #{$isDamaged['damage_number']}) and cannot be sold!"
            ]);
            exit;
        }

        // 4. Already Purchased / Sold Check
        $isPurchased = Database::fetchOne(
            "SELECT po.po_number, po.status
             FROM purchase_items pi
             JOIN purchase_orders po ON pi.purchase_order_id = po.id
             WHERE pi.variant_id = ?
               AND (
                   FIND_IN_SET(?, REPLACE(COALESCE(pi.batch_lot_no, ''), ' ', '')) > 0
                   OR pi.batch_lot_no = ?
                   OR pi.notes LIKE ?
               )
               AND po.status NOT IN ('CANCELLED', 'VOID')
             LIMIT 1",
            [$variant['id'], $extractedTag, $extractedTag, '%' . $extractedTag . '%']
        );
        if ($isPurchased) {
            echo json_encode([
                'success' => false,
                'message' => "🚫 Piece Already Sold: Tag '{$extractedTag}' was already sold in Direct Purchase #{$isPurchased['po_number']} ({$isPurchased['status']}). Cannot scan an already sold piece!"
            ]);
            exit;
        }

        $retailPrice = (float)($variant['selling_price'] > 0 ? $variant['selling_price'] : $variant['mrp']);
        echo json_encode([
            'success'       => true,
            'tag_code'      => $extractedTag,
            'extracted_tag' => $extractedTag,
            'variant'       => [
                'id'              => (int)$variant['id'],
                'sku'             => $variant['sku'],
                'qr_code'         => !empty($variant['qr_code']) ? $variant['qr_code'] : $extractedTag,
                'piece_tag'       => $extractedTag,
                'product_name'    => $variant['product_name'],
                'product_code'    => $variant['product_code'],
                'size_name'       => $variant['size_name'] ?? 'Free',
                'color_name'      => $variant['color_name'] ?? 'Std',
                'category_name'   => $variant['category_name'] ?? 'General',
                'selling_price'   => $retailPrice,
                'mrp'             => $retailPrice,
                'gst_rate'        => (float)($variant['gst_rate'] ?? 5.0),
                'available_stock' => $availableStock
            ]
        ]);
        exit;
    }

    public function ajaxSearchCustomers(): void {
        header('Content-Type: application/json');
        $q = trim($_REQUEST['q'] ?? $_REQUEST['query'] ?? $_REQUEST['search'] ?? '');
        if ($q === '') {
            $customers = Database::fetchAll("SELECT * FROM customers WHERE is_active = 1 ORDER BY name ASC LIMIT 25");
        } else {
            $like = '%' . $q . '%';
            $customers = Database::fetchAll(
                "SELECT * FROM customers 
                 WHERE is_active = 1 AND (name LIKE ? OR phone LIKE ? OR city LIKE ? OR gst_number LIKE ?)
                 ORDER BY 
                    CASE 
                        WHEN name = ? OR phone = ? THEN 1
                        WHEN phone LIKE ? THEN 2
                        WHEN name LIKE ? THEN 3
                        ELSE 4 
                    END, name ASC 
                 LIMIT 25",
                [$like, $like, $like, $like, $q, $q, $q . '%', $q . '%']
            );
        }
        echo json_encode(['success' => true, 'customers' => $customers]);
        exit;
    }

    public function ajaxCustomerPurchaseHistory(int|string $id = 0): void {
        header('Content-Type: application/json');
        
        $customerId = (int)($id ?: ($_REQUEST['customer_id'] ?? $_REQUEST['id'] ?? 0));
        $query = trim($_REQUEST['q'] ?? $_REQUEST['query'] ?? $_REQUEST['phone'] ?? $_REQUEST['search'] ?? '');

        $customer = null;
        if ($customerId > 0) {
            $customer = Database::fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
        } elseif ($query !== '') {
            $customer = Database::fetchOne(
                "SELECT * FROM customers WHERE (phone = ? OR name = ? OR email = ?) AND is_active = 1 LIMIT 1",
                [$query, $query, $query]
            );
            if ($customer) {
                $customerId = (int)$customer['id'];
            }
        }

        if (!$customer && $customerId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Customer profile not found or invalid customer ID.'
            ]);
            exit;
        }

        // If customer record not in table but queried by id
        if (!$customer && $customerId > 0) {
            $customer = [
                'id'         => $customerId,
                'name'       => 'Customer #' . $customerId,
                'phone'      => '',
                'email'      => '',
                'city'       => 'Local',
                'state'      => '',
                'address'    => '',
                'gst_number' => ''
            ];
        }

        // Query all Purchase Orders by this customer
        $orders = Database::fetchAll(
            "SELECT po.*, 
                    w.name as warehouse_name, w.code as warehouse_code,
                    u.full_name as created_by_name,
                    COALESCE(pi_stats.total_items, 0) as total_items,
                    COALESCE(pi_stats.total_ordered_qty, 0) as total_ordered_qty
             FROM purchase_orders po
             JOIN warehouses w ON po.warehouse_id = w.id
             LEFT JOIN users u ON po.created_by = u.id
             LEFT JOIN (
                 SELECT purchase_order_id, COUNT(*) as total_items, SUM(quantity) as total_ordered_qty
                 FROM purchase_items
                 GROUP BY purchase_order_id
             ) pi_stats ON po.id = pi_stats.purchase_order_id
             WHERE po.customer_id = ?
             ORDER BY po.order_date DESC, po.id DESC",
            [$customerId]
        );

        // Query all Line Items purchased by this customer across orders
        $items = Database::fetchAll(
            "SELECT pi.*, 
                    po.id as po_id, po.po_number, po.order_date, po.warehouse_id, w.name as warehouse_name,
                    po.payment_status, po.payment_method, po.status as order_status,
                    COALESCE(pi.batch_lot_no, '') as scanned_qr,
                    pv.id as variant_id, pv.sku, pv.qr_code as variant_qr, pv.selling_price as current_selling_price, pv.mrp as variant_mrp,
                    p.id as product_id, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                    COALESCE(cat.name, c_old.name, 'General') as category_name,
                    s.name as size_name, s.code as size_code,
                    c.name as color_name, c.hex_code as color_hex
             FROM purchase_items pi
             JOIN purchase_orders po ON pi.purchase_order_id = po.id
             JOIN product_variants pv ON pi.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             JOIN warehouses w ON po.warehouse_id = w.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE po.customer_id = ?
             ORDER BY po.order_date DESC, pi.id DESC",
            [$customerId]
        );

        // Build distinct purchased products aggregation
        $productsMap = [];
        $totalPieces = 0;
        $totalItemsAmount = 0;

        foreach ($items as $item) {
            $vId = (int)$item['variant_id'];
            $qty = (int)$item['quantity'];
            $amt = (float)$item['total_amount'];

            $totalPieces += $qty;
            $totalItemsAmount += $amt;

            if (!isset($productsMap[$vId])) {
                $productsMap[$vId] = [
                    'variant_id'        => $vId,
                    'product_id'        => (int)$item['product_id'],
                    'product_name'      => $item['product_name'],
                    'product_code'      => $item['product_code'],
                    'category_name'     => $item['category_name'],
                    'sku'               => $item['sku'],
                    'size_name'         => $item['size_name'] ?: 'Std',
                    'size_code'         => $item['size_code'] ?: '',
                    'color_name'        => $item['color_name'] ?: 'Std',
                    'color_hex'         => $item['color_hex'] ?: '#6b7280',
                    'mrp'               => (float)($item['variant_mrp'] ?: $item['mrp']),
                    'unit_price'        => (float)$item['unit_price'],
                    'discount_pct'      => (float)$item['discount_percentage'],
                    'gst_pct'           => (float)$item['gst_percentage'],
                    'total_quantity'    => 0,
                    'total_amount'      => 0.0,
                    'times_ordered'     => 0,
                    'first_order_date'  => $item['order_date'],
                    'last_order_date'   => $item['order_date'],
                    'orders'            => [],
                    'qr_codes'          => []
                ];
            }

            $productsMap[$vId]['total_quantity'] += $qty;
            $productsMap[$vId]['total_amount'] += $amt;
            $productsMap[$vId]['times_ordered'] += 1;

            if (strtotime($item['order_date']) < strtotime($productsMap[$vId]['first_order_date'])) {
                $productsMap[$vId]['first_order_date'] = $item['order_date'];
            }
            if (strtotime($item['order_date']) >= strtotime($productsMap[$vId]['last_order_date'])) {
                $productsMap[$vId]['last_order_date'] = $item['order_date'];
                $productsMap[$vId]['unit_price'] = (float)$item['unit_price'];
            }

            $productsMap[$vId]['orders'][] = [
                'po_id'        => (int)$item['po_id'],
                'po_number'    => $item['po_number'],
                'order_date'   => $item['order_date'],
                'quantity'     => $qty,
                'unit_price'   => (float)$item['unit_price'],
                'total_amount' => $amt,
                'qr_code'      => $item['scanned_qr'] ?: ($item['notes'] ?? '')
            ];

            if (!empty($item['scanned_qr'])) {
                $tags = explode(',', $item['scanned_qr']);
                foreach ($tags as $t) {
                    $t = trim($t);
                    if ($t !== '' && !in_array($t, $productsMap[$vId]['qr_codes'])) {
                        $productsMap[$vId]['qr_codes'][] = $t;
                    }
                }
            }
        }

        // Map items into each order for full details
        $ordersMap = [];
        foreach ($orders as $ord) {
            $oId = (int)$ord['id'];
            $ord['items'] = [];
            $ordersMap[$oId] = $ord;
        }
        foreach ($items as $item) {
            $oId = (int)$item['po_id'];
            if (isset($ordersMap[$oId])) {
                $ordersMap[$oId]['items'][] = $item;
            }
        }

        $totalOrdersCount = count($orders);
        $totalSpend = array_sum(array_column($orders, 'total_amount'));
        $paidSpend = array_sum(array_map(fn($o) => ($o['payment_status'] ?? 'PAID') === 'PAID' ? (float)$o['total_amount'] : 0, $orders));
        $pendingSpend = $totalSpend - $paidSpend;
        $avgOrderVal = $totalOrdersCount > 0 ? round($totalSpend / $totalOrdersCount, 2) : 0;
        $methods = array_values(array_unique(array_filter(array_column($orders, 'payment_method'))));

        $summary = [
            'total_orders'             => $totalOrdersCount,
            'total_spend'              => $totalSpend,
            'total_pieces'             => $totalPieces,
            'distinct_products_count'  => count($productsMap),
            'paid_spend'               => $paidSpend,
            'pending_spend'            => $pendingSpend,
            'avg_order_value'          => $avgOrderVal,
            'is_repeat_customer'       => $totalOrdersCount > 1,
            'first_order_date'         => !empty($orders) ? end($orders)['order_date'] : null,
            'last_order_date'          => !empty($orders) ? $orders[0]['order_date'] : null,
            'payment_methods'          => $methods,
            'primary_warehouse'        => !empty($orders) ? $orders[0]['warehouse_name'] : 'Central Warehouse'
        ];

        echo json_encode([
            'success'   => true,
            'customer'  => $customer,
            'summary'   => $summary,
            'products'  => array_values($productsMap),
            'orders'    => array_values($ordersMap)
        ]);
        exit;
    }

    public function ajaxGetWarehouseStock(): void {
        header('Content-Type: application/json');
        $warehouseId = (int)($_REQUEST['warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            $stock = Database::fetchAll(
                "SELECT variant_id, COALESCE(SUM(quantity), 0) as available_stock 
                 FROM stock_balances 
                 WHERE location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'
                 GROUP BY variant_id",
                [$warehouseId]
            );
        } else {
            $stock = Database::fetchAll(
                "SELECT variant_id, COALESCE(SUM(quantity), 0) as available_stock 
                 FROM stock_balances 
                 WHERE location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'
                 GROUP BY variant_id"
            );
        }
        $stockMap = [];
        foreach ($stock as $s) {
            $stockMap[(int)$s['variant_id']] = (int)$s['available_stock'];
        }
        echo json_encode(['success' => true, 'stock_map' => $stockMap]);
        exit;
    }

    /**
     * Display Direct Purchases Customer Returns & Exchanges Portal.
     */
    public function purchaseReturnsIndex(): void {
        if (!hasPermission('purchases_returns', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view purchase returns.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_GET['warehouse_id'] ?? Session::get('warehouse_id') ?? 1);
        $summary = WarehouseService::getPurchaseReturnsSummary($warehouseId);
        $recentReturns = WarehouseService::getPurchaseReturnsList($_GET, $warehouseId);

        // Fetch all active warehouse products and variants for Different Product exchange catalog picker
        $catalogVariants = Database::fetchAll(
            "SELECT pv.id, pv.sku, COALESCE(NULLIF(pv.qr_code, ''), CONCAT('QR-', pv.sku)) as qr_code, pv.selling_price, pv.mrp,
                    p.name as product_name, p.code as product_code,
                    sz.name as size_name, cl.name as color_name,
                    COALESCE((SELECT SUM(quantity) FROM stock_balances sb 
                              WHERE sb.variant_id = pv.id 
                                AND sb.location_type = 'WAREHOUSE' 
                                AND sb.location_id = ? 
                                AND sb.stock_state = 'AVAILABLE'), 0) as available_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             WHERE pv.is_active = 1 AND p.is_active = 1
             ORDER BY p.name ASC, sz.sort_order ASC, sz.name ASC",
            [$warehouseId]
        );

        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAllActive();

        Response::view('warehouse.purchases.returns', [
            'pageTitle'        => 'Direct Purchases Customer Returns & Exchanges',
            'activeModule'     => 'warehouse',
            'activeMenu'       => 'purchases-returns',
            'breadcrumb'       => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Direct Purchases', 'url' => url('warehouse/purchases')],
                ['label' => 'Returns & Exchanges']
            ],
            'summary'          => $summary,
            'recentReturns'    => $recentReturns,
            'catalogVariants'  => $catalogVariants,
            'warehouses'       => $warehouses,
            'warehouseId'      => $warehouseId,
            'filters'          => $_GET,
        ]);
    }

    /**
     * AJAX Search for Purchase Order by PO Number or Customer Name/Phone.
     */
    public function purchaseReturnsLookup(): void {
        if (!hasPermission('purchases_returns', 'view') && !hasPermission('purchases_returns', 'create')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        $query = trim($_GET['query'] ?? '');
        $warehouseId = (int)(Session::get('warehouse_id') ?? $_GET['warehouse_id'] ?? 1);

        if (empty($query)) {
            Response::json(['success' => false, 'message' => 'Please enter a PO number, Invoice number, Customer Name, or Phone.'], 400);
            return;
        }

        try {
            $data = WarehouseService::lookupPurchaseForReturn($query, $warehouseId);
            if (!$data) {
                Response::json(['success' => false, 'message' => "No Direct Customer Purchase Order found matching '{$query}'."], 404);
                return;
            }

            Response::json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Process Customer Return or Exchange for Direct Purchase Order.
     */
    public function processPurchaseReturn(): void {
        if (!hasPermission('purchases_returns', 'create') && !hasPermission('purchases_returns', 'process')) {
            Response::json(['success' => false, 'message' => 'Access denied: You do not have permission to process purchase returns.'], 403);
            return;
        }
        $rawInput = file_get_contents('php://input');
        $jsonPayload = !empty($rawInput) ? (json_decode($rawInput, true) ?: []) : [];
        $payload = array_merge($_POST, $jsonPayload);

        $csrfToken = $payload['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!CSRF::validate($csrfToken)) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($payload['is_ajax'])) {
                Response::json(['success' => false, 'message' => 'Security token expired or invalid form submission.'], 403);
                return;
            }
            Session::flash('error', 'Security token expired. Please try again.');
            Response::redirect(url('warehouse/purchases/returns'));
            return;
        }

        $userId = (int)(Session::userId() ?? 1);

        if (empty($payload['items']) && !empty($payload['json_payload'])) {
            $decoded = json_decode($payload['json_payload'], true);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        if (!empty($payload['payment_screenshot_base64'])) {
            $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $payload['payment_screenshot_base64']);
            $tmpFilePath = sys_get_temp_dir() . '/ret_pay_' . uniqid() . '.png';
            file_put_contents($tmpFilePath, base64_decode($base64Data));
            
            require_once BASE_PATH . '/app/Services/CloudinaryService.php';
            $cloudUrl = CloudinaryService::upload($tmpFilePath, 'grm_inventory/returns/payments', 'image');
            @unlink($tmpFilePath);
            
            if ($cloudUrl) {
                $payload['notes'] = trim(($payload['notes'] ?? '') . "\n\nPayment Proof: " . $cloudUrl);
            }
        }

        try {
            $result = WarehouseService::processPurchaseReturn($payload, $userId);

            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($payload['is_ajax'])) {
                Response::json([
                    'success'       => true,
                    'message'       => "Return transaction #{$result['return_number']} processed successfully!",
                    'return_id'     => $result['return_id'],
                    'return_number' => $result['return_number'],
                    'invoice_url'   => url('warehouse/purchases/returns/invoice/' . $result['return_id']),
                    'result'        => $result,
                ]);
                return;
            }

            Session::flash('success', "Return transaction #{$result['return_number']} processed successfully!");
            Response::redirect(url('warehouse/purchases/returns/invoice/' . $result['return_id']));
        } catch (\Throwable $e) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($payload['is_ajax'])) {
                Response::json(['success' => false, 'message' => $e->getMessage()], 400);
                return;
            }
            Session::flash('error', 'Failed to process return: ' . $e->getMessage());
            Response::redirect(url('warehouse/purchases/returns'));
        }
    }

    /**
     * View Audit Details of a Direct Purchase Return / Exchange.
     */
    public function purchaseReturnView(string $id): void {
        if (!hasPermission('purchases_returns', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/purchases/returns'));
            return;
        }
        $returnId = (int)$id;
        $data = WarehouseService::getPurchaseReturnDetails($returnId);

        if (!$data || empty($data['return'])) {
            Session::flash('error', 'Return / Exchange transaction not found.');
            Response::redirect(url('warehouse/purchases/returns'));
            return;
        }

        Response::view('warehouse.purchases.return_view', [
            'pageTitle'    => 'Return #' . $data['return']['return_number'],
            'activeModule' => 'warehouse',
            'activeMenu'   => 'purchases-returns',
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Direct Purchases', 'url' => url('warehouse/purchases')],
                ['label' => 'Returns & Exchanges', 'url' => url('warehouse/purchases/returns')],
                ['label' => $data['return']['return_number']]
            ],
            'return'       => $data['return'],
            'returnItems'  => $data['return_items'],
            'exchange'     => $data['exchange'],
            'exchangeItems'=> $data['exchange_items'],
        ]);
    }

    /**
     * Printable Tax Invoice & Return/Exchange Slip for Warehouse Direct Purchase.
     */
    public function purchaseReturnInvoice(string $id): void {
        if (!hasPermission('purchases_returns', 'print') && !hasPermission('purchases_returns', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/purchases/returns'));
            return;
        }
        $returnId = (int)$id;
        $data = WarehouseService::getPurchaseReturnDetails($returnId);

        if (!$data || empty($data['return'])) {
            Session::flash('error', 'Return / Exchange transaction not found.');
            Response::redirect(url('warehouse/purchases/returns'));
            return;
        }

        Response::view('warehouse.purchases.return_invoice', [
            'pageTitle'    => 'Tax Receipt — ' . $data['return']['return_number'],
            'activeModule' => 'warehouse',
            'activeMenu'   => 'purchases-returns',
            'return'       => $data['return'],
            'returnItems'  => $data['return_items'],
            'exchange'     => $data['exchange'],
            'exchangeItems'=> $data['exchange_items'],
        ]);
    }

    public function ajaxAddCustomer(): void {
        if (!hasPermission('purchases', 'create') && !hasPermission('customers', 'create')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $rawPhone = trim($_POST['phone'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', $rawPhone);
        $address = trim($_POST['address'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $gstNumber = strtoupper(trim($_POST['gst_number'] ?? ''));

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Customer name is required.'], 400);
            return;
        }

        if (strlen($phone) !== 10) {
            Response::json(['success' => false, 'message' => 'Mobile number must be exactly 10 digits.'], 400);
            return;
        }

        try {
            $existing = Database::fetchOne("SELECT id, name, phone, address, city, state, gst_number FROM customers WHERE phone = ?", [$phone]);
            if ($existing) {
                Response::json([
                    'success'   => true,
                    'message'   => 'Customer with this phone number already exists.',
                    'customer'  => $existing
                ]);
                return;
            }

            Database::execute(
                "INSERT INTO customers (name, phone, email, address, city, state, gst_number, source_channel, is_active, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [$name, $phone, $email ?: null, $address ?: null, $city ?: null, $state ?: null, $gstNumber ?: null, (!empty($_POST['source_channel']) ? trim($_POST['source_channel']) : 'WAREHOUSE')]
            );
            $id = (int)Database::lastInsertId();

            AuditService::log('WAREHOUSE', 'QUICK_ADD_CUSTOMER', 'customers', $id, null, [
                'name'    => $name,
                'phone'   => $phone,
                'address' => $address,
                'city'    => $city,
                'state'   => $state
            ]);

            Response::json([
                'success'  => true,
                'message'  => 'Customer registered successfully.',
                'customer' => [
                    'id'         => $id,
                    'name'       => $name,
                    'phone'      => $phone,
                    'address'    => $address,
                    'city'       => $city,
                    'state'      => $state,
                    'gst_number' => $gstNumber
                ]
            ]);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 5. STOCK ADJUSTMENTS & PHYSICAL AUDITS — Person 1
    // =========================================================================

    public function adjustmentList(): void {
        if (!hasPermission('stock', 'view') && !hasPermission('stock', 'adjust') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? (string)$scopedWarehouseId : ($_GET['warehouse_id'] ?? '');
        $status = $_GET['status'] ?? '';
        $type = $_GET['adjustment_type'] ?? '';

        $filters = [];
        if (!empty($warehouseId)) $filters['warehouse_id'] = $warehouseId;
        if (!empty($status)) $filters['status'] = $status;
        if (!empty($type)) $filters['adjustment_type'] = $type;

        $adjustments = StockAdjustment::getAll($filters);
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, p.name as product_name, s.name as size_name, c.name as color_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        Response::view('warehouse.adjustments.index', [
            'pageTitle'          => 'Stock Adjustments & Audits',
            'activeModule'       => 'warehouse',
            'activeMenu'         => 'wh-adjustments',
            'adjustments'        => $adjustments,
            'warehouses'         => $warehouses,
            'variants'           => $variants,
            'warehouseId'        => $warehouseId,
            'scopedWarehouseId'  => $scopedWarehouseId,
            'status'             => $status,
            'type'               => $type,
            'breadcrumb'         => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Stock Adjustments'],
            ],
        ]);
    }

    public function storeAdjustment(): void {
        if (!hasPermission('stock', 'adjust')) {
            Session::flash('error', 'Access denied: You do not have permission to create stock adjustments.');
            Response::redirect(url('warehouse/adjustments'));
            return;
        }
        try {
            $userId = Session::userId() ?? 1;
            $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
            $whId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)$_POST['warehouse_id'];

            $data = [
                'warehouse_id'    => $whId,
                'variant_id'      => (int)$_POST['variant_id'],
                'adjustment_type' => $_POST['adjustment_type'],
                'quantity'        => (float)$_POST['quantity'],
                'reason'          => trim($_POST['reason'] ?? ''),
                'notes'           => trim($_POST['notes'] ?? ''),
            ];

            WarehouseService::createAdjustment($data, $userId);
            Response::redirect(url('warehouse/adjustments'), 'success', 'Stock adjustment request submitted for approval.');
        } catch (Exception $e) {
            Response::redirect(url('warehouse/adjustments'), 'error', 'Error creating adjustment: ' . $e->getMessage());
        }
    }

    public function approveAdjustment(string $id): void {
        if (!hasPermission('stock', 'adjust')) {
            Session::flash('error', 'Access denied: You do not have permission to approve stock adjustments.');
            Response::redirect(url('warehouse/adjustments'));
            return;
        }
        $adjId = (int)$id;
        $action = $_POST['action'] ?? 'APPROVE';
        try {
            $userId = Session::userId() ?? 1;
            WarehouseService::approveAdjustment($adjId, $action, $userId);
            $msg = $action === 'APPROVE' ? 'Stock adjustment approved and stock balance updated!' : 'Stock adjustment request rejected.';
            Response::redirect(url('warehouse/adjustments'), 'success', $msg);
        } catch (Exception $e) {
            Response::redirect(url('warehouse/adjustments'), 'error', 'Approval failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 6. SHOP STOCK ALLOCATION — Person 2
    // =========================================================================

    public function allocationList(): void {
        if (!hasPermission('allocation', 'view') && !hasPermission('wh_batches', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied: You do not have permission to view allocations.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $tab = $_GET['tab'] ?? '';

        if (empty($tab)) {
            $tab = (isset($_GET['active_menu']) && $_GET['active_menu'] === 'wh-batches') ? 'batches' : 'allocations';
        }

        $filters = array_filter(['status' => $status, 'search' => $search]);
        $filters['only_allocations'] = true;
        $allocations = WarehouseAllocation::getAll($filters);
        $activeMenu = ($tab === 'batches') ? 'wh-batches' : 'wh-allocations';

        Response::view('warehouse.allocation.index', [
            'pageTitle'    => $tab === 'batches' ? 'Warehouse Allocation Dispatch Batches' : 'Warehouse Stock Allocations',
            'activeModule' => 'warehouse',
            'activeMenu'   => $activeMenu,
            'allocations'  => $allocations,
            'currentStatus'=> $status,
            'currentTab'   => $tab,
            'search'       => $search,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/allocation')],
                ['label' => $tab === 'batches' ? 'Dispatch Batches' : 'Stock Allocations'],
            ],
        ]);
    }

    public function createAllocation(): void {
        if (!hasPermission('allocation', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create store allocations.');
            Response::redirect(url('warehouse/allocation'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $shops = function_exists('getScopedShops') ? getScopedShops() : Shop::getActive();
        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $subcategories = Database::fetchAll("SELECT id, category_id, name FROM subcategories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $subSubcategories = Database::fetchAll("SELECT id, subcategory_id, name FROM sub_subcategories WHERE is_active = 1 ORDER BY name ASC");
        $salesChannels = Database::fetchAll("SELECT * FROM sales_channels WHERE is_active = 1 ORDER BY name ASC");

        $defaultWhId = $scopedWarehouseId !== null ? $scopedWarehouseId : (!empty($warehouses) ? (int)$warehouses[0]['id'] : 0);
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code as barcode, pv.mrp, pv.selling_price, pv.purchase_price,
                    pv.qr_type, pv.pack_quantity, pv.series_start_code, pv.series_end_code,
                    COALESCE(pv.is_complementary, p.is_complementary, 0) as is_complementary, p.is_complementary as prod_is_comp,
                    p.id as product_id, p.name as product_name, p.category_id, p.subcategory_id, p.sub_subcategory_id,
                    sz.name as size_name, c.name as color_name,
                    cat.name as category_name, subcat.name as subcategory_name, subsub.name as sub_subcategory_name,
                    COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE variant_id = pv.id AND location_type = 'WAREHOUSE' " . ($defaultWhId > 0 ? "AND location_id = {$defaultWhId}" : "") . " AND stock_state = 'AVAILABLE'), 0) as total_warehouse_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
             LEFT JOIN sub_subcategories subsub ON p.sub_subcategory_id = subsub.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, sz.sort_order ASC"
        );

        Response::view('warehouse.allocation.create', [
            'pageTitle'          => 'New Shop Allocation Requisition',
            'activeModule'       => 'warehouse',
            'activeMenu'         => 'wh-allocations',
            'warehouses'         => $warehouses,
            'scopedWarehouseId'  => $scopedWarehouseId,
            'shops'              => $shops,
            'salesChannels'      => $salesChannels,
            'categories'         => $categories,
            'subcategories'      => $subcategories,
            'subSubcategories'   => $subSubcategories,
            'variants'           => $variants,
            'breadcrumb'         => [
                ['label' => 'Warehouse', 'url' => url('warehouse/allocation')],
                ['label' => 'Create Allocation'],
            ],
        ]);
    }

    public function storeAllocation(): void {
        if (!hasPermission('allocation', 'create')) {
            Session::flash('error', 'Access denied: You do not have permission to create store allocations.');
            Response::redirect(url('warehouse/allocation'));
            return;
        }
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/allocation/create'));
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_POST['warehouse_id'] ?? 0);
        $sourceShopId = (int)($_POST['source_shop_id'] ?? 0);
        $destWarehouseId = (int)($_POST['destination_warehouse_id'] ?? 0);
        $destinationRaw = $_POST['destination_type'] ?? '';

        $shopId = null;
        $channelId = null;
        $destWhId = null;

        if ($sourceShopId > 0) {
            $shopId = $sourceShopId;
            $destinationType = 'SHOP';
            $destinationId = $shopId;
        } elseif ($destWarehouseId > 0) {
            $destWhId = $destWarehouseId;
            $destinationType = 'WAREHOUSE';
            $destinationId = $destWhId;
        } elseif (str_starts_with($destinationRaw, 'WAREHOUSE_')) {
            $destWhId = (int)substr($destinationRaw, 10);
            $destinationType = 'WAREHOUSE';
            $destinationId = $destWhId;
        } elseif (str_starts_with($destinationRaw, 'CHANNEL_')) {
            $channelId = (int)substr($destinationRaw, 8);
            $destinationType = 'CHANNEL';
            $destinationId = $channelId;
        } elseif (str_starts_with($destinationRaw, 'SHOP_')) {
            $shopId = (int)substr($destinationRaw, 5);
            $destinationType = 'SHOP';
            $destinationId = $shopId;
        } else {
            $destinationType = !empty($destinationRaw) ? $destinationRaw : '';
            $destinationId = 0;
        }

        $notes = trim($_POST['notes'] ?? '');
        $variantIds = $_POST['variant_ids'] ?? [];
        $requestedQty = $_POST['requested_qty'] ?? [];
        $selectedQrCodes = $_POST['selected_qr_codes'] ?? [];

        if (!$warehouseId || empty($variantIds) || (!$shopId && !$channelId && !$destWhId && empty($destinationRaw))) {
            Session::flash('error', 'Please select source warehouse, destination (Source Shop, Destination Channel, or Destination Warehouse), and at least one item variant.');
            Response::redirect(url('warehouse/allocation/create'));
            return;
        }

        // Server-side validation 1: prevent reserving more total stock than available per variant
        $requestedPerVariant = [];
        foreach ($variantIds as $idx => $vId) {
            $qty = (float)($requestedQty[$idx] ?? 0);
            if ($qty <= 0) continue;
            $vIdInt = (int)$vId;
            $requestedPerVariant[$vIdInt] = ($requestedPerVariant[$vIdInt] ?? 0) + $qty;
        }

        foreach ($requestedPerVariant as $vIdInt => $totalReqQty) {
            $avail = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                [$vIdInt, $warehouseId]
            );
            $availableStock = (float)($avail['qty'] ?? 0);

            if ($totalReqQty > $availableStock) {
                $variant = Database::fetchOne("SELECT sku FROM product_variants WHERE id = ?", [$vIdInt]);
                $skuName = $variant['sku'] ?? "Variant #$vIdInt";
                Session::flash('error', "Total requested quantity ({$totalReqQty} pcs) for SKU {$skuName} exceeds available warehouse stock ({$availableStock} pcs).");
                Response::redirect(url('warehouse/allocation/create'));
                return;
            }
        }

        // Server-side validation 2: prevent double-allocating piece QR tags (both across system and within this submission)
        $seenInSubmission = [];
        foreach ($variantIds as $idx => $vId) {
            $qrCodesStr = trim($selectedQrCodes[$idx] ?? '');
            if (!empty($qrCodesStr)) {
                $submittedTags = array_filter(array_map('trim', explode(',', $qrCodesStr)));
                foreach ($submittedTags as $stag) {
                    if (empty($stag) || strlen($stag) < 3) continue;
                    $stagUpper = strtoupper($stag);

                    if (isset($seenInSubmission[$stagUpper])) {
                        Session::flash('error', "Cannot save: Piece Tag '{$stagUpper}' was added multiple times in this requisition.");
                        Response::redirect(url('warehouse/allocation/create'));
                        return;
                    }
                    $seenInSubmission[$stagUpper] = true;

                    $exist = Database::fetchOne(
                        "SELECT wa.allocation_number, wa.status, w.name as warehouse_name
                         FROM allocation_items ai
                         JOIN warehouse_allocations wa ON ai.allocation_id = wa.id
                         LEFT JOIN warehouses w ON wa.warehouse_id = w.id
                         WHERE (ai.selected_qr_codes = ? OR ai.selected_qr_codes LIKE ? OR ai.selected_qr_codes LIKE ? OR ai.selected_qr_codes LIKE ?)
                           AND wa.status NOT IN ('CANCELLED', 'REJECTED')
                         LIMIT 1",
                        [$stag, $stag . ',%', '%,' . $stag, '%,' . $stag . ',%']
                    );
                    if ($exist) {
                        // Check if this variant is a Master pack / Series batch / Complementary pack with sufficient available stock
                        $vCheck = Database::fetchOne(
                            "SELECT pv.id, pv.qr_type, pv.is_complementary, p.is_complementary as prod_comp
                             FROM product_variants pv 
                             JOIN products p ON pv.product_id = p.id 
                             WHERE pv.id = ?",
                            [(int)$vId]
                        );
                        $isMasterOrComp = ($vCheck && ($vCheck['qr_type'] === 'SERIES_BATCH' || !empty($vCheck['is_complementary']) || !empty($vCheck['prod_comp'])));

                        $availStockRow = Database::fetchOne(
                            "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                             WHERE variant_id = ? AND location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND location_id = {$warehouseId}" : "") . " AND stock_state = 'AVAILABLE'",
                            [(int)$vId]
                        );
                        $availQty = (float)($availStockRow['qty'] ?? 0);
                        $rowReqQty = (float)($requestedQty[$idx] ?? 0);

                        if ($isMasterOrComp && $availQty >= $rowReqQty) {
                            // Valid split allocation from remaining available stock!
                            $exist = null;
                        } else {
                            Session::flash('error', "Cannot save: Piece Tag '{$stag}' is already allocated in Pass #{$exist['allocation_number']} ({$exist['status']}) at {$exist['warehouse_name']}.");
                            Response::redirect(url('warehouse/allocation/create'));
                            return;
                        }
                    }
                }
            }
        }

        try {
            $allocationNumber = WarehouseAllocation::generateNumber();
            $userId = Session::get('user_id');

            $allocationId = WarehouseAllocation::create([
                'allocation_number' => $allocationNumber,
                'warehouse_id'      => $warehouseId,
                'destination_type'  => $destinationType,
                'destination_id'    => $destinationId,
                'shop_id'           => $shopId,
                'channel_id'        => $channelId,
                'requested_by'      => $userId,
                'status'            => 'REQUESTED',
                'notes'             => $notes,
            ]);

            foreach ($variantIds as $idx => $vId) {
                $qty = (float)($requestedQty[$idx] ?? 0);
                if ($qty <= 0) continue;

                $qrCodesStr = trim($selectedQrCodes[$idx] ?? '');

                AllocationItem::create([
                    'allocation_id'     => $allocationId,
                    'variant_id'        => (int)$vId,
                    'requested_qty'     => $qty,
                    'approved_qty'      => $qty,
                    'selected_qr_codes' => !empty($qrCodesStr) ? $qrCodesStr : null,
                ]);
            }

            AuditService::log('WAREHOUSE', 'ALLOCATION_CREATE', 'warehouse_allocations', $allocationId, null, [
                'number' => $allocationNumber, 'warehouse_id' => $warehouseId
            ]);

            Session::flash('success', "Allocation Requisition #{$allocationNumber} created successfully!");
            Response::redirect(url('warehouse/allocation/view/' . $allocationId));
        } catch (Exception $e) {
            Session::flash('error', 'Failed to create allocation: ' . $e->getMessage());
            Response::redirect(url('warehouse/allocation/create'));
        }
    }

    public function ajaxVariantStockHierarchy(): void {
        $warehouseId = (int)($_REQUEST['warehouse_id'] ?? 0);
        $variantId   = (int)($_REQUEST['variant_id'] ?? 0);
        $categoryId  = (int)($_REQUEST['category_id'] ?? 0);

        if ($variantId > 0) {
            $variant = Database::fetchOne(
                "SELECT pv.id, pv.sku, pv.mrp, pv.selling_price, p.name as product_name, p.code as product_code, 
                        p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        c.name as color_name, sz.name as size_name,
                        cat.name as category_name, subcat.name as subcategory_name, subsub.name as sub_subcategory_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                 LEFT JOIN sub_subcategories subsub ON p.sub_subcategory_id = subsub.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 WHERE pv.id = ?",
                [$variantId]
            );

            if (!$variant) {
                Response::json(['success' => false, 'message' => 'Variant not found.'], 404);
                return;
            }

            if ($warehouseId > 0) {
                $avail = Database::fetchOne(
                    "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                     WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                    [$variantId, $warehouseId]
                );
                $res = Database::fetchOne(
                    "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                     WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'RESERVED'",
                    [$variantId, $warehouseId]
                );
            } else {
                $avail = Database::fetchOne(
                    "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                     WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'",
                    [$variantId]
                );
                $res = Database::fetchOne(
                    "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                     WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'RESERVED'",
                    [$variantId]
                );
            }

            $availableStock = (float)($avail['qty'] ?? 0);
            $reservedStock  = (float)($res['qty'] ?? 0);
            $totalStock     = $availableStock + $reservedStock;

            $qrPieces = $this->generateVariantQrPieces($variant, (int)$availableStock);

            Response::json([
                'success'              => true,
                'warehouse_id'         => $warehouseId,
                'variant_id'           => $variantId,
                'total_stock'          => $totalStock,
                'reserved_stock'       => $reservedStock,
                'available_stock'      => $availableStock,
                'hierarchy'            => trim(($variant['category_name'] ?? '') . ' > ' . ($variant['subcategory_name'] ?? '') . ($variant['sub_subcategory_name'] ? ' > ' . $variant['sub_subcategory_name'] : ''), ' >'),
                'category_id'          => (int)($variant['category_id'] ?? 0),
                'subcategory_id'       => (int)($variant['subcategory_id'] ?? 0),
                'sub_subcategory_id'   => (int)($variant['sub_subcategory_id'] ?? 0),
                'category_name'        => $variant['category_name'] ?? '',
                'subcategory_name'     => $variant['subcategory_name'] ?? '',
                'sub_subcategory_name' => $variant['sub_subcategory_name'] ?? '',
                'product_name'         => $variant['product_name'] ?? '',
                'sku'                  => $variant['sku'] ?? '',
                'color_name'           => $variant['color_name'] ?? '',
                'size_name'            => $variant['size_name'] ?? '',
                'qr_pieces'            => $qrPieces,
            ]);
            return;
        }

        if ($warehouseId > 0) {
            $sql = "SELECT pv.id, pv.sku, p.name as product_name, p.category_id, p.subcategory_id, p.sub_subcategory_id,
                           c.name as color_name, sz.name as size_name,
                           cat.name as category_name, subcat.name as subcategory_name, subsub.name as sub_subcategory_name,
                           COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE variant_id = pv.id AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'), 0) as available_stock,
                           COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE variant_id = pv.id AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'RESERVED'), 0) as reserved_stock
                    FROM product_variants pv
                    JOIN products p ON pv.product_id = p.id
                    LEFT JOIN categories cat ON p.category_id = cat.id
                    LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                    LEFT JOIN sub_subcategories subsub ON p.sub_subcategory_id = subsub.id
                    LEFT JOIN colors c ON pv.color_id = c.id
                    LEFT JOIN sizes sz ON pv.size_id = sz.id
                    WHERE pv.is_active = 1";
            $params = [$warehouseId, $warehouseId];
        } else {
            $sql = "SELECT pv.id, pv.sku, p.name as product_name, p.category_id, p.subcategory_id, p.sub_subcategory_id,
                           c.name as color_name, sz.name as size_name,
                           cat.name as category_name, subcat.name as subcategory_name, subsub.name as sub_subcategory_name,
                           COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE variant_id = pv.id AND location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'), 0) as available_stock,
                           COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE variant_id = pv.id AND location_type = 'WAREHOUSE' AND stock_state = 'RESERVED'), 0) as reserved_stock
                    FROM product_variants pv
                    JOIN products p ON pv.product_id = p.id
                    LEFT JOIN categories cat ON p.category_id = cat.id
                    LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                    LEFT JOIN sub_subcategories subsub ON p.sub_subcategory_id = subsub.id
                    LEFT JOIN colors c ON pv.color_id = c.id
                    LEFT JOIN sizes sz ON pv.size_id = sz.id
                    WHERE pv.is_active = 1";
            $params = [];
        }

        if ($categoryId > 0) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY p.name ASC, sz.sort_order ASC";
        $variants = Database::fetchAll($sql, $params);

        foreach ($variants as &$v) {
            $v['total_stock'] = (float)$v['available_stock'] + (float)$v['reserved_stock'];
            $v['available_stock'] = (float)$v['available_stock'];
            $v['reserved_stock'] = (float)$v['reserved_stock'];
            $v['hierarchy'] = trim(($v['category_name'] ?? '') . ' > ' . ($v['subcategory_name'] ?? '') . ($v['sub_subcategory_name'] ? ' > ' . $v['sub_subcategory_name'] : ''), ' >');
        }

        Response::json([
            'success'      => true,
            'warehouse_id' => $warehouseId,
            'variants'     => $variants,
        ]);
    }

    private function generateVariantQrPieces(array $variant, int $count): array {
        if ($count <= 0) return [];

        $sku = $variant['sku'] ?? 'SKU';
        $pName = $variant['product_name'] ?? '';
        $isSeries = (($variant['qr_type'] ?? '') === 'SERIES_BATCH');
        $isComp = (!empty($variant['is_complementary']) || !empty($variant['prod_is_comp']));

        if ($isSeries || $isComp) {
            $packQty = !empty($variant['pack_quantity']) ? (int)$variant['pack_quantity'] : $count;
            if ($packQty <= 0) $packQty = max(1, $count);
            $startCode = !empty($variant['series_start_code']) ? $variant['series_start_code'] : ($sku . '-001');
            $endCode = !empty($variant['series_end_code']) ? $variant['series_end_code'] : ($sku . '-' . sprintf('%03d', $packQty));
            $tagCode = "{$startCode} - {$endCode}";

            return [[
                'piece_no'          => 1,
                'total_count'       => 1,
                'qr_code'           => $tagCode,
                'tag_code'          => $tagCode,
                'sku'               => $sku,
                'size'              => $variant['size_name'] ?? 'Free',
                'color'             => $variant['color_name'] ?? '',
                'product'           => $pName,
                'mrp'               => (float)($variant['mrp'] ?? 0),
                'selling_price'     => (float)($variant['selling_price'] ?? 0),
                'is_series'         => true,
                'is_complementary'  => $isComp,
                'pack_quantity'     => $packQty,
            ]];
        }
        
        $c1 = !empty($variant['category_name']) ? trim($variant['category_name']) : (!empty($variant['category_code']) ? trim($variant['category_code']) : '');
        $c2 = !empty($variant['subcategory_name']) ? trim($variant['subcategory_name']) : (!empty($variant['subcategory_code']) ? trim($variant['subcategory_code']) : '');
        $c3 = !empty($variant['sub_subcategory_name']) ? trim($variant['sub_subcategory_name']) : (!empty($variant['sub_subcategory_code']) ? trim($variant['sub_subcategory_code']) : '');

        $prefix = '';
        if ($c1 !== '') $prefix .= strtoupper($c1[0]);
        if ($c2 !== '') $prefix .= strtoupper($c2[0]);
        if ($c3 !== '') $prefix .= strtoupper($c3[0]);

        if (strlen($prefix) < 2) {
            if (!empty($variant['qr_code_prefix'])) {
                $clean = preg_replace('/[^A-Za-z0-9]/', '', $variant['qr_code_prefix']);
                $prefix = strtoupper(substr($clean, 0, 3));
            }
        }
        if (empty($prefix)) {
            $prefix = 'TTS';
        }

        $m = date('n');
        $y = date('y');

        // Fetch all already allocated piece QR codes across the entire system
        $alreadyAllocatedCodes = [];
        $activeAllocRows = Database::fetchAll(
            "SELECT ai.selected_qr_codes
             FROM allocation_items ai
             JOIN warehouse_allocations wa ON ai.allocation_id = wa.id
             WHERE wa.status NOT IN ('CANCELLED', 'REJECTED')"
        );

        foreach ($activeAllocRows as $row) {
            if (!empty($row['selected_qr_codes'])) {
                $codes = array_map('trim', explode(',', $row['selected_qr_codes']));
                foreach ($codes as $c) {
                    if (!empty($c)) {
                        $alreadyAllocatedCodes[$c] = true;
                    }
                }
            }
        }

        $pieces = [];
        $i = 1;
        while (count($pieces) < $count && $i <= 500) {
            $code = $prefix . sprintf("%02d", (int)$m) . $y . sprintf("%02d", $i);
            
            // EXCLUDE ALREADY ALLOCATED QR CODES
            if (!isset($alreadyAllocatedCodes[$code])) {
                $pieces[] = [
                    'piece_no'      => $i,
                    'qr_code'       => $code,
                    'sku'           => $sku,
                    'size'          => $variant['size_name'] ?? 'Free',
                    'color'         => $variant['color_name'] ?? '',
                    'product'       => $pName,
                    'mrp'           => (float)($variant['mrp'] ?? 0),
                    'selling_price' => (float)($variant['selling_price'] ?? 0),
                ];
            }
            $i++;
        }

        return $pieces;
    }

    /**
     * AJAX Endpoint: Live Scanner / Barcode Lookup for Shop Allocation Requisition
     * Resolves scanned piece stickers, QR codes, or SKUs with live warehouse stock balances.
     */
    public function ajaxScanAllocationQr(): void {
        header('Content-Type: application/json');

        $rawCode = trim($_REQUEST['code'] ?? $_REQUEST['query'] ?? $_REQUEST['search'] ?? '');
        $warehouseId = (int)($_REQUEST['warehouse_id'] ?? 0);

        if (empty($rawCode)) {
            echo json_encode(['success' => false, 'message' => 'No scan code or SKU provided.']);
            exit;
        }

        // Clean raw input (strip surrounding quotes, whitespace, control characters)
        $rawCode = trim($rawCode, " \t\n\r\0\x0B\"'");

        $extractedTag = $rawCode;
        $candidateCodes = [$rawCode];
        $parsedSeriesQty = null;

        // 1. JSON Payload parsing
        if (str_starts_with($rawCode, '{') && str_ends_with($rawCode, '}')) {
            $decoded = json_decode($rawCode, true);
            if (is_array($decoded)) {
                $extractedTag = $decoded['tag'] ?? $decoded['tag_code'] ?? $decoded['code'] ?? $rawCode;
                if (!empty($decoded['sku'])) $candidateCodes[] = trim($decoded['sku']);
                if (!empty($decoded['tag'])) $candidateCodes[] = trim($decoded['tag']);
                if (!empty($decoded['tag_code'])) $candidateCodes[] = trim($decoded['tag_code']);
                if (!empty($decoded['code'])) $candidateCodes[] = trim($decoded['code']);
                if (!empty($decoded['qr_code'])) $candidateCodes[] = trim($decoded['qr_code']);
                if (!empty($decoded['qty'])) $parsedSeriesQty = (int)$decoded['qty'];
            }
        }
        // 2. Pipe-delimited QR payload (e.g. "SERIES|Brand|SKU|start-end|QTY:50|..." or "TTS092601|SKU|...")
        elseif (strpos($rawCode, '|') !== false) {
            $parts = array_map('trim', explode('|', $rawCode));
            $extractedTag = $parts[0];

            if (strtoupper($parts[0]) === 'SERIES') {
                foreach ($parts as $p) {
                    if (stripos($p, 'QTY:') === 0) {
                        $parsedSeriesQty = (int)substr($p, 4);
                    } elseif (stripos($p, 'MRP:') === false && stripos($p, 'Disc:') === false && stripos($p, 'Cost:') === false && stripos($p, 'Price:') === false && stripos($p, 'Sell:') === false && stripos($p, 'COMP:') === false && stripos($p, 'PO:') === false) {
                        $candidateCodes[] = $p;
                        if (strpos($p, '-') !== false) {
                            $cleanSub = preg_replace('/-\d{2,4}$/', '', $p);
                            if ($cleanSub && $cleanSub !== $p) $candidateCodes[] = $cleanSub;
                        }
                    }
                }
            } else {
                foreach ($parts as $part) {
                    if (stripos($part, 'MRP:') === 0 || stripos($part, 'Disc:') === 0 || stripos($part, 'Cost:') === 0 || stripos($part, 'Price:') === 0 || stripos($part, 'Sell:') === 0) {
                        continue;
                    }
                    if (stripos($part, 'QTY:') === 0) {
                        $parsedSeriesQty = (int)substr($part, 4);
                        continue;
                    }
                    $candidateCodes[] = $part;
                }
            }

            if (isset($parts[1]) && !empty($parts[1])) {
                $candidateCodes[] = $parts[1];
            }
            if (isset($parts[2]) && !empty($parts[2])) {
                $candidateCodes[] = $parts[2];
            }
        }
        // 3. URL encoded with query params
        elseif (strpos($rawCode, 'http') === 0 && (strpos($rawCode, '?') !== false || strpos($rawCode, '/') !== false)) {
            $parsedUrl = parse_url($rawCode);
            if (!empty($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                if (!empty($queryParams['code'])) $candidateCodes[] = $queryParams['code'];
                if (!empty($queryParams['sku'])) $candidateCodes[] = $queryParams['sku'];
                if (!empty($queryParams['tag'])) $candidateCodes[] = $queryParams['tag'];
            }
            if (!empty($parsedUrl['path'])) {
                $pathSegments = explode('/', trim($parsedUrl['path'], '/'));
                $candidateCodes[] = end($pathSegments);
            }
        }

        // 4. Series Range code (e.g. "TP-CHOCO-RED-247-001 - TP-CHOCO-RED-247-050" or "WOMM102601 - WOMM102620")
        if (strpos($rawCode, ' - ') !== false) {
            $rangeParts = array_map('trim', explode(' - ', $rawCode));
            foreach ($rangeParts as $rp) {
                $candidateCodes[] = $rp;
                $cleanSub = preg_replace('/-\d{2,4}$/', '', $rp);
                if ($cleanSub && $cleanSub !== $rp) $candidateCodes[] = $cleanSub;
            }
        } elseif (preg_match('/^(.+)-(\d{2,4})$/', $rawCode, $mSku)) {
            $candidateCodes[] = $mSku[1];
        }

        $candidateCodes = array_values(array_unique(array_filter($candidateCodes)));

        // Guardrail: Check if this piece tag or candidate code is already allocated in ANY active/dispatched/delivered allocation
        foreach ($candidateCodes as $codeToCheck) {
            if (empty($codeToCheck) || strlen($codeToCheck) < 3) continue;
            $alreadyAlloc = Database::fetchOne(
                "SELECT ai.id, wa.id as allocation_id, wa.allocation_number, wa.status,
                        w.name as warehouse_name,
                        p.name as product_name, pv.sku, pv.id as variant_id,
                        col.name as color_name, sz.name as size_name
                 FROM allocation_items ai
                 JOIN warehouse_allocations wa ON ai.allocation_id = wa.id
                 JOIN product_variants pv ON ai.variant_id = pv.id
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN warehouses w ON wa.warehouse_id = w.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 WHERE (
                     ai.selected_qr_codes = ? 
                     OR ai.selected_qr_codes LIKE ? 
                     OR ai.selected_qr_codes LIKE ? 
                     OR ai.selected_qr_codes LIKE ?
                 )
                 AND wa.status NOT IN ('CANCELLED', 'REJECTED')
                 LIMIT 1",
                [$codeToCheck, $codeToCheck . ',%', '%,' . $codeToCheck, '%,' . $codeToCheck . ',%']
            );

            if ($alreadyAlloc) {
                // Check if this variant is a Master pack / Series batch / Complementary pack with remaining available warehouse stock
                $allocVar = Database::fetchOne(
                    "SELECT pv.id, pv.qr_type, pv.is_complementary, p.is_complementary as prod_comp
                     FROM product_variants pv 
                     JOIN products p ON pv.product_id = p.id 
                     WHERE pv.id = ?",
                    [$alreadyAlloc['variant_id']]
                );
                $isMasterOrComp = ($allocVar && ($allocVar['qr_type'] === 'SERIES_BATCH' || !empty($allocVar['is_complementary']) || !empty($allocVar['prod_comp'])));

                if ($isMasterOrComp) {
                    $availStockRow = Database::fetchOne(
                        "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                         WHERE variant_id = ? AND location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND location_id = {$warehouseId}" : "") . " AND stock_state = 'AVAILABLE'",
                        [$alreadyAlloc['variant_id']]
                    );
                    $remStock = (float)($availStockRow['qty'] ?? 0);
                    if ($remStock > 0) {
                        // Master pack has remaining stock in the warehouse! Allow scan to proceed for splitting/allocating
                        continue;
                    }
                }

                echo json_encode([
                    'success'              => false,
                    'is_already_allocated' => true,
                    'allocation_number'    => $alreadyAlloc['allocation_number'],
                    'allocation_id'        => $alreadyAlloc['allocation_id'],
                    'allocation_status'    => $alreadyAlloc['status'],
                    'allocated_warehouse'  => $alreadyAlloc['warehouse_name'] ?? 'Warehouse',
                    'allocated_product'    => ($alreadyAlloc['product_name'] ?? '') . ' (' . ($alreadyAlloc['sku'] ?? '') . ')',
                    'tag_code'             => $codeToCheck,
                    'message'              => "Piece Tag '{$codeToCheck}' has ALREADY been allocated/dispatched in Pass #{$alreadyAlloc['allocation_number']} (Status: {$alreadyAlloc['status']}) at {$alreadyAlloc['warehouse_name']}! Every physical piece QR code is unique and cannot be allocated twice."
                ]);
                exit;
            }
        }

        $variant = null;

        // Strategy 1: Exact match on candidate codes against product_variants (sku, qr_code, id) or products (code, qr_code_prefix)
        foreach ($candidateCodes as $c) {
            if (empty($c)) continue;
            $variant = Database::fetchOne(
                "SELECT pv.*, 
                        p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                        p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        s.name as size_name, s.code as size_code,
                        col.name as color_name, col.hex_code as color_hex,
                        COALESCE(wc.name, cat.name) as category_name,
                        COALESCE(wsc.name, subcat.name) as subcategory_name,
                        COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                 LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                 LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                 LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                 WHERE pv.sku = ? 
                    OR pv.qr_code = ? 
                    OR pv.series_start_code = ?
                    OR pv.series_end_code = ?
                    OR p.code = ?
                    OR p.qr_code_prefix = ?
                    OR (pv.id = ? AND ? > 0)
                 LIMIT 1",
                [$c, $c, $c, $c, $c, $c, is_numeric($c) ? (int)$c : 0, is_numeric($c) ? (int)$c : 0]
            );
            if ($variant) break;
        }

        // Strategy 2: Historical Inward GRN / Stock transactions / Purchase Items tag search
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                if (empty($c) || strlen($c) < 3) continue;
                $tx = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM stock_transactions st
                     JOIN product_variants pv ON st.variant_id = pv.id
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . " AND sb.stock_state = 'AVAILABLE'
                     WHERE st.notes LIKE ? OR st.notes LIKE ?
                     ORDER BY sb.quantity DESC, st.id DESC
                     LIMIT 1",
                    ['%' . $c . '%', '%Tags: ' . $c . '%']
                );
                if ($tx) {
                    $variant = $tx;
                    break;
                }

                $pi = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM purchase_items pi
                     JOIN product_variants pv ON pi.variant_id = pv.id
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . " AND sb.stock_state = 'AVAILABLE'
                     WHERE pi.batch_lot_no LIKE ? OR pi.notes LIKE ?
                     ORDER BY sb.quantity DESC, pi.id DESC
                     LIMIT 1",
                    ['%' . $c . '%', '%' . $c . '%']
                );
                if ($pi) {
                    $variant = $pi;
                    break;
                }
            }
        }

        // Strategy 3: Lookup from QC Items recorded tag_code
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                $qc = Database::fetchOne(
                    "SELECT qi.variant_id, qi.tag_code, pb.destination_warehouse_id, w.name as dest_warehouse_name 
                     FROM qc_items qi 
                     LEFT JOIN production_batches pb ON qi.qc_batch_id = pb.qc_batch_id 
                     LEFT JOIN warehouses w ON pb.destination_warehouse_id = w.id
                     WHERE qi.tag_code = ? ORDER BY qi.id DESC LIMIT 1",
                    [$c]
                );
                if ($qc && !empty($qc['variant_id'])) {
                    if ($warehouseId > 0 && !empty($qc['destination_warehouse_id']) && (int)$qc['destination_warehouse_id'] !== $warehouseId) {
                        echo json_encode([
                            'success' => false,
                            'is_no_stock' => true,
                            'message' => "QR Code '{$qc['tag_code']}' physically belongs to '{$qc['dest_warehouse_name']}', but you have selected a different Source Warehouse! Please check the physical stock."
                        ]);
                        exit;
                    }

                    $variant = Database::fetchOne(
                        "SELECT pv.*, 
                                p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                s.name as size_name, s.code as size_code,
                                col.name as color_name, col.hex_code as color_hex,
                                COALESCE(wc.name, cat.name) as category_name,
                                COALESCE(wsc.name, subcat.name) as subcategory_name,
                                COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                         LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                         LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                         LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                         WHERE pv.id = ? LIMIT 1",
                        [$qc['variant_id']]
                    );
                    if ($variant) {
                        $extractedTag = $qc['tag_code'] ?: $extractedTag;
                        break;
                    }
                }
            }
        }

        // Strategy 4: Lookup by Production Batch Number
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                $batchItem = Database::fetchOne(
                    "SELECT pbi.variant_id 
                     FROM production_batches pb 
                     JOIN production_batch_items pbi ON pbi.production_batch_id = pb.id 
                     WHERE pb.batch_number = ? OR pb.batch_number LIKE ? 
                     ORDER BY pbi.id ASC LIMIT 1",
                    [$c, '%' . $c . '%']
                );
                if ($batchItem && !empty($batchItem['variant_id'])) {
                    $variant = Database::fetchOne(
                        "SELECT pv.*, 
                                p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                                p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                                p.category_id, p.subcategory_id, p.sub_subcategory_id,
                                s.name as size_name, s.code as size_code,
                                col.name as color_name, col.hex_code as color_hex,
                                COALESCE(wc.name, cat.name) as category_name,
                                COALESCE(wsc.name, subcat.name) as subcategory_name,
                                COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         LEFT JOIN sizes s ON pv.size_id = s.id
                         LEFT JOIN colors col ON pv.color_id = col.id
                         LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                         LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                         LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                         LEFT JOIN categories cat ON p.category_id = cat.id
                         LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                         LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                         WHERE pv.id = ? LIMIT 1",
                        [$batchItem['variant_id']]
                    );
                    if ($variant) break;
                }
            }
        }

        // Strategy 5: Dynamic Piece Tag Decomposition & Reverse Prefix/Sequence Matching
        if (!$variant) {
            $tagPatternMatched = false;
            $exceededStockInfo = null;

            if (preg_match('/^([A-Za-z_-]{2,6})(\d{4})(\d{1,5})$/i', $extractedTag, $matches)) {
                $tagPatternMatched = true;
                $prefix = strtoupper($matches[1]);
                $mfgDigits = $matches[2];
                $seq = (int)$matches[3];
                $mfgMonth = (int)substr($mfgDigits, 0, 2);

                if ($seq >= 1 && $mfgMonth >= 1 && $mfgMonth <= 12) {
                    $allVariants = Database::fetchAll("
                        SELECT pv.*, 
                               p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                               p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                               p.category_id, p.subcategory_id, p.sub_subcategory_id,
                               s.name as size_name, s.code as size_code,
                               col.name as color_name, col.hex_code as color_hex,
                               COALESCE(wc.name, cat.name) as category_name,
                               COALESCE(wsc.name, subcat.name) as subcategory_name,
                               COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name,
                               COALESCE(wc.code, cat.code, p.category_code) as cat_code,
                               COALESCE(wsc.code, subcat.code, p.subcategory_code) as sub_code,
                               COALESCE(wssc.code, subsubcat.code, p.sub_subcategory_code) as subsub_code,
                               COALESCE(sb.quantity, 0) as wh_stock
                        FROM product_variants pv
                        JOIN products p ON pv.product_id = p.id
                        LEFT JOIN sizes s ON pv.size_id = s.id
                        LEFT JOIN colors col ON pv.color_id = col.id
                        LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                        LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                        LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                        LEFT JOIN categories cat ON p.category_id = cat.id
                        LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                        LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                        LEFT JOIN stock_balances sb ON sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' " . ($warehouseId > 0 ? "AND sb.location_id = {$warehouseId}" : "") . " AND sb.stock_state = 'AVAILABLE'
                        WHERE pv.is_active = 1
                        ORDER BY wh_stock DESC, pv.id ASC
                    ");

                    $bestMatch = null;
                    $bestScore = 0;

                    foreach ($allVariants as $v) {
                        $prefixes = [];
                        if (!empty($v['qr_code_prefix'])) {
                            $rawQp = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v['qr_code_prefix']));
                            $prefixes[] = $rawQp;
                            if (strlen($rawQp) === 9) {
                                $prefixes[] = $rawQp[0] . $rawQp[3] . $rawQp[6];
                            } elseif (strlen($rawQp) > 4) {
                                $prefixes[] = substr($rawQp, 0, 3);
                            }
                        }
                        if (!empty($v['product_code'])) {
                            $prefixes[] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', explode('-', $v['product_code'])[0]));
                        }
                        if (!empty($v['sku'])) {
                            $prefixes[] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', explode('-', $v['sku'])[0]));
                            $prefixes[] = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $v['sku']), 0, 3));
                        }
                        $c1 = !empty($v['category_name']) ? strtoupper($v['category_name'][0]) : (!empty($v['cat_code']) ? strtoupper($v['cat_code'][0]) : '');
                        $c2 = !empty($v['subcategory_name']) ? strtoupper($v['subcategory_name'][0]) : (!empty($v['sub_code']) ? strtoupper($v['sub_code'][0]) : '');
                        $c3 = !empty($v['sub_subcategory_name']) ? strtoupper($v['sub_subcategory_name'][0]) : (!empty($v['subsub_code']) ? strtoupper($v['subsub_code'][0]) : '');
                        if ($c1 && $c2) {
                            $prefixes[] = $c1 . $c2 . $c3;
                            $prefixes[] = $c1 . $c2;
                            $prefixes[] = 'R' . $c1 . $c2 . $c3;
                        }

                        $stock = (float)$v['wh_stock'];
                        $isPrefixMatch = false;

                        foreach ($prefixes as $pfx) {
                            if (empty($pfx)) continue;
                            if ($prefix === $pfx || str_starts_with($prefix, $pfx) || str_starts_with($pfx, $prefix)) {
                                $isPrefixMatch = true;
                                break;
                            }
                        }

                        // If prefix matches this product, score it based on available stock
                        if ($isPrefixMatch) {
                            $score = ($stock > 0 ? 200 : 50) + min(20, (int)$stock);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestMatch = $v;
                            }
                        } elseif ($prefix === 'TTS' && $stock > 0) {
                            $score = 30 + min(50, (int)$stock);
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestMatch = $v;
                            }
                        }
                    }

                    if ($bestMatch && $bestScore > 0) {
                        $variant = $bestMatch;
                    }
                }
            }
        }

        // Strategy 6: Fuzzy search LIKE on SKU, Product Name, Code
        if (!$variant) {
            foreach ($candidateCodes as $c) {
                if (strlen($c) < 2) continue;
                $variant = Database::fetchOne(
                    "SELECT pv.*, 
                            p.id as product_id, p.name as product_name, p.code as product_code, p.image_url as product_image,
                            p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                            p.category_id, p.subcategory_id, p.sub_subcategory_id,
                            s.name as size_name, s.code as size_code,
                            col.name as color_name, col.hex_code as color_hex,
                            COALESCE(wc.name, cat.name) as category_name,
                            COALESCE(wsc.name, subcat.name) as subcategory_name,
                            COALESCE(wssc.name, subsubcat.name) as sub_subcategory_name
                     FROM product_variants pv
                     JOIN products p ON pv.product_id = p.id
                     LEFT JOIN sizes s ON pv.size_id = s.id
                     LEFT JOIN colors col ON pv.color_id = col.id
                     LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
                     LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
                     LEFT JOIN warehouse_sub_subcategories wssc ON p.warehouse_sub_subcategory_id = wssc.id
                     LEFT JOIN categories cat ON p.category_id = cat.id
                     LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                     LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                     WHERE pv.sku LIKE ? OR p.name LIKE ? OR p.code LIKE ? OR col.name LIKE ?
                     LIMIT 1",
                    ['%' . $c . '%', '%' . $c . '%', '%' . $c . '%', '%' . $c . '%']
                );
                if ($variant) break;
            }
        }

        if (!$variant) {
            echo json_encode([
                'success' => false,
                'message' => 'No matching product variant found for scanned code "' . htmlspecialchars($rawCode) . '"'
            ]);
            exit;
        }

        // Fetch Source Warehouse name for friendly messages
        $warehouseName = 'Warehouse';
        if ($warehouseId > 0) {
            $whRow = Database::fetchOne("SELECT name FROM warehouses WHERE id = ?", [$warehouseId]);
            if ($whRow && !empty($whRow['name'])) {
                $warehouseName = $whRow['name'];
            }
        }

        // Fetch Live Stock Balances in this warehouse
        if ($warehouseId > 0) {
            $avail = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                [$variant['id'], $warehouseId]
            );
            $res = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'RESERVED'",
                [$variant['id'], $warehouseId]
            );
        } else {
            $avail = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'",
                [$variant['id']]
            );
            $res = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as qty FROM stock_balances 
                 WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'RESERVED'",
                [$variant['id']]
            );
        }

        $availableStock = (float)($avail['qty'] ?? 0);
        $reservedStock  = (float)($res['qty'] ?? 0);
        $totalStock     = $availableStock + $reservedStock;

        if ($warehouseId > 0 && $availableStock <= 0) {
            echo json_encode([
                'success'     => false,
                'is_no_stock' => true,
                'message'     => "Product '{$variant['product_name']}' ({$variant['sku']}) has 0 AVAILABLE stock in {$warehouseName}! Cannot allocate out-of-stock inventory."
            ]);
            exit;
        }

        // Fetch/generate piece QR items for this variant
        $qrPieces = $this->generateVariantQrPieces($variant, (int)$availableStock);

        // Ensure scanned tag is present in piece list if valid format
        $tagExistsInPieces = false;
        foreach ($qrPieces as $qp) {
            if ($qp['qr_code'] === $extractedTag) {
                $tagExistsInPieces = true;
                break;
            }
        }

        if (!$tagExistsInPieces && !empty($extractedTag) && $extractedTag !== $variant['sku']) {
            array_unshift($qrPieces, [
                'piece_no'      => 1,
                'qr_code'       => $extractedTag,
                'sku'           => $variant['sku'],
                'size'          => $variant['size_name'] ?? 'Free',
                'color'         => $variant['color_name'] ?? '',
                'product'       => $variant['product_name'],
                'mrp'           => (float)($variant['mrp'] ?? 0),
                'selling_price' => (float)($variant['selling_price'] ?? 0),
            ]);
        }

        $catHierarchy = trim(($variant['category_name'] ?? '') . ' > ' . ($variant['subcategory_name'] ?? '') . ($variant['sub_subcategory_name'] ? ' > ' . $variant['sub_subcategory_name'] : ''), ' >');

        $isSeries = (($variant['qr_type'] ?? '') === 'SERIES_BATCH');
        $isComplementary = (!empty($variant['is_complementary']) || !empty($variant['prod_is_comp']));
        $packQty = (int)(!empty($variant['pack_quantity']) ? $variant['pack_quantity'] : ($isSeries || $isComplementary ? $availableStock : 1));
        if ($packQty <= 0) $packQty = 1;
        $scanQty = ($isSeries || $isComplementary) ? (int)(!empty($parsedSeriesQty) ? $parsedSeriesQty : min($packQty, (int)$availableStock)) : 1;
        if ($scanQty <= 0) $scanQty = 1;

        echo json_encode([
            'success'              => true,
            'warehouse_id'         => $warehouseId,
            'tag_code'             => $extractedTag,
            'variant_id'           => (int)$variant['id'],
            'sku'                  => $variant['sku'],
            'product_id'           => (int)$variant['product_id'],
            'product_name'         => $variant['product_name'],
            'product_code'         => $variant['product_code'],
            'category_id'          => (int)($variant['category_id'] ?? 0),
            'subcategory_id'       => (int)($variant['subcategory_id'] ?? 0),
            'sub_subcategory_id'   => (int)($variant['sub_subcategory_id'] ?? 0),
            'category_name'        => $variant['category_name'] ?? '',
            'subcategory_name'     => $variant['subcategory_name'] ?? '',
            'sub_subcategory_name' => $variant['sub_subcategory_name'] ?? '',
            'hierarchy'            => $catHierarchy,
            'size_name'            => $variant['size_name'] ?? 'Free Size',
            'color_name'           => $variant['color_name'] ?? '',
            'color_hex'            => $variant['color_hex'] ?? '#333333',
            'total_stock'          => $totalStock,
            'reserved_stock'       => $reservedStock,
            'available_stock'      => $availableStock,
            'mrp'                  => (float)($variant['mrp'] ?? $variant['selling_price'] ?? 0),
            'selling_price'        => (float)($variant['selling_price'] ?? 0),
            'is_series'            => $isSeries,
            'is_complementary'     => $isComplementary,
            'qr_type'              => $variant['qr_type'] ?? 'INDIVIDUAL',
            'pack_quantity'        => $packQty,
            'scan_qty'             => $scanQty,
            'series_start_code'    => $variant['series_start_code'] ?? null,
            'series_end_code'      => $variant['series_end_code'] ?? null,
            'qr_pieces'            => $qrPieces,
        ]);
        exit;
    }


    public function viewAllocation(int $id): void {
        if (!hasPermission('allocation', 'view') && !hasPermission('wh_batches', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/allocation'));
            return;
        }
        $allocation = WarehouseAllocation::find($id);
        if (!$allocation) {
            Session::flash('error', 'Allocation requisition not found.');
            Response::redirect(url('warehouse/allocation'));
        }

        $pieceQRs = [];
        $monthStr = sprintf('%02d', date('n'));
        $yearStr = date('y');

        foreach ($allocation['items'] as $item) {
            $variantId = (int)$item['variant_id'];
            $variant = Database::fetchOne(
                "SELECT pv.*, 
                        p.name as product_name, p.code as product_code,
                        p.qr_code_prefix, p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        cat.name as category_name, subcat.name as subcategory_name, subsub.name as sub_subcategory_name,
                        s.name as size_name, col.name as color_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                 LEFT JOIN sub_subcategories subsub ON p.sub_subcategory_id = subsub.id
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 WHERE pv.id = ?",
                [$variantId]
            );

            if (!$variant) continue;

            $c1 = !empty($variant['category_name']) ? trim($variant['category_name']) : (!empty($variant['category_code']) ? trim($variant['category_code']) : '');
            $c2 = !empty($variant['subcategory_name']) ? trim($variant['subcategory_name']) : (!empty($variant['subcategory_code']) ? trim($variant['subcategory_code']) : '');
            $c3 = !empty($variant['sub_subcategory_name']) ? trim($variant['sub_subcategory_name']) : (!empty($variant['sub_subcategory_code']) ? trim($variant['sub_subcategory_code']) : '');

            $prefix = '';
            if ($c1 !== '') $prefix .= strtoupper($c1[0]);
            if ($c2 !== '') $prefix .= strtoupper($c2[0]);
            if ($c3 !== '') $prefix .= strtoupper($c3[0]);

            if (strlen($prefix) < 2) {
                if (!empty($variant['qr_code_prefix'])) {
                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $variant['qr_code_prefix']);
                    $prefix = strtoupper(substr($clean, 0, 3));
                }
            }
            if (empty($prefix)) {
                $prefix = 'TTS';
            }

            $savedQrCodes = !empty($item['selected_qr_codes']) ? array_filter(array_map('trim', explode(',', $item['selected_qr_codes']))) : [];

            if (!empty($savedQrCodes)) {
                $pIdx = 1;
                foreach ($savedQrCodes as $code) {
                    $pieceQRs[] = [
                        'piece_no'      => $pIdx++,
                        'tag_code'      => $code,
                        'sku'           => $item['sku'],
                        'product_name'  => $item['product_name'],
                        'color_name'    => $item['color_name'] ?? 'Std',
                        'size_name'     => $item['size_name'] ?? 'Free',
                        'mrp'           => (float)($variant['mrp'] ?? $item['mrp'] ?? 0),
                        'selling_price' => (float)($variant['selling_price'] ?? $item['selling_price'] ?? 0),
                    ];
                }
            } else {
                $qty = (int)($item['requested_qty'] > 0 ? $item['requested_qty'] : ($item['dispatched_qty'] > 0 ? $item['dispatched_qty'] : 1));
                for ($p = 1; $p <= $qty; $p++) {
                    $tagCode = $prefix . sprintf("%02d", $monthStr) . $yearStr . sprintf("%02d", $p);
                    $pieceQRs[] = [
                        'piece_no'      => $p,
                        'tag_code'      => $tagCode,
                        'sku'           => $item['sku'],
                        'product_name'  => $item['product_name'],
                        'color_name'    => $item['color_name'] ?? 'Std',
                        'size_name'     => $item['size_name'] ?? 'Free',
                        'mrp'           => (float)($variant['mrp'] ?? $item['mrp'] ?? 0),
                        'selling_price' => (float)($variant['selling_price'] ?? $item['selling_price'] ?? 0),
                    ];
                }
            }
        }

        $fromSource = $_GET['from'] ?? '';
        $isShopRequest = ($fromSource === 'shop-requests' || ($allocation['destination_type'] ?? '') === 'SHOP');

        $activeMenu = $isShopRequest ? 'wh-shop-requests' : 'wh-allocations';
        $breadcrumb = $isShopRequest ? [
            ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
            ['label' => 'Shop Requests', 'url' => url('warehouse/shop-requests')],
            ['label' => $allocation['allocation_number']],
        ] : [
            ['label' => 'Warehouse', 'url' => url('warehouse/allocation')],
            ['label' => $allocation['allocation_number']],
        ];

        Response::view('warehouse.allocation.view', [
            'pageTitle'     => ($isShopRequest ? 'Shop Request #' : 'Allocation #') . $allocation['allocation_number'],
            'activeModule'  => 'warehouse',
            'activeMenu'    => $activeMenu,
            'isShopRequest' => $isShopRequest,
            'allocation'    => $allocation,
            'pieceQRs'      => $pieceQRs,
            'breadcrumb'    => $breadcrumb,
        ]);
    }

    public function approveAllocation(int $id): void {
        if (!hasPermission('allocation', 'approve')) {
            Session::flash('error', 'Access denied: You do not have permission to approve allocations.');
            Response::redirect(url('warehouse/allocation/view/' . $id));
            return;
        }
        $allocation = WarehouseAllocation::find($id);
        if (!$allocation) {
            Session::flash('error', 'Allocation not found.');
            Response::redirect(url('warehouse/allocation'));
        }

        $userId = Session::get('user_id');
        WarehouseAllocation::updateStatus($id, 'APPROVED', $userId);

        Session::flash('success', "Allocation #{$allocation['allocation_number']} approved!");
        
        $referer = $_SERVER['HTTP_REFERER'] ?? url('warehouse/allocation');
        Response::redirect($referer);
    }

    public function pickAllocation(int $id): void {
        if (!hasPermission('allocation', 'pick')) {
            Session::flash('error', 'Access denied: You do not have permission to execute floor picking.');
            Response::redirect(url('warehouse/allocation/view/' . $id));
            return;
        }
        $allocation = WarehouseAllocation::find($id);
        if (!$allocation) {
            Session::flash('error', 'Allocation not found.');
            Response::redirect(url('warehouse/allocation'));
        }

        $pickedQty = $_POST['picked_qty'] ?? [];
        foreach ($allocation['items'] as $item) {
            $pQty = (int)($pickedQty[$item['id']] ?? $item['approved_qty']);
            AllocationItem::updateQuantities($item['id'], ['picked_qty' => $pQty]);
        }

        WarehouseAllocation::updateStatus($id, 'PICKING');

        Session::flash('success', "Picked quantities updated for Allocation #{$allocation['allocation_number']}");
        Response::redirect(url('warehouse/allocation/view/' . $id));
    }

    public function packAllocation(int $id): void {
        if (!hasPermission('allocation', 'pack')) {
            Session::flash('error', 'Access denied: You do not have permission to execute carton packing.');
            Response::redirect(url('warehouse/allocation/view/' . $id));
            return;
        }
        $allocation = WarehouseAllocation::find($id);
        if (!$allocation) {
            Session::flash('error', 'Allocation not found.');
            Response::redirect(url('warehouse/allocation'));
        }

        $packedQty = $_POST['packed_qty'] ?? [];
        foreach ($allocation['items'] as $item) {
            $pQty = (int)($packedQty[$item['id']] ?? $item['picked_qty']);
            AllocationItem::updateQuantities($item['id'], ['packed_qty' => $pQty]);
        }

        WarehouseAllocation::updateStatus($id, 'PACKING');

        Session::flash('success', "Packing confirmed for Allocation #{$allocation['allocation_number']}");
        Response::redirect(url('warehouse/allocation/view/' . $id));
    }

    public function dispatchAllocation(int $id): void {
        if (!hasPermission('allocation', 'dispatch')) {
            Session::flash('error', 'Access denied: You do not have permission to dispatch allocations.');
            Response::redirect(url('warehouse/allocation/view/' . $id));
            return;
        }
        $allocation = WarehouseAllocation::find($id);
        if (!$allocation) {
            Session::flash('error', 'Allocation not found.');
            Response::redirect(url('warehouse/allocation'));
        }

        $userId = Session::get('user_id');
        $dispatchedQty = $_POST['dispatched_qty'] ?? [];

        try {
            foreach ($allocation['items'] as $item) {
                $dQty = (float)($dispatchedQty[$item['id']] ?? $item['packed_qty'] ?? $item['approved_qty']);
                if ($dQty <= 0) continue;

                AllocationItem::updateQuantities($item['id'], ['dispatched_qty' => (int)$dQty]);

                $vId = (int)$item['variant_id'];
                $wId = (int)$allocation['warehouse_id'];
                $resBal = StockService::getBalance($vId, 'WAREHOUSE', $wId, 'RESERVED');
                $deductState = ($resBal >= $dQty) ? 'RESERVED' : 'AVAILABLE';

                StockService::decrease(
                    variantId: $vId,
                    locationType: 'WAREHOUSE',
                    locationId: $wId,
                    stockState: $deductState,
                    quantity: $dQty,
                    transactionType: 'ALLOCATION_DISPATCH',
                    referenceType: 'warehouse_allocations',
                    referenceId: $id,
                    userId: $userId,
                    notes: "Dispatched to {$allocation['destination_name']}"
                );
            }

            WarehouseAllocation::updateStatus($id, 'DISPATCHED', $userId);

            AuditService::log('WAREHOUSE', 'ALLOCATION_DISPATCH', 'warehouse_allocations', $id, null, [
                'number' => $allocation['allocation_number']
            ]);

            Session::flash('success', "Allocation #{$allocation['allocation_number']} dispatched successfully!");
            Response::redirect(url('warehouse/allocation/view/' . $id));
        } catch (Exception $e) {
            Session::flash('error', 'Dispatch failed: ' . $e->getMessage());
            Response::redirect(url('warehouse/allocation/view/' . $id));
        }
    }

    public function allocationOutwardList(): void {
        if (!hasPermission('allocation_outward', 'view') && !hasPermission('allocation', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $status = $_GET['status'] ?? '';
        $warehouseId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_GET['warehouse_id'] ?? 0);
        $search = trim($_GET['search'] ?? '');

        // Fetch outward dispatch history
        $sql = "SELECT ao.*, 
                       wa.allocation_number, wa.destination_type as alloc_dest_type,
                       w.name as warehouse_name, w.code as warehouse_code,
                       s.name as shop_name, sc.name as channel_name,
                       u.full_name as created_by_name
                FROM allocation_outwards ao
                JOIN warehouse_allocations wa ON ao.allocation_id = wa.id
                JOIN warehouses w ON ao.warehouse_id = w.id
                LEFT JOIN shops s ON ao.shop_id = s.id
                LEFT JOIN sales_channels sc ON ao.channel_id = sc.id
                LEFT JOIN users u ON ao.dispatched_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($status)) {
            $sql .= " AND ao.status = ?";
            $params[] = $status;
        }
        if ($warehouseId > 0) {
            $sql .= " AND ao.warehouse_id = ?";
            $params[] = $warehouseId;
        }
        if (!empty($search)) {
            $sql .= " AND (ao.outward_number LIKE ? OR wa.allocation_number LIKE ? OR ao.vehicle_number LIKE ? OR s.name LIKE ? OR sc.name LIKE ?)";
            $srch = '%' . $search . '%';
            $params = array_merge($params, [$srch, $srch, $srch, $srch, $srch]);
        }
        $sql .= " ORDER BY ao.created_at DESC";

        $outwards = Database::fetchAll($sql, $params);

        // Fetch approved & packed allocations ready for dispatch outward
        $appSql = "
            SELECT wa.*, 
                   w.name as warehouse_name,
                   s.name as shop_name,
                   sc.name as channel_name,
                   dw.name as dest_warehouse_name,
                   COALESCE(u.full_name, u.username) as requested_by_name,
                   (SELECT COALESCE(SUM(requested_qty), 0) FROM allocation_items WHERE allocation_id = wa.id) as total_qty,
                   (SELECT COUNT(*) FROM allocation_items WHERE allocation_id = wa.id) as total_items
            FROM warehouse_allocations wa
            JOIN warehouses w ON wa.warehouse_id = w.id
            LEFT JOIN shops s ON wa.shop_id = s.id
            LEFT JOIN sales_channels sc ON wa.channel_id = sc.id
            LEFT JOIN warehouses dw ON wa.destination_id = dw.id AND wa.destination_type = 'WAREHOUSE'
            LEFT JOIN users u ON wa.requested_by = u.id
            WHERE wa.status IN ('APPROVED', 'PACKING', 'PICKING', 'REQUESTED')";
        $appParams = [];
        if ($warehouseId > 0) {
            $appSql .= " AND wa.warehouse_id = ?";
            $appParams[] = $warehouseId;
        }
        $appSql .= " ORDER BY wa.created_at DESC";
        $approvedAllocations = Database::fetchAll($appSql, $appParams);

        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        Response::view('warehouse.allocation.outward', [
            'pageTitle'           => 'Warehouse Outward & Dispatch',
            'activeModule'        => 'warehouse',
            'activeMenu'          => 'wh-outward',
            'outwards'            => $outwards,
            'approvedAllocations' => $approvedAllocations,
            'warehouses'          => $warehouses,
            'scopedWarehouseId'   => $scopedWarehouseId,
            'warehouseId'         => $warehouseId,
            'currentStatus'       => $status,
            'search'              => $search,
            'breadcrumb'          => [
                ['label' => 'Warehouse', 'url' => url('warehouse/allocation')],
                ['label' => 'Outward & Dispatch'],
            ],
        ]);
    }

    public function storeAllocationOutward(): void {
        if (!hasPermission('allocation_outward', 'create') && !hasPermission('allocation', 'dispatch')) {
            Session::flash('error', 'Access denied: You do not have permission to create outward manifests.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/allocation/outward'));
        }

        $allocationId = (int)($_POST['allocation_id'] ?? 0);
        $vehicleNumber = strtoupper(trim($_POST['vehicle_number'] ?? ''));
        $driverName = trim($_POST['driver_name'] ?? '');
        $driverContact = preg_replace('/[^0-9]/', '', trim($_POST['driver_contact'] ?? ''));
        $transporterName = trim($_POST['transporter_name'] ?? '');
        $lrNumber = trim($_POST['lr_number'] ?? '');
        $totalBoxes = (int)($_POST['total_boxes'] ?? 0);
        $dispatchDate = !empty($_POST['dispatch_date']) ? $_POST['dispatch_date'] : date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');

        // Validation: LR Number and Notes are OPTIONAL, all others are REQUIRED
        if (!$allocationId) {
            Session::flash('error', 'Please select an approved allocation batch to dispatch.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        if (empty($vehicleNumber)) {
            Session::flash('error', 'Vehicle / Transport Number is required.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        if (empty($driverName)) {
            Session::flash('error', 'Driver Name is required.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        if (empty($driverContact) || strlen($driverContact) !== 10) {
            Session::flash('error', 'Driver Contact Number must be a valid 10-digit mobile number.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        if (empty($transporterName)) {
            Session::flash('error', 'Transporter / Logistics Name is required.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        if ($totalBoxes < 1) {
            Session::flash('error', 'Total Box / Carton Count must be at least 1.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        if (empty($dispatchDate)) {
            Session::flash('error', 'Dispatch Date & Time is required.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        $allocation = WarehouseAllocation::find($allocationId);
        if (!$allocation) {
            Session::flash('error', 'Selected allocation batch not found.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        try {
            $userId = Session::get('user_id');
            $items = AllocationItem::getByAllocationId($allocationId);
            $totalQty = 0;
            foreach ($items as $item) {
                $totalQty += (int)($item['packed_qty'] > 0 ? $item['packed_qty'] : $item['requested_qty']);
            }

            // Generate Outward Number
            $prefix = 'OUT-ALC-' . date('Ymd') . '-';
            $last = Database::fetchOne("SELECT outward_number FROM allocation_outwards WHERE outward_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
            $num = $last ? ((int)substr($last['outward_number'], -4) + 1) : 1;
            $outwardNumber = $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);

            Database::execute("
                INSERT INTO allocation_outwards 
                (outward_number, allocation_id, warehouse_id, destination_type, destination_id, shop_id, channel_id, total_boxes, total_quantity, vehicle_number, driver_name, driver_contact, transporter_name, lr_number, dispatched_by, dispatch_date, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DISPATCHED', ?)
            ", [
                $outwardNumber,
                $allocationId,
                $allocation['warehouse_id'],
                $allocation['destination_type'],
                $allocation['destination_id'] ?? 0,
                $allocation['shop_id'] ?? null,
                $allocation['channel_id'] ?? null,
                $totalBoxes,
                $totalQty,
                $vehicleNumber ?: null,
                $driverName ?: null,
                $driverContact ?: null,
                $transporterName ?: null,
                $lrNumber ?: null,
                $userId,
                $dispatchDate,
                $notes ?: null
            ]);

            $outwardId = (int)Database::lastInsertId();

            // Deduct stock from RESERVED state
            foreach ($items as $item) {
                $qtyToDeduct = (float)($item['packed_qty'] > 0 ? $item['packed_qty'] : $item['requested_qty']);
                if ($qtyToDeduct <= 0) continue;

                AllocationItem::updateQuantities($item['id'], ['dispatched_qty' => (int)$qtyToDeduct]);

                $vId = (int)$item['variant_id'];
                $wId = (int)$allocation['warehouse_id'];
                $resBal = StockService::getBalance($vId, 'WAREHOUSE', $wId, 'RESERVED');
                $deductState = ($resBal >= $qtyToDeduct) ? 'RESERVED' : 'AVAILABLE';

                StockService::decrease(
                    variantId: $vId,
                    locationType: 'WAREHOUSE',
                    locationId: $wId,
                    stockState: $deductState,
                    quantity: $qtyToDeduct,
                    transactionType: 'ALLOCATION_DISPATCH',
                    referenceType: 'allocation_outwards',
                    referenceId: $outwardId,
                    userId: $userId,
                    notes: "Outward Dispatch #{$outwardNumber} to {$allocation['destination_name']}"
                );
            }

            WarehouseAllocation::updateStatus($allocationId, 'DISPATCHED', $userId);

            AuditService::log('WAREHOUSE', 'ALLOCATION_OUTWARD', 'allocation_outwards', $outwardId, null, [
                'outward_number' => $outwardNumber, 'allocation_id' => $allocationId
            ]);

            Session::flash('success', "Warehouse Outward #{$outwardNumber} created and allocation dispatched successfully!");
            Response::redirect(url('warehouse/allocation/outward'));
        } catch (Exception $e) {
            Session::flash('error', 'Outward dispatch failed: ' . $e->getMessage());
            Response::redirect(url('warehouse/allocation/outward'));
        }
    }

    public function markOutwardDelivered(string $id): void {
        if (!hasPermission('allocation_outward', 'deliver')) {
            Session::flash('error', 'Access denied: You do not have permission to mark outwards delivered.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }
        $outwardId = (int)$id;
        $outward = Database::fetchOne("SELECT * FROM allocation_outwards WHERE id = ?", [$outwardId]);
        if (!$outward) {
            Session::flash('error', 'Outward record not found.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }

        try {
            $userId = Session::get('user_id') ?? 1;

            // Update outward status to DELIVERED
            Database::execute("UPDATE allocation_outwards SET status = 'DELIVERED', updated_at = NOW() WHERE id = ?", [$outwardId]);

            // Also update parent allocation status and credit destination if applicable
            if (!empty($outward['allocation_id'])) {
                $allocId = (int)$outward['allocation_id'];
                Database::execute("UPDATE warehouse_allocations SET status = 'DELIVERED', updated_at = NOW() WHERE id = ?", [$allocId]);

                $destShopId    = (int)($outward['shop_id'] ?? ($outward['destination_type'] === 'SHOP' ? $outward['destination_id'] : 0));
                $destWhId      = (int)($outward['destination_type'] === 'WAREHOUSE' ? $outward['destination_id'] : 0);
                $destChannelId = (int)($outward['channel_id'] ?? ($outward['destination_type'] === 'CHANNEL' ? $outward['destination_id'] : 0));

                // Auto-credit Shop inventory if delivered to a Shop and not yet credited
                if ($destShopId > 0) {
                    $alreadyCredited = Database::fetchOne(
                        "SELECT id FROM stock_transactions 
                         WHERE reference_type = 'warehouse_allocations' AND reference_id = ? AND location_type = 'SHOP' AND location_id = ?
                         LIMIT 1",
                        [$allocId, $destShopId]
                    );
                    if (!$alreadyCredited) {
                        require_once BASE_PATH . '/app/Services/ShopService.php';
                        ShopService::receiveShopAllocation($allocId, $destShopId, $userId, "Auto-credited upon Outward #{$outward['outward_number']} delivery confirmation.");
                    }
                } elseif ($destChannelId > 0) {
                    // Auto-credit Sales Channel (WhatsApp / Website) inventory
                    $items = AllocationItem::getByAllocationId($allocId);
                    foreach ($items as $item) {
                        $qty = (float)($item['dispatched_qty'] > 0 ? $item['dispatched_qty'] : ($item['packed_qty'] > 0 ? $item['packed_qty'] : $item['requested_qty']));
                        if ($qty > 0 && !empty($item['variant_id'])) {
                            AllocationItem::updateQuantities($item['id'], ['received_qty' => (int)$qty]);
                            StockService::increase(
                                variantId: (int)$item['variant_id'],
                                locationType: 'CHANNEL',
                                locationId: $destChannelId,
                                stockState: 'AVAILABLE',
                                quantity: $qty,
                                transactionType: 'TRANSFER_IN',
                                referenceType: 'warehouse_allocations',
                                referenceId: $allocId,
                                userId: $userId,
                                notes: "Credited to Channel #{$destChannelId} via Outward #{$outward['outward_number']}"
                            );
                        }
                    }
                    Database::execute("UPDATE warehouse_allocations SET status = 'COMPLETED', updated_at = NOW() WHERE id = ?", [$allocId]);
                } elseif ($destWhId > 0 && $destWhId !== (int)$outward['warehouse_id']) {
                    // Transfer between warehouses: credit destination warehouse
                    $items = AllocationItem::getByAllocationId($allocId);
                    foreach ($items as $item) {
                        $qty = (float)($item['dispatched_qty'] > 0 ? $item['dispatched_qty'] : ($item['packed_qty'] > 0 ? $item['packed_qty'] : $item['requested_qty']));
                        if ($qty > 0) {
                            StockService::increase(
                                variantId: (int)$item['variant_id'],
                                locationType: 'WAREHOUSE',
                                locationId: $destWhId,
                                stockState: 'AVAILABLE',
                                quantity: $qty,
                                transactionType: 'TRANSFER_IN',
                                referenceType: 'allocation_outwards',
                                referenceId: $outwardId,
                                userId: $userId,
                                notes: "Transferred from Warehouse #{$outward['warehouse_id']} via Outward #{$outward['outward_number']}"
                            );
                        }
                    }
                }
            }

            Session::flash('success', "Outward #{$outward['outward_number']} marked as DELIVERED and inventory updated successfully!");
            Response::redirect(url('warehouse/allocation/outward'));
        } catch (Exception $e) {
            Session::flash('error', 'Failed to update status: ' . $e->getMessage());
            Response::redirect(url('warehouse/allocation/outward'));
        }
    }

    public function printAllocationGatePass(string $id): void {
        if (!hasPermission('allocation_outward', 'gatepass') && !hasPermission('allocation_outward', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/allocation/outward'));
            return;
        }
        $allocationId = (int)$id;
        $allocation = WarehouseAllocation::find($allocationId);
        if (!$allocation) {
            Response::error(404, 'Allocation not found.');
            return;
        }

        $outward = Database::fetchOne("SELECT * FROM allocation_outwards WHERE allocation_id = ? ORDER BY id DESC LIMIT 1", [$allocationId]);
        $items = AllocationItem::getByAllocationId($allocationId);

        Response::viewOnly('warehouse.allocation.gatepass', [
            'allocation' => $allocation,
            'outward'    => $outward,
            'items'      => $items,
        ]);
    }

    // =========================================================================
    // 7. INTER-WAREHOUSE TRANSFERS — Person 2
    // =========================================================================

    public function transferList(): void {
        if (!hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $status = $_GET['status'] ?? '';
        $transfers = WarehouseTransfer::getAll(array_filter(['status' => $status]));

        Response::view('warehouse.transfers.index', [
            'pageTitle'    => 'Inter-Warehouse Stock Transfers',
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-transfers',
            'transfers'    => $transfers,
            'currentStatus'=> $status,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Stock Transfers'],
            ],
        ]);
    }

    public function createTransfer(): void {
        if (!hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/transfers'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $shops = function_exists('getScopedShops') ? getScopedShops() : Shop::getActive();
        $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
        $subcategories = Database::fetchAll("SELECT id, category_id, name FROM subcategories WHERE is_active = 1 ORDER BY name ASC");
        $subSubcategories = Database::fetchAll("SELECT id, subcategory_id, name FROM sub_subcategories WHERE is_active = 1 ORDER BY name ASC");

        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, p.name as product_name, p.category_id, p.subcategory_id, p.sub_subcategory_id,
                    sz.name as size_name, c.name as color_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC"
        );

        $destinationWarehouses = Warehouse::getAll(['is_active' => 1, 'unscoped' => true]);

        Response::view('warehouse.transfers.create', [
            'pageTitle'              => 'Create Stock Transfer',
            'activeModule'           => 'warehouse',
            'activeMenu'             => 'wh-transfers',
            'warehouses'             => $warehouses,
            'destinationWarehouses'  => $destinationWarehouses,
            'scopedWarehouseId'      => $scopedWarehouseId,
            'shops'                  => $shops,
            'categories'             => $categories,
            'subcategories'          => $subcategories,
            'subSubcategories'       => $subSubcategories,
            'variants'               => $variants,
            'breadcrumb'             => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Transfers', 'url' => url('warehouse/transfers')],
                ['label' => 'Create Transfer'],
            ],
        ]);
    }

    public function storeTransfer(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/transfers/create'));
            return;
        }

        if (!hasPermission('allocation', 'create') && !hasPermission('stock', 'adjust') && !hasPermission('warehouse', 'edit') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/transfers'));
            return;
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;

        $srcTarget = $_POST['source_target'] ?? '';
        if (str_starts_with($srcTarget, 'SHOP_')) {
            $srcType = 'SHOP';
            $srcId = (int)substr($srcTarget, 5);
        } elseif (str_starts_with($srcTarget, 'WAREHOUSE_')) {
            $srcType = 'WAREHOUSE';
            $srcId = (int)substr($srcTarget, 10);
        } else {
            $srcType = $_POST['source_type'] ?? 'WAREHOUSE';
            $srcId = (int)($_POST['source_id'] ?? 0);
        }

        if ($scopedWarehouseId !== null && $srcType === 'WAREHOUSE') {
            $srcId = $scopedWarehouseId;
        }

        $destTarget = $_POST['destination_target'] ?? '';
        if (str_starts_with($destTarget, 'SHOP_')) {
            $destType = 'SHOP';
            $destId = (int)substr($destTarget, 5);
        } elseif (str_starts_with($destTarget, 'WAREHOUSE_')) {
            $destType = 'WAREHOUSE';
            $destId = (int)substr($destTarget, 10);
        } else {
            $destType = $_POST['destination_type'] ?? 'WAREHOUSE';
            $destId = (int)($_POST['destination_id'] ?? 0);
        }

        $notesRaw = trim($_POST['notes'] ?? '');
        $logistics = [
            'carrier_name'     => trim($_POST['carrier_name'] ?? ''),
            'vehicle_no'       => trim($_POST['vehicle_no'] ?? ''),
            'driver_phone'     => trim($_POST['driver_phone'] ?? ''),
            'dispatch_at'      => trim($_POST['dispatch_at'] ?? ''),
            'expected_arrival' => trim($_POST['expected_arrival'] ?? ''),
        ];
        $formattedNotes = $notesRaw . "\n[LOGISTICS]" . json_encode($logistics) . "[/LOGISTICS]";

        $variantIds = $_POST['variant_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];

        if ($srcId === $destId && $srcType === $destType) {
            Session::flash('error', 'Source and destination cannot be identical.');
            Response::redirect(url('warehouse/transfers/create'));
            return;
        }

        try {
            $transferNumber = WarehouseTransfer::generateNumber();
            $userId = Session::get('user_id');

            $transferId = WarehouseTransfer::create([
                'transfer_number'  => $transferNumber,
                'source_type'      => $srcType,
                'source_id'        => $srcId,
                'destination_type' => $destType,
                'destination_id'   => $destId,
                'requested_by'     => $userId,
                'status'           => 'REQUESTED',
                'notes'            => $formattedNotes
            ]);

            foreach ($variantIds as $idx => $vId) {
                $qty = (int)($quantities[$idx] ?? 0);
                if ($qty <= 0) continue;

                TransferItem::create([
                    'transfer_id' => $transferId,
                    'variant_id'  => (int)$vId,
                    'quantity'    => $qty,
                ]);
            }

            Session::flash('success', "Transfer Request #{$transferNumber} submitted!");
            Response::redirect(url('warehouse/transfers'));
        } catch (Exception $e) {
            Session::flash('error', 'Failed to create transfer: ' . $e->getMessage());
            Response::redirect(url('warehouse/transfers/create'));
        }
    }

    public function approveTransfer(int $id): void {
        if (!hasPermission('allocation', 'approve') && !hasPermission('stock', 'adjust') && !hasPermission('warehouse', 'edit')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/transfers'));
            return;
        }
        $transfer = WarehouseTransfer::find($id);
        if (!$transfer) {
            Session::flash('error', 'Transfer not found.');
            Response::redirect(url('warehouse/transfers'));
            return;
        }

        $userId = Session::get('user_id');
        WarehouseTransfer::updateStatus($id, 'IN_TRANSIT', $userId);

        Session::flash('success', "Transfer #{$transfer['transfer_number']} approved and set to IN_TRANSIT.");
        Response::redirect(url('warehouse/transfers'));
    }

    public function receiveTransfer(int $id): void {
        if (!hasPermission('allocation', 'dispatch') && !hasPermission('stock', 'adjust') && !hasPermission('inward', 'verify') && !hasPermission('inward', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/transfers'));
            return;
        }
        $transfer = WarehouseTransfer::find($id);
        if (!$transfer) {
            Session::flash('error', 'Transfer not found.');
            Response::redirect(url('warehouse/transfers'));
            return;
        }

        $userId = Session::get('user_id');

        try {
            foreach ($transfer['items'] as $item) {
                $rQty = (float)($item['quantity']);

                StockService::transfer(
                    variantId: (int)$item['variant_id'],
                    srcLocationType: $transfer['source_type'],
                    srcLocationId: (int)$transfer['source_id'],
                    destLocationType: $transfer['destination_type'],
                    destLocationId: (int)$transfer['destination_id'],
                    quantity: $rQty,
                    referenceType: 'warehouse_transfers',
                    referenceId: $id,
                    userId: $userId,
                    notes: "Inter-Warehouse Transfer #{$transfer['transfer_number']}"
                );

                TransferItem::updateReceivedQty($item['id'], (int)$rQty, 0);
            }

            WarehouseTransfer::updateStatus($id, 'RECEIVED', $userId);

            Session::flash('success', "Transfer #{$transfer['transfer_number']} received and stock credited!");
            Response::redirect(url('warehouse/transfers'));
        } catch (Exception $e) {
            Session::flash('error', 'Transfer receipt failed: ' . $e->getMessage());
            Response::redirect(url('warehouse/transfers'));
        }
    }

    // =========================================================================
    // 8. WAREHOUSE RETURNS & QC INSPECTION — Person 2
    // =========================================================================

    public function returnList(): void {
        if (!hasPermission('returns', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $filters = [
            'source_type'  => trim($_GET['source_type'] ?? 'ALL'),
            'warehouse_id' => $scopedWarehouseId ? (string)$scopedWarehouseId : trim($_GET['warehouse_id'] ?? 'ALL'),
            'status'       => trim($_GET['status'] ?? 'ALL'),
            'search'       => trim($_GET['search'] ?? '')
        ];

        $returns = WarehouseReturn::getAll($filters);
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        $stats = [
            'total_batches'    => count($returns),
            'whatsapp_batches' => count(array_filter($returns, fn($r) => ($r['source_type'] ?? '') === 'WHATSAPP')),
            'shop_batches'     => count(array_filter($returns, fn($r) => ($r['source_type'] ?? '') === 'SHOP')),
            'website_batches'  => count(array_filter($returns, fn($r) => ($r['source_type'] ?? '') === 'WEBSITE')),
            'total_units'      => array_sum(array_column($returns, 'total_returned_qty'))
        ];

        Response::view('warehouse.returns.index', [
            'pageTitle'          => 'Warehouse Returns & Quality Control',
            'activeModule'       => 'warehouse',
            'activeMenu'         => 'wh-returns',
            'returns'            => $returns,
            'warehouses'         => $warehouses,
            'scopedWarehouseId'  => $scopedWarehouseId,
            'filters'            => $filters,
            'stats'              => $stats,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Returns & QC'],
            ],
        ]);
    }

    public function receiveReturn(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/returns'));
            return;
        }

        if (!hasPermission('returns', 'receive') && !hasPermission('returns', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/returns'));
            return;
        }

        $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
        $srcType = $_POST['source_type'] ?? 'SHOP';
        $srcId = (int)($_POST['source_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $variantIds = $_POST['variant_ids'] ?? [];
        $returnedQty = $_POST['returned_qty'] ?? [];

        if (!$warehouseId || empty($variantIds)) {
            Session::flash('error', 'Please select warehouse and returned items.');
            Response::redirect(url('warehouse/returns'));
            return;
        }

        try {
            $returnNumber = WarehouseReturn::generateNumber();

            $returnId = WarehouseReturn::create([
                'return_number' => $returnNumber,
                'source_type'   => $srcType,
                'source_id'     => $srcId,
                'warehouse_id'  => $warehouseId,
                'reason'        => $reason,
                'status'        => 'RECEIVED',
            ]);

            foreach ($variantIds as $idx => $vId) {
                $qty = (int)($returnedQty[$idx] ?? 0);
                if ($qty <= 0) continue;

                WarehouseReturnItem::create([
                    'warehouse_return_id' => $returnId,
                    'variant_id'          => (int)$vId,
                    'returned_qty'        => $qty,
                    'good_qty'            => $qty,
                    'classification'      => 'GOOD'
                ]);
            }

            Session::flash('success', "Return receipt #{$returnNumber} logged. Pending QC classification.");
            Response::redirect(url('warehouse/returns/inspect/' . $returnId));
        } catch (Exception $e) {
            Session::flash('error', 'Failed to log return: ' . $e->getMessage());
            Response::redirect(url('warehouse/returns'));
        }
    }

    public function inspectReturn(int $id): void {
        if (!hasPermission('returns', 'inspect') && !hasPermission('returns', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/returns'));
            return;
        }
        $return = WarehouseReturn::find($id);
        if (!$return) {
            Session::flash('error', 'Return record not found.');
            Response::redirect(url('warehouse/returns'));
            return;
        }

        Response::view('warehouse.returns.inspect', [
            'pageTitle'    => 'Inspect Return #' . $return['return_number'],
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-returns',
            'return'       => $return,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Returns', 'url' => url('warehouse/returns')],
                ['label' => 'Inspect ' . $return['return_number']],
            ],
        ]);
    }

    public function classifyReturn(int $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Response::redirect(url('warehouse/returns/inspect/' . $id));
            return;
        }

        if (!hasPermission('returns', 'classify') && !hasPermission('returns', 'inspect')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/returns'));
            return;
        }

        $return = WarehouseReturn::find($id);
        if (!$return) {
            Session::flash('error', 'Return record not found.');
            Response::redirect(url('warehouse/returns'));
            return;
        }

        $userId = Session::get('user_id');
        $goodQty       = $_POST['good_qty']      ?? [];
        $damagedQty    = $_POST['damaged_qty']   ?? [];
        $classification = $_POST['classification'] ?? [];

        try {
            foreach ($return['items'] as $item) {
                $itemId    = $item['id'];
                $returned  = (int)$item['returned_qty'];
                $class  = $classification[$itemId] ?? 'GOOD';
                $g      = max(0, min($returned, (int)($goodQty[$itemId] ?? 0)));
                $d      = max(0, (int)($damagedQty[$itemId] ?? 0));
                $df     = 0;

                if ($class === 'GOOD') {
                    $g  = $returned;
                    $d  = 0;
                    $df = 0;
                } else {
                    $nonGood = max(0, $returned - $g);
                    if ($d < $nonGood) {
                        $d = $nonGood;
                    }
                    $d = max(0, $d);

                    if ($class === 'DEFECTIVE' || $class === 'WRONG_SIZE') {
                        $df = $d;
                        $d  = 0;
                    }
                }

                WarehouseReturnItem::updateClassification($itemId, [
                    'good_qty'       => $g,
                    'damaged_qty'    => $d,
                    'defective_qty'  => $df,
                    'classification' => $class,
                ]);

                if ($g > 0) {
                    StockService::increase(
                        variantId: (int)$item['variant_id'],
                        locationType: 'WAREHOUSE',
                        locationId: (int)$return['warehouse_id'],
                        stockState: 'AVAILABLE',
                        quantity: $g,
                        transactionType: 'RETURN_GOOD',
                        referenceType: 'warehouse_returns',
                        referenceId: $id,
                        userId: $userId,
                        notes: "Return QC (Good) #{$return['return_number']}"
                    );
                }

                if ($d > 0) {
                    StockService::increase(
                        variantId: (int)$item['variant_id'],
                        locationType: 'WAREHOUSE',
                        locationId: (int)$return['warehouse_id'],
                        stockState: 'DAMAGED',
                        quantity: $d,
                        transactionType: 'RETURN_DAMAGED',
                        referenceType: 'warehouse_returns',
                        referenceId: $id,
                        userId: $userId,
                        notes: "Return QC (Damaged/Scrap) #{$return['return_number']}"
                    );
                }

                if ($df > 0) {
                    $reworkState = ($class === 'WRONG_SIZE') ? 'RETURNED' : 'DAMAGED';
                    StockService::increase(
                        variantId: (int)$item['variant_id'],
                        locationType: 'WAREHOUSE',
                        locationId: (int)$return['warehouse_id'],
                        stockState: $reworkState,
                        quantity: $df,
                        transactionType: 'RETURN_DAMAGED',
                        referenceType: 'warehouse_returns',
                        referenceId: $id,
                        userId: $userId,
                        notes: "Return QC ({$class}) #{$return['return_number']}"
                    );
                }
            }

            WarehouseReturn::updateStatus($id, 'CLASSIFIED', $userId);

            Session::flash('success', "Return #{$return['return_number']} classified and inventory credited successfully.");
            Response::redirect(url('warehouse/returns'));
        } catch (Exception $e) {
            Session::flash('error', 'Classification failed: ' . $e->getMessage());
            Response::redirect(url('warehouse/returns/inspect/' . $id));
        }
    }

    // =========================================================================
    // 9. QR LABELS & BARCODES GENERATOR — Person 2
    // =========================================================================

    public function labels(): void {
        if (!hasPermission('wh_labels', 'view') && !hasPermission('stock', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $categories = Database::fetchAll("SELECT id, name FROM warehouse_categories WHERE is_active = 1 ORDER BY name ASC");
        if (empty($categories)) {
            $categories = Database::fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
        }
        $subcategories = Database::fetchAll("SELECT id, category_id, name FROM warehouse_subcategories WHERE is_active = 1 ORDER BY name ASC");
        if (empty($subcategories)) {
            $subcategories = Database::fetchAll("SELECT id, category_id, name FROM subcategories WHERE is_active = 1 ORDER BY name ASC");
        }
        $subSubcategories = Database::fetchAll("SELECT id, subcategory_id, name FROM warehouse_sub_subcategories WHERE is_active = 1 ORDER BY name ASC");
        if (empty($subSubcategories)) {
            $subSubcategories = Database::fetchAll("SELECT id, subcategory_id, name FROM sub_subcategories WHERE is_active = 1 ORDER BY name ASC");
        }
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC");

        // Brands list for Brand dropdown
        $brands = Database::fetchAll("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC");

        // Active warehouse stock variants with real-time available balances
        $stockVariants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code as barcode, pv.mrp, pv.purchase_price, pv.production_cost, pv.selling_price,
                    pv.qr_type, pv.series_start_code, pv.series_end_code, pv.pack_quantity,
                    COALESCE(pv.is_complementary, p.is_complementary, 0) as is_complementary,
                    p.id as product_id, p.name as product_name, p.code as product_code, p.catalog_code, p.qr_code_prefix,
                    p.brand_id, b.name as brand_name,
                    p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                    p.category_code, p.subcategory_code, p.sub_subcategory_code,
                    p.source_type, p.warehouse_supplier_id as seller_id, s.name as seller_name, s.company as seller_company,
                    sz.name as size_name, c.name as color_name,
                    COALESCE(cat.name, c_old.name, '') as category_name, 
                    COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                    COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'WAREHOUSE'
                    ), 0) as available_qty
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
             LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
             LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        // Third-Party variants only (for Tab 2: Third-Party Buy Products QR & Stock Inward)
        $thirdPartyVariants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code as barcode, pv.mrp, pv.purchase_price, pv.production_cost, pv.selling_price,
                    pv.qr_type, pv.series_start_code, pv.series_end_code, pv.pack_quantity,
                    COALESCE(pv.is_complementary, p.is_complementary, 0) as is_complementary,
                    p.id as product_id, p.name as product_name, p.code as product_code, p.catalog_code, p.qr_code_prefix,
                    p.brand_id, COALESCE(b.name, s.company, s.name, 'Third-Party Brand') as brand_name,
                    p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                    p.category_code, p.subcategory_code, p.sub_subcategory_code,
                    p.source_type, p.warehouse_supplier_id as seller_id, s.name as seller_name, s.company as seller_company,
                    sz.name as size_name, c.name as color_name,
                    COALESCE(cat.name, c_old.name, '') as category_name, 
                    COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                    COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'WAREHOUSE'
                    ), 0) as available_qty
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
             LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
             LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
               AND p.warehouse_supplier_id IS NOT NULL
             ORDER BY brand_name ASC, p.name ASC, pv.sku ASC"
        );

        // External Purchase Orders
        $purchaseOrders = Database::fetchAll(
            "SELECT po.id, po.po_number, po.status, po.total_amount, po.created_at, s.name as seller_name, s.company as seller_company,
                    COALESCE(pi_agg.item_count, 0) as item_count,
                    COALESCE(pi_agg.total_qty, 0) as total_qty
             FROM purchase_orders po
             JOIN warehouse_suppliers s ON po.warehouse_supplier_id = s.id
             LEFT JOIN (
                 SELECT purchase_order_id, COUNT(id) as item_count, SUM(quantity) as total_qty
                 FROM purchase_items
                 GROUP BY purchase_order_id
             ) pi_agg ON pi_agg.purchase_order_id = po.id
             ORDER BY po.id DESC LIMIT 50"
        );

        // Third-Party Sellers
        $sellers = Database::fetchAll(
            "SELECT id, name, company, phone, email
             FROM warehouse_suppliers
             WHERE is_active = 1
             ORDER BY name ASC"
        );

        // Customer & Shop Returns (for Tab 1: Customer Returns & RMA)
        $returns = Database::fetchAll(
            "SELECT wr.id, wr.return_number, wr.source_type, wr.status, wr.created_at,
                    COALESCE(s.name, wr.source_type) as source_name
             FROM warehouse_returns wr
             LEFT JOIN shops s ON wr.source_type = 'SHOP' AND wr.source_id = s.id
             ORDER BY wr.id DESC LIMIT 50"
        );

        $classifiedReturns = Database::fetchAll(
            "SELECT wri.id, wri.warehouse_return_id, wri.variant_id, wri.good_qty, wri.damaged_qty, wri.defective_qty, wri.classification,
                    wr.return_number, p.name as product_name, pv.sku, pv.mrp, pv.selling_price,
                    sz.name as size_name, c.name as color_name,
                    COALESCE(cat.name, c_old.name, '') as category_name, 
                    COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                    COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name
             FROM warehouse_return_items wri
             JOIN warehouse_returns wr ON wri.warehouse_return_id = wr.id
             JOIN product_variants pv ON wri.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
             LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
             LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
             LEFT JOIN categories c_old ON p.category_id = c_old.id
             LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
             LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             ORDER BY wri.id DESC LIMIT 50"
        );

        Response::view('warehouse.labels.index', [
            'pageTitle'         => 'Warehouse QR Labels & Printing Suite',
            'activeModule'      => 'warehouse',
            'activeMenu'        => 'wh-labels',
            'categories'        => $categories,
            'subcategories'     => $subcategories,
            'subSubcategories'  => $subSubcategories,
            'warehouses'        => $warehouses,
            'brands'            => $brands,
            'stockVariants'     => $stockVariants,
            'variants'          => $stockVariants,
            'thirdPartyVariants'=> $thirdPartyVariants,
            'purchaseOrders'    => $purchaseOrders,
            'sellers'           => $sellers,
            'returns'           => $returns,
            'classifiedReturns' => $classifiedReturns,
            'breadcrumb'        => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'QR Labels & Printing Suite'],
            ],
        ]);
    }

    public function ajaxLabelSourceData(string $type, string $id): void {
        if (!hasPermission('wh_labels', 'view') && !hasPermission('stock', 'view') && !hasPermission('warehouse', 'view')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        $id = (int)$id;
        $items = [];

        if ($type === 'stock') {
            $variant = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                        p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        c.name as category_name, subcat.name as subcategory_name, subsubcat.name as sub_subcategory_name,
                        sz.name as size_name, col.name as color_name,
                        COALESCE((
                            SELECT SUM(sb.quantity)
                            FROM stock_balances sb
                            WHERE sb.variant_id = pv.id
                              AND sb.stock_state = 'AVAILABLE'
                              AND sb.location_type = 'WAREHOUSE'
                        ), 0) as available_qty
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN subcategories subcat ON p.subcategory_id = subcat.id
                 LEFT JOIN sub_subcategories subsubcat ON p.sub_subcategory_id = subsubcat.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 WHERE pv.id = ?",
                [$id]
            );
            if ($variant) {
                $catH = implode(' › ', array_filter([$variant['category_name'] ?? '', $variant['subcategory_name'] ?? '', $variant['sub_subcategory_name'] ?? '']));
                $items[] = [
                    'variant_id'         => $variant['id'],
                    'product_name'       => $variant['product_name'],
                    'sku'                => $variant['sku'],
                    'size_name'          => $variant['size_name'] ?? 'Free',
                    'color_name'         => $variant['color_name'] ?? 'Std',
                    'mrp'                => (float)($variant['mrp'] > 0 ? $variant['mrp'] : $variant['selling_price']),
                    'selling_price'      => (float)($variant['selling_price'] > 0 ? $variant['selling_price'] : $variant['mrp']),
                    'quantity'           => max(1, (int)$variant['available_qty']),
                    'available_qty'      => (int)$variant['available_qty'],
                    'tag_prefix'         => $variant['qr_code_prefix'] ?? 'WMF',
                    'category_name'      => $variant['category_name'] ?? 'Apparel',
                    'subcategory_name'   => $variant['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $variant['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        } elseif ($type === 'po' || $type === 'tp_purchase') {
            $poItems = PurchaseItem::getByPurchaseOrder($id);
            foreach ($poItems as $pi) {
                $catH = implode(' › ', array_filter([$pi['category_name'] ?? '', $pi['subcategory_name'] ?? '', $pi['sub_subcategory_name'] ?? '']));
                $items[] = [
                    'variant_id'         => $pi['variant_id'],
                    'product_name'       => $pi['product_name'],
                    'sku'                => $pi['sku'],
                    'size_name'          => $pi['size_name'] ?? 'Free',
                    'color_name'         => $pi['color_name'] ?? 'Std',
                    'mrp'                => (float)($pi['mrp'] > 0 ? $pi['mrp'] : ($pi['unit_price'] * 1.5)),
                    'selling_price'      => (float)($pi['unit_price'] > 0 ? $pi['unit_price'] : 0),
                    'quantity'           => (int)$pi['quantity'],
                    'available_qty'      => (int)$pi['quantity'],
                    'tag_prefix'         => 'TP',
                    'category_name'      => $pi['category_name'] ?? 'External Purchase',
                    'subcategory_name'   => $pi['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $pi['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                    'batch_lot_no'       => $pi['batch_lot_no'] ?? '',
                    'expiry_date'        => $pi['expiry_date'] ?? '',
                ];
            }
        } elseif ($type === 'batch') {
            $batchItems = ProductionBatch::getItems($id);
            foreach ($batchItems as $bi) {
                $catH = implode(' › ', array_filter([$bi['category_name'] ?? '', $bi['subcategory_name'] ?? '', $bi['sub_subcategory_name'] ?? '']));
                $items[] = [
                    'variant_id'         => $bi['variant_id'],
                    'product_name'       => $bi['product_name'],
                    'sku'                => $bi['sku'],
                    'size_name'          => $bi['size_name'] ?? 'Free',
                    'color_name'         => $bi['color_name'] ?? 'Std',
                    'mrp'                => (float)($bi['mrp'] > 0 ? $bi['mrp'] : $bi['selling_price']),
                    'selling_price'      => (float)($bi['selling_price'] > 0 ? $bi['selling_price'] : $bi['mrp']),
                    'quantity'           => (int)$bi['quantity'],
                    'available_qty'      => (int)$bi['quantity'],
                    'tag_prefix'         => 'WMF',
                    'category_name'      => $bi['category_name'] ?? 'Apparel',
                    'subcategory_name'   => $bi['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $bi['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        } elseif ($type === 'return' || $type === 'returns') {
            $retItems = WarehouseReturnItem::getByReturnId($id);
            foreach ($retItems as $ri) {
                $prodPrefix = !empty($ri['qr_code_prefix']) ? trim($ri['qr_code_prefix']) : '';
                if (empty($prodPrefix) && (!empty($ri['category_code']) || !empty($ri['subcategory_code']) || !empty($ri['sub_subcategory_code']))) {
                    $prodPrefix = strtoupper(($ri['category_code'] ?? '') . ($ri['subcategory_code'] ?? '') . ($ri['sub_subcategory_code'] ?? ''));
                }
                if (empty($prodPrefix)) {
                    $catHierarchy = array_filter([$ri['category_name'] ?? '', $ri['subcategory_name'] ?? '', $ri['sub_subcategory_name'] ?? '']);
                    if (!empty($catHierarchy)) {
                        $abbr = '';
                        foreach ($catHierarchy as $catPart) {
                            $words = preg_split('/\s+/', trim($catPart));
                            if (!empty($words[0])) { $abbr .= strtoupper($words[0][0]); }
                        }
                        $prodPrefix = $abbr;
                    }
                }
                if (empty($prodPrefix)) $prodPrefix = 'WMF';
                $retTagPrefix = (strlen($prodPrefix) >= 2) ? ('R' . substr($prodPrefix, 1)) : 'R';
                $catH = implode(' › ', array_filter([$ri['category_name'] ?? '', $ri['subcategory_name'] ?? '', $ri['sub_subcategory_name'] ?? '']));

                $items[] = [
                    'variant_id'         => $ri['variant_id'],
                    'product_name'       => $ri['product_name'],
                    'sku'                => $ri['sku'],
                    'size_name'          => $ri['size_name'] ?? 'Free',
                    'color_name'         => $ri['color_name'] ?? 'Std',
                    'mrp'                => (float)($ri['mrp'] > 0 ? $ri['mrp'] : $ri['selling_price']),
                    'selling_price'      => (float)($ri['selling_price'] > 0 ? $ri['selling_price'] : $ri['mrp']),
                    'quantity'           => max(1, (int)$ri['returned_qty']),
                    'classification'     => $ri['classification'] ?? 'GOOD',
                    'tag_prefix'         => $retTagPrefix,
                    'category_name'      => $ri['category_name'] ?? 'Customer Return',
                    'subcategory_name'   => $ri['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $ri['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        }

        Response::jsonSuccess("Items fetched", ['items' => $items, 'type' => $type, 'id' => $id]);
    }

    public function generateLabels(): void {
        if (!hasPermission('wh_labels', 'create') && !hasPermission('wh_labels', 'print') && !hasPermission('wh_labels', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/labels'));
            return;
        }
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $sourceType = trim($_POST['source_type'] ?? 'stock');
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $copies = max(1, min(500, (int)($_POST['copies'] ?? 10)));
        $tagType = trim($_POST['tag_type'] ?? 'GOOD');
        $reason = urlencode(trim($_POST['reason'] ?? ''));

        Response::redirect(url("warehouse/labels?source_type={$sourceType}&source_id={$sourceId}&variant_id={$variantId}&tag_type={$tagType}&copies={$copies}&reason={$reason}"));
    }

    /**
     * Confirm Stickered Third-Party Products & Inward to Warehouse Inventory (Stock Update)
     */
    public function confirmStickeredInward(): void {
        if (!hasPermission('inward', 'verify') && !hasPermission('inward', 'create') && !hasPermission('wh_labels', 'create') && !hasPermission('purchases', 'receive')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        $variantId   = (int)($_POST['variant_id'] ?? 0);
        $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
        $quantity    = (float)($_POST['quantity'] ?? 0);
        $sellerId    = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
        $poId        = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : (!empty($_POST['po_id']) ? (int)$_POST['po_id'] : null);
        $tagCodes    = !empty($_POST['tag_codes']) ? (is_array($_POST['tag_codes']) ? $_POST['tag_codes'] : json_decode($_POST['tag_codes'], true)) : [];
        $notes       = trim($_POST['notes'] ?? '');

        if ($variantId <= 0) {
            Response::json(['success' => false, 'message' => 'Please select a valid product variant.'], 400);
            return;
        }

        if ($quantity <= 0) {
            Response::json(['success' => false, 'message' => 'Stickered quantity to inward must be at least 1 unit.'], 400);
            return;
        }

        $scopedWhId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        if ($scopedWhId !== null) {
            $warehouseId = $scopedWhId;
        } elseif ($warehouseId <= 0) {
            $defaultWh = Database::fetchOne("SELECT id FROM warehouses WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
            $warehouseId = $defaultWh ? (int)$defaultWh['id'] : 1;
        }

        $variant = Database::fetchOne(
            "SELECT pv.*, p.name as product_name, p.code as product_code,
                    sz.name as size_name, c.name as color_name,
                    cat.name as category_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.id = ?",
            [$variantId]
        );

        if (!$variant) {
            Response::json(['success' => false, 'message' => 'Selected product variant was not found.'], 404);
            return;
        }

        $warehouse = Database::fetchOne("SELECT * FROM warehouses WHERE id = ?", [$warehouseId]);
        $whName = $warehouse['name'] ?? ('Warehouse #' . $warehouseId);

        $userId = Session::get('user_id') ?: 1;
        $tagSummary = !empty($tagCodes) ? (is_array($tagCodes) ? implode(', ', array_slice($tagCodes, 0, 5)) . (count($tagCodes) > 5 ? '... (total ' . count($tagCodes) . ')' : '') : (string)$tagCodes) : 'QR Stickers Generated';

        $inwardNote = !empty($notes) ? $notes : "Third-Party Stickered QR Inward (+{$quantity} pcs) for SKU {$variant['sku']}. Tags: {$tagSummary}";

        try {
            // Central Stock Service safely increases AVAILABLE stock in stock_balances with audit log
            StockService::increase(
                $variantId,
                'WAREHOUSE',
                $warehouseId,
                'AVAILABLE',
                $quantity,
                'PURCHASE_INWARD',
                'THIRD_PARTY_STICKERED',
                $poId ?: $sellerId,
                $userId,
                $inwardNote
            );

            // Fetch new real-time available stock balance
            $newBalance = Database::fetchOne(
                "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                [$variantId, $warehouseId]
            )['quantity'] ?? $quantity;

            $totalWarehouseStock = Database::fetchOne(
                "SELECT COALESCE(SUM(quantity), 0) as total FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND stock_state = 'AVAILABLE'",
                [$variantId]
            )['total'] ?? $newBalance;

            AuditService::log('warehouse', 'inward', 'stock_balances', $variantId, null, [
                'action'       => 'THIRD_PARTY_STICKERED_INWARD',
                'variant_id'   => $variantId,
                'sku'          => $variant['sku'],
                'quantity'     => $quantity,
                'warehouse_id' => $warehouseId,
                'warehouse'    => $whName,
                'new_balance'  => $newBalance,
            ]);

            $successMsg = "Successfully inwarded {$quantity} stickered unit(s) of {$variant['product_name']} ({$variant['sku']}) into {$whName}! Current available stock: {$newBalance} pcs.";

            // If regular form POST (non-AJAX)
            if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false && empty($_POST['is_ajax'])) {
                Session::flash('success', $successMsg);
                Response::redirect(url('warehouse/labels?source_type=tp_purchase&variant_id=' . $variantId));
                return;
            }

            Response::json([
                'success'               => true,
                'message'               => $successMsg,
                'inward_qty'            => (int)$quantity,
                'variant_id'            => $variantId,
                'sku'                   => $variant['sku'],
                'product_name'          => $variant['product_name'],
                'warehouse_id'          => $warehouseId,
                'warehouse_name'        => $whName,
                'warehouse_stock'       => (int)$newBalance,
                'total_warehouse_stock' => (int)$totalWarehouseStock,
            ]);
        } catch (Exception $e) {
            error_log("confirmStickeredInward error: " . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Failed to inward inventory: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 10. DELIVERY PIPELINE — Person 2
    // =========================================================================

    public function deliveryList(): void {
        if (!hasPermission('allocation_outward', 'view') && !hasPermission('allocation', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $status = $_GET['status'] ?? '';
        $deliveries = Delivery::getPipeline(array_filter(['status' => $status]));

        Response::view('warehouse.delivery.index', [
            'pageTitle'    => 'Delivery & Dispatch Pipeline',
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-delivery',
            'deliveries'   => $deliveries,
            'currentStatus'=> $status,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Delivery Pipeline'],
            ],
        ]);
    }

    public function approveDelivery(int $id): void {
        if (!hasPermission('allocation', 'approve') && !hasPermission('allocation_outward', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/delivery'));
            return;
        }
        WarehouseAllocation::updateStatus($id, 'APPROVED', Session::get('user_id'));
        Session::flash('success', 'Delivery pass approved.');
        Response::redirect(url('warehouse/delivery'));
    }

    public function dispatchDelivery(int $id): void {
        if (!hasPermission('allocation', 'dispatch') && !hasPermission('allocation_outward', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/delivery'));
            return;
        }
        WarehouseAllocation::updateStatus($id, 'DISPATCHED', Session::get('user_id'));
        Session::flash('success', 'Delivery dispatched.');
        Response::redirect(url('warehouse/delivery'));
    }

    public function confirmDelivery(int $id): void {
        if (!hasPermission('allocation_outward', 'deliver') && !hasPermission('allocation', 'dispatch')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/delivery'));
            return;
        }
        WarehouseAllocation::updateStatus($id, 'COMPLETED', Session::get('user_id'));
        Session::flash('success', 'Delivery confirmed as completed!');
        Response::redirect(url('warehouse/delivery'));
    }

    // =========================================================================
    // 11. AJAX QUICK-ADD & DELETE HANDLERS — Person 2
    // =========================================================================

    public function ajaxAddWarehouse(): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('warehouse', 'create') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Warehouse name is required.'], 400);
            return;
        }

        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        try {
            if (empty($code)) {
                $num = 1;
                do {
                    $code = 'WH-' . str_pad($num, 2, '0', STR_PAD_LEFT);
                    $exists = Database::fetchOne("SELECT id FROM warehouses WHERE code = ?", [$code]);
                    if ($exists) {
                        $num++;
                    } else {
                        break;
                    }
                } while (true);
            } else {
                $exists = Database::fetchOne("SELECT id FROM warehouses WHERE code = ?", [$code]);
                if ($exists) {
                    Response::json(['success' => false, 'message' => "Warehouse code '{$code}' already exists."], 400);
                    return;
                }
            }

            Database::execute(
                "INSERT INTO warehouses (name, code, manager_name, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $code, $managerName ?: null, $phone ?: null, $address ?: null, $isActive]
            );
            $id = (int)Database::lastInsertId();

            AuditService::log('WAREHOUSE', 'QUICK_ADD_WAREHOUSE', 'warehouses', $id, null, ['name' => $name, 'code' => $code]);

            Response::json([
                'success'   => true,
                'message'   => 'Warehouse added successfully.',
                'warehouse' => ['id' => $id, 'name' => $name, 'code' => $code]
            ]);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteWarehouse(int $id): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('warehouse', 'delete') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        try {
            try {
                Database::execute("DELETE FROM warehouses WHERE id = ?", [$id]);
            } catch (Exception $ex) {
                Database::execute("UPDATE warehouses SET is_active = 0 WHERE id = ?", [$id]);
            }
            AuditService::log('WAREHOUSE', 'DELETE_WAREHOUSE', 'warehouses', $id);

            Response::json(['success' => true, 'message' => 'Warehouse deleted successfully.']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Failed to delete warehouse: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxAddShop(): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('warehouse', 'create') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Shop name is required.'], 400);
            return;
        }

        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        try {
            if (empty($code)) {
                $num = 1;
                do {
                    $code = 'SH-' . str_pad($num, 2, '0', STR_PAD_LEFT);
                    $exists = Database::fetchOne("SELECT id FROM shops WHERE code = ?", [$code]);
                    if ($exists) {
                        $num++;
                    } else {
                        break;
                    }
                } while (true);
            } else {
                $exists = Database::fetchOne("SELECT id FROM shops WHERE code = ?", [$code]);
                if ($exists) {
                    Response::json(['success' => false, 'message' => "Shop code '{$code}' already exists."], 400);
                    return;
                }
            }

            Database::execute(
                "INSERT INTO shops (name, code, manager_name, gst_number, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$name, $code, $managerName ?: null, $gstNumber ?: null, $phone ?: null, $address ?: null, $isActive]
            );
            $id = (int)Database::lastInsertId();

            AuditService::log('WAREHOUSE', 'QUICK_ADD_SHOP', 'shops', $id, null, ['name' => $name, 'code' => $code]);

            Response::json([
                'success' => true,
                'message' => 'Destination Shop added successfully.',
                'shop'    => ['id' => $id, 'name' => $name, 'code' => $code]
            ]);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteShop(int $id): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('warehouse', 'delete') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        try {
            try {
                Database::execute("DELETE FROM shops WHERE id = ?", [$id]);
            } catch (Exception $ex) {
                Database::execute("UPDATE shops SET is_active = 0 WHERE id = ?", [$id]);
            }
            AuditService::log('WAREHOUSE', 'DELETE_SHOP', 'shops', $id);

            Response::json(['success' => true, 'message' => 'Destination Shop deleted successfully.']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Failed to delete shop: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // WAREHOUSE & SHOP & SALES CHANNEL CRUD
    // ==========================================
    public function ajaxGetWarehouse(string $id): void {
        if (!hasPermission('warehouse', 'view')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        $id = (int)$id;
        $wh = Database::fetchOne("SELECT * FROM warehouses WHERE id = ?", [$id]);
        if ($wh) {
            Response::json(['success' => true, 'data' => $wh]);
        } else {
            Response::json(['success' => false, 'message' => 'Warehouse not found.'], 404);
        }
    }

    public function ajaxUpdateWarehouse(string $id = ''): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $id = (int)($id ?: ($_POST['id'] ?? 0));
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid Warehouse ID.'], 400);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Warehouse name is required.'], 400);
            return;
        }

        try {
            $exists = Database::fetchOne("SELECT id FROM warehouses WHERE code = ? AND id != ?", [$code, $id]);
            if ($exists) {
                Response::json(['success' => false, 'message' => "Warehouse code '{$code}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE warehouses SET name = ?, code = ?, manager_name = ?, phone = ?, address = ?, is_active = ? WHERE id = ?",
                [$name, $code, $managerName ?: null, $phone ?: null, $address ?: null, $isActive, $id]
            );

            AuditService::log('WAREHOUSE', 'UPDATE_WAREHOUSE', 'warehouses', $id, null, ['name' => $name, 'code' => $code]);

            Response::json(['success' => true, 'message' => 'Warehouse updated successfully.']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxGetShop(string $id): void {
        if (!hasPermission('warehouse', 'view')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        $id = (int)$id;
        $shop = Database::fetchOne("SELECT * FROM shops WHERE id = ?", [$id]);
        if ($shop) {
            Response::json(['success' => true, 'data' => $shop]);
        } else {
            Response::json(['success' => false, 'message' => 'Shop not found.'], 404);
        }
    }

    public function ajaxUpdateShop(string $id = ''): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $id = (int)($id ?: ($_POST['id'] ?? 0));
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid Shop ID.'], 400);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Shop name is required.'], 400);
            return;
        }

        try {
            $exists = Database::fetchOne("SELECT id FROM shops WHERE code = ? AND id != ?", [$code, $id]);
            if ($exists) {
                Response::json(['success' => false, 'message' => "Shop code '{$code}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE shops SET name = ?, code = ?, manager_name = ?, gst_number = ?, phone = ?, address = ?, is_active = ? WHERE id = ?",
                [$name, $code, $managerName ?: null, $gstNumber ?: null, $phone ?: null, $address ?: null, $isActive, $id]
            );

            AuditService::log('WAREHOUSE', 'UPDATE_SHOP', 'shops', $id, null, ['name' => $name, 'code' => $code]);

            Response::json(['success' => true, 'message' => 'Shop updated successfully.']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxAddChannel(): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('sales_channels', 'create') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Sales Channel name is required.'], 400);
            return;
        }

        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        try {
            if (empty($code)) {
                $num = 1;
                do {
                    $code = 'SC-' . str_pad($num, 2, '0', STR_PAD_LEFT);
                    $exists = Database::fetchOne("SELECT id FROM sales_channels WHERE code = ?", [$code]);
                    if ($exists) {
                        $num++;
                    } else {
                        break;
                    }
                } while (true);
            } else {
                $exists = Database::fetchOne("SELECT id FROM sales_channels WHERE code = ?", [$code]);
                if ($exists) {
                    Response::json(['success' => false, 'message' => "Sales Channel code '{$code}' already exists."], 400);
                    return;
                }
            }

            Database::execute(
                "INSERT INTO sales_channels (name, code, manager_name, phone, description, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $code, $managerName ?: null, $phone ?: null, $description ?: null, $isActive]
            );
            $id = (int)Database::lastInsertId();

            AuditService::log('WAREHOUSE', 'QUICK_ADD_CHANNEL', 'sales_channels', $id, null, ['name' => $name, 'code' => $code]);

            Response::json([
                'success' => true,
                'message' => 'Sales Channel added successfully.',
                'channel' => ['id' => $id, 'name' => $name, 'code' => $code]
            ]);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxGetChannel(string $id): void {
        if (!hasPermission('sales_channels', 'view') && !hasPermission('warehouse', 'view')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        $id = (int)$id;
        $chan = Database::fetchOne("SELECT * FROM sales_channels WHERE id = ?", [$id]);
        if ($chan) {
            Response::json(['success' => true, 'data' => $chan]);
        } else {
            Response::json(['success' => false, 'message' => 'Sales Channel not found.'], 404);
        }
    }

    public function ajaxUpdateChannel(string $id = ''): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('sales_channels', 'create') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $id = (int)($id ?: ($_POST['id'] ?? 0));
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid Sales Channel ID.'], 400);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Sales Channel name is required.'], 400);
            return;
        }

        try {
            $exists = Database::fetchOne("SELECT id FROM sales_channels WHERE code = ? AND id != ?", [$code, $id]);
            if ($exists) {
                Response::json(['success' => false, 'message' => "Sales Channel code '{$code}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE sales_channels SET name = ?, code = ?, manager_name = ?, phone = ?, description = ?, is_active = ? WHERE id = ?",
                [$name, $code, $managerName ?: null, $phone ?: null, $description ?: null, $isActive, $id]
            );

            AuditService::log('WAREHOUSE', 'UPDATE_CHANNEL', 'sales_channels', $id, null, ['name' => $name, 'code' => $code]);

            Response::json(['success' => true, 'message' => 'Sales Channel updated successfully.']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteChannel(string $id): void {
        if (!CSRF::validateRequest()) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        if (!hasPermission('sales_channels', 'delete') && !hasPermission('warehouse', 'edit')) {
            Response::json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $id = (int)$id;
        try {
            try {
                Database::execute("DELETE FROM sales_channels WHERE id = ?", [$id]);
            } catch (Exception $ex) {
                Database::execute("UPDATE sales_channels SET is_active = 0 WHERE id = ?", [$id]);
            }
            AuditService::log('WAREHOUSE', 'DELETE_CHANNEL', 'sales_channels', $id);

            Response::json(['success' => true, 'message' => 'Sales Channel deleted successfully.']);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => 'Failed to delete sales channel: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 11. WAREHOUSE CATALOG (Products, Styles & Third-Party Variants)
    // =========================================================================

    public function catalogList(): void {
        if (!hasPermission('wh_catalog', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $search = trim($_GET['search'] ?? '');
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $sellerId = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : null;
        $sourceType = trim($_GET['source_type'] ?? '');

        $sql = "SELECT p.*, c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name, b.name as brand_name,
                       s.name as seller_name, s.company as seller_company,
                       COALESCE(pv_summary.variant_count, 0) as variant_count,
                       COALESCE(pv_summary.min_cost_price, 0) as min_cost_price,
                       COALESCE(pv_summary.max_cost_price, 0) as max_cost_price,
                       COALESCE(pv_summary.min_selling_price, 0) as min_selling_price,
                       COALESCE(pv_summary.max_selling_price, 0) as max_selling_price,
                       COALESCE(pv_summary.min_mrp, 0) as min_mrp,
                       COALESCE(pv_summary.max_mrp, 0) as max_mrp,
                       COALESCE(pv_summary.total_warehouse_stock, 0) as total_warehouse_stock
                FROM products p
                LEFT JOIN warehouse_categories c ON p.warehouse_category_id = c.id
                LEFT JOIN warehouse_subcategories sc ON p.warehouse_subcategory_id = sc.id
                LEFT JOIN warehouse_sub_subcategories ssc ON p.warehouse_sub_subcategory_id = ssc.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
                LEFT JOIN (
                    SELECT pv.product_id,
                           COUNT(pv.id) as variant_count,
                           MIN(COALESCE(pv.purchase_price, pv.production_cost, 0)) as min_cost_price,
                           MAX(COALESCE(pv.purchase_price, pv.production_cost, 0)) as max_cost_price,
                           MIN(pv.selling_price) as min_selling_price,
                           MAX(pv.selling_price) as max_selling_price,
                           MIN(pv.mrp) as min_mrp,
                           MAX(pv.mrp) as max_mrp,
                           COALESCE(SUM(sb_sum.wh_stock), 0) as total_warehouse_stock
                    FROM product_variants pv
                    LEFT JOIN (
                        SELECT variant_id, SUM(quantity) as wh_stock
                        FROM stock_balances
                        WHERE stock_state = 'AVAILABLE' AND location_type = 'WAREHOUSE'
                        GROUP BY variant_id
                    ) sb_sum ON sb_sum.variant_id = pv.id
                    WHERE pv.is_active = 1
                    GROUP BY pv.product_id
                ) pv_summary ON pv_summary.product_id = p.id
                WHERE p.is_active = 1 AND (p.code NOT LIKE 'TP-%' OR p.code LIKE 'TP-STY-%')";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.catalog_code LIKE ? OR p.material LIKE ? OR c.name LIKE ? OR sc.name LIKE ? OR pv.sku LIKE ?)";
            $s = '%' . $search . '%';
            $params = [$s, $s, $s, $s, $s, $s, $s];
        }

        if ($categoryId) {
            $sql .= " AND p.warehouse_category_id = ?";
            $params[] = $categoryId;
        }

        if ($sellerId) {
            $sql .= " AND p.warehouse_supplier_id = ?";
            $params[] = $sellerId;
        }

        if (!empty($sourceType)) {
            $sql .= " AND p.source_type = ?";
            $params[] = $sourceType;
        }

        $sql .= " ORDER BY p.id DESC";
        $products = Database::fetchAll($sql, $params);

        // Fetch categories, brands, suppliers, and active warehouses for admin catalog
        $categories = Database::fetchAll("SELECT * FROM warehouse_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $brands = Database::fetchAll("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $suppliers = Database::fetchAll(
            "SELECT DISTINCT ws.id, ws.name, ws.company, ws.phone 
             FROM warehouse_suppliers ws 
             INNER JOIN products p ON p.warehouse_supplier_id = ws.id 
             WHERE ws.is_active = 1 AND p.is_active = 1 AND (p.code NOT LIKE 'TP-%' OR p.code LIKE 'TP-STY-%')
             ORDER BY ws.name ASC"
        );
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        $productIds = array_column($products, 'id');
        $variantsByProduct = [];
        if (!empty($productIds)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $whStockClause = $scopedWarehouseId ? " AND sb.location_id = " . (int)$scopedWarehouseId : "";
            $allVariants = Database::fetchAll(
                "SELECT pv.*, s.name as size_name, s.code as size_code, col.name as color_name, col.hex_code as color_hex,
                        COALESCE((
                            SELECT SUM(sb.quantity) FROM stock_balances sb 
                            WHERE sb.variant_id = pv.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE' {$whStockClause}
                        ), 0) as current_stock
                 FROM product_variants pv
                 LEFT JOIN sizes s ON pv.size_id = s.id
                 LEFT JOIN colors col ON pv.color_id = col.id
                 WHERE pv.product_id IN ($placeholders) AND pv.is_active = 1
                 ORDER BY s.sort_order ASC, col.name ASC",
                $productIds
            );
            foreach ($allVariants as $v) {
                $variantsByProduct[$v['product_id']][] = $v;
            }
        }
        foreach ($products as &$p) {
            $p['variants'] = $variantsByProduct[$p['id']] ?? [];
        }
        unset($p);

        Response::view('warehouse.catalog.index', [
            'pageTitle'        => 'Warehouse Product Catalog & Third-Party Styles',
            'activeModule'     => 'warehouse',
            'activeMenu'       => 'wh-catalog',
            'products'         => $products,
            'categories'       => $categories,
            'brands'           => $brands,
            'suppliers'        => $suppliers,
            'selectedSellerId' => $sellerId,
            'warehouses'       => $warehouses,
            'search'           => $search,
            'categoryId'       => $categoryId,
            'sourceType'       => $sourceType,
            'breadcrumb'       => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Product Catalog'],
            ],
        ]);
    }

    // =========================================================================
    // WAREHOUSE CATEGORIES (AJAX)
    // =========================================================================

    public function ajaxCategories(): void {
        try {
            $categories = Database::fetchAll("SELECT id, name, code, slug FROM warehouse_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
            Response::json($categories);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddCategory(): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Main Category name is required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne("SELECT id, name, code, is_active FROM warehouse_categories WHERE name = ? OR slug = ?", [$name, $slug]);
            if ($existing) {
                Database::execute("UPDATE warehouse_categories SET is_active = 1, name = ?, updated_at = NOW() WHERE id = ?", [$name, $existing['id']]);
                Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name, 'code' => $existing['code'] ?? '']);
                return;
            }

            $maxSort = Database::fetchOne("SELECT MAX(sort_order) as m FROM warehouse_categories")['m'] ?? 0;
            
            $codeBase = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
            if (empty($codeBase)) $codeBase = 'CAT';
            $code = $codeBase;
            $counter = 1;
            while (Database::fetchOne("SELECT id FROM warehouse_categories WHERE code = ?", [$code])) {
                $code = $codeBase . $counter;
                $counter++;
            }

            Database::execute(
                "INSERT INTO warehouse_categories (name, code, slug, sort_order, is_active) VALUES (?, ?, ?, ?, 1)",
                [$name, $code, $slug, ((int)$maxSort + 1)]
            );
            $id = (int)Database::lastInsertId();
            Response::json(['success' => true, 'id' => $id, 'name' => $name, 'code' => $code]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating category: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxUpdateCategory(int $id): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Main Category name is required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne(
                "SELECT id, name FROM warehouse_categories WHERE (name = ? OR slug = ?) AND id != ?",
                [$name, $slug, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => "Category '{$existing['name']}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE warehouse_categories SET name = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                [$name, $slug, $id]
            );
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating category: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteCategory(int $id): void {
        try {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE (warehouse_category_id = ? OR category_id = ?) AND is_active = 1", [$id, $id])['c'] ?? 0;
            if ($count > 0) {
                Response::json(['success' => false, 'message' => "Cannot delete this category because {$count} product style(s) are linked to it."], 400);
                return;
            }

            Database::execute("UPDATE warehouse_categories SET is_active = 0 WHERE id = ?", [$id]);
            Database::execute("UPDATE warehouse_subcategories SET is_active = 0 WHERE category_id = ?", [$id]);
            Response::json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting category: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxSubcategories(int $categoryId): void {
        try {
            $subcats = Database::fetchAll(
                "SELECT id, name, slug, code FROM warehouse_subcategories WHERE category_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC",
                [$categoryId]
            );
            Response::json($subcats);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddSubcategory(): void {
        try {
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if ($categoryId <= 0 || empty($name)) {
                Response::json(['success' => false, 'message' => 'Category and Subcategory name are required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne("SELECT id, name, code, is_active FROM warehouse_subcategories WHERE category_id = ? AND (name = ? OR slug = ?)", [$categoryId, $name, $slug]);
            if ($existing) {
                Database::execute("UPDATE warehouse_subcategories SET is_active = 1, name = ?, updated_at = NOW() WHERE id = ?", [$name, $existing['id']]);
                Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name, 'code' => $existing['code'] ?? '']);
                return;
            }

            $maxSort = Database::fetchOne("SELECT MAX(sort_order) as m FROM warehouse_subcategories WHERE category_id = ?", [$categoryId])['m'] ?? 0;

            $codeBase = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
            if (empty($codeBase)) $codeBase = 'SUB';
            $code = $codeBase;
            $counter = 1;
            while (Database::fetchOne("SELECT id FROM warehouse_subcategories WHERE code = ?", [$code])) {
                $code = $codeBase . $counter;
                $counter++;
            }

            Database::execute(
                "INSERT INTO warehouse_subcategories (category_id, name, code, slug, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                [$categoryId, $name, $code, $slug, ((int)$maxSort + 1)]
            );
            $id = (int)Database::lastInsertId();
            Response::json(['success' => true, 'id' => $id, 'name' => $name, 'code' => $code]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxUpdateSubcategory(int $id): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Subcategory name is required.'], 400);
                return;
            }
            $subcat = Database::fetchOne("SELECT category_id FROM warehouse_subcategories WHERE id = ?", [$id]);
            if (!$subcat) {
                Response::json(['success' => false, 'message' => 'Subcategory not found.'], 404);
                return;
            }
            $categoryId = (int)$subcat['category_id'];
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne(
                "SELECT id, name FROM warehouse_subcategories WHERE category_id = ? AND (name = ? OR slug = ?) AND id != ?",
                [$categoryId, $name, $slug, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => "Subcategory '{$existing['name']}' already exists in this category."], 400);
                return;
            }

            Database::execute(
                "UPDATE warehouse_subcategories SET name = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                [$name, $slug, $id]
            );
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteSubcategory(int $id): void {
        try {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE (warehouse_subcategory_id = ? OR subcategory_id = ?) AND is_active = 1", [$id, $id])['c'] ?? 0;
            if ($count > 0) {
                Response::json(['success' => false, 'message' => "Cannot delete this subcategory because {$count} product style(s) are linked to it."], 400);
                return;
            }
            Database::execute("UPDATE warehouse_subcategories SET is_active = 0 WHERE id = ?", [$id]);
            Database::execute("UPDATE warehouse_sub_subcategories SET is_active = 0 WHERE subcategory_id = ?", [$id]);
            Response::json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxSubSubcategories(int $subcategoryId): void {
        try {
            $subSubcats = Database::fetchAll(
                "SELECT id, name, slug, code FROM warehouse_sub_subcategories WHERE subcategory_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC",
                [$subcategoryId]
            );
            Response::json($subSubcats);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function ajaxQuickAddSubSubcategory(): void {
        try {
            $subcategoryId = (int)($_POST['subcategory_id'] ?? 0);
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if ($subcategoryId <= 0 || empty($name)) {
                Response::json(['success' => false, 'message' => 'Subcategory and Sub-subcategory name are required.'], 400);
                return;
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne("SELECT id, name, code, is_active FROM warehouse_sub_subcategories WHERE subcategory_id = ? AND (name = ? OR slug = ?)", [$subcategoryId, $name, $slug]);
            if ($existing) {
                Database::execute("UPDATE warehouse_sub_subcategories SET is_active = 1, name = ?, updated_at = NOW() WHERE id = ?", [$name, $existing['id']]);
                Response::json(['success' => true, 'id' => (int)$existing['id'], 'name' => $name, 'code' => $existing['code'] ?? '']);
                return;
            }

            $maxSort = Database::fetchOne("SELECT MAX(sort_order) as m FROM warehouse_sub_subcategories WHERE subcategory_id = ?", [$subcategoryId])['m'] ?? 0;

            $codeBase = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
            if (empty($codeBase)) $codeBase = 'SSC';
            $code = $codeBase;
            $counter = 1;
            while (Database::fetchOne("SELECT id FROM warehouse_sub_subcategories WHERE code = ?", [$code])) {
                $code = $codeBase . $counter;
                $counter++;
            }

            Database::execute(
                "INSERT INTO warehouse_sub_subcategories (subcategory_id, name, code, slug, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                [$subcategoryId, $name, $code, $slug, ((int)$maxSort + 1)]
            );
            $id = (int)Database::lastInsertId();
            Response::json(['success' => true, 'id' => $id, 'name' => $name, 'code' => $code]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating sub-subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxUpdateSubSubcategory(int $id): void {
        try {
            $name = mb_strtoupper(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Sub-subcategory name is required.'], 400);
                return;
            }
            $subSubcat = Database::fetchOne("SELECT subcategory_id FROM warehouse_sub_subcategories WHERE id = ?", [$id]);
            if (!$subSubcat) {
                Response::json(['success' => false, 'message' => 'Sub-subcategory not found.'], 404);
                return;
            }
            $subcategoryId = (int)$subSubcat['subcategory_id'];
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $existing = Database::fetchOne(
                "SELECT id, name FROM warehouse_sub_subcategories WHERE subcategory_id = ? AND (name = ? OR slug = ?) AND id != ?",
                [$subcategoryId, $name, $slug, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => "Sub-subcategory '{$existing['name']}' already exists."], 400);
                return;
            }

            Database::execute(
                "UPDATE warehouse_sub_subcategories SET name = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                [$name, $slug, $id]
            );
            Response::json(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating sub-subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxDeleteSubSubcategory(int $id): void {
        try {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products WHERE (warehouse_sub_subcategory_id = ? OR sub_subcategory_id = ?) AND is_active = 1", [$id, $id])['c'] ?? 0;
            if ($count > 0) {
                Response::json(['success' => false, 'message' => "Cannot delete this sub-subcategory because {$count} product style(s) are linked to it."], 400);
                return;
            }
            Database::execute("UPDATE warehouse_sub_subcategories SET is_active = 0 WHERE id = ?", [$id]);
            Response::json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting sub-subcategory: ' . $e->getMessage()], 500);
        }
    }

    public function createCatalog(): void {
        if (!hasPermission('wh_catalog', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }
        $categories = Database::fetchAll("SELECT * FROM warehouse_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $brands = Database::fetchAll("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $sizes = Database::fetchAll("SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $colors = Database::fetchAll("SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC");
        $sellers = Database::fetchAll("SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC");
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        // Generate auto style code suggestion
        $count = Database::fetchOne("SELECT COUNT(*) as c FROM products")['c'] ?? 0;
        $suggestedCode = 'GRM-STY-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        $suggestedCatalogCode = 'CAT-STY-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);

        $sellerId = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
        $pendingSupplierProducts = $sellerId > 0 ? (Session::get('pending_supplier_products_' . $sellerId) ?: []) : [];

        Response::view('warehouse.catalog.create', [
            'pageTitle'               => 'Add Product Style | Warehouse Catalog',
            'activeModule'            => 'warehouse',
            'activeMenu'              => 'wh-catalog',
            'categories'              => $categories,
            'brands'                  => $brands,
            'sizes'                   => $sizes,
            'colors'                  => $colors,
            'sellers'                 => $sellers,
            'warehouses'              => $warehouses,
            'suggestedCode'           => $suggestedCode,
            'suggestedCatalogCode'    => $suggestedCatalogCode,
            'pendingSupplierProducts' => $pendingSupplierProducts,
            'breadcrumb'              => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Catalog', 'url' => url('warehouse/catalog')],
                ['label' => 'Add Style Form'],
            ],
        ]);
    }

    public function storeCatalog(): void {
        if (!hasPermission('wh_catalog', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }
        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $code = mb_strtoupper(trim($_POST['code'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $sourceType = trim($_POST['source_type'] ?? 'EXTERNAL_PURCHASE');
        if ($sourceType === 'PURCHASED' || $sourceType === 'EXTERNAL_PURCHASE' || $sourceType === 'THIRD_PARTY') {
            $sourceType = 'EXTERNAL_PURCHASE';
        } else {
            $sourceType = 'OWN_PRODUCTION';
        }
        $material = mb_strtoupper(trim($_POST['material'] ?? ''));
        $hsnCode = mb_strtoupper(trim($_POST['hsn_code'] ?? ''));
        $gstPercentage = max(0.0, (float)($_POST['gst_percentage'] ?? 0));
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
            $sumQty = 0;
            foreach ($_POST['variants'] as $v) {
                $sumQty += max(0, (int)($v['quantity'] ?? 0));
            }
            if ($sumQty > 0 || empty($quantity)) {
                $quantity = $sumQty;
            }
        }
        $weight = max(0.0, (float)($_POST['weight'] ?? 0.0));
        $minStock = max(0, (int)($_POST['min_stock'] ?? 10));
        $maxStock = max(0, (int)($_POST['max_stock'] ?? 1000));
        $description = trim($_POST['description'] ?? '');

        // Fallback: extract category hierarchy from variants if not present at root level
        if ($categoryId <= 0 && !empty($_POST['variants']) && is_array($_POST['variants'])) {
            foreach ($_POST['variants'] as $v) {
                if (!empty($v['category_id'])) {
                    $categoryId = (int)$v['category_id'];
                    if (empty($subcategoryId) && !empty($v['subcategory_id'])) $subcategoryId = (int)$v['subcategory_id'];
                    if (empty($subSubcategoryId) && !empty($v['sub_subcategory_id'])) $subSubcategoryId = (int)$v['sub_subcategory_id'];
                    if (empty($categoryCode) && !empty($v['category_code'])) $categoryCode = mb_strtoupper(trim($v['category_code']));
                    if (empty($subcategoryCode) && !empty($v['subcategory_code'])) $subcategoryCode = mb_strtoupper(trim($v['subcategory_code']));
                    if (empty($subSubcategoryCode) && !empty($v['sub_subcategory_code'])) $subSubcategoryCode = mb_strtoupper(trim($v['sub_subcategory_code']));
                    if (empty($qrCodePrefix) && !empty($v['qr_code_prefix'])) $qrCodePrefix = mb_strtoupper(trim($v['qr_code_prefix']));
                    break;
                }
            }
        }

        // Validations
        if ($categoryId <= 0) {
            Session::flash('error', 'Please select a valid Category for the product style or variants.');
            Response::redirect(url('warehouse/catalog/create'));
            return;
        }

        if (empty($name)) {
            $catNames = [];
            if ($subSubcategoryId) {
                $ssc = Database::fetchOne("SELECT name FROM warehouse_sub_subcategories WHERE id = ?", [$subSubcategoryId]);
                if ($ssc) $catNames[] = $ssc['name'];
            }
            if ($subcategoryId) {
                $sc = Database::fetchOne("SELECT name FROM warehouse_subcategories WHERE id = ?", [$subcategoryId]);
                if ($sc) $catNames[] = $sc['name'];
            }
            $c = Database::fetchOne("SELECT name FROM warehouse_categories WHERE id = ?", [$categoryId]);
            if ($c) $catNames[] = $c['name'];

            $name = !empty($catNames) ? implode(' - ', array_reverse($catNames)) : 'PRODUCT STYLE';
        }

        $catalogCode = mb_strtoupper(trim($_POST['catalog_code'] ?? ''));
        if (empty($code)) {
            $count = Database::fetchOne("SELECT COUNT(*) as c FROM products")['c'] ?? 0;
            $code = ($sourceType === 'PURCHASED' ? 'TP-STY-' : 'GRM-STY-') . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
            $existing = Database::fetchOne("SELECT id FROM products WHERE code = ?", [$code]);
            if ($existing) {
                $code .= '-' . rand(10, 99);
            }
        }
        if (empty($catalogCode)) {
            $countProd = Database::fetchOne("SELECT COUNT(*) as c FROM products")['c'] ?? 0;
            $catalogCode = 'CAT-STY-' . str_pad((string)($countProd + 1), 4, '0', STR_PAD_LEFT);
        }

        // Image Upload
        $imageUrl = null;
        $cloudinaryPublicId = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $cloudUrl = CloudinaryService::upload($_FILES['image'], 'grm_inventory/catalog', 'image');
            if ($cloudUrl) {
                $imageUrl = $cloudUrl;
                $cloudinaryPublicId = CloudinaryService::extractPublicId($cloudUrl) ?? ('catalog_' . time());
            }
        }

        $categoryCode = mb_strtoupper(trim($_POST['category_code'] ?? ''));
        $subcategoryCode = mb_strtoupper(trim($_POST['subcategory_code'] ?? ''));
        $subSubcategoryCode = mb_strtoupper(trim($_POST['sub_subcategory_code'] ?? ''));
        $qrCodePrefix = mb_strtoupper(trim($_POST['qr_code_prefix'] ?? ''));
        $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
        $isComplementary = !empty($_POST['is_complementary']) ? 1 : 0;
        $mfgDate = !empty($_POST['mfg_date']) ? trim($_POST['mfg_date']) : null;
        $expiryDate = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;

        $legacyCategoryId = Database::fetchOne("SELECT id FROM categories WHERE id = ?", [$categoryId]) ? $categoryId : null;
        $legacySubcategoryId = $subcategoryId && Database::fetchOne("SELECT id FROM subcategories WHERE id = ?", [$subcategoryId]) ? $subcategoryId : null;
        $legacySubSubcategoryId = $subSubcategoryId && Database::fetchOne("SELECT id FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]) ? $subSubcategoryId : null;
        $legacySellerId = $sellerId && Database::fetchOne("SELECT id FROM sellers WHERE id = ?", [$sellerId]) ? $sellerId : null;

        try {
            Database::beginTransaction();

            Database::execute(
                "INSERT INTO products (name, code, catalog_code, category_id, warehouse_category_id, subcategory_id, warehouse_subcategory_id, sub_subcategory_id, warehouse_sub_subcategory_id, category_code, subcategory_code, sub_subcategory_code, qr_code_prefix, brand_id, seller_id, warehouse_supplier_id, source_type, material, description, hsn_code, gst_percentage, quantity, weight, mfg_date, expiry_date, min_stock, max_stock, image_url, cloudinary_public_id, is_complementary, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [
                    $name,
                    $code,
                    $catalogCode,
                    $legacyCategoryId,
                    $categoryId,
                    $legacySubcategoryId,
                    $subcategoryId,
                    $legacySubSubcategoryId,
                    $subSubcategoryId,
                    $categoryCode,
                    $subcategoryCode,
                    $subSubcategoryCode,
                    $qrCodePrefix,
                    $brandId,
                    $legacySellerId,
                    $sellerId,
                    $sourceType,
                    $material,
                    $description,
                    $hsnCode,
                    $gstPercentage,
                    $quantity,
                    $weight,
                    $mfgDate,
                    $expiryDate,
                    $minStock,
                    $maxStock,
                    $imageUrl,
                    $cloudinaryPublicId,
                    $isComplementary
                ]
            );

            $productId = (int)Database::lastInsertId();

        // Process variants matrix or attributes
        $selectedSizes = !empty($_POST['sizes']) && is_array($_POST['sizes']) ? array_filter(array_map('intval', $_POST['sizes'])) : [];
        $selectedColors = !empty($_POST['colors']) && is_array($_POST['colors']) ? array_filter(array_map('intval', $_POST['colors'])) : [];
        $initialWarehouseId = !empty($_POST['initial_warehouse_id']) ? (int)$_POST['initial_warehouse_id'] : 1;

        $variantCount = 0;
        $totalCreditedStock = 0;
        $userId = Session::get('user_id') ?? 1;
        $qrGenMode = trim($_POST['global_qr_type'] ?? $_POST['qr_generation_mode'] ?? 'INDIVIDUAL');

        if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
            foreach ($_POST['variants'] as $v) {
                $sizeId = !empty($v['size_id']) ? (int)$v['size_id'] : null;
                $colorId = !empty($v['color_id']) ? (int)$v['color_id'] : null;

                // Dynamically resolve or create color if custom name is typed
                if (empty($colorId) && !empty($v['color_name'])) {
                    $cNameUpper = strtoupper(trim($v['color_name']));
                    $cHex = !empty($v['color_hex']) ? trim($v['color_hex']) : null;
                    $existingColor = Database::fetchOne("SELECT id, hex_code FROM colors WHERE UPPER(name) = ?", [$cNameUpper]);
                    if ($existingColor) {
                        $colorId = (int)$existingColor['id'];
                        if ($cHex && empty($existingColor['hex_code'])) {
                            Database::execute("UPDATE colors SET hex_code = ? WHERE id = ?", [$cHex, $colorId]);
                        }
                    } else {
                        Database::execute("INSERT INTO colors (name, hex_code, is_active, created_at) VALUES (?, ?, 1, NOW())", [$cNameUpper, $cHex]);
                        $colorId = (int)Database::lastInsertId();
                    }
                }
                // Dynamically resolve or create size if custom name is typed
                if (empty($sizeId) && !empty($v['size_name'])) {
                    $sCodeUpper = strtoupper(trim($v['size_name']));
                    $existingSize = Database::fetchOne("SELECT id FROM sizes WHERE UPPER(code) = ? OR UPPER(name) = ?", [$sCodeUpper, $sCodeUpper]);
                    if ($existingSize) {
                        $sizeId = (int)$existingSize['id'];
                    } else {
                        Database::execute("INSERT INTO sizes (code, name, is_active, created_at) VALUES (?, ?, 1, NOW())", [$sCodeUpper, $sCodeUpper]);
                        $sizeId = (int)Database::lastInsertId();
                    }
                }

                $sku = mb_strtoupper(trim($v['sku'] ?? ''));
                $costPrice = max(0.0, (float)($v['purchase_price'] ?? $v['production_cost'] ?? $_POST['default_purchase_price'] ?? 0));
                $mrp = max(0.0, (float)($v['mrp'] ?? $_POST['default_mrp'] ?? 0));
                $sellingPrice = max(0.0, (float)($v['selling_price'] ?? $_POST['default_selling_price'] ?? $mrp));
                $variantQty = max(0, (int)($v['quantity'] ?? 0));
                $weight = max(0.0, (float)($v['weight'] ?? 0.0));
                $vMfg = !empty($v['mfg_date']) ? trim($v['mfg_date']) : $mfgDate;
                $vExp = !empty($v['expiry_date']) ? trim($v['expiry_date']) : $expiryDate;

                $qrType = (!empty($v['qr_type']) && in_array($v['qr_type'], ['SERIES_BATCH', 'INDIVIDUAL'])) ? $v['qr_type'] : ($qrGenMode === 'SERIES_BATCH' ? 'SERIES_BATCH' : 'INDIVIDUAL');
                $seriesStart = !empty($v['series_start_code']) ? trim($v['series_start_code']) : null;
                $seriesEnd = !empty($v['series_end_code']) ? trim($v['series_end_code']) : null;
                $packQty = !empty($v['pack_quantity']) ? (int)$v['pack_quantity'] : ($variantQty > 0 ? $variantQty : 1);

                if (empty($sku)) {
                    $sku = $code;
                    if ($sizeId) {
                        $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? $sizeId;
                        $sku .= '-' . $sCode;
                    }
                    if ($colorId) {
                        $cName = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId])['name'] ?? $colorId;
                        $sku .= '-' . substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cName)), 0, 4);
                    }
                }

                $variantQr = $sku;

                $vHsn = !empty($v['hsn_code']) ? strtoupper(trim($v['hsn_code'])) : (!empty($_POST['hsn_code']) ? strtoupper(trim($_POST['hsn_code'])) : null);

                Database::execute(
                    "INSERT INTO product_variants (product_id, size_id, color_id, sku, hsn_code, qr_code, mrp, purchase_price, production_cost, selling_price, weight, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [
                        $productId,
                        $sizeId,
                        $colorId,
                        $sku,
                        $vHsn,
                        $variantQr,
                        $mrp,
                        $costPrice,
                        $costPrice,
                        $sellingPrice,
                        $weight,
                        $vMfg,
                        $vExp,
                        $qrType,
                        $seriesStart,
                        $seriesEnd,
                        $packQty,
                        $isComplementary
                    ]
                );
                $variantId = (int)Database::lastInsertId();
                if ($vHsn && empty($_POST['hsn_code'])) {
                    Database::execute("UPDATE products SET hsn_code = ? WHERE id = ? AND (hsn_code IS NULL OR hsn_code = '')", [$vHsn, $productId]);
                }
                $variantCount++;

                // Credit warehouse inventory upon catalog saving
                if ($variantQty > 0 && $initialWarehouseId > 0) {
                    try {
                        StockService::increase(
                            $variantId,
                            'WAREHOUSE',
                            $initialWarehouseId,
                            'AVAILABLE',
                            $variantQty,
                            $isComplementary === 1 ? 'COMPLEMENTARY_INWARD' : 'INITIAL_STOCK',
                            'products',
                            $productId,
                            $userId,
                            "Initial stock credited from Warehouse Catalog creation for variant {$sku}"
                        );
                        $totalCreditedStock += $variantQty;
                    } catch (\Throwable $stkEx) {
                        error_log("[storeCatalog] StockService error: " . $stkEx->getMessage());
                    }
                }
            }
        } elseif (!empty($selectedSizes) || !empty($selectedColors)) {
            $defaultCost = max(0.0, (float)($_POST['default_production_cost'] ?? $_POST['default_purchase_price'] ?? 0));
            $defaultMrp = max(0.0, (float)($_POST['default_mrp'] ?? 0));
            $defaultSelling = max(0.0, (float)($_POST['default_selling_price'] ?? $defaultMrp));
            $perVariantQty = ($quantity > 0 && count($selectedSizes) > 0) ? (int)floor($quantity / max(1, count($selectedSizes) * max(1, count($selectedColors)))) : 0;

            $sizesList = !empty($selectedSizes) ? $selectedSizes : [null];
            $colorsList = !empty($selectedColors) ? $selectedColors : [null];
            $qrType = ($qrGenMode === 'SERIES_BATCH') ? 'SERIES_BATCH' : 'INDIVIDUAL';

            foreach ($sizesList as $sizeId) {
                $sCode = '';
                if ($sizeId) {
                    $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? ('SZ' . $sizeId);
                }

                foreach ($colorsList as $colorId) {
                    $cCode = '';
                    if ($colorId) {
                        $cRow = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId]);
                        $cCode = $cRow ? substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cRow['name'])), 0, 4) : ('COL' . $colorId);
                    }

                    $skuParts = [$code];
                    if ($sCode) $skuParts[] = $sCode;
                    if ($cCode) $skuParts[] = $cCode;
                    $sku = implode('-', $skuParts);

                    $seriesStart = $qrType === 'SERIES_BATCH' ? ($sku . '-001') : null;
                    $seriesEnd = ($qrType === 'SERIES_BATCH' && $perVariantQty > 0) ? ($sku . '-' . sprintf('%03d', $perVariantQty)) : null;

                    Database::execute(
                        "INSERT INTO product_variants (product_id, size_id, color_id, sku, qr_code, mrp, purchase_price, production_cost, selling_price, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                        [
                            $productId,
                            $sizeId,
                            $colorId,
                            $sku,
                            $sku,
                            $defaultMrp,
                            $defaultCost,
                            $defaultCost,
                            $defaultSelling,
                            $mfgDate,
                            $expiryDate,
                            $qrType,
                            $seriesStart,
                            $seriesEnd,
                            max(1, $perVariantQty)
                        ]
                    );
                    $variantId = (int)Database::lastInsertId();
                    $variantCount++;
                    if ($perVariantQty > 0 && $initialWarehouseId > 0) {
                        try {
                            StockService::increase(
                                $variantId,
                                'WAREHOUSE',
                                $initialWarehouseId,
                                'AVAILABLE',
                                $perVariantQty,
                                'INITIAL_STOCK',
                                'products',
                                $productId,
                                $userId,
                                "Initial stock credited from Warehouse Catalog creation for variant {$sku}"
                            );
                            $totalCreditedStock += $perVariantQty;
                        } catch (\Throwable $stkEx) {
                            error_log("[storeCatalog] StockService error: " . $stkEx->getMessage());
                        }
                    }
                }
            }
        } else {
            // Default single variant
            $defaultCost = max(0.0, (float)($_POST['default_production_cost'] ?? $_POST['default_purchase_price'] ?? 0));
            $defaultMrp = max(0.0, (float)($_POST['default_mrp'] ?? 0));
            $defaultSelling = max(0.0, (float)($_POST['default_selling_price'] ?? $defaultMrp));
            $qrType = ($qrGenMode === 'SERIES_BATCH') ? 'SERIES_BATCH' : 'INDIVIDUAL';
            $seriesStart = $qrType === 'SERIES_BATCH' ? ($code . '-STD-001') : null;
            $seriesEnd = ($qrType === 'SERIES_BATCH' && $quantity > 0) ? ($code . '-STD-' . sprintf('%03d', $quantity)) : null;

            Database::execute(
                "INSERT INTO product_variants (product_id, sku, qr_code, mrp, purchase_price, production_cost, selling_price, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [
                    $productId,
                    $code . '-STD',
                    $code . '-STD',
                    $defaultMrp,
                    $defaultCost,
                    $defaultCost,
                    $defaultSelling,
                    $mfgDate,
                    $expiryDate,
                    $qrType,
                    $seriesStart,
                    $seriesEnd,
                    max(1, $quantity)
                ]
            );
            $variantId = (int)Database::lastInsertId();
            $variantCount = 1;

            if ($quantity > 0 && $initialWarehouseId > 0) {
                StockService::increase(
                    $variantId,
                    'WAREHOUSE',
                    $initialWarehouseId,
                    'AVAILABLE',
                    $quantity,
                    'INITIAL_STOCK',
                    'products',
                    $productId,
                    $userId,
                    "Initial stock credited from Warehouse Catalog creation for variant {$code}-STD"
                );
                $totalCreditedStock += $quantity;
            }
        }

        // Sync total quantity on products table
        if ($totalCreditedStock > 0) {
            Database::execute("UPDATE products SET quantity = ? WHERE id = ?", [$totalCreditedStock, $productId]);
        }

        AuditService::log('warehouse', 'create', 'products', (int)$productId, null, ['name' => $name, 'code' => $code, 'variants' => $variantCount, 'source_type' => $sourceType, 'initial_stock' => $totalCreditedStock]);

            Database::commit();

            if (!empty($sellerId)) {
                Session::remove('pending_supplier_products_' . (int)$sellerId);
            }
        } catch (\PDOException $e) {
            Database::rollBack();
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                preg_match("/Duplicate entry '([^']+)' for key/i", $e->getMessage(), $matches);
                $dupVal = $matches[1] ?? '';
                if ($dupVal) {
                    Session::flash('error', "A product or variant with Code/SKU '{$dupVal}' already exists. Please use a unique Style Code or Variant SKU.");
                } else {
                    Session::flash('error', 'A product or variant with this SKU/Code already exists. Please use a unique SKU.');
                }
            } else {
                Session::flash('error', 'Error creating product: ' . $e->getMessage());
            }
            Response::redirect(url('warehouse/catalog/create'));
            return;
        }

        Session::flash('success', "Product style '{$name}' with {$variantCount} variant(s) created in Warehouse Catalog (" . number_format($totalCreditedStock) . " pcs added to stock).");
        Response::redirect(url('warehouse/catalog'));
    }

    public function viewCatalog(int $id): void {
        if (!hasPermission('wh_catalog', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }
        $product = Database::fetchOne(
            "SELECT p.*, c.name as category_name, sc.name as subcategory_name, ssc.name as sub_subcategory_name,
                    b.name as brand_name, s.name as seller_name, s.company as seller_company, s.phone as seller_phone
             FROM products p
             LEFT JOIN warehouse_categories c ON p.warehouse_category_id = c.id
             LEFT JOIN warehouse_subcategories sc ON p.warehouse_subcategory_id = sc.id
             LEFT JOIN warehouse_sub_subcategories ssc ON p.warehouse_sub_subcategory_id = ssc.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_suppliers s ON p.warehouse_supplier_id = s.id
             WHERE p.id = ?",
            [$id]
        );

        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }

        $variants = Database::fetchAll(
            "SELECT pv.*, s.name as size_name, s.code as size_code, col.name as color_name, col.hex_code as color_hex,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'WAREHOUSE'
                    ), 0) as warehouse_stock,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'SHOP'
                    ), 0) as shop_stock
             FROM product_variants pv
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors col ON pv.color_id = col.id
             WHERE pv.product_id = ? AND pv.is_active = 1
             ORDER BY s.sort_order ASC, col.name ASC",
            [$id]
        );

        // Recent purchases / inward batches for this product
        $recentPurchases = Database::fetchAll(
            "SELECT po.po_number, po.order_date, po.status, s.name as seller_name, s.company as seller_company, pi.quantity, pi.unit_price, pi.total_amount
             FROM purchase_items pi
             JOIN purchase_orders po ON pi.purchase_order_id = po.id
             JOIN product_variants pv ON pi.variant_id = pv.id
             LEFT JOIN warehouse_suppliers s ON po.warehouse_supplier_id = s.id
             WHERE pv.product_id = ?
             ORDER BY po.id DESC LIMIT 5",
            [$id]
        );

        Response::view('warehouse.catalog.view', [
            'pageTitle'       => $product['name'] . ' | Warehouse Catalog',
            'activeModule'    => 'warehouse',
            'activeMenu'      => 'wh-catalog',
            'product'         => $product,
            'variants'        => $variants,
            'recentPurchases' => $recentPurchases,
            'breadcrumb'      => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Catalog', 'url' => url('warehouse/catalog')],
                ['label' => $product['code']],
            ],
        ]);
    }

    public function editCatalog(int $id): void {
        if (!hasPermission('wh_catalog', 'edit')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }
        $product = Database::fetchOne(
            "SELECT p.*,
                    COALESCE(p.warehouse_category_id, p.category_id) AS category_id,
                    COALESCE(p.warehouse_subcategory_id, p.subcategory_id) AS subcategory_id,
                    COALESCE(p.warehouse_sub_subcategory_id, p.sub_subcategory_id) AS sub_subcategory_id,
                    COALESCE(p.warehouse_supplier_id, p.seller_id) AS seller_id
             FROM products p
             WHERE p.id = ?",
            [$id]
        );
        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }

        $catId = !empty($product['category_id']) ? (int)$product['category_id'] : null;
        $subCatId = !empty($product['subcategory_id']) ? (int)$product['subcategory_id'] : null;
        $subSubCatId = !empty($product['sub_subcategory_id']) ? (int)$product['sub_subcategory_id'] : null;

        $categories = Database::fetchAll("SELECT * FROM warehouse_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        if (empty($categories)) {
            $categories = Database::fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
        }
        $subcategories = $catId ? Database::fetchAll("SELECT * FROM warehouse_subcategories WHERE category_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC", [$catId]) : [];
        if ($catId && empty($subcategories)) {
            $subcategories = Database::fetchAll("SELECT * FROM subcategories WHERE category_id = ? AND is_active = 1 ORDER BY name ASC", [$catId]);
        }
        $subSubcategories = $subCatId ? Database::fetchAll("SELECT * FROM warehouse_sub_subcategories WHERE subcategory_id = ? AND is_active = 1 ORDER BY name ASC", [$subCatId]) : [];
        if ($subCatId && empty($subSubcategories)) {
            $subSubcategories = Database::fetchAll("SELECT * FROM sub_subcategories WHERE subcategory_id = ? AND is_active = 1 ORDER BY name ASC", [$subCatId]);
        }
        $brands = Database::fetchAll("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $sellers = Database::fetchAll("SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC");
        if (empty($sellers)) {
            $sellers = Database::fetchAll("SELECT * FROM sellers WHERE is_active = 1 ORDER BY name ASC");
        }
        $sizes = Database::fetchAll("SELECT * FROM sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $colors = Database::fetchAll("SELECT * FROM colors WHERE is_active = 1 ORDER BY name ASC");
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        // If category short codes are not saved on product, auto-derive from category records
        if (empty($product['category_code']) && $catId) {
            $catRec = Database::fetchOne("SELECT code, name FROM warehouse_categories WHERE id = ?", [$catId])
                   ?: Database::fetchOne("SELECT code, name FROM categories WHERE id = ?", [$catId]);
            if ($catRec) $product['category_code'] = $catRec['code'] ?: strtoupper(substr($catRec['name'], 0, 1));
        }
        if (empty($product['subcategory_code']) && $subCatId) {
            $subRec = Database::fetchOne("SELECT code, name FROM warehouse_subcategories WHERE id = ?", [$subCatId])
                   ?: Database::fetchOne("SELECT code, name FROM subcategories WHERE id = ?", [$subCatId]);
            if ($subRec) $product['subcategory_code'] = $subRec['code'] ?: strtoupper(substr($subRec['name'], 0, 1));
        }
        if (empty($product['sub_subcategory_code']) && $subSubCatId) {
            $subSubRec = Database::fetchOne("SELECT code, name FROM warehouse_sub_subcategories WHERE id = ?", [$subSubCatId])
                      ?: Database::fetchOne("SELECT code, name FROM sub_subcategories WHERE id = ?", [$subSubCatId]);
            if ($subSubRec) $product['sub_subcategory_code'] = $subSubRec['code'] ?: strtoupper(substr($subSubRec['name'], 0, 1));
        }
        if (empty($product['qr_code_prefix'])) {
            $cPrefix = ($product['category_code'] ?? '') . ($product['subcategory_code'] ?? '') . ($product['sub_subcategory_code'] ?? '');
            $product['qr_code_prefix'] = $cPrefix ?: 'PROD';
        }

        $variants = Database::fetchAll(
            "SELECT pv.*, s.name as size_name, s.code as size_code, col.name as color_name, col.hex_code as color_hex,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id AND sb.stock_state = 'AVAILABLE' AND sb.location_type = 'WAREHOUSE'
                    ), 0) as current_stock,
                    wc.name  as category_name,
                    wsc.name as subcategory_name,
                    wssc.name as sub_subcategory_name
             FROM product_variants pv
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors col ON pv.color_id = col.id
             LEFT JOIN warehouse_categories wc ON wc.id = ?
             LEFT JOIN warehouse_subcategories wsc ON wsc.id = ?
             LEFT JOIN warehouse_sub_subcategories wssc ON wssc.id = ?
             WHERE pv.product_id = ? AND pv.is_active = 1
             ORDER BY s.sort_order ASC, col.name ASC",
            [$catId, $subCatId, $subSubCatId, $id]
        );

        // Inject product-level category IDs and qr_code_prefix into each variant (shared hierarchy)
        foreach ($variants as &$v) {
            if (empty($v['category_id']))        $v['category_id']        = $catId;
            if (empty($v['subcategory_id']))     $v['subcategory_id']     = $subCatId;
            if (empty($v['sub_subcategory_id'])) $v['sub_subcategory_id'] = $subSubCatId;
            if (empty($v['qr_code_prefix']))     $v['qr_code_prefix']     = $product['qr_code_prefix'] ?? '';
        }
        unset($v);

        $defaultCost = !empty($variants) ? ($variants[0]['purchase_price'] ?? $variants[0]['production_cost'] ?? 0) : ($product['cost_price'] ?? 0);
        $defaultMrp = !empty($variants) ? ($variants[0]['mrp'] ?? 0) : ($product['mrp'] ?? 0);
        $defaultSelling = !empty($variants) ? ($variants[0]['selling_price'] ?? 0) : ($product['selling_price'] ?? 0);

        Response::view('warehouse.catalog.edit', [
            'pageTitle'        => 'Edit ' . $product['name'] . ' | Warehouse Catalog',
            'activeModule'     => 'warehouse',
            'activeMenu'       => 'wh-catalog',
            'product'          => $product,
            'variants'         => $variants,
            'defaultCost'      => $defaultCost,
            'defaultMrp'       => $defaultMrp,
            'defaultSelling'   => $defaultSelling,
            'categories'       => $categories,
            'subcategories'    => $subcategories,
            'subSubcategories' => $subSubcategories,
            'brands'           => $brands,
            'sellers'          => $sellers,
            'sizes'            => $sizes,
            'colors'           => $colors,
            'warehouses'       => $warehouses,
            'breadcrumb'       => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Catalog', 'url' => url('warehouse/catalog')],
                ['label' => 'Edit ' . $product['code']],
            ],
        ]);
    }

    public function updateCatalog(int $id): void {
        if (!hasPermission('wh_catalog', 'edit')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }
        $product = Database::fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
        if (!$product) {
            Session::flash('error', 'Product style not found.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }

        $name = mb_strtoupper(trim($_POST['name'] ?? ''));
        $code = mb_strtoupper(trim($_POST['code'] ?? ''));
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $subcategoryId = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $subSubcategoryId = !empty($_POST['sub_subcategory_id']) ? (int)$_POST['sub_subcategory_id'] : null;
        $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
        $isComplementary = !empty($_POST['is_complementary']) ? 1 : 0;
        $sourceType = trim($_POST['source_type'] ?? ($product['source_type'] ?? 'EXTERNAL_PURCHASE'));
        if ($sourceType === 'PURCHASED' || $sourceType === 'EXTERNAL_PURCHASE' || $sourceType === 'THIRD_PARTY') {
            $sourceType = 'EXTERNAL_PURCHASE';
        } else {
            $sourceType = 'OWN_PRODUCTION';
        }
        $material = mb_strtoupper(trim($_POST['material'] ?? ''));
        $hsnCode = mb_strtoupper(trim($_POST['hsn_code'] ?? ''));
        $gstPercentage = max(0.0, (float)($_POST['gst_percentage'] ?? 0));
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
            $sumQty = 0;
            foreach ($_POST['variants'] as $v) {
                $sumQty += max(0, (int)($v['quantity'] ?? 0));
            }
            if ($sumQty > 0 || empty($quantity)) {
                $quantity = $sumQty;
            }
        }
        $weight = max(0.0, (float)($_POST['weight'] ?? 0.0));
        $minStock = max(0, (int)($_POST['min_stock'] ?? 10));
        $maxStock = max(0, (int)($_POST['max_stock'] ?? 1000));
        $description = trim($_POST['description'] ?? '');

        // Fallback: extract category hierarchy from variants if not present at root level
        if ($categoryId <= 0 && !empty($_POST['variants']) && is_array($_POST['variants'])) {
            foreach ($_POST['variants'] as $v) {
                if (!empty($v['category_id'])) {
                    $categoryId = (int)$v['category_id'];
                    if (empty($subcategoryId) && !empty($v['subcategory_id'])) $subcategoryId = (int)$v['subcategory_id'];
                    if (empty($subSubcategoryId) && !empty($v['sub_subcategory_id'])) $subSubcategoryId = (int)$v['sub_subcategory_id'];
                    if (empty($categoryCode) && !empty($v['category_code'])) $categoryCode = mb_strtoupper(trim($v['category_code']));
                    if (empty($subcategoryCode) && !empty($v['subcategory_code'])) $subcategoryCode = mb_strtoupper(trim($v['subcategory_code']));
                    if (empty($subSubcategoryCode) && !empty($v['sub_subcategory_code'])) $subSubcategoryCode = mb_strtoupper(trim($v['sub_subcategory_code']));
                    if (empty($qrCodePrefix) && !empty($v['qr_code_prefix'])) $qrCodePrefix = mb_strtoupper(trim($v['qr_code_prefix']));
                    break;
                }
            }
        }

        if ($categoryId <= 0) {
            Session::flash('error', 'Category is required for the product style or variants.');
            Response::redirect(url('warehouse/catalog/edit/' . $id));
            return;
        }

        if (empty($name)) {
            $name = $product['name'];
        }
        if (empty($code)) {
            $code = $product['code'];
        }

        $imageUrl = $product['image_url'];
        $cloudinaryPublicId = $product['cloudinary_public_id'];
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $cloudUrl = CloudinaryService::upload($_FILES['image'], 'grm_inventory/catalog', 'image');
            if ($cloudUrl) {
                $imageUrl = $cloudUrl;
                $cloudinaryPublicId = CloudinaryService::extractPublicId($cloudUrl) ?? ('catalog_' . time());
            }
        }

        $catalogCode = mb_strtoupper(trim($_POST['catalog_code'] ?? ''));
        if (empty($catalogCode)) {
            $catalogCode = $product['catalog_code'] ?? ('CAT-STY-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT));
        }

        $categoryCode = mb_strtoupper(trim($_POST['category_code'] ?? ''));
        $subcategoryCode = mb_strtoupper(trim($_POST['subcategory_code'] ?? ''));
        $subSubcategoryCode = mb_strtoupper(trim($_POST['sub_subcategory_code'] ?? ''));
        $qrCodePrefix = mb_strtoupper(trim($_POST['qr_code_prefix'] ?? ''));
        $mfgDate = !empty($_POST['mfg_date']) ? trim($_POST['mfg_date']) : null;
        $expiryDate = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;

        $legacyCategoryId = Database::fetchOne("SELECT id FROM categories WHERE id = ?", [$categoryId]) ? $categoryId : null;
        $legacySubcategoryId = $subcategoryId && Database::fetchOne("SELECT id FROM subcategories WHERE id = ?", [$subcategoryId]) ? $subcategoryId : null;
        $legacySubSubcategoryId = $subSubcategoryId && Database::fetchOne("SELECT id FROM sub_subcategories WHERE id = ?", [$subSubcategoryId]) ? $subSubcategoryId : null;
        $legacySellerId = $sellerId && Database::fetchOne("SELECT id FROM sellers WHERE id = ?", [$sellerId]) ? $sellerId : null;

        try {
            Database::beginTransaction();

            Database::execute(
                "UPDATE products SET name = ?, code = ?, catalog_code = ?, category_id = ?, warehouse_category_id = ?, subcategory_id = ?, warehouse_subcategory_id = ?, sub_subcategory_id = ?, warehouse_sub_subcategory_id = ?, category_code = ?, subcategory_code = ?, sub_subcategory_code = ?, qr_code_prefix = ?, brand_id = ?, seller_id = ?, warehouse_supplier_id = ?, source_type = ?, is_complementary = ?, material = ?, description = ?, hsn_code = ?, gst_percentage = ?, quantity = ?, weight = ?, mfg_date = ?, expiry_date = ?, min_stock = ?, max_stock = ?, image_url = ?, cloudinary_public_id = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $name,
                    $code,
                    $catalogCode,
                    $legacyCategoryId,
                    $categoryId,
                    $legacySubcategoryId,
                    $subcategoryId,
                    $legacySubSubcategoryId,
                    $subSubcategoryId,
                    $categoryCode,
                    $subcategoryCode,
                    $subSubcategoryCode,
                    $qrCodePrefix,
                    $brandId,
                    $legacySellerId,
                    $sellerId,
                    $sourceType,
                    $isComplementary,
                    $material,
                    $description,
                    $hsnCode,
                    $gstPercentage,
                    $quantity,
                    $weight,
                    $mfgDate,
                    $expiryDate,
                    $minStock,
                    $maxStock,
                    $imageUrl,
                    $cloudinaryPublicId,
                    $id
                ]
            );

        // Sync variants
        $selectedSizes = !empty($_POST['sizes']) && is_array($_POST['sizes']) ? array_filter(array_map('intval', $_POST['sizes'])) : [];
        $selectedColors = !empty($_POST['colors']) && is_array($_POST['colors']) ? array_filter(array_map('intval', $_POST['colors'])) : [];
        $initialWarehouseId = !empty($_POST['initial_warehouse_id']) ? (int)$_POST['initial_warehouse_id'] : 1;

        $userId = Session::get('user_id') ?? 1;
        $qrGenMode = trim($_POST['global_qr_type'] ?? $_POST['qr_generation_mode'] ?? 'INDIVIDUAL');

        if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
            foreach ($_POST['variants'] as $v) {
                $sizeId = !empty($v['size_id']) ? (int)$v['size_id'] : null;
                $colorId = !empty($v['color_id']) ? (int)$v['color_id'] : null;

                // Dynamically resolve or create color if custom name is typed
                if (empty($colorId) && !empty($v['color_name'])) {
                    $cNameUpper = strtoupper(trim($v['color_name']));
                    $cHex = !empty($v['color_hex']) ? trim($v['color_hex']) : null;
                    $existingColor = Database::fetchOne("SELECT id, hex_code FROM colors WHERE UPPER(name) = ?", [$cNameUpper]);
                    if ($existingColor) {
                        $colorId = (int)$existingColor['id'];
                        if ($cHex && empty($existingColor['hex_code'])) {
                            Database::execute("UPDATE colors SET hex_code = ? WHERE id = ?", [$cHex, $colorId]);
                        }
                    } else {
                        Database::execute("INSERT INTO colors (name, hex_code, is_active, created_at) VALUES (?, ?, 1, NOW())", [$cNameUpper, $cHex]);
                        $colorId = (int)Database::lastInsertId();
                    }
                }
                // Dynamically resolve or create size if custom name is typed
                if (empty($sizeId) && !empty($v['size_name'])) {
                    $sCodeUpper = strtoupper(trim($v['size_name']));
                    $existingSize = Database::fetchOne("SELECT id FROM sizes WHERE UPPER(code) = ? OR UPPER(name) = ?", [$sCodeUpper, $sCodeUpper]);
                    if ($existingSize) {
                        $sizeId = (int)$existingSize['id'];
                    } else {
                        Database::execute("INSERT INTO sizes (code, name, is_active, created_at) VALUES (?, ?, 1, NOW())", [$sCodeUpper, $sCodeUpper]);
                        $sizeId = (int)Database::lastInsertId();
                    }
                }

                $sku = mb_strtoupper(trim($v['sku'] ?? ''));
                $costPrice = max(0.0, (float)($v['purchase_price'] ?? $v['production_cost'] ?? $_POST['default_purchase_price'] ?? 0));
                $mrp = max(0.0, (float)($v['mrp'] ?? $_POST['default_mrp'] ?? 0));
                $sellingPrice = max(0.0, (float)($v['selling_price'] ?? $_POST['default_selling_price'] ?? $mrp));
                $variantQty = isset($v['quantity']) ? (int)$v['quantity'] : null;
                $weight = max(0.0, (float)($v['weight'] ?? 0.0));
                $vMfg = !empty($v['mfg_date']) ? trim($v['mfg_date']) : $mfgDate;
                $vExp = !empty($v['expiry_date']) ? trim($v['expiry_date']) : $expiryDate;

                $qrType = (!empty($v['qr_type']) && in_array($v['qr_type'], ['SERIES_BATCH', 'INDIVIDUAL'])) ? $v['qr_type'] : ($qrGenMode === 'SERIES_BATCH' ? 'SERIES_BATCH' : 'INDIVIDUAL');
                $seriesStart = !empty($v['series_start_code']) ? trim($v['series_start_code']) : null;
                $seriesEnd = !empty($v['series_end_code']) ? trim($v['series_end_code']) : null;
                $packQty = !empty($v['pack_quantity']) ? (int)$v['pack_quantity'] : (($variantQty !== null && $variantQty > 0) ? $variantQty : 1);

                if (empty($sku)) {
                    $sku = $code . ($sizeId ? '-' . $sizeId : '') . ($colorId ? '-' . $colorId : '');
                }

                $vHsn = !empty($v['hsn_code']) ? strtoupper(trim($v['hsn_code'])) : (!empty($_POST['hsn_code']) ? strtoupper(trim($_POST['hsn_code'])) : null);

                $existingVar = Database::fetchOne("SELECT id FROM product_variants WHERE product_id = ? AND sku = ?", [$id, $sku]);
                if ($existingVar) {
                    Database::execute(
                        "UPDATE product_variants SET size_id = ?, color_id = ?, sku = ?, hsn_code = ?, qr_code = ?, mrp = ?, purchase_price = ?, production_cost = ?, selling_price = ?, weight = ?, mfg_date = ?, expiry_date = ?, qr_type = ?, series_start_code = ?, series_end_code = ?, pack_quantity = ?, is_complementary = ?, is_active = 1, updated_at = NOW() WHERE id = ?",
                        [$sizeId, $colorId, $sku, $vHsn, $sku, $mrp, $costPrice, $costPrice, $sellingPrice, $weight, $vMfg, $vExp, $qrType, $seriesStart, $seriesEnd, $packQty, $isComplementary, $existingVar['id']]
                    );
                    $variantId = (int)$existingVar['id'];
                } else {
                    Database::execute(
                        "INSERT INTO product_variants (product_id, size_id, color_id, sku, hsn_code, qr_code, mrp, purchase_price, production_cost, selling_price, weight, mfg_date, expiry_date, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                        [$id, $sizeId, $colorId, $sku, $vHsn, $sku, $mrp, $costPrice, $costPrice, $sellingPrice, $weight, $vMfg, $vExp, $qrType, $seriesStart, $seriesEnd, $packQty, $isComplementary]
                    );
                    $variantId = (int)Database::lastInsertId();
                }
                if ($vHsn && empty($_POST['hsn_code'])) {
                    Database::execute("UPDATE products SET hsn_code = ? WHERE id = ? AND (hsn_code IS NULL OR hsn_code = '')", [$vHsn, $id]);
                }

                // Adjust live warehouse inventory upon catalog update
                if ($initialWarehouseId > 0 && $variantQty !== null) {
                    try {
                        $curStock = (int)(Database::fetchOne(
                            "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                            [$variantId, $initialWarehouseId]
                        )['quantity'] ?? 0);
                        $diff = $variantQty - $curStock;
                        if ($diff > 0) {
                            StockService::increase(
                                $variantId,
                                'WAREHOUSE',
                                $initialWarehouseId,
                                'AVAILABLE',
                                $diff,
                                $isComplementary === 1 ? 'COMPLEMENTARY_INWARD' : 'CATALOG_STOCK_UPDATE',
                                'products',
                                $id,
                                $userId,
                                "Stock credited from catalog update for variant {$sku}"
                            );
                        } elseif ($diff < 0) {
                            StockService::decrease(
                                $variantId,
                                'WAREHOUSE',
                                $initialWarehouseId,
                                'AVAILABLE',
                                abs($diff),
                                $isComplementary === 1 ? 'COMPLEMENTARY_ADJUSTMENT' : 'CATALOG_STOCK_ADJUSTMENT',
                                'products',
                                $id,
                                $userId,
                                "Stock adjustment from catalog update for variant {$sku}"
                            );
                        }
                    } catch (\Throwable $stkEx) {
                        error_log("[updateCatalog] StockService error: " . $stkEx->getMessage());
                    }
                }
            }
        } elseif (!empty($selectedSizes) || !empty($selectedColors)) {
            try {
                Database::execute("DELETE FROM product_variants WHERE product_id = ?", [$id]);
            } catch (Exception $e) {
                Database::execute("UPDATE product_variants SET is_active = 0 WHERE product_id = ?", [$id]);
            }

            $defaultCost = max(0.0, (float)($_POST['default_production_cost'] ?? $_POST['default_purchase_price'] ?? 0));
            $defaultMrp = max(0.0, (float)($_POST['default_mrp'] ?? 0));
            $defaultSelling = max(0.0, (float)($_POST['default_selling_price'] ?? $defaultMrp));

            $sizesList = !empty($selectedSizes) ? $selectedSizes : [null];
            $colorsList = !empty($selectedColors) ? $selectedColors : [null];

            foreach ($sizesList as $sizeId) {
                $sCode = '';
                if ($sizeId) {
                    $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? ('SZ' . $sizeId);
                }

                foreach ($colorsList as $colorId) {
                    $cCode = '';
                    if ($colorId) {
                        $cRow = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId]);
                        $cCode = $cRow ? substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cRow['name'])), 0, 4) : ('COL' . $colorId);
                    }

                    $skuParts = [$code];
                    if ($sCode) $skuParts[] = $sCode;
                    if ($cCode) $skuParts[] = $cCode;
                    $sku = implode('-', $skuParts);

                    Database::execute(
                        "INSERT INTO product_variants (product_id, size_id, color_id, sku, mrp, purchase_price, production_cost, selling_price, mfg_date, expiry_date, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE product_id=VALUES(product_id), size_id=VALUES(size_id), color_id=VALUES(color_id), mrp=VALUES(mrp), purchase_price=VALUES(purchase_price), production_cost=VALUES(production_cost), selling_price=VALUES(selling_price), mfg_date=VALUES(mfg_date), expiry_date=VALUES(expiry_date), is_active=1",
                        [
                            $id,
                            $sizeId,
                            $colorId,
                            $sku,
                            $defaultMrp,
                            $defaultCost,
                            $defaultCost,
                            $defaultSelling,
                            $mfgDate,
                            $expiryDate
                        ]
                    );
                }
            }
        } else {
            $defaultCost = max(0.0, (float)($_POST['default_production_cost'] ?? $_POST['default_purchase_price'] ?? 0));
            $defaultMrp = max(0.0, (float)($_POST['default_mrp'] ?? 0));
            $defaultSelling = max(0.0, (float)($_POST['default_selling_price'] ?? $defaultMrp));

            $existingSingle = Database::fetchOne("SELECT id FROM product_variants WHERE product_id = ? LIMIT 1", [$id]);
            if ($existingSingle) {
                Database::execute(
                    "UPDATE product_variants SET sku = ?, mrp = ?, purchase_price = ?, production_cost = ?, selling_price = ?, mfg_date = ?, expiry_date = ?, is_active = 1 WHERE id = ?",
                    [$code . '-STD', $defaultMrp, $defaultCost, $defaultCost, $defaultSelling, $mfgDate, $expiryDate, $existingSingle['id']]
                );
            } else {
                Database::execute(
                    "INSERT INTO product_variants (product_id, sku, mrp, purchase_price, production_cost, selling_price, mfg_date, expiry_date, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$id, $code . '-STD', $defaultMrp, $defaultCost, $defaultCost, $defaultSelling, $mfgDate, $expiryDate]
                );
            }
        }

        AuditService::log('warehouse', 'update', 'products', (int)$id, null, ['name' => $name, 'code' => $code]);

            Database::commit();
        } catch (\PDOException $e) {
            Database::rollBack();
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'Duplicate entry')) {
                preg_match("/Duplicate entry '([^']+)' for key/i", $e->getMessage(), $matches);
                $dupVal = $matches[1] ?? '';
                if ($dupVal) {
                    Session::flash('error', "A product or variant with Code/SKU '{$dupVal}' already exists. Please use a unique Style Code or Variant SKU.");
                } else {
                    Session::flash('error', 'A product or variant with this SKU/Code already exists. Please use a unique SKU.');
                }
            } else {
                Session::flash('error', 'Error updating product: ' . $e->getMessage());
            }
            Response::redirect(url('warehouse/catalog/edit/' . $id));
            return;
        }

        Session::flash('success', "Product style '{$name}' updated successfully in Warehouse Catalog.");
        Response::redirect(url('warehouse/catalog'));
    }

    public function deleteCatalog(int $id): void {
        if (!hasPermission('wh_catalog', 'delete')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/catalog'));
            return;
        }
        $ts = time();
        Database::execute("UPDATE products SET is_active = 0, code = CONCAT(code, '_DEL_', ?) WHERE id = ?", [$ts, $id]);
        Database::execute("UPDATE product_variants SET is_active = 0, sku = CONCAT(sku, '_DEL_', ?), qr_code = CONCAT(qr_code, '_DEL_', ?) WHERE product_id = ?", [$ts, $ts, $id]);
        AuditService::log('warehouse', 'delete', 'products', (int)$id, null, ['is_active' => 0]);
        Session::flash('success', 'Product style removed from Warehouse Catalog.');
        Response::redirect(url('warehouse/catalog'));
    }

    // =========================================================================
    // 12. WAREHOUSE SELLERS & THIRD-PARTY PRODUCT PURCHASES
    // =========================================================================

    /**
     * List Warehouse Sellers with Revenue & Spend KPI metrics.
     */
    public function sellerList(): void {
        if (!hasPermission('wh_sellers', 'view') && !hasPermission('purchases', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $sellerType = $_GET['seller_type'] ?? '';

        $sql = "SELECT s.*,
                       COALESCE(po_summary.total_orders, 0) as total_orders,
                       COALESCE(po_summary.total_spend, 0) as total_spend,
                       COALESCE(po_summary.total_units, 0) as total_units,
                       COALESCE(po_summary.paid_amount, 0) as paid_amount,
                       COALESCE(po_summary.pending_dues, 0) as pending_dues
                FROM warehouse_suppliers s
                LEFT JOIN (
                    SELECT COALESCE(po.warehouse_supplier_id, po.seller_id) as seller_id,
                           COUNT(po.id) as total_orders,
                           SUM(po.total_amount) as total_spend,
                           SUM(COALESCE(pi_sum.total_qty, 0)) as total_units,
                           SUM(CASE WHEN po.payment_status = 'PAID' THEN po.total_amount ELSE 0 END) as paid_amount,
                           SUM(CASE WHEN po.payment_status != 'PAID' THEN po.total_amount ELSE 0 END) as pending_dues
                    FROM purchase_orders po
                    LEFT JOIN (
                        SELECT purchase_order_id, SUM(quantity) as total_qty 
                        FROM purchase_items 
                        GROUP BY purchase_order_id
                    ) pi_sum ON po.id = pi_sum.purchase_order_id
                    WHERE (po.warehouse_supplier_id IS NOT NULL OR po.seller_id IS NOT NULL)
                    GROUP BY COALESCE(po.warehouse_supplier_id, po.seller_id)
                ) po_summary ON po_summary.seller_id = s.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (s.name LIKE ? OR s.company LIKE ? OR s.phone LIKE ? OR s.city LIKE ? OR s.gst_number LIKE ?)";
            $term = '%' . $search . '%';
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        if ($status === '1') {
            $sql .= " AND s.is_active = 1";
        } elseif ($status === '0') {
            $sql .= " AND s.is_active = 0";
        } elseif ($status === 'all') {
            // Show all
        } else {
            $sql .= " AND s.is_active = 1";
        }

        if ($sellerType !== '') {
            $sql .= " AND s.seller_type = ?";
            $params[] = $sellerType;
        }

        $sql .= " ORDER BY s.id DESC";

        $sellers = Database::fetchAll($sql, $params);

        // Compute overall top KPI cards
        $stats = [
            'total_sellers'      => (int)(Database::fetchOne("SELECT COUNT(*) as cnt FROM warehouse_suppliers WHERE is_active = 1")['cnt'] ?? 0),
            'total_orders'       => (int)(Database::fetchOne("SELECT COUNT(*) as cnt FROM purchase_orders WHERE warehouse_supplier_id IS NOT NULL OR seller_id IS NOT NULL")['cnt'] ?? 0),
            'total_spend'        => (float)(Database::fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM purchase_orders WHERE warehouse_supplier_id IS NOT NULL OR seller_id IS NOT NULL")['total'] ?? 0),
            'total_inward_units' => (int)(Database::fetchOne("SELECT COALESCE(SUM(pi.quantity), 0) as total FROM purchase_items pi JOIN purchase_orders po ON pi.purchase_order_id = po.id WHERE po.warehouse_supplier_id IS NOT NULL OR po.seller_id IS NOT NULL")['total'] ?? 0),
            'paid_spend'         => (float)(Database::fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM purchase_orders WHERE (warehouse_supplier_id IS NOT NULL OR seller_id IS NOT NULL) AND payment_status = 'PAID'")['total'] ?? 0),
            'pending_spend'      => (float)(Database::fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM purchase_orders WHERE (warehouse_supplier_id IS NOT NULL OR seller_id IS NOT NULL) AND payment_status != 'PAID'")['total'] ?? 0),
        ];

        Response::view('warehouse.sellers.index', [
            'pageTitle'    => 'Warehouse Third-Party Sellers & Suppliers',
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-sellers',
            'sellers'      => $sellers,
            'stats'        => $stats,
            'search'       => $search,
            'status'       => $status,
            'sellerType'   => $sellerType,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Sellers Hub'],
            ],
        ]);
    }

    /**
     * Show create seller form.
     */
    public function createSeller(): void {
        if (!hasPermission('wh_sellers', 'create') && !hasPermission('purchases', 'create') && !hasPermission('warehouse', 'create') && !hasPermission('wh_catalog', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code, pv.selling_price, pv.mrp, pv.purchase_price, pv.production_cost,
                    COALESCE(pv.purchase_price, pv.production_cost, 0) as cost_price,
                    p.id as product_id, p.name as product_name, p.code as product_code,
                    s.name as size_name, c.name as color_name,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'WAREHOUSE'
                    ), 0) as available_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        Response::view('warehouse.sellers.create', [
            'pageTitle'    => 'Register New Third-Party Seller / Vendor',
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-sellers',
            'warehouses'   => $warehouses,
            'variants'     => $variants,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Sellers', 'url' => url('warehouse/sellers')],
                ['label' => 'Register Supplier'],
            ],
        ]);
    }

    /**
     * Store new seller.
     */
    public function storeSeller(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        if (!hasPermission('wh_sellers', 'create') && !hasPermission('purchases', 'create') && !hasPermission('warehouse', 'create') && !hasPermission('wh_catalog', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? 'Tamil Nadu');
        $paymentTerms = trim($_POST['payment_terms'] ?? '');
        $materialType = trim($_POST['material_type'] ?? 'Third-Party Garments & Products');
        $sellerType = trim($_POST['seller_type'] ?? 'PRODUCT_SELLER');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Session::flash('error', 'Supplier name is required.');
            Response::redirect(url('warehouse/sellers/create'));
            return;
        }

        // Handle Bill Screenshot / Document Upload
        $billUrl = null;
        $billPublicId = null;
        if (!empty($_FILES['bill_image']['name']) && $_FILES['bill_image']['error'] === UPLOAD_ERR_OK) {
            require_once BASE_PATH . '/app/Services/CloudinaryService.php';
            $uploadedUrl = CloudinaryService::upload(
                $_FILES['bill_image'],
                'grm_inventory/bills',
                'image'
            );
            if ($uploadedUrl) {
                $billUrl = $uploadedUrl;
                $billPublicId = CloudinaryService::extractPublicId($uploadedUrl) ?? ('bill_' . time());
            }
        }

        Database::beginTransaction();
        try {
            Database::execute(
                "INSERT INTO warehouse_suppliers (name, company, phone, email, gst_number, address, city, state, payment_terms, material_type, seller_type, document_url, notes, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [$name, $company ?: null, $phone ?: null, $email ?: null, $gstNumber ?: null, $address ?: null, $city ?: null, $state ?: null, $paymentTerms ?: null, $materialType ?: null, $sellerType, $billUrl, $notes ?: null]
            );
            $sellerId = (int)Database::lastInsertId();

            AuditService::log('WAREHOUSE', 'CREATE_SELLER', 'warehouse_suppliers', $sellerId, null, [
                'name' => $name,
                'company' => $company,
                'phone' => $phone,
                'seller_type' => $sellerType,
            ]);

            // Check if user provided multi-product inward line items
            $productNames = $_POST['product_name'] ?? [];
            $productSkus = $_POST['product_sku'] ?? [];
            $productCategories = $_POST['product_category'] ?? [];
            $productColors = $_POST['product_color'] ?? [];
            $productSizes = $_POST['product_size'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $unitPrices = $_POST['unit_price'] ?? [];
            $mrps = $_POST['mrp'] ?? [];
            $sellingPrices = $_POST['selling_price'] ?? [];
            $gstPercentages = $_POST['gst_percentage'] ?? [];
            $variantIds = $_POST['variant_id'] ?? [];
            $productQrTypes = $_POST['product_qr_type'] ?? [];
            $isComplementaryList = $_POST['is_complementary'] ?? [];
            $globalQrType = $_POST['global_qr_type'] ?? 'INDIVIDUAL';
            $packQuantities = $_POST['pack_quantity'] ?? [];
            $seriesStartCodes = $_POST['series_start_code'] ?? [];
            $seriesEndCodes = $_POST['series_end_code'] ?? [];

            $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
            $orderDate = $_POST['order_date'] ?? date('Y-m-d');
            $expectedDate = !empty($_POST['expected_date']) ? $_POST['expected_date'] : $orderDate;
            $paymentMethod = $_POST['payment_method'] ?? 'CASH';
            $paymentStatus = $_POST['payment_status'] ?? 'PAID';
            $gstType = $_POST['gst_type'] ?? 'CGST_SGST';
            $billNumberInput = trim($_POST['bill_number'] ?? '');

            $itemsToInsert = [];
            $grossSubtotal = 0.00;
            $totalCgst = 0.00;
            $totalSgst = 0.00;
            $totalIgst = 0.00;
            $grandTotal = 0.00;

            // Process text-input based product items: buffer them for Catalog creation so they are created with full category & no duplicate un-categorized stock
            $pendingProducts = [];
            if (is_array($productNames) && !empty($productNames)) {
                foreach ($productNames as $idx => $pName) {
                    $pName = trim($pName);
                    $pSku = trim($productSkus[$idx] ?? '');
                    $pCat = trim($productCategories[$idx] ?? '');
                    $pColor = trim($productColors[$idx] ?? '');
                    $pSize = trim($productSizes[$idx] ?? '');
                    $qty = max(1, (int)($quantities[$idx] ?? 1));
                    $costRate = max(0.0, (float)($unitPrices[$idx] ?? 0.00));
                    $mrpVal = max($costRate, (float)($mrps[$idx] ?? $costRate));
                    $sellPriceVal = max(0.00, (float)($sellingPrices[$idx] ?? $costRate));
                    $gstPct = max(0.00, (float)($gstPercentages[$idx] ?? 0.00));

                    // If product name is empty, fallback to SKU or a fallback label so rows are never skipped
                    if (empty($pName)) {
                        if (!empty($pSku)) {
                            $pName = $pSku;
                        } elseif (!empty($pColor) || !empty($pSize) || $costRate > 0) {
                            $pName = 'TP Product ' . ($idx + 1);
                        } else {
                            continue;
                        }
                    }

                    if (empty($pSku)) {
                        $pSku = 'TP-' . strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($pName, 0, 6))) . '-' . rand(100, 999);
                    }

                    $lineQrType = !empty($productQrTypes[$idx]) ? $productQrTypes[$idx] : $globalQrType;
                    $linePackQty = !empty($packQuantities[$idx]) ? (int)$packQuantities[$idx] : ($lineQrType === 'SERIES_BATCH' ? $qty : 1);
                    $lineStartCode = !empty($seriesStartCodes[$idx]) ? trim($seriesStartCodes[$idx]) : ($lineQrType === 'SERIES_BATCH' ? ($pSku ? $pSku . '-001' : null) : null);
                    $lineEndCode = !empty($seriesEndCodes[$idx]) ? trim($seriesEndCodes[$idx]) : ($lineQrType === 'SERIES_BATCH' ? ($pSku ? $pSku . '-' . sprintf('%03d', $qty) : null) : null);
                    $lineIsComp = !empty($isComplementaryList[$idx]) ? 1 : 0;

                    $pendingProducts[] = [
                        'product_name'      => $pName,
                        'sku'               => $pSku,
                        'category'          => $pCat,
                        'color_name'        => $pColor,
                        'size_name'         => $pSize,
                        'quantity'          => $qty,
                        'cost_price'        => $costRate,
                        'purchase_price'    => $costRate,
                        'mrp'               => $mrpVal,
                        'selling_price'     => $sellPriceVal,
                        'gst_percentage'    => $gstPct,
                        'qr_type'           => $lineQrType,
                        'pack_quantity'     => $linePackQty,
                        'series_start_code' => $lineStartCode,
                        'series_end_code'   => $lineEndCode,
                        'is_complementary'  => $lineIsComp,
                        'warehouse_id'      => $warehouseId,
                        'seller_id'         => $sellerId,
                    ];
                }
            }

            if (!empty($pendingProducts)) {
                Session::set('pending_supplier_products_' . $sellerId, $pendingProducts);
            } elseif (is_array($variantIds) && !empty($variantIds) && $warehouseId > 0) {
                // Fallback for variant ID array
                foreach ($variantIds as $idx => $vId) {
                    $vId = (int)$vId;
                    if ($vId <= 0) continue;

                    $qty = max(1, (int)($quantities[$idx] ?? 1));
                    $price = max(0.00, (float)($unitPrices[$idx] ?? 0.00));
                    $mrp = max($price, (float)($mrps[$idx] ?? $price));
                    $gstPct = max(0.00, (float)($gstPercentages[$idx] ?? 5.00));

                    $lineGross = $qty * $price;
                    $grossSubtotal += $lineGross;

                    if ($gstType === 'IGST') {
                        $lineIgst = ($lineGross * $gstPct) / 100;
                        $lineCgst = 0.00;
                        $lineSgst = 0.00;
                    } else {
                        $halfGst = $gstPct / 2;
                        $lineCgst = ($lineGross * $halfGst) / 100;
                        $lineSgst = ($lineGross * $halfGst) / 100;
                        $lineIgst = 0.00;
                    }

                    $totalCgst += $lineCgst;
                    $totalSgst += $lineSgst;
                    $totalIgst += $lineIgst;

                    $lineTotal = $lineGross + $lineCgst + $lineSgst + $lineIgst;
                    $grandTotal += $lineTotal;

                    $itemsToInsert[] = [
                        'variant_id'          => $vId,
                        'quantity'            => $qty,
                        'unit_price'          => $price,
                        'discount_percentage' => 0.00,
                        'gst_percentage'      => $gstPct,
                        'cgst_amount'         => $lineCgst,
                        'sgst_amount'         => $lineSgst,
                        'igst_amount'         => $lineIgst,
                        'total_amount'        => $lineTotal,
                        'mrp'                 => $mrp,
                    ];
                }
            }

            if (!empty($itemsToInsert) && $warehouseId > 0) {
                $poNumber = !empty($billNumberInput) ? $billNumberInput : WarehouseService::generateSequenceNumber('purchase_orders', 'po_number', 'PO-WH');

                $poId = PurchaseOrder::create([
                    'po_number'                    => $poNumber,
                    'customer_id'                  => null,
                    'seller_id'                    => null,
                    'warehouse_supplier_id'        => $sellerId,
                    'warehouse_id'                 => $warehouseId,
                    'order_date'                   => $orderDate,
                    'expected_date'                => $expectedDate,
                    'subtotal'                     => $grossSubtotal,
                    'discount_amount'              => 0.00,
                    'cgst_amount'                  => $totalCgst,
                    'sgst_amount'                  => $totalSgst,
                    'igst_amount'                  => $totalIgst,
                    'total_amount'                 => $grandTotal,
                    'payment_status'               => $paymentStatus,
                    'payment_method'               => $paymentMethod,
                    'payment_screenshot_url'       => $billUrl,
                    'payment_screenshot_public_id' => $billPublicId,
                    'status'                       => 'DRAFT',
                    'notes'                        => $notes ?: "Initial purchase bill from {$name}",
                    'created_by'                   => Session::userId(),
                ]);

                foreach ($itemsToInsert as $item) {
                    $item['purchase_order_id'] = $poId;
                    PurchaseItem::create($item);

                    // If variant is complementary, immediately credit live warehouse stock
                    $vIsComp = (int)(Database::fetchOne("SELECT is_complementary FROM product_variants WHERE id = ?", [$item['variant_id']])['is_complementary'] ?? 0);
                    if ($vIsComp === 1 && $warehouseId > 0 && ($item['quantity'] ?? 0) > 0) {
                        try {
                            StockService::increase(
                                (int)$item['variant_id'],
                                'WAREHOUSE',
                                (int)$warehouseId,
                                'AVAILABLE',
                                (float)$item['quantity'],
                                'COMPLEMENTARY_INWARD',
                                'purchase_orders',
                                $poId,
                                Session::userId() ?: 1,
                                "Direct inward for supplier complementary variant"
                            );
                        } catch (\Throwable $cEx) {
                            error_log("[storeSeller] Complementary StockService error: " . $cEx->getMessage());
                        }
                    }
                }

                AuditService::log('WAREHOUSE', 'CREATE_SELLER_PURCHASE_DRAFT', 'purchase_orders', $poId, null, [
                    'po_number'    => $poNumber,
                    'seller_id'    => $sellerId,
                    'total_amount' => $grandTotal,
                    'items_count'  => count($itemsToInsert),
                    'status'       => 'PENDING',
                ]);
            }

            Database::commit();

            if (!empty($itemsToInsert)) {
                Session::flash('success', "Supplier '{$name}' registered successfully! Now add their products to the catalog to enable QR generation.");
            } else {
                Session::flash('success', "Supplier '{$name}' registered! Add products to the catalog next to set up QR labels.");
            }
            Response::redirect(url('warehouse/catalog/create?seller_id=' . $sellerId));
        } catch (\Throwable $e) {
            Database::rollback();
            Session::flash('error', 'Failed to register supplier: ' . $e->getMessage());
            Response::redirect(url('warehouse/sellers/create'));
        }
    }

    /**
     * AJAX: Quick Add Seller / Supplier.
     */
    public function ajaxQuickAddSeller(): void {
        try {
            $name = trim($_POST['name'] ?? '');
            $company = trim($_POST['company'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $gstNumber = trim($_POST['gst_number'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? 'Tamil Nadu');
            $address = trim($_POST['address'] ?? '');
            $sellerType = trim($_POST['seller_type'] ?? 'PRODUCT_SELLER');

            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Supplier name is required.'], 400);
                return;
            }

            Database::execute(
                "INSERT INTO warehouse_suppliers (name, company, phone, gst_number, city, state, address, seller_type, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [
                    $name,
                    $company ?: null,
                    $phone ?: null,
                    $gstNumber ?: null,
                    $city ?: null,
                    $state ?: null,
                    $address ?: null,
                    $sellerType
                ]
            );
            $sellerId = (int)Database::lastInsertId();
            $seller = Database::fetchOne("SELECT * FROM warehouse_suppliers WHERE id = ?", [$sellerId]);

            Response::json([
                'success' => true,
                'id'      => $sellerId,
                'name'    => $name,
                'company' => $company,
                'seller'  => $seller
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating supplier: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View Seller Profile and Inward History.
     */
    public function viewSeller(int $id): void {
        if (!hasPermission('wh_sellers', 'view') && !hasPermission('purchases', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }
        $seller = Database::fetchOne("SELECT * FROM warehouse_suppliers WHERE id = ?", [$id]);
        if (!$seller) {
            Session::flash('error', 'Seller not found.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        // Get linked purchase orders from this seller
        $orders = Database::fetchAll(
            "SELECT po.*, w.name as warehouse_name, w.code as warehouse_code,
                    COALESCE(pi_count.total_items, 0) as total_items,
                    COALESCE(pi_count.total_qty, 0) as total_qty
             FROM purchase_orders po
             JOIN warehouses w ON po.warehouse_id = w.id
             LEFT JOIN (
                 SELECT purchase_order_id, COUNT(*) as total_items, SUM(quantity) as total_qty
                 FROM purchase_items
                 GROUP BY purchase_order_id
             ) pi_count ON po.id = pi_count.purchase_order_id
             WHERE (po.warehouse_supplier_id = ? OR po.seller_id = ?)
             ORDER BY po.id DESC",
            [$id, $id]
        );

        // Get all products bought from this supplier (grouped by variant) with real-time stock
        $purchasedProducts = Database::fetchAll(
            "SELECT pv.id as variant_id, pv.sku, pv.qr_code, pv.selling_price, pv.is_complementary as variant_is_complementary,
                    p.id as product_id, p.name as product_name, p.code as product_code, p.is_complementary,
                    c.name as category_name,
                    sz.name as size_name, cl.name as color_name,
                    SUM(pi.quantity) as total_bought_qty,
                    AVG(pi.unit_price) as avg_unit_price,
                    MAX(pi.unit_price) as last_unit_price,
                    MAX(pi.mrp) as mrp,
                    SUM(pi.total_amount) as total_spend_val,
                    MAX(po.order_date) as last_buy_date,
                    w.name as last_warehouse_name,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'WAREHOUSE'
                    ), 0) as current_stock_qty
             FROM purchase_items pi
             JOIN purchase_orders po ON pi.purchase_order_id = po.id
             JOIN product_variants pv ON pi.variant_id = pv.id
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             LEFT JOIN warehouses w ON po.warehouse_id = w.id
             WHERE (po.warehouse_supplier_id = ? OR po.seller_id = ?)
             GROUP BY pv.id, pv.sku, pv.qr_code, pv.selling_price, p.id, p.name, p.code, c.name, sz.name, cl.name
             ORDER BY total_bought_qty DESC",
            [$id, $id]
        );

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);

        $stats = [
            'total_orders'    => count($orders),
            'total_spend'     => array_sum(array_column($orders, 'total_amount')),
            'total_units'     => array_sum(array_column($orders, 'total_qty')),
            'unique_products' => count($purchasedProducts),
            'paid_spend'      => array_sum(array_map(fn($o) => in_array($o['payment_status'] ?? '', ['PAID']) ? (float)$o['total_amount'] : 0, $orders)),
            'pending_dues'    => array_sum(array_map(fn($o) => !in_array($o['payment_status'] ?? '', ['PAID']) ? (float)$o['total_amount'] : 0, $orders)),
        ];

        Response::view('warehouse.sellers.view', [
            'pageTitle'         => 'Supplier Profile: ' . $seller['name'],
            'activeModule'      => 'warehouse',
            'activeMenu'        => 'wh-sellers',
            'seller'            => $seller,
            'orders'            => $orders,
            'purchasedProducts' => $purchasedProducts,
            'warehouses'        => $warehouses,
            'stats'             => $stats,
            'breadcrumb'        => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Sellers', 'url' => url('warehouse/sellers')],
                ['label' => $seller['name']],
            ],
        ]);
    }

    /**
     * AJAX: Get catalog products for a seller, grouped by brand.
     * Used for the dynamic "Add Products" panel on the seller view page.
     */
    public function ajaxSellerCatalog(): void {
        header('Content-Type: application/json');
        if (!hasPermission('wh_sellers', 'view') && !hasPermission('purchases', 'view') && !hasPermission('wh_catalog', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $sellerId = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
        $brandName = trim($_GET['brand_name'] ?? '');
        $brandId = !empty($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;

        if (!$sellerId) {
            echo json_encode(['success' => false, 'message' => 'Seller ID required']);
            exit;
        }

        // Get all brands/products linked to this supplier in the catalog
        $products = Database::fetchAll(
            "SELECT p.id as product_id, p.name as product_name, p.code as product_code,
                    p.image_url, p.brand_id,
                    p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                    wc.name as category_name, wc.code as category_code,
                    wsc.name as subcategory_name, wsc.code as subcategory_code,
                    wss.name as sub_subcategory_name, wss.code as sub_subcategory_code,
                    p.material, COALESCE(pv.hsn_code, p.hsn_code, '') as hsn_code, p.gst_percentage, p.is_complementary as is_complementary, pv.is_complementary as variant_is_complementary,
                    COALESCE(b.name, ws.company, ws.name, 'No Brand') as brand_name,
                    pv.id as variant_id, pv.sku, pv.hsn_code as variant_hsn_code, pv.mrp, pv.selling_price, pv.purchase_price,
                    pv.size_id, pv.color_id, pv.weight,
                    pv.qr_type, pv.series_start_code, pv.series_end_code, pv.pack_quantity,
                    sz.name as size_name, sz.code as size_code,
                    cl.name as color_name, cl.hex_code as color_hex,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id AND sb.stock_state = 'AVAILABLE' AND sb.location_type = 'WAREHOUSE'
                    ), 0) as warehouse_stock,
                    COALESCE((
                        SELECT SUM(pi.quantity)
                        FROM purchase_items pi
                        JOIN purchase_orders po ON pi.purchase_order_id = po.id
                        WHERE pi.variant_id = pv.id
                    ), 0) as purchased_qty
             FROM products p
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN warehouse_suppliers ws ON p.warehouse_supplier_id = ws.id
             LEFT JOIN warehouse_categories wc ON p.warehouse_category_id = wc.id
             LEFT JOIN warehouse_subcategories wsc ON p.warehouse_subcategory_id = wsc.id
             LEFT JOIN warehouse_sub_subcategories wss ON p.warehouse_sub_subcategory_id = wss.id
             LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             WHERE p.warehouse_supplier_id = ?
             ORDER BY p.name ASC, sz.sort_order ASC",
            [$sellerId]
        );

        // Group by brand, then by product
        $grouped = [];
        $allBrands = [];
        foreach ($products as $row) {
            $brand = $row['brand_name'] ?: 'No Brand';
            if (!in_array($brand, $allBrands)) $allBrands[] = $brand;
            if (!isset($grouped[$brand])) $grouped[$brand] = [];
            $pid = $row['product_id'];
            if (!isset($grouped[$brand][$pid])) {
                $grouped[$brand][$pid] = [
                    'product_id'        => $pid,
                    'product_name'      => $row['product_name'],
                    'product_code'      => $row['product_code'],
                    'image_url'         => $row['image_url'],
                    'brand_id'          => $row['brand_id'],
                    'brand_name'        => $brand,
                    'category_id'       => $row['category_id'],
                    'subcategory_id'    => $row['subcategory_id'],
                    'sub_subcategory_id'=> $row['sub_subcategory_id'],
                    'category_name'     => $row['category_name'] ?? '',
                    'category_code'     => $row['category_code'] ?? '',
                    'subcategory_name'  => $row['subcategory_name'] ?? '',
                    'subcategory_code'  => $row['subcategory_code'] ?? '',
                    'sub_subcategory_name' => $row['sub_subcategory_name'] ?? '',
                    'sub_subcategory_code' => $row['sub_subcategory_code'] ?? '',
                    'material'          => $row['material'],
                    'hsn_code'          => $row['hsn_code'],
                    'gst_percentage'    => $row['gst_percentage'],
                    'variants'          => []
                ];
            }
            if (!empty($row['variant_id'])) {
                $variantQty = (int)($row['warehouse_stock'] > 0 ? $row['warehouse_stock'] : ($row['purchased_qty'] > 0 ? $row['purchased_qty'] : ($row['pack_quantity'] > 0 ? $row['pack_quantity'] : 1)));
                $grouped[$brand][$pid]['variants'][] = [
                    'variant_id'        => $row['variant_id'],
                    'product_id'        => $row['product_id'],
                    'product_name'      => $row['product_name'],
                    'product_code'      => $row['product_code'],
                    'sku'               => $row['sku'],
                    'category_id'       => $row['category_id'],
                    'subcategory_id'    => $row['subcategory_id'],
                    'sub_subcategory_id'=> $row['sub_subcategory_id'],
                    'category_name'     => $row['category_name'] ?? '',
                    'category_code'     => $row['category_code'] ?? '',
                    'subcategory_name'  => $row['subcategory_name'] ?? '',
                    'subcategory_code'  => $row['subcategory_code'] ?? '',
                    'sub_subcategory_name' => $row['sub_subcategory_name'] ?? '',
                    'sub_subcategory_code' => $row['sub_subcategory_code'] ?? '',
                    'material'          => $row['material'] ?? '',
                    'is_complementary' => (int)($row['is_complementary'] ?? $row['variant_is_complementary'] ?? 0),
                    'hsn_code'          => $row['hsn_code'] ?? '',
                    'size_id'           => $row['size_id'],
                    'size_name'         => $row['size_name'] ?? 'Free',
                    'size_code'         => $row['size_code'] ?? '',
                    'color_id'          => $row['color_id'],
                    'color_name'        => $row['color_name'] ?? 'Std',
                    'color_hex'         => $row['color_hex'] ?? '',
                    'weight'            => (float)($row['weight'] ?? 0),
                    'mrp'               => (float)$row['mrp'],
                    'selling_price'     => (float)$row['selling_price'],
                    'cost_price'        => (float)$row['purchase_price'],
                    'quantity'          => $variantQty,
                    'qr_type'           => $row['qr_type'] ?? 'INDIVIDUAL',
                    'series_start_code' => $row['series_start_code'] ?? '',
                    'series_end_code'   => $row['series_end_code'] ?? '',
                    'pack_quantity'     => (int)($row['pack_quantity'] ?? $variantQty),
                    'warehouse_stock'   => (int)$row['warehouse_stock'],
                ];
            }
        }

        // Filter by brand if requested
        $filteredBrand = null;
        $filteredProducts = [];
        $flatVariants = [];

        if ($brandName && isset($grouped[$brandName])) {
            $filteredBrand = $brandName;
            $filteredProducts = array_values($grouped[$brandName]);
        } elseif ($brandId > 0) {
            foreach ($grouped as $bN => $pList) {
                foreach ($pList as $pItem) {
                    if ((int)$pItem['brand_id'] === $brandId) {
                        $filteredBrand = $bN;
                        $filteredProducts[] = $pItem;
                    }
                }
            }
        } elseif (!empty($grouped)) {
            // Default to all products for this seller
            foreach ($grouped as $bN => $pList) {
                foreach ($pList as $pItem) {
                    $filteredProducts[] = $pItem;
                }
            }
        }

        foreach ($filteredProducts as $fp) {
            foreach ($fp['variants'] as $v) {
                $flatVariants[] = $v;
            }
        }

        $pendingProducts = Session::get('pending_supplier_products_' . $sellerId, []);
        if (empty($flatVariants) && !empty($pendingProducts)) {
            $synthVariants = [];
            foreach ($pendingProducts as $pp) {
                $synthVariants[] = [
                    'product_name'      => $pp['product_name'],
                    'sku'               => $pp['sku'],
                    'size_name'         => $pp['size_name'] ?: 'Free',
                    'color_name'        => $pp['color_name'] ?: 'Std',
                    'mrp'               => (float)($pp['mrp'] ?? 0),
                    'selling_price'     => (float)($pp['selling_price'] ?? 0),
                    'cost_price'        => (float)($pp['cost_price'] ?? 0),
                    'quantity'          => (int)($pp['quantity'] ?? 1),
                    'qr_type'           => $pp['qr_type'] ?? 'INDIVIDUAL',
                    'pack_quantity'     => (int)($pp['pack_quantity'] ?? 1),
                    'is_complementary'  => (int)($pp['is_complementary'] ?? 0),
                    'series_start_code' => $pp['series_start_code'] ?? '',
                    'series_end_code'   => $pp['series_end_code'] ?? '',
                ];
            }
            $flatVariants = $synthVariants;
            if (empty($filteredProducts) && !empty($synthVariants)) {
                $filteredProducts[] = [
                    'product_name' => $synthVariants[0]['product_name'],
                    'variants'     => $synthVariants
                ];
            }
        }

        echo json_encode([
            'success'          => true,
            'seller_id'        => $sellerId,
            'brands'           => $allBrands,
            'selected_brand'   => $filteredBrand,
            'products'         => $filteredProducts,
            'variants'         => $flatVariants,
            'pending_products' => $pendingProducts,
            'all_grouped'      => $grouped,
        ]);
        exit;
    }

    /**
     * Edit Seller Form.
     */
    public function editSeller(int $id): void {
        if (!hasPermission('wh_sellers', 'edit')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }
        $seller = Database::fetchOne("SELECT * FROM warehouse_suppliers WHERE id = ?", [$id]);
        if (!$seller) {
            Session::flash('error', 'Seller not found.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        // Get all products and variants associated with this supplier
        $products = Database::fetchAll(
            "SELECT p.id as product_id, p.name as product_name, p.code as product_code,
                    pv.id as variant_id, pv.sku, pv.purchase_price, pv.mrp, pv.selling_price,
                    pv.size_id, pv.color_id, pv.qr_type, pv.series_start_code, pv.series_end_code, pv.pack_quantity,
                    sz.name as size_name, cl.name as color_name,
                    COALESCE((
                        SELECT SUM(sb.quantity) FROM stock_balances sb 
                        WHERE sb.variant_id = pv.id AND sb.stock_state = 'AVAILABLE' AND sb.location_type = 'WAREHOUSE'
                    ), 0) as current_stock,
                    COALESCE((
                        SELECT SUM(pi.quantity) FROM purchase_items pi
                        JOIN purchase_orders po ON pi.purchase_order_id = po.id
                        WHERE pi.variant_id = pv.id
                    ), 0) as total_bought_qty
             FROM products p
             JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
             LEFT JOIN sizes sz ON pv.size_id = sz.id
             LEFT JOIN colors cl ON pv.color_id = cl.id
             WHERE (p.warehouse_supplier_id = ? OR p.seller_id = ?)
             ORDER BY p.name ASC, pv.sku ASC",
            [$id, $id]
        );

        Response::view('warehouse.sellers.edit', [
            'pageTitle'    => 'Edit Seller: ' . $seller['name'],
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-sellers',
            'seller'       => $seller,
            'products'     => $products,
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Sellers', 'url' => url('warehouse/sellers')],
                ['label' => 'Edit ' . $seller['name']],
            ],
        ]);
    }

    /**
     * Update Seller.
     */
    public function updateSeller(int $id): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        if (!hasPermission('wh_sellers', 'edit')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM warehouse_suppliers WHERE id = ?", [$id]);
        if (!$seller) {
            Session::flash('error', 'Seller not found.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? 'Tamil Nadu');
        $paymentTerms = trim($_POST['payment_terms'] ?? '');
        $materialType = trim($_POST['material_type'] ?? '');
        $sellerType = trim($_POST['seller_type'] ?? 'PRODUCT_SELLER');
        $notes = trim($_POST['notes'] ?? ($seller['notes'] ?? ''));
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        $documentUrl = $seller['document_url'] ?? null;
        if (!empty($_FILES['bill_image']['name']) && $_FILES['bill_image']['error'] === UPLOAD_ERR_OK) {
            require_once BASE_PATH . '/app/Services/CloudinaryService.php';
            $uploadedUrl = CloudinaryService::upload($_FILES['bill_image'], 'grm_inventory/bills', 'image');
            if ($uploadedUrl) {
                $documentUrl = $uploadedUrl;
            }
        }

        Database::beginTransaction();
        try {
            Database::execute(
                "UPDATE warehouse_suppliers 
                 SET name = ?, company = ?, phone = ?, email = ?, gst_number = ?, address = ?, city = ?, state = ?, payment_terms = ?, material_type = ?, seller_type = ?, document_url = ?, notes = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ?",
                [$name, $company ?: null, $phone, $email ?: null, $gstNumber ?: null, $address ?: null, $city ?: null, $state ?: null, $paymentTerms ?: null, $materialType ?: null, $sellerType, $documentUrl, $notes ?: null, $isActive, $id]
            );

            // 1. Process Existing Product Variants Updates
            $existingVariantIds = $_POST['existing_variant_id'] ?? [];
            $existingProductNames = $_POST['existing_product_name'] ?? [];
            $existingSkus = $_POST['existing_sku'] ?? [];
            $existingColors = $_POST['existing_color'] ?? [];
            $existingSizes = $_POST['existing_size'] ?? [];
            $existingCosts = $_POST['existing_cost'] ?? [];
            $existingMrps = $_POST['existing_mrp'] ?? [];
            $existingSellPrices = $_POST['existing_selling_price'] ?? [];
            $existingQrTypes = $_POST['existing_qr_type'] ?? [];
            $existingPackQtys = $_POST['existing_pack_quantity'] ?? [];
            $existingStartCodes = $_POST['existing_series_start_code'] ?? [];
            $existingEndCodes = $_POST['existing_series_end_code'] ?? [];
            $existingStocks = $_POST['existing_stock'] ?? [];

            if (!empty($existingVariantIds) && is_array($existingVariantIds)) {
                foreach ($existingVariantIds as $idx => $vId) {
                    $vId = (int)$vId;
                    if ($vId <= 0) continue;

                    $pName = trim($existingProductNames[$idx] ?? '');
                    $pSku = trim($existingSkus[$idx] ?? '');
                    $pColor = trim($existingColors[$idx] ?? '');
                    $pSize = trim($existingSizes[$idx] ?? '');
                    $costVal = max(0.0, (float)($existingCosts[$idx] ?? 0.00));
                    $mrpVal = max($costVal, (float)($existingMrps[$idx] ?? $costVal));
                    $sellPriceVal = max(0.0, (float)($existingSellPrices[$idx] ?? $costVal));
                    $qrType = ($existingQrTypes[$idx] ?? 'INDIVIDUAL') === 'SERIES_BATCH' ? 'SERIES_BATCH' : 'INDIVIDUAL';
                    $packQty = max(1, (int)($existingPackQtys[$idx] ?? 1));
                    $startCode = !empty($existingStartCodes[$idx]) ? trim($existingStartCodes[$idx]) : ($qrType === 'SERIES_BATCH' ? $pSku . '-001' : null);
                    $endCode = !empty($existingEndCodes[$idx]) ? trim($existingEndCodes[$idx]) : ($qrType === 'SERIES_BATCH' ? $pSku . '-' . sprintf('%03d', $packQty) : null);

                    $colorId = null;
                    $sizeId = null;
                    if (!empty($pColor)) {
                        $cRow = Database::fetchOne("SELECT id FROM colors WHERE UPPER(name) = ? LIMIT 1", [strtoupper($pColor)]);
                        if ($cRow) {
                            $colorId = (int)$cRow['id'];
                        } else {
                            try {
                                Database::execute("INSERT INTO colors (name, hex_code, is_active, created_at) VALUES (?, '#4f46e5', 1, NOW())", [strtoupper($pColor)]);
                                $colorId = (int)Database::lastInsertId();
                            } catch (\Throwable $e) {}
                        }
                    }
                    if (!empty($pSize)) {
                        $sRow = Database::fetchOne("SELECT id FROM sizes WHERE UPPER(name) = ? OR UPPER(code) = ? LIMIT 1", [strtoupper($pSize), strtoupper($pSize)]);
                        if ($sRow) {
                            $sizeId = (int)$sRow['id'];
                        } else {
                            try {
                                $sCode = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($pSize)), 0, 10) ?: strtoupper($pSize);
                                Database::execute("INSERT INTO sizes (name, code, is_active, created_at) VALUES (?, ?, 1, NOW())", [strtoupper($pSize), $sCode]);
                                $sizeId = (int)Database::lastInsertId();
                            } catch (\Throwable $e) {}
                        }
                    }

                    Database::execute(
                        "UPDATE product_variants 
                         SET sku = ?, qr_code = ?, purchase_price = ?, production_cost = ?, mrp = ?, selling_price = ?, color_id = ?, size_id = ?, qr_type = ?, series_start_code = ?, series_end_code = ?, pack_quantity = ?, updated_at = NOW()
                         WHERE id = ?",
                        [$pSku, $pSku, $costVal, $costVal, $mrpVal, $sellPriceVal, $colorId, $sizeId, $qrType, $startCode, $endCode, $packQty, $vId]
                    );

                    if (!empty($pName)) {
                        $vRow = Database::fetchOne("SELECT product_id FROM product_variants WHERE id = ?", [$vId]);
                        if ($vRow && !empty($vRow['product_id'])) {
                            Database::execute("UPDATE products SET name = ?, updated_at = NOW() WHERE id = ?", [$pName, $vRow['product_id']]);
                        }
                    }

                    // Task 3: Update live warehouse stock level if entered
                    if (isset($existingStocks[$idx]) && $existingStocks[$idx] !== '') {
                        $targetStock = max(0.0, (float)$existingStocks[$idx]);
                        $whId = function_exists('currentWarehouseId') && currentWarehouseId() ? currentWarehouseId() : 1;
                        $currBalanceRow = Database::fetchOne(
                            "SELECT quantity FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = ? AND stock_state = 'AVAILABLE'",
                            [$vId, $whId]
                        );
                        $currentQty = (float)($currBalanceRow['quantity'] ?? 0);
                        if ($targetStock != $currentQty) {
                            Database::execute(
                                "INSERT INTO stock_balances (variant_id, location_type, location_id, stock_state, quantity, last_updated, created_at)
                                 VALUES (?, 'WAREHOUSE', ?, 'AVAILABLE', ?, NOW(), NOW())
                                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), last_updated = NOW()",
                                [$vId, $whId, $targetStock]
                            );
                            $diff = $targetStock - $currentQty;
                            try {
                                StockTransaction::create([
                                    'variant_id'       => $vId,
                                    'location_type'    => 'WAREHOUSE',
                                    'location_id'      => $whId,
                                    'stock_state'      => 'AVAILABLE',
                                    'transaction_type' => 'SUPPLIER_EDIT_STOCK_UPDATE',
                                    'quantity'         => $diff,
                                    'balance_after'    => $targetStock,
                                    'reference_type'   => 'warehouse_suppliers',
                                    'reference_id'     => $id,
                                    'user_id'          => Session::get('user_id') ?? 1,
                                    'notes'            => "Stock updated from supplier edit (was {$currentQty}, now {$targetStock})"
                                ]);
                            } catch (\Throwable $te) {}

                            // Sync master product quantity
                            $vProd = Database::fetchOne("SELECT product_id FROM product_variants WHERE id = ?", [$vId]);
                            if ($vProd && !empty($vProd['product_id'])) {
                                Database::execute(
                                    "UPDATE products p
                                     SET p.quantity = (
                                         SELECT COALESCE(SUM(sb.quantity), 0)
                                         FROM stock_balances sb
                                         JOIN product_variants pv ON sb.variant_id = pv.id
                                         WHERE pv.product_id = p.id AND sb.location_type = 'WAREHOUSE' AND sb.stock_state = 'AVAILABLE'
                                     )
                                     WHERE p.id = ?",
                                    [$vProd['product_id']]
                                );
                            }
                        }
                    }
                }
            }

            // 2. Process Newly Added Product Lines
            $newProductNames = $_POST['new_product_name'] ?? [];
            $newProductSkus = $_POST['new_product_sku'] ?? [];
            $newProductColors = $_POST['new_product_color'] ?? [];
            $newProductSizes = $_POST['new_product_size'] ?? [];
            $newQuantities = $_POST['new_quantity'] ?? [];
            $newUnitPrices = $_POST['new_unit_price'] ?? [];
            $newQrTypes = $_POST['new_product_qr_type'] ?? [];
            $newPackQtys = $_POST['new_pack_quantity'] ?? [];
            $newStartCodes = $_POST['new_series_start_code'] ?? [];
            $newEndCodes = $_POST['new_series_end_code'] ?? [];
            $newIsComplementaryList = $_POST['new_is_complementary'] ?? [];

            if (!empty($newProductNames) && is_array($newProductNames)) {
                foreach ($newProductNames as $nIdx => $npName) {
                    $npName = trim($npName);
                    $npSku = trim($newProductSkus[$nIdx] ?? '');
                    $npColor = trim($newProductColors[$nIdx] ?? '');
                    $npSize = trim($newProductSizes[$nIdx] ?? '');
                    $nQty = max(1, (int)($newQuantities[$nIdx] ?? 1));
                    $nCostRate = max(0.0, (float)($newUnitPrices[$nIdx] ?? 0.00));

                    if (empty($npName)) {
                        if (!empty($npSku)) $npName = $npSku;
                        elseif (!empty($npColor) || !empty($npSize) || $nCostRate > 0) $npName = 'TP Product ' . ($nIdx + 1);
                        else continue;
                    }
                    if (empty($npSku)) {
                        $npSku = 'TP-' . strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($npName, 0, 6))) . '-' . rand(100, 999);
                    }

                    $nLineQrType = !empty($newQrTypes[$nIdx]) ? $newQrTypes[$nIdx] : 'INDIVIDUAL';
                    $nLinePackQty = !empty($newPackQtys[$nIdx]) ? (int)$newPackQtys[$nIdx] : ($nLineQrType === 'SERIES_BATCH' ? $nQty : 1);
                    $nLineStartCode = !empty($newStartCodes[$nIdx]) ? trim($newStartCodes[$nIdx]) : ($nLineQrType === 'SERIES_BATCH' ? $npSku . '-001' : null);
                    $nLineEndCode = !empty($newEndCodes[$nIdx]) ? trim($newEndCodes[$nIdx]) : ($nLineQrType === 'SERIES_BATCH' ? $npSku . '-' . sprintf('%03d', $nQty) : null);

                    $nLineIsComp = !empty($newIsComplementaryList[$nIdx]) ? 1 : 0;
                    $this->resolveOrCreateProductVariant(
                        $npName, $npSku, '', $npColor, $npSize, $nCostRate, $nCostRate, $nCostRate, $id,
                        $nLineQrType, $nLinePackQty, $nLineStartCode, $nLineEndCode, $nLineIsComp
                    );
                }
            }

            Database::commit();

            AuditService::log('WAREHOUSE', 'UPDATE_SELLER', 'warehouse_suppliers', $id, $seller, [
                'name' => $name, 'phone' => $phone, 'company' => $company, 'is_active' => $isActive
            ]);

            Session::flash('success', "Supplier '{$name}' and product catalog details updated successfully.");
            Response::redirect(url('warehouse/sellers/view/' . $id));
        } catch (Exception $e) {
            Database::rollback();
            Session::flash('error', 'Failed to update supplier: ' . $e->getMessage());
            Response::redirect(url('warehouse/sellers/edit/' . $id));
        }
    }

    /**
     * Delete / Soft Delete Seller.
     */
    public function deleteSeller(int $id): void {
        if (!hasPermission('wh_sellers', 'delete') && !hasPermission('purchases', 'delete') && !hasPermission('warehouse', 'delete')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM warehouse_suppliers WHERE id = ?", [$id]);
        if (!$seller) {
            Session::flash('error', 'Supplier not found.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        Database::beginTransaction();
        try {
            // Soft delete supplier
            Database::execute("UPDATE warehouse_suppliers SET is_active = 0, updated_at = NOW() WHERE id = ?", [$id]);

            AuditService::log('WAREHOUSE', 'DELETE_SELLER', 'warehouse_suppliers', $id, null, [
                'name' => $seller['name'],
                'company' => $seller['company'],
                'is_active' => 0
            ]);

            Database::commit();
            Session::flash('success', "Supplier '{$seller['name']}' removed successfully.");
        } catch (\Throwable $e) {
            Database::rollback();
            Session::flash('error', 'Failed to delete supplier: ' . $e->getMessage());
        }

        Response::redirect(url('warehouse/sellers'));
    }

    /**
     * Third-Party Products Buying & Stock Inward Form.
     */
    public function createThirdPartyPurchase(): void {
        if (!hasPermission('purchases', 'create') && !hasPermission('wh_sellers', 'edit') && !hasPermission('wh_sellers', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $sellers = Database::fetchAll("SELECT * FROM warehouse_suppliers WHERE is_active = 1 ORDER BY name ASC");
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.qr_code, pv.selling_price, pv.mrp, pv.purchase_price, pv.production_cost,
                    COALESCE(pv.purchase_price, pv.production_cost, 0) as cost_price,
                    p.id as product_id, p.name as product_name, p.code as product_code,
                    s.name as size_name, c.name as color_name,
                    COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM stock_balances sb
                        WHERE sb.variant_id = pv.id
                          AND sb.stock_state = 'AVAILABLE'
                          AND sb.location_type = 'WAREHOUSE'
                    ), 0) as available_stock
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             LEFT JOIN sizes s ON pv.size_id = s.id
             LEFT JOIN colors c ON pv.color_id = c.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        $preSelectedSellerId = (int)($_GET['seller_id'] ?? 0);

        Response::view('warehouse.sellers.buy', [
            'pageTitle'           => 'Buy Third-Party Products & Inward Stock',
            'activeModule'        => 'warehouse',
            'activeMenu'          => 'wh-sellers',
            'sellers'             => $sellers,
            'warehouses'          => $warehouses,
            'variants'            => $variants,
            'preSelectedSellerId' => $preSelectedSellerId,
            'breadcrumb'          => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Sellers', 'url' => url('warehouse/sellers')],
                ['label' => 'Buy Products & Inward Stock'],
            ],
        ]);
    }

    /**
     * Store Third-Party Purchase and Automatically Inward Stock to Warehouse.
     */
    public function storeThirdPartyPurchase(): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/sellers/buy'));
            return;
        }

        if (!hasPermission('purchases', 'create') && !hasPermission('wh_sellers', 'edit') && !hasPermission('wh_sellers', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $sellerId = (int)($_POST['seller_id'] ?? 0);
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_POST['warehouse_id'] ?? 0);
        $orderDate = $_POST['order_date'] ?? date('Y-m-d');
        $expectedDate = !empty($_POST['expected_date']) ? $_POST['expected_date'] : $orderDate;
        $paymentMethod = $_POST['payment_method'] ?? 'CASH';
        $paymentStatus = $_POST['payment_status'] ?? 'PAID';
        $gstType = $_POST['gst_type'] ?? 'CGST_SGST';
        $notes = trim($_POST['notes'] ?? '');

        $productNames = $_POST['product_name'] ?? [];
        $productSkus = $_POST['product_sku'] ?? [];
        $productCategories = $_POST['product_category'] ?? [];
        $productColors = $_POST['product_color'] ?? [];
        $productSizes = $_POST['product_size'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unitPrices = $_POST['unit_price'] ?? [];
        $mrps = $_POST['mrp'] ?? [];
        $sellingPrices = $_POST['selling_price'] ?? [];
        $gstPercentages = $_POST['gst_percentage'] ?? [];
        $variantIds = $_POST['variant_id'] ?? [];

        if (empty($sellerId) || empty($warehouseId)) {
            Session::flash('error', 'Please select both a valid Supplier and a Destination Warehouse.');
            Response::redirect(url('warehouse/sellers/buy'));
            return;
        }

        $seller = Database::fetchOne("SELECT * FROM warehouse_suppliers WHERE id = ?", [$sellerId]);
        if (!$seller) {
            Session::flash('error', 'Selected supplier does not exist.');
            Response::redirect(url('warehouse/sellers/buy'));
            return;
        }

        Database::beginTransaction();
        try {
            $poNumber = WarehouseService::generateSequenceNumber('purchase_orders', 'po_number', 'PO-WH');
            $grnNumber = WarehouseService::generateSequenceNumber('purchase_inwards', 'grn_number', 'GRN-WH');

            $grossSubtotal = 0.00;
            $totalCgst = 0.00;
            $totalSgst = 0.00;
            $totalIgst = 0.00;
            $grandTotal = 0.00;
            $itemsToInsert = [];

            // Process text-input based product items
            if (is_array($productNames) && !empty($productNames)) {
                foreach ($productNames as $idx => $pName) {
                    $pName = trim($pName);
                    if (empty($pName)) continue;

                    $pSku = trim($productSkus[$idx] ?? '');
                    $pCat = trim($productCategories[$idx] ?? '');
                    $pColor = trim($productColors[$idx] ?? '');
                    $pSize = trim($productSizes[$idx] ?? '');
                    $qty = max(1, (int)($quantities[$idx] ?? 1));
                    $costRate = max(0.01, (float)($unitPrices[$idx] ?? 0.00));
                    $mrpVal = max($costRate, (float)($mrps[$idx] ?? $costRate));
                    $sellPriceVal = max(0.00, (float)($sellingPrices[$idx] ?? $costRate));
                    $gstPct = max(0.00, (float)($gstPercentages[$idx] ?? 5.00));

                    $vId = $this->resolveOrCreateProductVariant(
                        $pName, $pSku, $pCat, $pColor, $pSize, $costRate, $mrpVal, $sellPriceVal, $sellerId
                    );

                    if ($vId <= 0) continue;

                    $lineGross = $qty * $costRate;
                    $grossSubtotal += $lineGross;

                    if ($gstType === 'IGST') {
                        $lineIgst = ($lineGross * $gstPct) / 100;
                        $lineCgst = 0.00;
                        $lineSgst = 0.00;
                    } else {
                        $halfGst = $gstPct / 2;
                        $lineCgst = ($lineGross * $halfGst) / 100;
                        $lineSgst = ($lineGross * $halfGst) / 100;
                        $lineIgst = 0.00;
                    }

                    $totalCgst += $lineCgst;
                    $totalSgst += $lineSgst;
                    $totalIgst += $lineIgst;

                    $lineTotal = $lineGross + $lineCgst + $lineSgst + $lineIgst;
                    $grandTotal += $lineTotal;

                    $itemsToInsert[] = [
                        'variant_id'          => $vId,
                        'quantity'            => $qty,
                        'unit_price'          => $costRate,
                        'discount_percentage' => 0.00,
                        'gst_percentage'      => $gstPct,
                        'cgst_amount'         => $lineCgst,
                        'sgst_amount'         => $lineSgst,
                        'igst_amount'         => $lineIgst,
                        'total_amount'        => $lineTotal,
                        'mrp'                 => $mrpVal,
                    ];
                }
            } elseif (is_array($variantIds) && !empty($variantIds)) {
                // Fallback for legacy variant IDs
                foreach ($variantIds as $idx => $vId) {
                    $vId = (int)$vId;
                    if ($vId <= 0) continue;

                    $qty = max(1, (int)($quantities[$idx] ?? 1));
                    $price = max(0.00, (float)($unitPrices[$idx] ?? 0.00));
                    $mrp = max($price, (float)($mrps[$idx] ?? $price));
                    $gstPct = max(0.00, (float)($gstPercentages[$idx] ?? 5.00));

                    $lineGross = $qty * $price;
                    $grossSubtotal += $lineGross;

                    if ($gstType === 'IGST') {
                        $lineIgst = ($lineGross * $gstPct) / 100;
                        $lineCgst = 0.00;
                        $lineSgst = 0.00;
                    } else {
                        $halfGst = $gstPct / 2;
                        $lineCgst = ($lineGross * $halfGst) / 100;
                        $lineSgst = ($lineGross * $halfGst) / 100;
                        $lineIgst = 0.00;
                    }

                    $totalCgst += $lineCgst;
                    $totalSgst += $lineSgst;
                    $totalIgst += $lineIgst;

                    $lineTotal = $lineGross + $lineCgst + $lineSgst + $lineIgst;
                    $grandTotal += $lineTotal;

                    $itemsToInsert[] = [
                        'variant_id'          => $vId,
                        'quantity'            => $qty,
                        'unit_price'          => $price,
                        'discount_percentage' => 0.00,
                        'gst_percentage'      => $gstPct,
                        'cgst_amount'         => $lineCgst,
                        'sgst_amount'         => $lineSgst,
                        'igst_amount'         => $lineIgst,
                        'total_amount'        => $lineTotal,
                        'mrp'                 => $mrp,
                    ];
                }
            }

            if (empty($itemsToInsert)) {
                throw new Exception('Please enter at least one product item with name and rate.');
            }

            // Handle Bill / Invoice Screenshot Upload
            $billUrl = null;
            $billPublicId = null;
            $fileInput = !empty($_FILES['bill_image']['name']) ? $_FILES['bill_image'] : (!empty($_FILES['payment_screenshot']['name']) ? $_FILES['payment_screenshot'] : null);
            if ($fileInput && ($fileInput['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                require_once BASE_PATH . '/app/Services/CloudinaryService.php';
                $uploadedUrl = CloudinaryService::upload(
                    $fileInput,
                    'grm_inventory/bills',
                    'image'
                );
                if ($uploadedUrl) {
                    $billUrl = $uploadedUrl;
                    $billPublicId = CloudinaryService::extractPublicId($uploadedUrl) ?? ('bill_' . time());
                }
            }

            // Create Purchase Order (status PENDING - Admin inward will credit stock)
            $poId = PurchaseOrder::create([
                'po_number'                    => $poNumber,
                'customer_id'                  => null,
                'seller_id'                    => null,
                'warehouse_supplier_id'        => $sellerId,
                'warehouse_id'                 => $warehouseId,
                'order_date'                   => $orderDate,
                'expected_date'                => $expectedDate,
                'subtotal'                     => $grossSubtotal,
                'discount_amount'              => 0.00,
                'cgst_amount'                  => $totalCgst,
                'sgst_amount'                  => $totalSgst,
                'igst_amount'                  => $totalIgst,
                'total_amount'                 => $grandTotal,
                'payment_status'               => $paymentStatus,
                'payment_method'               => $paymentMethod,
                'payment_screenshot_url'       => $billUrl,
                'payment_screenshot_public_id' => $billPublicId,
                'status'                       => 'DRAFT',
                'notes'                        => $notes,
                'created_by'                   => Session::userId(),
            ]);

            // Insert Purchase Items into Catalog & Purchase Log
            foreach ($itemsToInsert as $item) {
                $item['purchase_order_id'] = $poId;
                PurchaseItem::create($item);
            }

            AuditService::log('WAREHOUSE', 'CREATE_THIRD_PARTY_PURCHASE_DRAFT', 'purchase_orders', $poId, null, [
                'po_number'    => $poNumber,
                'seller_id'    => $sellerId,
                'seller_name'  => $seller['name'],
                'warehouse_id' => $warehouseId,
                'total_amount' => $grandTotal,
                'items_count'  => count($itemsToInsert),
                'status'       => 'PENDING',
            ]);

            Database::commit();

            Session::flash('success', "Third-Party Purchase #{$poNumber} recorded successfully for {$seller['name']}! " . count($itemsToInsert) . " product(s) added to catalog. Stock is pending inward. You can view QR codes and click 'Inward Stock' to add units to warehouse inventory.");
            Response::redirect(url('warehouse/sellers/view/' . $sellerId));
        } catch (Exception $e) {
            Database::rollback();
            Session::flash('error', 'Failed to record purchase: ' . $e->getMessage());
            Response::redirect(url('warehouse/sellers/buy'));
        }
    }

    /**
     * Admin Inward Stock for Third-Party Supplier Bill / Purchase Order
     */
    public function inwardSellerBill(int $poId): void {
        if (!CSRF::validateRequest()) {
            Session::flash('error', 'Invalid security token.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        if (!hasPermission('purchases', 'receive') && !hasPermission('inward', 'verify') && !hasPermission('inward', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $po = Database::fetchOne(
            "SELECT po.*, s.name as seller_name, s.company as seller_company 
             FROM purchase_orders po 
             LEFT JOIN warehouse_suppliers s ON (po.warehouse_supplier_id = s.id OR po.seller_id = s.id) 
             WHERE po.id = ?",
            [$poId]
        );

        if (!$po) {
            Session::flash('error', 'Purchase Bill not found.');
            Response::redirect(url('warehouse/sellers'));
            return;
        }

        $sellerId = $po['warehouse_supplier_id'] ?: $po['seller_id'];

        if ($po['status'] === 'RECEIVED') {
            Session::flash('warning', "Purchase Order #{$po['po_number']} has already been inwarded into stock.");
            Response::redirect(url('warehouse/sellers/view/' . $sellerId));
            return;
        }

        $items = PurchaseItem::getByPurchaseOrder($poId);
        if (empty($items)) {
            Session::flash('error', 'No product items found in this purchase bill.');
            Response::redirect(url('warehouse/sellers/view/' . $sellerId));
            return;
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        if ($scopedWarehouseId !== null) {
            $warehouseId = $scopedWarehouseId;
        } else {
            $warehouseId = (int)($_POST['warehouse_id'] ?? $po['warehouse_id']);
            if ($warehouseId <= 0) {
                $defaultWh = Database::fetchOne("SELECT id FROM warehouses WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
                $warehouseId = $defaultWh ? (int)$defaultWh['id'] : 1;
            }
        }

        $whName = Database::fetchOne("SELECT name FROM warehouses WHERE id = ?", [$warehouseId])['name'] ?? ('Warehouse #' . $warehouseId);

        Database::beginTransaction();
        try {
            $grnNumber = WarehouseService::generateSequenceNumber('purchase_inwards', 'grn_number', 'GRN-WH');
            $receivedDate = date('Y-m-d');
            $userId = Session::userId() ?: 1;

            $inwardId = PurchaseInward::create([
                'purchase_order_id' => $poId,
                'grn_number'        => $grnNumber,
                'warehouse_id'      => $warehouseId,
                'received_date'     => $receivedDate,
                'received_by'       => $userId,
                'verified_by'       => $userId,
                'notes'             => "Third-party stock inward by Admin for PO #{$po['po_number']}. " . ($po['notes'] ?? ''),
            ]);

            $totalInwardQty = 0;
            foreach ($items as $item) {
                $qty = (float)$item['quantity'];
                if ($qty <= 0) continue;

                $totalInwardQty += $qty;

                PurchaseInwardItem::create([
                    'purchase_inward_id' => $inwardId,
                    'variant_id'         => $item['variant_id'],
                    'ordered_qty'        => $qty,
                    'received_qty'       => $qty,
                    'damaged_qty'        => 0,
                    'accepted_qty'       => $qty,
                    'location_id'        => null,
                    'notes'              => 'Admin Verified Third-Party Stock Inward',
                ]);

                // Increase Available Stock in Warehouse
                StockService::increase(
                    variantId: (int)$item['variant_id'],
                    locationType: 'WAREHOUSE',
                    locationId: $warehouseId,
                    stockState: 'AVAILABLE',
                    quantity: $qty,
                    transactionType: 'PURCHASE_GRN',
                    referenceType: 'purchase_orders',
                    referenceId: $poId,
                    userId: $userId,
                    notes: "Stock Inward for PO #{$po['po_number']} ({$po['seller_name']})"
                );
            }

            // Update Purchase Order status to RECEIVED
            PurchaseOrder::updateStatus($poId, 'RECEIVED');

            AuditService::log('WAREHOUSE', 'ADMIN_INWARD_STOCK', 'purchase_orders', $poId, null, [
                'po_number'    => $po['po_number'],
                'grn_number'   => $grnNumber,
                'warehouse_id' => $warehouseId,
                'warehouse'    => $whName,
                'total_qty'    => $totalInwardQty,
                'items_count'  => count($items),
            ]);

            Database::commit();

            Session::flash('success', "Stock Inward Successful! Inwarded {$totalInwardQty} unit(s) across " . count($items) . " item(s) for PO #{$po['po_number']} into {$whName}. GRN #{$grnNumber} generated.");
            Response::redirect(url('warehouse/sellers/view/' . $sellerId));
        } catch (Exception $e) {
            Database::rollback();
            Session::flash('error', 'Failed to inward stock: ' . $e->getMessage());
            Response::redirect(url('warehouse/sellers/view/' . $sellerId));
        }
    }

    // =========================================================================
    // SHOP REQUESTS, WHATSAPP & WEBSITE CHANNEL ORDERS
    // =========================================================================

    public function shopRequestList(): void {
        if (!hasPermission('shop_requests', 'view') && !hasPermission('allocations', 'view') && !hasPermission('warehouse', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = trim($_GET['status']);
        }
        if (!empty($_GET['destination_id'])) {
            $filters['destination_id'] = trim($_GET['destination_id']);
        } elseif (!empty($_GET['shop_id'])) {
            $filters['destination_id'] = 'shop_' . (int)$_GET['shop_id'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = trim($_GET['search']);
        }

        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $whWhere = $scopedWarehouseId ? " AND (wa.warehouse_id = {$scopedWarehouseId} OR wa.warehouse_id IS NULL)" : "";
        $allRequests = Database::fetchAll(
            "SELECT wa.*, 
                    w.name as warehouse_name,
                    s.name as shop_name, s.code as shop_code,
                    sc.name as channel_name, sc.code as channel_code, sc.channel_type,
                    CASE 
                        WHEN wa.allocation_number LIKE 'IND-%' OR (wa.destination_type = 'SHOP' AND wa.allocation_number LIKE 'IND-%') THEN 'SHOP'
                        WHEN wa.destination_type = 'SHOP' AND wa.allocation_number LIKE 'IND-%' THEN 'SHOP'
                        WHEN wa.destination_type = 'CHANNEL' AND sc.channel_type = 'WHATSAPP' THEN 'WHATSAPP'
                        WHEN wa.destination_type = 'CHANNEL' AND sc.channel_type = 'WEBSITE' THEN 'WEBSITE'
                        ELSE 'SHOP'
                    END as request_type,
                    (SELECT COUNT(*) FROM allocation_items WHERE allocation_id = wa.id) as item_count,
                    (SELECT COALESCE(SUM(requested_qty), 0) FROM allocation_items WHERE allocation_id = wa.id) as total_requested_qty,
                    (SELECT COALESCE(SUM(dispatched_qty), 0) FROM allocation_items WHERE allocation_id = wa.id) as total_dispatched_qty,
                    (SELECT COALESCE(SUM(ai.requested_qty * COALESCE(pv.selling_price, pv.mrp, 0)), 0) 
                     FROM allocation_items ai 
                     JOIN product_variants pv ON ai.variant_id = pv.id 
                     WHERE ai.allocation_id = wa.id) as total_request_value,
                    u.full_name as requested_by_name
             FROM warehouse_allocations wa
             LEFT JOIN warehouses w ON wa.warehouse_id = w.id
             LEFT JOIN shops s ON (wa.shop_id = s.id OR (wa.destination_type = 'SHOP' AND wa.destination_id = s.id))
             LEFT JOIN sales_channels sc ON (wa.destination_type = 'CHANNEL' AND wa.destination_id = sc.id)
             LEFT JOIN users u ON wa.requested_by = u.id
             WHERE (wa.allocation_number LIKE 'IND-%' 
                OR (wa.destination_type = 'CHANNEL' AND sc.channel_type IN ('WHATSAPP', 'WEBSITE')))$whWhere
             ORDER BY wa.created_at DESC"
        );

        // Separate by Channel Type before search filtering for accurate KPI metrics
        $allShopList = array_values(array_filter($allRequests, fn($r) => $r['request_type'] === 'SHOP' || str_starts_with($r['allocation_number'], 'IND-')));
        $allWhatsappList = array_values(array_filter($allRequests, fn($r) => $r['request_type'] === 'WHATSAPP'));
        $allWebsiteList = array_values(array_filter($allRequests, fn($r) => $r['request_type'] === 'WEBSITE'));

        // KPI Metrics calculation
        $shopStats = [
            'total_count'   => count($allShopList),
            'total_pcs'     => array_sum(array_column($allShopList, 'total_requested_qty')),
            'total_value'   => array_sum(array_column($allShopList, 'total_request_value')),
            'pending_count' => count(array_filter($allShopList, fn($r) => in_array($r['status'], ['REQUESTED', 'PENDING']))),
        ];

        $whatsappStats = [
            'total_count'   => count($allWhatsappList),
            'total_pcs'     => array_sum(array_column($allWhatsappList, 'total_requested_qty')),
            'total_value'   => array_sum(array_column($allWhatsappList, 'total_request_value')),
            'pending_count' => count(array_filter($allWhatsappList, fn($r) => in_array($r['status'], ['REQUESTED', 'PENDING']))),
        ];

        $websiteStats = [
            'total_count'   => count($allWebsiteList),
            'total_pcs'     => array_sum(array_column($allWebsiteList, 'total_requested_qty')),
            'total_value'   => array_sum(array_column($allWebsiteList, 'total_request_value')),
            'pending_count' => count(array_filter($allWebsiteList, fn($r) => in_array($r['status'], ['REQUESTED', 'PENDING']))),
        ];

        // Apply filters
        $filterList = function($list) use ($filters) {
            return array_values(array_filter($list, function($r) use ($filters) {
                if (!empty($filters['status']) && $r['status'] !== $filters['status']) return false;
                if (!empty($filters['destination_id'])) {
                    if (str_starts_with($filters['destination_id'], 'shop_')) {
                        $sid = (int)str_replace('shop_', '', $filters['destination_id']);
                        if ((int)($r['shop_id'] ?? $r['destination_id'] ?? 0) !== $sid) return false;
                    } elseif (str_starts_with($filters['destination_id'], 'chan_')) {
                        $cid = (int)str_replace('chan_', '', $filters['destination_id']);
                        if ((int)($r['destination_id'] ?? $r['channel_id'] ?? 0) !== $cid) return false;
                    }
                }
                if (!empty($filters['search'])) {
                    $s = strtolower($filters['search']);
                    if (!str_contains(strtolower($r['allocation_number']), $s) && 
                        !str_contains(strtolower($r['shop_name'] ?? ''), $s) && 
                        !str_contains(strtolower($r['channel_name'] ?? ''), $s) && 
                        !str_contains(strtolower($r['requested_by_name'] ?? ''), $s) && 
                        !str_contains(strtolower($r['notes'] ?? ''), $s)) {
                        return false;
                    }
                }
                return true;
            }));
        };

        $shopRequests = $filterList($allShopList);
        $whatsappRequests = $filterList($allWhatsappList);
        $websiteRequests = $filterList($allWebsiteList);

        $shops = Database::fetchAll("SELECT * FROM shops WHERE is_active = 1 ORDER BY name ASC");
        $channels = Database::fetchAll("SELECT * FROM sales_channels WHERE is_active = 1 ORDER BY channel_type ASC, name ASC");

        $activeTab = $_GET['tab'] ?? 'shop';
        if (!in_array($activeTab, ['shop', 'whatsapp', 'website'])) {
            $activeTab = 'shop';
        }

        Response::view('warehouse.shop_requests.index', [
            'pageTitle'        => 'Stock Requests',
            'activeModule'     => 'warehouse',
            'activeMenu'       => 'wh-shop-requests',
            'breadcrumb'       => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Stock Requests']
            ],
            'activeTab'        => $activeTab,
            'shopRequests'     => $shopRequests,
            'whatsappRequests' => $whatsappRequests,
            'websiteRequests'  => $websiteRequests,
            'shopStats'        => $shopStats,
            'whatsappStats'    => $whatsappStats,
            'websiteStats'     => $websiteStats,
            'shops'            => $shops,
            'channels'         => $channels,
        ]);
    }

    public function createShopRequest(): void {
        if (!hasPermission('shop_requests', 'create') && !hasPermission('allocations', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/shop-requests'));
            return;
        }
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $shops = function_exists('getScopedShops') ? getScopedShops() : Database::fetchAll("SELECT * FROM shops WHERE is_active = 1 ORDER BY name ASC");
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Warehouse::getAll(['is_active' => 1]);
        $variants = Database::fetchAll(
            "SELECT pv.id, pv.sku, pv.mrp, pv.selling_price, p.name as product_name,
                    COALESCE((
                        SELECT SUM(quantity) FROM stock_balances 
                        WHERE variant_id = pv.id AND stock_state = 'AVAILABLE' AND location_type = 'WAREHOUSE'
                    ), 0) as stock_qty
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             WHERE pv.is_active = 1
             ORDER BY p.name ASC, pv.sku ASC"
        );

        Response::view('warehouse.shop_requests.create', [
            'pageTitle'    => 'Create Shop Stock Request',
            'activeModule' => 'warehouse',
            'activeMenu'   => 'wh-shop-requests',
            'breadcrumb'   => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Shop Requests', 'url' => url('warehouse/shop-requests')],
                ['label' => 'New Shop Request']
            ],
            'shops'        => $shops,
            'warehouses'   => $warehouses,
            'variants'     => $variants,
        ]);
    }

    public function storeShopRequest(): void {
        CSRF::verify();
        if (!hasPermission('shop_requests', 'create') && !hasPermission('allocations', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/shop-requests'));
            return;
        }

        $shopId = (int)($_POST['shop_id'] ?? 0);
        $scopedWarehouseId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = $scopedWarehouseId !== null ? $scopedWarehouseId : (int)($_POST['warehouse_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $items = $_POST['items'] ?? [];

        if (!$shopId || !$warehouseId) {
            Session::flash('error', 'Requesting shop and source warehouse are required.');
            Response::redirect(url('warehouse/shop-requests/create'));
            return;
        }

        if (empty($items) || !is_array($items)) {
            Session::flash('error', 'Please select at least one product item for the stock request.');
            Response::redirect(url('warehouse/shop-requests/create'));
            return;
        }

        $allocationNumber = WarehouseAllocation::generateNumber();
        $allocationId = WarehouseAllocation::create([
            'allocation_number' => $allocationNumber,
            'warehouse_id'      => $warehouseId,
            'destination_type'  => 'SHOP',
            'destination_id'    => $shopId,
            'shop_id'           => $shopId,
            'requested_by'      => Auth::id() ?? 1,
            'status'            => 'REQUESTED',
            'notes'             => $notes ?: 'Shop Stock Requisition',
        ]);

        foreach ($items as $itm) {
            $variantId = (int)($itm['variant_id'] ?? 0);
            $qty = (int)($itm['requested_qty'] ?? 0);
            if ($variantId > 0 && $qty > 0) {
                AllocationItem::create([
                    'allocation_id' => $allocationId,
                    'variant_id'    => $variantId,
                    'requested_qty' => $qty,
                    'approved_qty'  => 0,
                    'picked_qty'    => 0,
                    'packed_qty'    => 0,
                    'dispatched_qty'=> 0,
                ]);
            }
        }

        Session::flash('success', "Shop Stock Request #{$allocationNumber} submitted successfully.");
        Response::redirect(url('warehouse/shop-requests'));
    }

    public function viewShopRequest(int $id): void {
        $_GET['from'] = 'shop-requests';
        $this->viewAllocation($id);
    }

    // ==========================================
    // WAREHOUSE EXPENSE MANAGEMENT & OUTFLOW
    // ==========================================

    public function expensesIndex(): void {
        if (!hasPermission('warehouse_expenses', 'view') && !hasPermission('expenses', 'view')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/dashboard'));
            return;
        }
        require_once BASE_PATH . '/app/Services/ExpenseService.php';

        $scopedWhId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        $warehouseId = !empty($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : ($scopedWhId ?: 0);
        $warehouses = function_exists('getScopedWarehouses') ? getScopedWarehouses() : Database::fetchAll("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC");
        
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $category = $_GET['category'] ?? 'all';
        $paymentMethod = $_GET['payment_method'] ?? 'all';
        $search = trim($_GET['search'] ?? '');

        $filters = [
            'search'         => $search,
            'category'       => $category,
            'payment_method' => $paymentMethod,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'limit'          => 150
        ];

        $expenses = ExpenseService::getExpenses('WAREHOUSE', $warehouseId > 0 ? $warehouseId : null, $filters);
        $summary = ExpenseService::getSummary('WAREHOUSE', $warehouseId > 0 ? $warehouseId : null, $startDate, $endDate);
        $categories = ExpenseService::getCategories('WAREHOUSE');

        Response::view('warehouse.expenses.index', [
            'pageTitle'          => 'Warehouse Operational Expenses & Petty Cash',
            'activeModule'       => 'warehouse',
            'activeMenu'         => 'wh-expenses',
            'breadcrumb'         => [
                ['label' => 'Warehouse', 'url' => url('warehouse/dashboard')],
                ['label' => 'Operational Expenses'],
            ],
            'expenses'           => $expenses,
            'summary'            => $summary,
            'categories'         => $categories,
            'warehouses'         => $warehouses,
            'selectedWhId'       => $warehouseId,
            'scopedWarehouseId'  => $scopedWhId,
            'startDate'          => $startDate,
            'endDate'            => $endDate,
            'filters'            => $filters,
        ]);
    }

    public function createExpense(): void {
        if (!hasPermission('warehouse_expenses', 'create') && !hasPermission('expenses', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/expenses'));
            return;
        }
        $this->expensesIndex();
    }

    public function storeExpense(): void {
        if (!hasPermission('warehouse_expenses', 'create') && !hasPermission('expenses', 'create')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/expenses'));
            return;
        }
        require_once BASE_PATH . '/app/Services/ExpenseService.php';
        $userId = Session::userId() ?? 1;

        $payload = $_POST;
        $payload['location_type'] = 'WAREHOUSE';
        $scopedWhId = function_exists('currentWarehouseId') ? currentWarehouseId() : null;
        if ($scopedWhId !== null) {
            $payload['location_id'] = $scopedWhId;
        } elseif (empty($payload['location_id'])) {
            $payload['location_id'] = 1;
        }

        try {
            $res = ExpenseService::recordExpense($payload, $userId);
            Session::flash('success', "Warehouse Expense Voucher #{$res['expense_number']} for ₹" . number_format($res['amount'], 2) . " recorded successfully.");
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect(url('warehouse/expenses'));
    }

    public function printExpenseVoucher(int $id): void {
        if (!hasPermission('warehouse_expenses', 'voucher') && !hasPermission('warehouse_expenses', 'print') && !hasPermission('warehouse_expenses', 'view') && !hasPermission('expenses', 'view')) {
            die("Access denied.");
        }
        require_once BASE_PATH . '/app/Services/ExpenseService.php';
        $expense = ExpenseService::getVoucherDetails($id);
        if (!$expense) {
            die("Warehouse expense record not found.");
        }

        Response::viewOnly('warehouse.expenses.voucher', [
            'expense' => $expense,
            'module'  => 'warehouse'
        ]);
    }

    public function deleteExpense(int $id): void {
        if (!hasPermission('warehouse_expenses', 'delete') && !hasPermission('expenses', 'delete')) {
            Session::flash('error', 'Access denied.');
            Response::redirect(url('warehouse/expenses'));
            return;
        }
        require_once BASE_PATH . '/app/Services/ExpenseService.php';
        $userId = Session::userId() ?? 1;
        ExpenseService::deleteExpense($id, $userId);
        Session::flash('success', "Expense record deleted successfully.");
        Response::redirect(url('warehouse/expenses'));
    }

    // ==========================================
    // DELEGATED WHATSAPP & WEBSITE CONTROLLER METHODS
    // (Maintained for backwards compatibility)
    // ==========================================

    public function whatsappChannelOrders(): void { (new WhatsappController())->index(); }
    public function whatsappInwardIndex(): void { (new WhatsappController())->whatsappInwardIndex(); }
    public function receiveWhatsappInward(int $id): void { (new WhatsappController())->receiveWhatsappInward($id); }
    public function whatsappAllocations(): void { (new WhatsappController())->whatsappAllocations(); }
    public function createWhatsappStockRequest(): void { (new WhatsappController())->createWhatsappStockRequest(); }
    public function storeWhatsappStockRequest(): void { (new WhatsappController())->storeWhatsappStockRequest(); }
    public function whatsappOrdersHistory(): void { (new WhatsappController())->whatsappOrdersHistory(); }
    public function whatsappReturnsIndex(): void { (new WhatsappController())->whatsappReturnsIndex(); }
    public function whatsappReturnsLookup(): void { (new WhatsappController())->whatsappReturnsLookup(); }
    public function processWhatsappReturn(): void { (new WhatsappController())->processWhatsappReturn(); }
    public function whatsappReturnInvoice(int $id): void { (new WhatsappController())->whatsappReturnInvoice($id); }
    public function whatsappReturnView(int $id): void { (new WhatsappController())->whatsappReturnView($id); }
    public function whatsappCustomers(): void { (new WhatsappController())->whatsappCustomers(); }

    public function websiteChannelOrders(): void { (new WebsiteController())->index(); }
    public function websiteInwardIndex(): void { (new WebsiteController())->websiteInwardIndex(); }
    public function receiveWebsiteInward(int $id): void { (new WebsiteController())->receiveWebsiteInward($id); }
    public function websiteAllocations(): void { (new WebsiteController())->websiteAllocations(); }
    public function createWebsiteStockRequest(): void { (new WebsiteController())->createWebsiteStockRequest(); }
    public function storeWebsiteStockRequest(): void { (new WebsiteController())->storeWebsiteStockRequest(); }
    public function websiteOrdersHistory(): void { (new WebsiteController())->websiteOrdersHistory(); }
    public function websiteReturnsIndex(): void { (new WebsiteController())->websiteReturnsIndex(); }
    public function websiteReturnsLookup(): void { (new WebsiteController())->websiteReturnsLookup(); }
    public function processWebsiteReturn(): void { (new WebsiteController())->processWebsiteReturn(); }
    public function websiteReturnInvoice(int $id): void { (new WebsiteController())->websiteReturnInvoice($id); }
    public function websiteReturnView(int $id): void { (new WebsiteController())->websiteReturnView($id); }
    public function websiteCustomers(): void { (new WebsiteController())->websiteCustomers(); }

    /**
     * Resolve an existing product variant by SKU/name, or create product + variant on-the-fly.
     * Used by storeSeller() and storeThirdPartyPurchase() when the user enters text-based product lines.
     *
     * @param string $name         Product/item name
     * @param string $sku          SKU hint (optional)
     * @param string $category     Category name hint (optional)
     * @param string $color        Color name hint (optional)
     * @param string      $size            Size name hint (optional)
     * @param float       $costPrice       Purchase / cost price
     * @param float       $mrp             MRP
     * @param float       $sellPrice       Selling price
     * @param int         $supplierId      warehouse_suppliers.id to link (optional)
     * @param string      $qrType          'INDIVIDUAL' or 'SERIES_BATCH'
     * @param int         $packQuantity    Pack quantity for series bundle (default 1)
     * @param string|null $seriesStartCode Start code for series batch (optional)
     * @param string|null $seriesEndCode   End code for series batch (optional)
     * @return int  product_variants.id, or 0 on failure
     */
    private function resolveOrCreateProductVariant(
        string  $name,
        string  $sku,
        string  $category,
        string  $color,
        string  $size,
        float   $costPrice,
        float   $mrp,
        float   $sellPrice,
        int     $supplierId = 0,
        string  $qrType = 'INDIVIDUAL',
        int     $packQuantity = 1,
        ?string $seriesStartCode = null,
        ?string $seriesEndCode = null,
        int     $isComplementary = 0
    ): int {
        $skuClean = strtoupper(trim($sku));
        $effectiveQrType = ($qrType === 'SERIES_BATCH') ? 'SERIES_BATCH' : 'INDIVIDUAL';
        $effectivePackQty = ($packQuantity > 0) ? $packQuantity : 1;

        // 1. Try to match by SKU first (exact, case-insensitive)
        if (!empty($skuClean)) {
            $existing = Database::fetchOne(
                "SELECT pv.id, pv.qr_type FROM product_variants pv WHERE UPPER(pv.sku) = ? AND pv.is_active = 1 LIMIT 1",
                [$skuClean]
            );
            if ($existing) {
                $vId = (int)$existing['id'];
                if ($effectiveQrType === 'SERIES_BATCH') {
                    $effectiveStart = $seriesStartCode ?: ($skuClean . '-001');
                    $effectiveEnd = $seriesEndCode ?: ($skuClean . '-' . sprintf('%03d', $effectivePackQty));
                    Database::execute(
                        "UPDATE product_variants SET qr_type = 'SERIES_BATCH', series_start_code = ?, series_end_code = ?, pack_quantity = ? WHERE id = ?",
                        [$effectiveStart, $effectiveEnd, $effectivePackQty, $vId]
                    );
                }
                return $vId;
            }
        }

        // 2. Try to match by product name + color + size
        $nameUpper = mb_strtoupper(trim($name));
        $colorUpper = mb_strtoupper(trim($color));
        $sizeUpper  = mb_strtoupper(trim($size));

        $colorId = null;
        $sizeId  = null;

        if (!empty($colorUpper)) {
            $cRow = Database::fetchOne("SELECT id FROM colors WHERE UPPER(name) = ? LIMIT 1", [$colorUpper]);
            if ($cRow) {
                $colorId = (int)$cRow['id'];
            } else {
                try {
                    Database::execute("INSERT INTO colors (name, hex_code, is_active, created_at) VALUES (?, '#4f46e5', 1, NOW())", [$colorUpper]);
                    $colorId = (int)Database::lastInsertId();
                } catch (\Throwable $e) {}
            }
        }
        if (!empty($sizeUpper)) {
            $sRow = Database::fetchOne("SELECT id FROM sizes WHERE UPPER(name) = ? OR UPPER(code) = ? LIMIT 1", [$sizeUpper, $sizeUpper]);
            if ($sRow) {
                $sizeId = (int)$sRow['id'];
            } else {
                try {
                    $sCode = substr(preg_replace('/[^A-Z0-9]/', '', $sizeUpper), 0, 10) ?: $sizeUpper;
                    Database::execute("INSERT INTO sizes (name, code, is_active, created_at) VALUES (?, ?, 1, NOW())", [$sizeUpper, $sCode]);
                    $sizeId = (int)Database::lastInsertId();
                } catch (\Throwable $e) {}
            }
        }

        // Try to find matching product
        $product = Database::fetchOne(
            "SELECT id FROM products WHERE UPPER(name) = ? AND is_active = 1 LIMIT 1",
            [$nameUpper]
        );

        if ($product && empty($skuClean)) {
            $productId = (int)$product['id'];
            // Try to find a variant with matching color+size
            $variantWhere = "product_id = ? AND is_active = 1";
            $variantParams = [$productId];
            if ($colorId !== null) {
                $variantWhere .= " AND color_id = ?";
                $variantParams[] = $colorId;
            }
            if ($sizeId !== null) {
                $variantWhere .= " AND size_id = ?";
                $variantParams[] = $sizeId;
            }
            $variant = Database::fetchOne("SELECT id FROM product_variants WHERE {$variantWhere} LIMIT 1", $variantParams);
            if ($variant) {
                return (int)$variant['id'];
            }
        }

        // 3. Create a new product + variant
        try {
            // Resolve or create category
            $warehouseCategoryId = null;
            if (!empty($category)) {
                $catUpper = mb_strtoupper(trim($category));
                $catRow = Database::fetchOne("SELECT id FROM warehouse_categories WHERE UPPER(name) = ? LIMIT 1", [$catUpper]);
                if ($catRow) {
                    $warehouseCategoryId = (int)$catRow['id'];
                }
            }

            // Generate a unique product code
            $count = (int)(Database::fetchOne("SELECT COUNT(*) as c FROM products")['c'] ?? 0);
            $baseCode = 'TP-' . strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($nameUpper, 0, 6))) . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
            $tries = 0;
            $code = $baseCode;
            while (Database::fetchOne("SELECT id FROM products WHERE code = ?", [$code])) {
                $tries++;
                $code = $baseCode . '-' . $tries;
            }

            if (!$product) {
                Database::execute(
                    "INSERT INTO products (name, code, warehouse_category_id, warehouse_supplier_id, source_type, gst_percentage, quantity, min_stock, max_stock, is_complementary, is_active, created_at)
                     VALUES (?, ?, ?, ?, 'EXTERNAL_PURCHASE', 5.00, 0, 0, 9999, ?, 1, NOW())",
                    [
                        $nameUpper,
                        $code,
                        $warehouseCategoryId,
                        $supplierId > 0 ? $supplierId : null,
                        $isComplementary
                    ]
                );
                $productId = (int)Database::lastInsertId();
            } else {
                $productId = (int)$product['id'];
            }

            // Build SKU for new variant
            $variantSku = !empty($skuClean) ? $skuClean : $code;
            if (empty($skuClean)) {
                if ($sizeId) {
                    $sCode = Database::fetchOne("SELECT code FROM sizes WHERE id = ?", [$sizeId])['code'] ?? $sizeId;
                    $variantSku .= '-' . $sCode;
                }
                if ($colorId) {
                    $cName = Database::fetchOne("SELECT name FROM colors WHERE id = ?", [$colorId])['name'] ?? $colorId;
                    $variantSku .= '-' . substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $cName)), 0, 4);
                }
            }

            // Ensure SKU uniqueness
            $skuTries = 0;
            $finalSku = $variantSku;
            while (Database::fetchOne("SELECT id FROM product_variants WHERE sku = ?", [$finalSku])) {
                $skuTries++;
                $finalSku = $variantSku . '-' . $skuTries;
            }

            $effectiveStart = ($effectiveQrType === 'SERIES_BATCH') ? ($seriesStartCode ?: ($finalSku . '-001')) : null;
            $effectiveEnd = ($effectiveQrType === 'SERIES_BATCH') ? ($seriesEndCode ?: ($finalSku . '-' . sprintf('%03d', $effectivePackQty))) : null;

            Database::execute(
                "INSERT INTO product_variants (product_id, size_id, color_id, sku, qr_code, mrp, purchase_price, production_cost, selling_price, qr_type, series_start_code, series_end_code, pack_quantity, is_complementary, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [$productId, $sizeId, $colorId, $finalSku, $finalSku, $mrp, $costPrice, $costPrice, $sellPrice, $effectiveQrType, $effectiveStart, $effectiveEnd, $effectivePackQty, $isComplementary]
            );
            return (int)Database::lastInsertId();

        } catch (\Throwable $e) {
            error_log('[resolveOrCreateProductVariant] Error: ' . $e->getMessage());
            return 0;
        }
    }
}