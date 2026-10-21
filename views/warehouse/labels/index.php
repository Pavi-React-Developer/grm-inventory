<?php
// views/warehouse/labels/index.php
// Unified Warehouse QR Labels & Printing Suite

$currentSourceType = $_GET['source_type'] ?? (($_GET['tab'] ?? '') === 'returns' ? 'returns' : 'tp_purchase');
if (!in_array($currentSourceType, ['returns', 'tp_purchase', 'po'])) {
    $currentSourceType = 'tp_purchase';
}

$selectedVariantId = !empty($_GET['variant_id']) ? (int)$_GET['variant_id'] : (!empty($_GET['return_variant_id']) ? (int)$_GET['return_variant_id'] : null);
$selectedSourceId  = !empty($_GET['source_id']) ? (int)$_GET['source_id'] : (!empty($_GET['return_id']) ? (int)$_GET['return_id'] : null);
$selectedSellerId  = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : null;
$selectedBrandName = trim($_GET['brand_name'] ?? '');
$selectedCatalogCode = trim($_GET['catalog_code'] ?? '');
$selectedCustomBrand = trim($_GET['custom_brand'] ?? ($_GET['brand_name'] ?? ''));
$selectedCategoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$selectedSubcategoryId = !empty($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
$selectedSubSubcategoryId = !empty($_GET['sub_subcategory_id']) ? (int)$_GET['sub_subcategory_id'] : null;
$tagType           = $_GET['tag_type'] ?? 'GOOD';
$copiesCount       = !empty($_GET['copies']) ? max(1, (int)$_GET['copies']) : 1;
$notes             = trim($_GET['reason'] ?? '');
$targetWarehouseId = !empty($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : ($warehouses[0]['id'] ?? 1);
$qrMode            = trim($_GET['qr_mode'] ?? '');
$isZeroStockRestricted = false;

// Build items to generate labels for
$generatedItems = [];
$monthStr = sprintf('%02d', date('n'));
$yearStr = date('y');

if ($currentSourceType === 'po' || $currentSourceType === 'tp_purchase') {
    if ($selectedVariantId) {
        // Priority 1: User explicitly selected a Variant / SKU
        $variantsPool = !empty($thirdPartyVariants) ? $thirdPartyVariants : $stockVariants;
        $activeVar = null;
        foreach ($variantsPool as $sv) {
            if ($sv['id'] == $selectedVariantId) {
                $activeVar = $sv;
                break;
            }
        }
        if (!$activeVar) {
            foreach ($stockVariants as $sv) {
                if ($sv['id'] == $selectedVariantId) {
                    $activeVar = $sv;
                    break;
                }
            }
        }
        if (!$activeVar) {
            $activeVar = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, p.catalog_code, p.qr_code_prefix,
                        p.brand_id, COALESCE(b.name, s.company, s.name, 'Third-Party Brand') as brand_name,
                        p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.warehouse_category_id as category_id, p.warehouse_subcategory_id as subcategory_id, p.warehouse_sub_subcategory_id as sub_subcategory_id,
                        p.source_type, p.warehouse_supplier_id as seller_id, s.name as seller_name, s.company as seller_company,
                        COALESCE(cat.name, c_old.name, '') as category_name, 
                        COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                        COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name,
                        sz.name as size_name, c.name as color_name
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
                 WHERE pv.id = ?",
                [$selectedVariantId]
            );
        }

        if ($activeVar) {
            if (!empty($activeVar['catalog_code']) && empty($selectedCatalogCode)) {
                $selectedCatalogCode = $activeVar['catalog_code'];
            }

            $availQty = (int)($activeVar['available_qty'] ?? 0);
            $effectiveStock = $availQty;

            // Validation & Restriction: 0 Product Stock Count cannot generate QR labels!
            if ($effectiveStock <= 0) {
                $isZeroStockRestricted = true;
                $copiesCount = 0;
            } else {
                $isZeroStockRestricted = false;
                if (isset($_GET['copies']) && (int)$_GET['copies'] > 0) {
                    $copiesCount = min($effectiveStock, (int)$_GET['copies']);
                } else {
                    $copiesCount = $availQty;
                }
            }
            $isSeries = ($activeVar['qr_type'] ?? '') === 'SERIES_BATCH' || $qrMode === 'SERIES_BATCH' || !empty($activeVar['is_complementary']);
            $poNumber = '';
            if ($selectedSourceId) {
                $poInfo = Database::fetchOne("SELECT po.*, s.name as seller_name, s.company as seller_company FROM purchase_orders po LEFT JOIN warehouse_suppliers s ON po.warehouse_supplier_id = s.id WHERE po.id = ?", [$selectedSourceId]);
                if ($poInfo) {
                    $poNumber = $poInfo['po_number'];
                    if (empty($selectedSellerId) && !empty($poInfo['warehouse_supplier_id'])) {
                        $selectedSellerId = (int)$poInfo['warehouse_supplier_id'];
                    }
                }
            }

            $brandOrSeller = !empty($activeVar['brand_name']) && $activeVar['brand_name'] !== 'Third-Party Brand' ? $activeVar['brand_name'] : (!empty($activeVar['seller_company']) ? $activeVar['seller_company'] : (!empty($activeVar['seller_name']) ? $activeVar['seller_name'] : 'GRM CLASSIC'));

            $mrpVal = (float)$activeVar['mrp'];
            $sellingVal = (float)($activeVar['selling_price'] ?? 0);
            if ($sellingVal <= 0) {
                $sellingVal = (float)($activeVar['purchase_price'] ?? $activeVar['production_cost'] ?? $mrpVal);
            }
            if ($sellingVal <= 0) {
                $sellingVal = $mrpVal;
            }
            $costVal = (float)($activeVar['purchase_price'] ?? $activeVar['production_cost'] ?? 0);
            $fmtMrp = ($mrpVal == (int)$mrpVal) ? (int)$mrpVal : number_format($mrpVal, 2, '.', '');
            $fmtSelling = ($sellingVal == (int)$sellingVal) ? (int)$sellingVal : number_format($sellingVal, 2, '.', '');
            $catH = implode(' › ', array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']));

            // Extract Category hierarchy 3-char prefix (e.g. WMF or NEW)
            $prodPrefix = !empty($activeVar['qr_code_prefix']) ? trim($activeVar['qr_code_prefix']) : '';
            if (empty($prodPrefix) && (!empty($activeVar['category_code']) || !empty($activeVar['subcategory_code']) || !empty($activeVar['sub_subcategory_code']))) {
                $prodPrefix = strtoupper(($activeVar['category_code'] ?? '') . ($activeVar['subcategory_code'] ?? '') . ($activeVar['sub_subcategory_code'] ?? ''));
            }
            if (empty($prodPrefix)) {
                $catParts = array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']);
                if (!empty($catParts)) {
                    $abbr = '';
                    foreach ($catParts as $cp) {
                        $words = preg_split('/\s+/', trim($cp));
                        if (!empty($words[0])) { $abbr .= strtoupper($words[0][0]); }
                    }
                    $prodPrefix = substr($abbr, 0, 3);
                }
            }
            if (empty($prodPrefix)) {
                $prodPrefix = 'NEW';
            }
            $prodPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prodPrefix));
            if (strlen($prodPrefix) > 4) {
                $prodPrefix = substr($prodPrefix, 0, 4);
            }

            // 2-digit Month + 2-digit Year (e.g. 1026 for Oct 2026)
            $monthYear = date('my');
            $baseTagPrefix = $prodPrefix . $monthYear; // e.g. NEW1026 or WMF1026

            if (!$isZeroStockRestricted && $effectiveStock > 0) {
                if ($isSeries) {
                    // Generate 1 Master QR for the entire Series / Batch
                    $packQty = !empty($activeVar['pack_quantity']) ? (int)$activeVar['pack_quantity'] : max(1, (int)($activeVar['available_qty'] ?? $copiesCount));
                    $seqPad = ($packQty > 99) ? 3 : 2;
                    $startCode = $baseTagPrefix . sprintf("%0{$seqPad}d", 1);
                    $endCode = $baseTagPrefix . sprintf("%0{$seqPad}d", $packQty);
                    $seriesRangeTag = "{$startCode} - {$endCode}";
                    $seriesQrPayload = "SERIES|{$brandOrSeller}|{$activeVar['sku']}|{$startCode}-{$endCode}|QTY:{$packQty}|MRP:{$fmtMrp}|Sell:{$fmtSelling}" . (!empty($poNumber) ? "|PO:{$poNumber}" : '');

                    $generatedItems[] = [
                        'piece_no'             => 1,
                        'total_count'          => 1,
                        'tag_code'             => $seriesRangeTag,
                        'product_name'         => $activeVar['product_name'],
                        'sku'                  => $activeVar['sku'],
                        'brand_name'           => $brandOrSeller,
                        'size_name'            => $activeVar['size_name'] ?? 'Free',
                        'color_name'           => $activeVar['color_name'] ?? 'Std',
                        'mrp'                  => $mrpVal,
                        'selling_price'        => $sellingVal,
                        'cost_price'           => $costVal,
                        'source_label'         => 'Master Series Pack: ' . $brandOrSeller . " ({$packQty} Pcs)",
                        'badge_class'          => 'bg-dark text-white',
                        'qr_payload'           => $seriesQrPayload,
                        'is_series'            => true,
                        'series_start_code'    => $startCode,
                        'series_end_code'      => $endCode,
                        'pack_quantity'        => $packQty,
                        'category_name'        => $activeVar['category_name'] ?? '',
                        'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
                        'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                        'category_hierarchy'   => $catH,
                    ];
                } else {
                    // Individual Piece QR Stickers (Category Prefix + Month/Year + Sequence Number)
                    if (isset($_GET['copies']) && (int)$_GET['copies'] > 0) {
                        $copiesCount = min($effectiveStock, (int)$_GET['copies']);
                    } elseif (!empty($activeVar['pack_quantity']) && (int)$activeVar['pack_quantity'] > 0) {
                        $copiesCount = (int)$activeVar['pack_quantity'];
                    } elseif (!empty($activeVar['available_qty']) && (int)$activeVar['available_qty'] > 0) {
                        $copiesCount = (int)$activeVar['available_qty'];
                    } else {
                        $copiesCount = 1;
                    }

                    $seqPad = ($copiesCount > 99) ? 3 : 2;
                    for ($i = 1; $i <= $copiesCount; $i++) {
                        $tagCode = $baseTagPrefix . sprintf("%0{$seqPad}d", $i);
                        $generatedItems[] = [
                            'piece_no'             => $i,
                            'total_count'          => $copiesCount,
                            'tag_code'             => $tagCode,
                            'product_name'         => $activeVar['product_name'],
                            'sku'                  => $activeVar['sku'],
                            'brand_name'           => $brandOrSeller,
                            'size_name'            => $activeVar['size_name'] ?? 'Free',
                            'color_name'           => $activeVar['color_name'] ?? 'Std',
                            'mrp'                  => $mrpVal,
                            'selling_price'        => $sellingVal,
                            'cost_price'           => $costVal,
                            'source_label'         => 'Third-Party: ' . $brandOrSeller . (!empty($poNumber) ? " (PO #{$poNumber})" : ''),
                            'badge_class'          => 'bg-warning text-dark',
                            'qr_payload'           => "{$tagCode}|{$activeVar['sku']}|MRP:{$fmtMrp}|Sell:{$fmtSelling}" . (!empty($poNumber) ? "|PO:{$poNumber}" : ''),
                            'is_series'            => false,
                            'category_name'        => $activeVar['category_name'] ?? '',
                            'subcategory_name'     => $activeVar['subcategory_name'] ?? '',
                            'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                            'category_hierarchy'   => $catH,
                        ];
                    }
                }
            } else {
                $generatedItems = [];
                $copiesCount = 0;
            }
        }
    } elseif ($selectedSourceId) {
        // Priority 2: Only PO selected, generate for all items in that PO
        $poItems = PurchaseItem::getByPurchaseOrder($selectedSourceId);
        $poInfo = Database::fetchOne("SELECT po.*, s.name as seller_name, s.company as seller_company FROM purchase_orders po LEFT JOIN warehouse_suppliers s ON po.warehouse_supplier_id = s.id WHERE po.id = ?", [$selectedSourceId]);
        $sellerLabel = !empty($poInfo['seller_company']) ? $poInfo['seller_company'] : ($poInfo['seller_name'] ?? 'Third-Party Seller');
        $seq = 1;
        foreach ($poItems as $pi) {
            $count = min(20, (int)$pi['quantity']);
            if ($count <= 0) $count = $copiesCount;
            for ($i = 1; $i <= $count; $i++) {
                $tagCode = 'TP' . $monthStr . $yearStr . sprintf("%02d", $seq++);
                $fmtMrp = ($pi['mrp'] == (int)$pi['mrp']) ? (int)$pi['mrp'] : number_format($pi['mrp'], 2, '.', '');
                $fmtPrice = ($pi['unit_price'] == (int)$pi['unit_price']) ? (int)$pi['unit_price'] : number_format($pi['unit_price'], 2, '.', '');
                $catH = implode(' › ', array_filter([$pi['category_name'] ?? '', $pi['subcategory_name'] ?? '', $pi['sub_subcategory_name'] ?? '']));
                $generatedItems[] = [
                    'piece_no'           => $i,
                    'total_count'        => $count,
                    'tag_code'           => $tagCode,
                    'product_name'       => $pi['product_name'],
                    'sku'                => $pi['sku'],
                    'brand_name'         => $sellerLabel,
                    'size_name'          => $pi['size_name'] ?? 'Free',
                    'color_name'         => $pi['color_name'] ?? 'Std',
                    'mrp'                => (float)$pi['mrp'],
                    'selling_price'      => (float)$pi['unit_price'],
                    'cost_price'         => (float)$pi['unit_price'],
                    'source_label'       => 'Third-Party: ' . $sellerLabel,
                    'badge_class'        => 'bg-warning text-dark',
                    'qr_payload'         => "TP|{$poInfo['po_number']}|{$pi['sku']}|MRP:{$fmtMrp}|Cost:{$fmtPrice}",
                    'category_name'      => $pi['category_name'] ?? '',
                    'subcategory_name'   => $pi['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $pi['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        }
    }
} elseif ($currentSourceType === 'returns') {
    if ($selectedVariantId) {
        $activeVar = null;
        foreach ($stockVariants as $sv) {
            if ($sv['id'] == $selectedVariantId) {
                $activeVar = $sv;
                break;
            }
        }
        if (!$activeVar) {
            $activeVar = Database::fetchOne(
                "SELECT pv.*, p.name as product_name, p.code as product_code, p.qr_code_prefix,
                        p.category_code, p.subcategory_code, p.sub_subcategory_code,
                        p.category_id, p.subcategory_id, p.sub_subcategory_id,
                        p.brand_id, COALESCE(b.name, 'GRM RETURN') as brand_name,
                        p.source_type, p.warehouse_supplier_id as seller_id, sz.name as size_name, c.name as color_name,
                        COALESCE(cat.name, c_old.name, '') as category_name, 
                        COALESCE(subcat.name, sc_old.name, '') as subcategory_name, 
                        COALESCE(subsubcat.name, ssc_old.name, '') as sub_subcategory_name
                 FROM product_variants pv
                 JOIN products p ON pv.product_id = p.id
                 LEFT JOIN brands b ON p.brand_id = b.id
                 LEFT JOIN warehouse_categories cat ON p.warehouse_category_id = cat.id
                 LEFT JOIN warehouse_subcategories subcat ON p.warehouse_subcategory_id = subcat.id
                 LEFT JOIN warehouse_sub_subcategories subsubcat ON p.warehouse_sub_subcategory_id = subsubcat.id
                 LEFT JOIN categories c_old ON p.category_id = c_old.id
                 LEFT JOIN subcategories sc_old ON p.subcategory_id = sc_old.id
                 LEFT JOIN sub_subcategories ssc_old ON p.sub_subcategory_id = ssc_old.id
                 LEFT JOIN sizes sz ON pv.size_id = sz.id
                 LEFT JOIN colors c ON pv.color_id = c.id
                 WHERE pv.id = ?",
                [$selectedVariantId]
            );
        }

        if ($activeVar) {
            $sourceName = 'Customer Return';
            $returnRef = 'RET-' . date('ymd');
            if ($selectedSourceId) {
                $retInfo = Database::fetchOne("SELECT wr.*, COALESCE(s.name, wr.source_type) as source_name FROM warehouse_returns wr LEFT JOIN shops s ON wr.source_type = 'SHOP' AND wr.source_id = s.id WHERE wr.id = ?", [$selectedSourceId]);
                if ($retInfo) {
                    if (!empty($retInfo['source_name'])) $sourceName = $retInfo['source_name'];
                    if (!empty($retInfo['return_number'])) $returnRef = $retInfo['return_number'];
                }
            }

            $c1 = !empty($activeVar['category_name']) ? trim($activeVar['category_name']) : (!empty($activeVar['category_code']) ? trim($activeVar['category_code']) : '');
            $c2 = !empty($activeVar['subcategory_name']) ? trim($activeVar['subcategory_name']) : (!empty($activeVar['subcategory_code']) ? trim($activeVar['subcategory_code']) : '');
            $c3 = !empty($activeVar['sub_subcategory_name']) ? trim($activeVar['sub_subcategory_name']) : (!empty($activeVar['sub_subcategory_code']) ? trim($activeVar['sub_subcategory_code']) : '');

            $prodPrefix = '';
            if ($c1 !== '') $prodPrefix .= strtoupper($c1[0]);
            if ($c2 !== '') $prodPrefix .= strtoupper($c2[0]);
            if ($c3 !== '') $prodPrefix .= strtoupper($c3[0]);
            if (strlen($prodPrefix) < 2) $prodPrefix = 'TTS';

            $retTagPrefix = (strlen($prodPrefix) >= 2) ? ('R' . substr($prodPrefix, 1)) : 'R';
            $badgeClass = $tagType === 'DAMAGED' ? 'bg-danger' : ($tagType === 'DEFECTIVE' ? 'bg-warning text-dark' : ($tagType === 'WRONG_SIZE' ? 'bg-info text-dark' : 'bg-success'));
            $fmtMrp = ($activeVar['mrp'] == (int)$activeVar['mrp']) ? (int)$activeVar['mrp'] : number_format($activeVar['mrp'], 2, '.', '');
            $returnBrand = !empty($selectedCustomBrand) ? $selectedCustomBrand : (!empty($activeVar['brand_name']) && $activeVar['brand_name'] !== 'Third-Party Brand' ? $activeVar['brand_name'] : 'GRM RETURN');

            for ($i = 1; $i <= $copiesCount; $i++) {
                $tagCode = $retTagPrefix . $monthStr . $yearStr . sprintf("%02d", $i);
                $catH = implode(' › ', array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']));
                $generatedItems[] = [
                    'piece_no'           => $i,
                    'total_count'        => $copiesCount,
                    'tag_code'           => $tagCode,
                    'product_name'       => $activeVar['product_name'],
                    'sku'                => $activeVar['sku'],
                    'brand_name'         => $returnBrand,
                    'size_name'          => $activeVar['size_name'] ?? 'Free',
                    'color_name'         => $activeVar['color_name'] ?? 'Std',
                    'mrp'                => (float)$activeVar['mrp'],
                    'selling_price'      => (float)$activeVar['selling_price'],
                    'cost_price'         => (float)$activeVar['selling_price'],
                    'source_label'       => 'Return (' . $sourceName . '): ' . $tagType . ' • ' . $returnBrand,
                    'badge_class'        => $badgeClass,
                    'qr_payload'         => "RET|{$returnRef}|{$activeVar['sku']}|{$tagType}|BRAND:{$returnBrand}|MRP:{$fmtMrp}",
                    'category_name'      => $activeVar['category_name'] ?? '',
                    'subcategory_name'   => $activeVar['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $activeVar['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        }
    } elseif ($selectedSourceId) {
        $retItems = WarehouseReturnItem::getByReturnId($selectedSourceId);
        $retInfo = Database::fetchOne("SELECT wr.*, COALESCE(s.name, wr.source_type) as source_name FROM warehouse_returns wr LEFT JOIN shops s ON wr.source_type = 'SHOP' AND wr.source_id = s.id WHERE wr.id = ?", [$selectedSourceId]);
        $sourceName = !empty($retInfo['source_name']) ? $retInfo['source_name'] : 'Customer Return';
        $returnRef = !empty($retInfo['return_number']) ? $retInfo['return_number'] : ('RET-' . date('ymd'));
        $seq = 1;

        foreach ($retItems as $ri) {
            $c1 = !empty($ri['category_name']) ? trim($ri['category_name']) : (!empty($ri['category_code']) ? trim($ri['category_code']) : '');
            $c2 = !empty($ri['subcategory_name']) ? trim($ri['subcategory_name']) : (!empty($ri['subcategory_code']) ? trim($ri['subcategory_code']) : '');
            $c3 = !empty($ri['sub_subcategory_name']) ? trim($ri['sub_subcategory_name']) : (!empty($ri['sub_subcategory_code']) ? trim($ri['sub_subcategory_code']) : '');

            $prodPrefix = '';
            if ($c1 !== '') $prodPrefix .= strtoupper($c1[0]);
            if ($c2 !== '') $prodPrefix .= strtoupper($c2[0]);
            if ($c3 !== '') $prodPrefix .= strtoupper($c3[0]);
            if (strlen($prodPrefix) < 2) $prodPrefix = 'TTS';

            $retTagPrefix = (strlen($prodPrefix) >= 2) ? ('R' . substr($prodPrefix, 1)) : 'R';
            $count = max(1, (int)$ri['returned_qty']);
            $class = !empty($ri['classification']) ? $ri['classification'] : $tagType;
            $returnBrand = !empty($selectedCustomBrand) ? $selectedCustomBrand : (!empty($ri['brand_name']) && $ri['brand_name'] !== 'Third-Party Brand' ? $ri['brand_name'] : 'GRM RETURN');

            for ($i = 1; $i <= $count; $i++) {
                $tagCode = $retTagPrefix . $monthStr . $yearStr . sprintf("%02d", $seq++);
                $fmtMrp = ($ri['mrp'] == (int)$ri['mrp']) ? (int)$ri['mrp'] : number_format($ri['mrp'], 2, '.', '');
                $badgeClass = $class === 'DAMAGED' ? 'bg-danger' : ($class === 'DEFECTIVE' ? 'bg-warning text-dark' : ($class === 'WRONG_SIZE' ? 'bg-info text-dark' : 'bg-success'));
                $catH = implode(' › ', array_filter([$ri['category_name'] ?? '', $ri['subcategory_name'] ?? '', $ri['sub_subcategory_name'] ?? '']));
                $generatedItems[] = [
                    'piece_no'           => $i,
                    'total_count'        => $count,
                    'tag_code'           => $tagCode,
                    'product_name'       => $ri['product_name'],
                    'sku'                => $ri['sku'],
                    'brand_name'         => $returnBrand,
                    'size_name'          => $ri['size_name'] ?? 'Free',
                    'color_name'         => $ri['color_name'] ?? 'Std',
                    'mrp'                => (float)$ri['mrp'],
                    'selling_price'      => (float)$ri['selling_price'],
                    'cost_price'         => (float)$ri['selling_price'],
                    'source_label'       => 'Return (' . $sourceName . '): ' . $class . ' • ' . $returnBrand,
                    'badge_class'        => $badgeClass,
                    'qr_payload'         => "RET|{$returnRef}|{$ri['sku']}|{$class}|BRAND:{$returnBrand}|MRP:{$fmtMrp}",
                    'category_name'      => $ri['category_name'] ?? '',
                    'subcategory_name'   => $ri['subcategory_name'] ?? '',
                    'sub_subcategory_name' => $ri['sub_subcategory_name'] ?? '',
                    'category_hierarchy' => $catH,
                ];
            }
        }
    }
}

// Prepare distinct brands list — ONLY from catalog-linked third-party variants
$distinctBrands = [];
$distinctCatalogCodes = [];
if (!empty($thirdPartyVariants)) {
    foreach ($thirdPartyVariants as $tpv) {
        $bName = trim($tpv['brand_name'] ?? '');
        if ($bName !== '' && !in_array($bName, $distinctBrands)) {
            $distinctBrands[] = $bName;
        }
        $cCode = trim($tpv['catalog_code'] ?? '');
        if ($cCode !== '' && !isset($distinctCatalogCodes[$cCode])) {
            $distinctCatalogCodes[$cCode] = [
                'code'  => $cCode,
                'name'  => $tpv['product_name'] ?? '',
                'brand' => $bName
            ];
        }
    }
}
sort($distinctBrands);
ksort($distinctCatalogCodes);
?>

<!-- Custom Styles for Thermal Sticker Preview and Browser Printing -->
<style>
.stock-stickers-sheet {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: center;
}
.stock-sticker-item {
    width: 280px;
    min-height: 122px;
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    padding: 12px;
    page-break-inside: avoid;
    break-inside: avoid;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stock-sticker-item:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

@media print {
    body * {
        visibility: hidden !important;
    }
    #modalPrintAreaWrapper, #modalPrintAreaWrapper *, #modalPrintArea, #modalPrintArea * {
        visibility: visible !important;
    }
    #modalPrintAreaWrapper {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    #modalPrintArea {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 5mm !important;
        padding: 4mm !important;
        justify-content: flex-start !important;
    }
    .stock-sticker-item {
        width: 48mm !important;
        min-height: 28mm !important;
        border: 1px dashed #666 !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 2mm !important;
        background: #fff !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .modal-header, .modal-footer, .btn, .no-print {
        display: none !important;
    }
}
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-qr-code-scan me-2 text-primary"></i>Warehouse QR Labels &amp; Printing Suite</h2>
        
    </div>
   
</div>

<!-- ========================================================================= -->
<!-- SOURCE SELECTION TABS (Tab 1: Customer Returns | Tab 2: Third-Party Buy Products) -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-4 no-print bg-white">
    <div class="card-header bg-white border-bottom p-3">
        <ul class="nav nav-pills card-header-pills gap-2 flex-wrap" id="sourceTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $currentSourceType === 'returns' ? 'active' : '' ?> fw-semibold" href="<?= url('warehouse/labels?source_type=returns') ?>">
                    <i class="bi bi-arrow-return-left me-1"></i> 1. Customer Return QR Labels
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentSourceType, ['po', 'tp_purchase']) ? 'active' : '' ?> fw-semibold" href="<?= url('warehouse/labels?source_type=tp_purchase') ?>">
                    <i class="bi bi-bag-check me-1"></i> 2. Third-Party Buy Products QR &amp; Stock Inward
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        <form method="GET" action="<?= url('warehouse/labels') ?>" id="qrGenForm" class="row g-3">
            <input type="hidden" name="source_type" value="<?= e($currentSourceType) ?>">

            <?php if ($currentSourceType === 'returns'): ?>
                <!-- SESSION 1: CUSTOMER RETURNS QR GENERATOR -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-folder-fill text-primary me-1"></i>Category</label>
                    <select name="category_id" id="returnsCatFilter" class="form-select form-select-sm" onchange="onCategoryFilterChange('returns')">
                        <option value="">-- All Categories --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($selectedCategoryId == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-folder2 text-primary me-1"></i>Subcategory</label>
                    <select name="subcategory_id" id="returnsSubcatFilter" class="form-select form-select-sm" onchange="onSubcategoryFilterChange('returns')">
                        <option value="">-- All Subcategories --</option>
                        <?php foreach ($subcategories as $sc): ?>
                            <option value="<?= $sc['id'] ?>" data-cat="<?= $sc['category_id'] ?>" <?= ($selectedSubcategoryId == $sc['id']) ? 'selected' : '' ?>><?= e($sc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-folder2-open text-primary me-1"></i>Sub-Subcategory</label>
                    <select name="sub_subcategory_id" id="returnsSubSubcatFilter" class="form-select form-select-sm" onchange="filterVariantsByHierarchy('returns')">
                        <option value="">-- All Sub-Subcategories --</option>
                        <?php foreach ($subSubcategories as $ssc): ?>
                            <option value="<?= $ssc['id'] ?>" data-subcat="<?= $ssc['subcategory_id'] ?>" <?= ($selectedSubSubcategoryId == $ssc['id']) ? 'selected' : '' ?>><?= e($ssc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark"><i class="bi bi-tag text-primary me-1"></i>Returned Product Variant / SKU <span class="text-danger">*</span></label>
                    <select name="variant_id" id="returnsVariantSelect" class="form-select form-select-sm" required onchange="onReturnVariantChange(this)">
                        <option value="">-- Select Product Variant --</option>
                        <?php foreach ($stockVariants as $sv): 
                            $hParts = array_filter([$sv['category_name'] ?? '', $sv['subcategory_name'] ?? '', $sv['sub_subcategory_name'] ?? '']);
                            $hBadge = !empty($hParts) ? ('[' . implode(' › ', $hParts) . '] ') : '';
                        ?>
                            <option value="<?= $sv['id'] ?>"
                                    data-cat="<?= $sv['category_id'] ?? '' ?>"
                                    data-subcat="<?= $sv['subcategory_id'] ?? '' ?>"
                                    data-subsubcat="<?= $sv['sub_subcategory_id'] ?? '' ?>"
                                    data-brand="<?= e($sv['brand_name'] ?? '') ?>"
                                    <?= ($selectedVariantId == $sv['id']) ? 'selected' : '' ?>>
                                <?= e($hBadge . $sv['product_name']) ?> | SKU: <?= e($sv['sku']) ?> (<?= e($sv['size_name'] ?? 'Free') ?> / <?= e($sv['color_name'] ?? 'Std') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Custom Brand for Return -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-tag-fill text-primary me-1"></i>Custom Brand for Return
                    </label>
                    <input type="text" name="custom_brand" id="returnsCustomBrand" class="form-control form-control-sm text-uppercase fw-semibold" list="returnsBrandList" placeholder="e.g. GRM RETURN or Brand" value="<?= e($selectedCustomBrand) ?>">
                    <datalist id="returnsBrandList">
                        <option value="GRM RETURN">
                        <option value="GRM CLASSIC">
                        <?php foreach ($distinctBrands as $bName): ?>
                            <option value="<?= e($bName) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small text-dark">Return QC Classification</label>
                    <select name="tag_type" class="form-select form-select-sm">
                        <option value="GOOD" <?= $tagType === 'GOOD' ? 'selected' : '' ?>>GOOD — Re-Stock (Available Inventory)</option>
                        <option value="DAMAGED" <?= $tagType === 'DAMAGED' ? 'selected' : '' ?>>DAMAGED — Quarantine / Scrap</option>
                        <option value="DEFECTIVE" <?= $tagType === 'DEFECTIVE' ? 'selected' : '' ?>>DEFECTIVE — Factory Rework</option>
                        <option value="WRONG_SIZE" <?= $tagType === 'WRONG_SIZE' ? 'selected' : '' ?>>WRONG SIZE — Store Exchange</option>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label fw-bold small text-dark">Copies</label>
                    <input type="number" name="copies" class="form-control form-control-sm text-center" value="<?= $copiesCount ?>" min="1" max="500" required>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold py-2 shadow-sm">
                        <i class="bi bi-qr-code me-1"></i> Generate QR Labels
                    </button>
                </div>

            <?php elseif (in_array($currentSourceType, ['po', 'tp_purchase'])): ?>
                <!-- SESSION 2: THIRD-PARTY BUY PRODUCTS & PO QR GENERATOR -->

                <?php if (empty($thirdPartyVariants)): ?>
                <!-- ⚠️ CATALOG GATE: Block QR generation until catalog products exist -->
                <div class="col-12">
                    <div class="alert alert-warning border-warning border-2 rounded-3 d-flex align-items-start gap-3 py-4 mb-0" style="background: linear-gradient(135deg, #fff8e1 0%, #fffde7 100%);">
                        <i class="bi bi-lock-fill fs-2 text-warning mt-1 flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>QR Generation Blocked — No Catalog Products Found</h6>
                            <p class="mb-3 text-secondary small">No third-party supplier products are catalogued yet. QR labels can only be generated for products that are properly added to the supplier catalog first.</p>
                            <p class="mb-2 text-secondary small fw-semibold">Correct workflow: <span class="badge bg-primary">1. Register Supplier</span> → <span class="badge bg-success">2. Add to Catalog</span> → <span class="badge bg-warning text-dark">3. Generate QR</span></p>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="<?= url('warehouse/catalog/create') ?>" class="btn btn-warning btn-sm fw-bold shadow-sm">
                                    <i class="bi bi-plus-circle me-1"></i> Add Products to Catalog
                                </a>
                                <a href="<?= url('warehouse/sellers') ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-shop-window me-1"></i> Go to Suppliers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                <!-- 1. Brand Dropdown (only shows brands with cataloged products) -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-tag-fill text-primary me-1"></i>1. Brand Name <span class="text-danger">*</span>
                    </label>
                    <select name="brand_name" id="brandFilterSelect" class="form-select form-select-sm fw-semibold" onchange="onBrandFilterChange(this.value)">
                        <option value="">-- All Brands --</option>
                        <?php foreach ($distinctBrands as $bName): ?>
                            <option value="<?= e($bName) ?>" <?= ($selectedBrandName === $bName) ? 'selected' : '' ?>>
                                <?= e($bName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-funnel me-1 text-primary"></i>Filters Catalog Code &amp; SKU.
                    </div>
                </div>

                <!-- 2. Catalog Code Dropdown -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-journal-code text-info me-1"></i>2. Catalog Code
                    </label>
                    <select name="catalog_code" id="catalogCodeFilterSelect" class="form-select form-select-sm fw-bold font-monospace" onchange="onCatalogCodeFilterChange(this.value)">
                        <option value="">-- All Catalog Codes --</option>
                        <?php foreach ($distinctCatalogCodes as $cCode => $cInfo): ?>
                            <option value="<?= e($cCode) ?>" data-brand="<?= e($cInfo['brand']) ?>" <?= ($selectedCatalogCode === $cCode) ? 'selected' : '' ?>>
                                <?= e($cCode) ?><?= !empty($cInfo['name']) ? ' &bull; ' . e($cInfo['name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-funnel me-1 text-info"></i>Filters SKU dropdown by Catalog Code.
                    </div>
                </div>

                <!-- 3. Product SKU / Variant Dropdown -->
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-upc-scan text-success me-1"></i>3. Select Product SKU / Variant <span class="text-danger">*</span>
                    </label>
                    <select name="variant_id" id="tpVariantSelect" class="form-select form-select-sm fw-bold font-monospace" required onchange="onSkuSelectChange(this)">
                        <option value="">-- Choose Product Variant / SKU --</option>
                        <?php 
                        $tpList = $thirdPartyVariants;
                        foreach ($tpList as $sv): 
                            $bName = trim($sv['brand_name'] ?? '');
                            $sellerName = !empty($sv['seller_company']) ? $sv['seller_company'] : (!empty($sv['seller_name']) ? $sv['seller_name'] : $bName);
                            $hParts = array_filter([$sv['category_name'] ?? '', $sv['subcategory_name'] ?? '', $sv['sub_subcategory_name'] ?? '']);
                            $hBadge = !empty($hParts) ? implode(' › ', $hParts) : 'General';
                            $cCode = trim($sv['catalog_code'] ?? '');
                        ?>
                            <option value="<?= $sv['id'] ?>" 
                                    data-brand="<?= e($bName) ?>"
                                    data-catalog-code="<?= e($cCode) ?>"
                                    data-seller-id="<?= (int)($sv['seller_id'] ?? 0) ?>"
                                    data-seller-name="<?= e($sellerName) ?>"
                                    data-sku="<?= e($sv['sku']) ?>"
                                    data-product-name="<?= e($sv['product_name']) ?>"
                                    data-cat="<?= e($sv['category_name'] ?? '') ?>"
                                    data-subcat="<?= e($sv['subcategory_name'] ?? '') ?>"
                                    data-subsubcat="<?= e($sv['sub_subcategory_name'] ?? '') ?>"
                                    data-size="<?= e($sv['size_name'] ?? 'Free') ?>"
                                    data-color="<?= e($sv['color_name'] ?? 'Std') ?>"
                                    data-mrp="<?= (float)$sv['mrp'] ?>"
                                    data-cost="<?= (float)($sv['purchase_price'] ?? $sv['production_cost'] ?? 0) ?>"
                                    data-selling="<?= (float)($sv['selling_price'] ?? 0) ?>"
                                    data-stock="<?= (int)($sv['available_qty'] ?? 0) ?>"
                                    data-qty="<?= (int)($sv['available_qty'] ?? 0) ?>"
                                    data-qr-type="<?= e($sv['qr_type'] ?? 'INDIVIDUAL') ?>"
                                    data-qr-prefix="<?= e($sv['qr_code_prefix'] ?? '') ?>"
                                    data-start-code="<?= e($sv['series_start_code'] ?? '') ?>"
                                    data-end-code="<?= e($sv['series_end_code'] ?? '') ?>"
                                    data-pack-qty="<?= (int)($sv['pack_quantity'] ?? 0) ?>"
                                    <?= ($selectedVariantId == $sv['id']) ? 'selected' : '' ?>>
                                [<?= e($bName ?: 'Brand') ?>] <?= !empty($cCode) ? '[' . e($cCode) . '] ' : '' ?><?= e($sv['sku']) ?> &bull; <?= e($sv['product_name']) ?> (<?= e($sv['size_name'] ?? 'Free') ?> / <?= e($sv['color_name'] ?? 'Std') ?>) <?= !empty($sv['qr_type']) && $sv['qr_type'] === 'SERIES_BATCH' ? ' &bull; [SERIES PACK]' : '' ?> &bull; <?= (int)($sv['available_qty'] ?? 0) ?> pcs
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ZERO STOCK RESTRICTION ALERT BANNER -->
                <div class="col-12" id="zeroStockValidationBanner" style="display: <?= !empty($isZeroStockRestricted) ? 'block' : 'none' ?>;">
                    <div class="alert alert-danger border-danger border-2 rounded-3 d-flex align-items-center gap-3 p-3 mb-0 shadow-sm" style="background-color: #fff5f5;">
                        <i class="bi bi-slash-circle-fill text-danger fs-3 flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold mb-1 text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>QR Generation Restricted — 0 Product Stock Count</h6>
                            <p class="mb-0 small text-danger-emphasis">
                                Selected SKU <strong><?= e($activeVar['sku'] ?? 'Item') ?></strong> has <strong>0 pcs</strong> in warehouse stock count. QR labels cannot be generated for out-of-stock items. Please choose a catalog product with positive stock count or inward stock first.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Target Warehouse (hidden, auto-pick first available) -->
                <input type="hidden" name="warehouse_id" value="<?= $targetWarehouseId ?>">

                <!-- DYNAMIC INFO PANEL: Dynamic 3-Tier Category Hierarchy, SKU Details & Series Batch Specs -->
                <div class="col-12" id="skuDynamicInfoPanel" style="display: none;">
                    <div class="card border rounded-3 p-3 bg-light shadow-xs">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary text-white px-2.5 py-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-diagram-3 me-1"></i>3-Tier Category Hierarchy:
                                </span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="badgeDynamicCat">
                                    <i class="bi bi-folder-fill me-1"></i>Category: <strong id="lblDynamicCat">—</strong>
                                </span>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" id="badgeDynamicSubcat">
                                    <i class="bi bi-folder2 me-1"></i>Subcategory: <strong id="lblDynamicSubcat">—</strong>
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" id="badgeDynamicSubSubcat">
                                    <i class="bi bi-folder2-open me-1"></i>Sub-Subcategory: <strong id="lblDynamicSubSubcat">—</strong>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-purple text-white shadow-xs" id="badgeDynamicSeriesMode" style="display: none; background-color: #6f42c1 !important;">
                                    <i class="bi bi-boxes me-1"></i>1 Master QR per Series Mode
                                </span>
                                <span class="badge bg-secondary-subtle text-dark border">
                                    <i class="bi bi-boxes me-1 text-success"></i>Current Stock: <strong id="lblDynamicStock">0 pcs</strong>
                                </span>
                            </div>
                        </div>

                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <div class="small text-muted">Product Style &amp; Code:</div>
                                <div class="fw-bold text-dark text-truncate" id="lblDynamicProductName">—</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Size / Color:</div>
                                <div class="fw-bold text-secondary font-monospace" id="lblDynamicVariantAttr">—</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">MRP (Max Retail):</div>
                                <div class="fw-bold text-success font-monospace" id="lblDynamicMrp">₹0.00</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-muted">Selling Price:</div>
                                <div class="fw-bold text-primary font-monospace" id="lblDynamicCost">₹0.00</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted" id="lblDynamicSeriesTitle">Brand / Supplier:</div>
                                <div class="fw-bold text-dark text-truncate" id="lblDynamicBrand">—</div>
                                <div id="lblDynamicSeriesRangeBox" class="mt-1 small fw-bold font-monospace" style="display: none; color: #6f42c1;">
                                    <i class="bi bi-upc me-1"></i>Range: <span id="lblDynamicSeriesRangeText">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO / Bill source (hidden) -->
                <input type="hidden" name="source_id" value="<?= (int)$selectedSourceId ?>">

                <!-- 4. QR Generation Mode Toggle -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-qr-code me-1 text-primary"></i>4. QR Label Generation Mode
                    </label>
                    <div class="d-flex align-items-center gap-3 pt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qr_mode" id="qrModeIndividual" value="INDIVIDUAL" <?= ($qrMode !== 'SERIES_BATCH' && (empty($activeVar) || ($activeVar['qr_type'] ?? '') !== 'SERIES_BATCH')) ? 'checked' : '' ?> onchange="onQrModeChange('INDIVIDUAL')">
                            <label class="form-check-label small fw-semibold text-dark" for="qrModeIndividual">
                                Individual Pieces
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qr_mode" id="qrModeSeries" value="SERIES_BATCH" <?= ($qrMode === 'SERIES_BATCH' || (!empty($activeVar) && ($activeVar['qr_type'] ?? '') === 'SERIES_BATCH')) ? 'checked' : '' ?> onchange="onQrModeChange('SERIES_BATCH')">
                            <label class="form-check-label small fw-semibold" for="qrModeSeries" style="color: #6f42c1;">
                                <i class="bi bi-boxes me-1"></i>1 Master QR / Series
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 5. Dynamic QR Count / Number of Stickers -->
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-dark">
                        <i class="bi bi-calculator text-primary me-1"></i>Dynamic QR Count (Number of Stickers) <span class="badge bg-secondary-subtle text-secondary border ms-1" id="copiesLockBadge"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                    </label>
                    <input type="number" name="copies" id="copiesInput" class="form-control form-control-sm fw-bold font-monospace text-center fs-6 border-secondary bg-light text-dark" value="<?= $copiesCount ?>" min="1" max="10000" readonly style="cursor: not-allowed; background-color: #f8f9fa !important;" title="Auto-set to current available warehouse stock count">
                    <div class="form-text text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-shield-lock-fill me-1 text-secondary"></i>Auto-locked to available stock count (<span id="copiesStockHint"><?= $copiesCount ?></span> pcs).
                    </div>
                </div>

                <!-- Generate Action Button -->
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" id="btnSubmitGenerateLabels" class="btn <?= !empty($isZeroStockRestricted) ? 'btn-secondary opacity-75' : 'btn-primary' ?> btn-sm w-100 fw-semibold py-2 shadow-sm" <?= !empty($isZeroStockRestricted) ? 'disabled title="Cannot generate QR labels: product stock is 0 pcs."' : '' ?>>
                        <i class="bi <?= !empty($isZeroStockRestricted) ? 'bi-slash-circle' : 'bi-qr-code' ?> me-1" id="btnSubmitIcon"></i> 
                        <span id="btnSubmitLabelText"><?= !empty($isZeroStockRestricted) ? 'QR Restricted (0 Stock)' : 'Generate QR Labels' ?></span>
                    </button>
                </div>
                <?php endif; // end catalog gate else ?>

            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (in_array($currentSourceType, ['po', 'tp_purchase']) && !empty($generatedItems) && $selectedVariantId): 
    $activeVar = null;
    $lookupPool = $thirdPartyVariants;
    foreach ($lookupPool as $sv) {
        if ($sv['id'] == $selectedVariantId) {
            $activeVar = $sv;
            break;
        }
    }
    $activeWh = null;
    foreach ($warehouses as $wh) {
        if ($wh['id'] == $targetWarehouseId) {
            $activeWh = $wh;
            break;
        }
    }
    if (!$activeWh && !empty($warehouses)) $activeWh = $warehouses[0];
?>
<!-- ========================================================================= -->
<!-- STEP 2: CONFIRM STICKERED PRODUCTS & UPDATE INVENTORY STOCK -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm mb-4 rounded-3 border-start border-4 border-success no-print" style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);" id="confirmInwardSection">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-success px-3 py-1 fs-7 shadow-xs"><i class="bi bi-box-seam-fill me-1"></i>Official Inventory Stock Inward</span>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-qr-code-scan me-1 text-primary"></i> <?= count($generatedItems) ?> QR Stickers Generated
                    </span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" id="inwardStatusBadge">
                        <i class="bi bi-hourglass-split me-1"></i> Stock NOT credited yet — Click button to update inventory
                    </span>
                </div>
                <h4 class="fw-bold mb-1 text-dark">
                    <?= e($activeVar['product_name'] ?? 'Selected Product') ?>
                    <span class="text-muted fw-normal fs-6 ms-2">(SKU: <strong class="text-dark font-monospace"><?= e($activeVar['sku'] ?? 'N/A') ?></strong> &bull; <?= e($activeVar['size_name'] ?? 'Free') ?> / <?= e($activeVar['color_name'] ?? 'Std') ?>)</span>
                </h4>
                <?php 
                $activeHierarchy = array_filter([$activeVar['category_name'] ?? '', $activeVar['subcategory_name'] ?? '', $activeVar['sub_subcategory_name'] ?? '']);
                if (!empty($activeHierarchy)):
                ?>
                    <div class="mt-1 mb-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2.5 py-1">
                            <i class="bi bi-diagram-3-fill me-1 text-warning-emphasis"></i><?= e(implode(' › ', $activeHierarchy)) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="text-muted small d-flex align-items-center gap-3 flex-wrap mt-2">
                    <span><i class="bi bi-building text-primary me-1"></i> Inward Warehouse: <strong><?= e($activeWh['name'] ?? 'Main Warehouse') ?></strong></span>
                    <span><i class="bi bi-boxes text-success me-1"></i> Current Stock in <?= e($activeWh['name'] ?? 'WH') ?>: <strong id="liveCurrentWhStock" class="badge bg-secondary-subtle text-dark fs-7"><?= number_format($activeVar['available_qty'] ?? 0) ?> pcs</strong></span>
                    <span><i class="bi bi-tags-fill text-warning-emphasis me-1"></i> QR Tag Range: <strong class="font-monospace text-dark"><?= e($generatedItems[0]['tag_code'] ?? '') ?> &rarr; <?= e(end($generatedItems)['tag_code'] ?? '') ?></strong></span>
                    <span><i class="bi bi-currency-rupee text-success me-1"></i> MRP: <strong>₹<?= number_format($activeVar['mrp'] ?? 0, 2) ?></strong></span>
                </div>
            </div>

            <?php if (hasPermission('inward', 'create') || hasPermission('stock', 'inward') || hasPermission('wh_labels', 'create')): ?>
            <div class="d-flex align-items-center gap-2">
                <form id="formConfirmInward" onsubmit="confirmStickeredInwardAction(event)">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="variant_id" value="<?= (int)$selectedVariantId ?>">
                    <input type="hidden" name="warehouse_id" id="inwardWarehouseId" value="<?= (int)($activeWh['id'] ?? 1) ?>">
                    <input type="hidden" name="quantity" value="<?= count($generatedItems) ?>">
                    <input type="hidden" name="seller_id" value="<?= (int)$selectedSellerId ?>">
                    <input type="hidden" name="source_id" value="<?= (int)$selectedSourceId ?>">
                    <input type="hidden" name="tag_codes" value="<?= htmlspecialchars(json_encode(array_column($generatedItems, 'tag_code')), ENT_QUOTES, 'UTF-8') ?>">
                    
                    <button type="submit" id="btnConfirmInwardSubmit" class="btn btn-success btn-lg shadow fw-bold px-4 py-3 d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-down-right fs-4"></i>
                        <span>Confirm Stickered &amp; Inward to Inventory (+<?= count($generatedItems) ?> Pcs)</span>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- GENERATED PIECE-BY-PIECE QR STICKERS GRID (Matching Image 2 UI) -->
<!-- ========================================================================= -->
<?php 
$hasSeriesGenerated = !empty($generatedItems) && !empty($generatedItems[0]['is_series']);
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0 fw-bold">
                <?php if ($hasSeriesGenerated): ?>
                    <i class="bi bi-boxes me-2 text-primary"></i>
                    Master Series Bundle Pack QR Label
                    <span class="badge bg-purple text-white rounded-pill ms-2" style="background-color: #6f42c1 !important;">
                        1 Master QR (<?= $generatedItems[0]['pack_quantity'] ?? 1 ?> Pcs Pack)
                    </span>
                <?php else: ?>
                    <i class="bi bi-qr-code me-2 text-success"></i>
                    Individual Piece QR Codes &amp; Labels
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-2">
                        <?= count($generatedItems) ?> Available Pieces
                    </span>
                <?php endif; ?>
            </h5>
            <small class="text-muted">
                <?= $hasSeriesGenerated ? 'Master bundle QR code sticker containing entire series range for warehouse inwarding & inventory tracking' : 'Direct piece-by-piece QR stickers & production-aligned barcodes for each physical garment in stock' ?>
            </small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm no-print" style="width: 220px;">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" id="filterTagInput" class="form-control" placeholder="Filter by Code or SKU..." onkeyup="filterRenderedStickers()">
            </div>
            <button type="button" class="btn btn-sm btn-outline-dark fw-semibold shadow-sm" onclick="openPrinterSettingsModal()" title="Calibrate TSC TE244 printer alignment and dimensions">
                <i class="bi bi-sliders me-1 text-warning"></i> Printer Settings
            </button>
            <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm" onclick="printAllGeneratedStickers()">
                <i class="bi bi-printer me-1"></i> Print All Stickers
            </button>
        </div>
    </div>

    <div class="card-body p-4">
        <?php if (empty($generatedItems)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-qr-code fs-1 d-block mb-3 text-secondary"></i>
                <h5>Select Brand &amp; SKU above to generate QR stickers</h5>
                <p class="mb-0">Choose the Brand/Supplier and Variant/SKU to generate scannable QR price stickers and inward stock to warehouse inventory.</p>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3" id="stickersCardGrid">
                <?php foreach ($generatedItems as $index => $item): ?>
                    <?php 
                        $fmtMrp = ($item['mrp'] == (int)$item['mrp']) ? (int)$item['mrp'] : number_format($item['mrp'], 2, '.', '');
                        $costVal = (!empty($item['cost_price']) && (float)$item['cost_price'] > 0) ? (float)$item['cost_price'] : ((!empty($item['selling_price']) && (float)$item['selling_price'] > 0) ? (float)$item['selling_price'] : (float)($item['mrp'] ?? 0));
                        $fmtCost = ($costVal == (int)$costVal) ? (int)$costVal : number_format($costVal, 2, '.', '');
                        $qrPayload = !empty($item['qr_payload']) ? $item['qr_payload'] : "{$item['tag_code']}|{$item['sku']}|MRP:{$fmtMrp}|Disc:{$fmtCost}";
                    ?>
                    <div class="col sticker-grid-col" data-code="<?= e(strtoupper($item['tag_code'])) ?>" data-sku="<?= e(strtoupper($item['sku'])) ?>">
                        <div class="card border rounded-3 p-2.5 h-100 shadow-sm bg-white piece-card position-relative <?= !empty($item['is_series']) ? 'border-primary' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <?php if (!empty($item['is_series'])): ?>
                                    <span class="badge bg-dark text-white font-monospace" style="font-size: 0.68rem;"><i class="bi bi-boxes me-1"></i>Master Pack (<?= $item['pack_quantity'] ?> Pcs)</span>
                                    <span class="badge bg-purple text-white" style="font-size: 0.68rem; background-color: #6f42c1 !important;"><i class="bi bi-qr-code me-1"></i>Series QR</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.68rem;">Piece #<?= $item['piece_no'] ?> of <?= $item['total_count'] ?></span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;"><i class="bi bi-check-circle me-1"></i>Available</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Modern Garment Tag Sticker Layout (Matching Image 2) -->
                            <div class="p-2 border rounded-3 bg-white shadow-xs d-flex align-items-start gap-2 mb-2" style="background: #fafafa;">
                                <!-- Left: QR Code + Tag Code Badge -->
                                <div class="text-center flex-shrink-0">
                                    <div class="piece-qr-box bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" id="qr-box-<?= $index ?>" data-json="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>" style="width: 64px; height: 64px;"></div>
                                    <div class="badge bg-dark text-white font-monospace mt-1 px-1 py-0.5 w-100 text-truncate" style="font-size: <?= !empty($item['is_series']) ? '0.62rem' : '0.70rem' ?>; letter-spacing: 0.3px;" title="<?= e($item['tag_code']) ?>">
                                        <?= e($item['tag_code']) ?>
                                    </div>
                                </div>
                                <!-- Right: Brand, Size/Color, MRP, Cost Price (as Disc) -->
                                <div class="overflow-hidden flex-grow-1" style="font-size: 0.76rem; margin-top: 1px;">
                                    <div class="fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.80rem; letter-spacing: 0.2px;">
                                        <?= e($item['brand_name'] ?? 'GRM CLASSIC') ?>
                                    </div>
                                    <div class="fw-semibold text-secondary text-truncate mt-0.5" style="font-size: 0.78rem;">
                                        <?= e($item['size_name']) ?>-<?= e($item['color_name']) ?> <?= !empty($item['is_series']) ? ' (' . $item['pack_quantity'] . ' Pcs)' : '' ?>
                                    </div>
                                    <div class="text-muted small mt-0.5 text-decoration-line-through" style="font-size: 0.72rem;">
                                        MRP: ₹<?= number_format($item['mrp'], 2) ?>
                                    </div>
                                    <div class="fw-bold text-success mt-0.5" style="font-size: 0.82rem;">
                                        Selling: ₹<?= number_format($sellingVal, 2) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons for This Piece -->
                            <div class="d-flex gap-1 mt-auto pt-1.5 border-top no-print">
                                <button type="button" 
                                        class="btn btn-xs btn-outline-primary flex-fill fw-semibold py-1" 
                                        onclick="previewSingleTag(<?= $index ?>)" title="Preview 2D QR Code Tag (Image 3 Style)">
                                    <i class="bi bi-eye"></i> Preview
                                </button>
                                <button type="button" 
                                        class="btn btn-xs btn-outline-dark flex-fill fw-semibold py-1" 
                                        onclick="printSinglePieceSticker(<?= $index ?>)" title="Print Thermal Sticker">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                                <button type="button" 
                                        class="btn btn-xs btn-outline-secondary px-2 py-1" 
                                        onclick="copyTagCode('<?= e($item['tag_code']) ?>', this)" title="Copy Code">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: 2D QR CODE TAG PREVIEW (Exact Image 3 Layout) -->
<!-- ========================================================================= -->
<div class="modal fade" id="singleQRModal" tabindex="-1" aria-labelledby="singleQRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-white py-3 border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="singleQRModalLabel">
                    <i class="bi bi-qr-code me-2 text-primary"></i>2D QR Code Tag Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center bg-light">
                <div class="p-3 border rounded-3 bg-white d-inline-block shadow-sm mb-3 text-start position-relative" style="width: 290px; min-height: 120px;">
                    <div class="d-flex align-items-start gap-2.5">
                        <div class="text-center flex-shrink-0">
                            <div id="singleQRCanvas" class="bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" style="width: 78px; height: 78px;"></div>
                            <div class="badge bg-dark text-white font-monospace mt-1 px-1.5 py-1 w-100 text-truncate" id="previewDamageCode" style="font-size: 0.72rem; letter-spacing: 0.3px;">TTS092601</div>
                        </div>
                        <div class="overflow-hidden ms-1 flex-grow-1" style="margin-top: 2px;">
                            <div class="fw-bold text-uppercase text-dark text-truncate" id="previewBrandName" style="font-size: 0.82rem; letter-spacing: 0.3px;">GRM WAREHOUSE</div>
                            <div class="fw-bold text-secondary text-truncate" id="previewAttrText" style="font-size: 0.80rem;">4XL-BLUE</div>
                            <div class="text-muted small text-decoration-line-through" id="previewBasePrice" style="font-size: 0.76rem;">MRP: ₹1000</div>
                            <div class="fw-bold text-success" id="previewDiscPrice" style="font-size: 0.84rem;">Disc: ₹800</div>
                        </div>
                    </div>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Scannable 2D QR Code containing SKU, Style, Size, Color &amp; Price payload.
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success px-4 fw-semibold shadow-sm" id="btnSinglePrintModal" onclick="printSinglePieceFromModal()">
                    <i class="bi bi-printer-fill me-1"></i> Print This Tag
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TSC TE244 PRINTER SETTINGS & LIVE DUAL-STICKER PREVIEW MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="printerSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1280px; width: 95%;">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2 text-warning"></i>TSC TE244 Printer &amp; Dynamic Label Layout Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Left Side: Interactive Controls -->
                    <div class="col-lg-5 col-md-6">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold py-2"><i class="bi bi-bounding-box me-1 text-primary"></i>1. Sticker Dimensions</div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Sticker Width (mm)</label>
                                        <input type="number" id="cfgWidth" class="form-control form-control-sm" value="40" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Sticker Height (mm)</label>
                                        <input type="number" id="cfgHeight" class="form-control form-control-sm" value="25" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Roll Columns</label>
                                        <select id="cfgColumns" class="form-select form-select-sm" onchange="updateLivePreview()">
                                            <option value="2">2 Columns (Dual Roll)</option>
                                            <option value="1">1 Column (Single Roll)</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Row Gap (mm)</label>
                                        <input type="number" id="cfgGap" class="form-control form-control-sm" value="2" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label small fw-semibold mb-1 text-primary"><i class="bi bi-arrow-left-right me-1"></i>2nd Sticker (Right Column) X Shift (dots)</label>
                                        <input type="number" id="cfgCol2ShiftDots" class="form-control form-control-sm border-primary" value="0" placeholder="e.g. -10 to move left, +10 to move right" oninput="updateLivePreview()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold py-2"><i class="bi bi-qr-code me-1 text-success"></i>2. 2D QR Code &amp; Tag Code Position</div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold mb-1">QR X (dots)</label>
                                        <input type="number" id="cfgQrX" class="form-control form-control-sm" value="12" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold mb-1">QR Y (dots)</label>
                                        <input type="number" id="cfgQrY" class="form-control form-control-sm" value="35" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold mb-1">QR Cell Size</label>
                                        <input type="number" id="cfgQrCellSize" class="form-control form-control-sm" value="3" min="1" max="6" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Tag Code X (dots)</label>
                                        <input type="number" id="cfgCodeX" class="form-control form-control-sm" value="12" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Tag Code Y (dots)</label>
                                        <input type="number" id="cfgCodeY" class="form-control form-control-sm" value="125" oninput="updateLivePreview()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold py-2"><i class="bi bi-fonts me-1 text-info"></i>3. Text &amp; Attributes Position</div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Text Left X (dots)</label>
                                        <input type="number" id="cfgTextX" class="form-control form-control-sm" value="115" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold mb-1">Font Code</label>
                                        <select id="cfgTextFont" class="form-select form-select-sm" onchange="updateLivePreview()">
                                            <option value="2">Font 2 (Clean/Standard)</option>
                                            <option value="1">Font 1 (Compact/Small)</option>
                                            <option value="3">Font 3 (Bold/Medium)</option>
                                        </select>
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Brand Y</label>
                                        <input type="number" id="cfgTitleY" class="form-control form-control-sm" value="35" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Size/Color Y</label>
                                        <input type="number" id="cfgSkuY" class="form-control form-control-sm" value="62" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">MRP Y</label>
                                        <input type="number" id="cfgMrpY" class="form-control form-control-sm" value="90" oninput="updateLivePreview()">
                                    </div>
                                    <div class="col-3 mt-2">
                                        <label class="form-label small fw-semibold mb-1">Disc Y</label>
                                        <input type="number" id="cfgDiscY" class="form-control form-control-sm" value="118" oninput="updateLivePreview()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Real-Time Dual Sticker Preview Box -->
                    <div class="col-lg-7 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white fw-bold py-2 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-display me-1 text-primary"></i>Live Thermal Sticker Output Preview</span>
                                <span class="badge bg-dark" id="previewDimensionBadge">Dual Roll 40mm × 25mm</span>
                            </div>
                            <div class="card-body p-4 d-flex align-items-center justify-content-center bg-secondary-subtle">
                                <div class="d-flex align-items-center gap-3" id="liveStickersPreviewContainer">
                                    <!-- Sticker 1 Preview Box -->
                                    <div class="border border-2 border-dark rounded-2 bg-white shadow-sm position-relative overflow-hidden" id="liveStickerCard1" style="width: 240px; height: 150px;">
                                        <div class="badge bg-light text-dark position-absolute top-0 start-0 m-1 border" style="font-size: 0.60rem;">Left Sticker #1</div>
                                        <div id="prevQRBox1" class="position-absolute bg-dark d-flex align-items-center justify-content-center text-white font-monospace" style="left: 12px; top: 35px; width: 45px; height: 45px; font-size: 0.65rem;">QR</div>
                                        <div id="prevCodeTxt1" class="position-absolute badge bg-dark text-white font-monospace" style="left: 12px; top: 85px; font-size: 0.60rem;">TTS092601</div>
                                        <div id="prevTitleTxt1" class="position-absolute fw-bold text-uppercase text-dark font-monospace" style="left: 68px; top: 35px; font-size: 0.70rem;">GRM WAREHOUSE</div>
                                        <div id="prevSkuTxt1" class="position-absolute fw-bold text-secondary font-monospace" style="left: 68px; top: 52px; font-size: 0.68rem;">4XL-BLUE</div>
                                        <div id="prevMrpTxt1" class="position-absolute text-muted font-monospace" style="left: 68px; top: 70px; font-size: 0.62rem;">MRP: 1000</div>
                                        <div id="prevDiscTxt1" class="position-absolute fw-bold text-success font-monospace" style="left: 68px; top: 88px; font-size: 0.70rem;">Disc: 800</div>
                                    </div>

                                    <!-- Sticker 2 Preview Box -->
                                    <div class="border border-2 border-dark rounded-2 bg-white shadow-sm position-relative overflow-hidden" id="liveStickerCard2" style="width: 240px; height: 150px;">
                                        <div class="badge bg-light text-dark position-absolute top-0 start-0 m-1 border" id="badgeSticker2" style="font-size: 0.60rem;">Right Sticker #2</div>
                                        <div id="prevQRBox2" class="position-absolute bg-dark d-flex align-items-center justify-content-center text-white font-monospace" style="left: 12px; top: 35px; width: 45px; height: 45px; font-size: 0.65rem;">QR</div>
                                        <div id="prevCodeTxt2" class="position-absolute badge bg-dark text-white font-monospace" style="left: 12px; top: 85px; font-size: 0.60rem;">TTS092602</div>
                                        <div id="prevTitleTxt2" class="position-absolute fw-bold text-uppercase text-dark font-monospace" style="left: 68px; top: 35px; font-size: 0.70rem;">GRM WAREHOUSE</div>
                                        <div id="prevSkuTxt2" class="position-absolute fw-bold text-secondary font-monospace" style="left: 68px; top: 52px; font-size: 0.68rem;">4XL-BLUE</div>
                                        <div id="prevMrpTxt2" class="position-absolute text-muted font-monospace" style="left: 68px; top: 70px; font-size: 0.62rem;">MRP: 1000</div>
                                        <div id="prevDiscTxt2" class="position-absolute fw-bold text-success font-monospace" style="left: 68px; top: 88px; font-size: 0.70rem;">Disc: 800</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" onclick="testPrintCurrentSettings()">
                    <i class="bi bi-printer me-1"></i> Test Print 1 Sticker
                </button>
                <button type="button" class="btn btn-success fw-semibold shadow-sm px-4" onclick="savePrinterSettings()">
                    <i class="bi bi-check2-circle me-1"></i> Save Calibration Settings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- BROWSER PRINT SHEET MODAL (Fallback when local port 9191 is unavailable) -->
<!-- ========================================================================= -->
<div class="modal fade" id="warehousePrintSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-printer me-1 text-warning fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0">Thermal Sticker Print Sheet Preview</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="modalPrintAreaWrapper">
                <div class="stock-stickers-sheet" id="modalPrintArea"></div>
            </div>
            <div class="modal-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Compact dual-column thermal sticker cards (TSC TE244 / 40mm × 25mm layout)
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i> Open Browser Print Dialog
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Library for rendering scannable QR codes client-side -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const GENERATED_ITEMS = <?= json_encode($generatedItems ?? []) ?>;
let currentPreviewIndex = 0;

// Initialize QR codes for rendered cards on page load
document.addEventListener('DOMContentLoaded', function() {
    GENERATED_ITEMS.forEach((item, i) => {
        const qrBox = document.getElementById(`qr-box-${i}`);
        if (qrBox) {
            const qrText = qrBox.getAttribute('data-json') || item.qr_payload || `${item.tag_code}|${item.sku}|MRP:${item.mrp}|Disc:${item.selling_price}`;
            new QRCode(qrBox, {
                text: qrText,
                width: 60,
                height: 60,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
        }
    });

    // Auto-select and trigger dynamic display if variant is preselected
    const vSel = document.getElementById('tpVariantSelect');
    if (vSel && vSel.value) {
        onSkuSelectChange(vSel);
    }
});

/**
 * Supplier Filter Change Handler: Dynamically filters the SKU dropdown & Brands
 */
function onSupplierFilterChange(sellerId) {
    const skuSelect = document.getElementById('tpVariantSelect');
    const brandSelect = document.getElementById('brandFilterSelect');
    if (!skuSelect) return;

    const sId = (sellerId || '').trim();
    const currentBrand = (brandSelect ? brandSelect.value : '').trim().toUpperCase();
    let firstVisibleIndex = -1;
    let matchCount = 0;

    for (let i = 0; i < skuSelect.options.length; i++) {
        const opt = skuSelect.options[i];
        if (!opt.value) {
            opt.style.display = '';
            continue;
        }

        const optSellerId = (opt.getAttribute('data-seller-id') || '').trim();
        const optBrand = (opt.getAttribute('data-brand') || opt.getAttribute('data-seller-name') || '').trim().toUpperCase();

        let show = true;
        if (sId && optSellerId !== sId) {
            show = false;
        }
        if (currentBrand && optBrand !== currentBrand) {
            show = false;
        }

        if (show) {
            opt.style.display = '';
            matchCount++;
            if (firstVisibleIndex === -1) {
                firstVisibleIndex = i;
            }
        } else {
            opt.style.display = 'none';
        }
    }

    if (skuSelect.selectedIndex >= 0 && skuSelect.options[skuSelect.selectedIndex].style.display === 'none') {
        if (firstVisibleIndex !== -1) {
            skuSelect.selectedIndex = firstVisibleIndex;
        } else {
            skuSelect.value = '';
        }
    }

    onSkuSelectChange(skuSelect);
}

/**
 * Brand Filter Change Handler: Dynamically filters the Catalog Code & SKU dropdowns
 */
function onBrandFilterChange(brandName) {
    const skuSelect = document.getElementById('tpVariantSelect');
    const catalogSelect = document.getElementById('catalogCodeFilterSelect');
    const sellerSelect = document.getElementById('sellerFilterSelect');
    if (!skuSelect) return;

    const bName = (brandName || '').trim().toUpperCase();
    const currentSellerId = (sellerSelect ? sellerSelect.value : '').trim();

    // 1. Filter Catalog Code dropdown options by Brand
    if (catalogSelect) {
        let firstVisibleCatCode = '';
        for (let i = 0; i < catalogSelect.options.length; i++) {
            const opt = catalogSelect.options[i];
            if (!opt.value) {
                opt.style.display = '';
                continue;
            }
            const optBrand = (opt.getAttribute('data-brand') || '').trim().toUpperCase();
            if (!bName || optBrand === bName) {
                opt.style.display = '';
                if (!firstVisibleCatCode) firstVisibleCatCode = opt.value;
            } else {
                opt.style.display = 'none';
            }
        }
        if (catalogSelect.selectedIndex >= 0 && catalogSelect.options[catalogSelect.selectedIndex].style.display === 'none') {
            catalogSelect.value = '';
        }
    }

    const currentCatCode = (catalogSelect ? catalogSelect.value : '').trim().toUpperCase();
    let firstVisibleIndex = -1;
    let matchCount = 0;

    for (let i = 0; i < skuSelect.options.length; i++) {
        const opt = skuSelect.options[i];
        if (!opt.value) {
            opt.style.display = '';
            continue;
        }

        const optSellerId = (opt.getAttribute('data-seller-id') || '').trim();
        const optBrand = (opt.getAttribute('data-brand') || opt.getAttribute('data-seller-name') || '').trim().toUpperCase();
        const optCatCode = (opt.getAttribute('data-catalog-code') || '').trim().toUpperCase();

        let show = true;
        if (bName && optBrand !== bName) {
            show = false;
        }
        if (currentCatCode && optCatCode !== currentCatCode) {
            show = false;
        }
        if (currentSellerId && optSellerId !== currentSellerId) {
            show = false;
        }

        if (show) {
            opt.style.display = '';
            matchCount++;
            if (firstVisibleIndex === -1) {
                firstVisibleIndex = i;
            }
        } else {
            opt.style.display = 'none';
        }
    }

    // Auto-select the first matching SKU if current selection is invalid or empty
    if (bName && firstVisibleIndex !== -1) {
        const currentOpt = skuSelect.options[skuSelect.selectedIndex];
        if (!currentOpt || !currentOpt.value || currentOpt.style.display === 'none') {
            skuSelect.selectedIndex = firstVisibleIndex;
        }
    } else if (skuSelect.selectedIndex >= 0 && skuSelect.options[skuSelect.selectedIndex].style.display === 'none') {
        skuSelect.selectedIndex = (firstVisibleIndex !== -1) ? firstVisibleIndex : 0;
    }

    onSkuSelectChange(skuSelect);
}

/**
 * Catalog Code Filter Change Handler: Dynamically filters SKU dropdown by Catalog Code
 */
function onCatalogCodeFilterChange(catalogCode) {
    const skuSelect = document.getElementById('tpVariantSelect');
    const brandSelect = document.getElementById('brandFilterSelect');
    if (!skuSelect) return;

    const cCode = (catalogCode || '').trim().toUpperCase();
    const currentBrand = (brandSelect ? brandSelect.value : '').trim().toUpperCase();
    let firstVisibleIndex = -1;
    let matchCount = 0;

    for (let i = 0; i < skuSelect.options.length; i++) {
        const opt = skuSelect.options[i];
        if (!opt.value) {
            opt.style.display = '';
            continue;
        }

        const optCatCode = (opt.getAttribute('data-catalog-code') || '').trim().toUpperCase();
        const optBrand = (opt.getAttribute('data-brand') || opt.getAttribute('data-seller-name') || '').trim().toUpperCase();

        let show = true;
        if (cCode && optCatCode !== cCode) {
            show = false;
        }
        if (currentBrand && optBrand !== currentBrand) {
            show = false;
        }

        if (show) {
            opt.style.display = '';
            matchCount++;
            if (firstVisibleIndex === -1) {
                firstVisibleIndex = i;
            }
        } else {
            opt.style.display = 'none';
        }
    }

    if (cCode && firstVisibleIndex !== -1) {
        skuSelect.selectedIndex = firstVisibleIndex;
    } else if (skuSelect.selectedIndex >= 0 && skuSelect.options[skuSelect.selectedIndex].style.display === 'none') {
        skuSelect.selectedIndex = (firstVisibleIndex !== -1) ? firstVisibleIndex : 0;
    }

    onSkuSelectChange(skuSelect);
}

/**
 * SKU / Variant Change Handler: Dynamically displays 3-Tier Categories, Series Batch Specs & QR Count
 * Restricts QR Generation if Stock Count is 0!
 */
function onSkuSelectChange(selectEl) {
    if (!selectEl) return;
    const opt = selectEl.options[selectEl.selectedIndex];
    const infoPanel = document.getElementById('skuDynamicInfoPanel');

    if (!opt || !opt.value) {
        if (infoPanel) infoPanel.style.display = 'none';
        return;
    }

    // If catalog code dropdown exists and not selected, sync it to match the chosen variant
    const catCodeFromOpt = (opt.getAttribute('data-catalog-code') || '').trim();
    const catalogSelect = document.getElementById('catalogCodeFilterSelect');
    if (catCodeFromOpt && catalogSelect && !catalogSelect.value) {
        for (let i = 0; i < catalogSelect.options.length; i++) {
            if (catalogSelect.options[i].value === catCodeFromOpt) {
                catalogSelect.selectedIndex = i;
                break;
            }
        }
    }

    // Extract attributes from selected option
    const cat = opt.getAttribute('data-cat') || 'General';
    const subcat = opt.getAttribute('data-subcat') || '—';
    const subsubcat = opt.getAttribute('data-subsubcat') || '—';
    const prodName = opt.getAttribute('data-product-name') || 'Selected Product';
    const size = opt.getAttribute('data-size') || 'Free';
    const color = opt.getAttribute('data-color') || 'Std';
    const mrp = parseFloat(opt.getAttribute('data-mrp') || 0);
    const selling = parseFloat(opt.getAttribute('data-selling') || 0);
    const cost = parseFloat(opt.getAttribute('data-cost') || 0);
    const brand = opt.getAttribute('data-brand') || opt.getAttribute('data-seller-name') || 'GRM CLASSIC';
    const stock = parseInt(opt.getAttribute('data-stock') || 0);
    const qrType = (opt.getAttribute('data-qr-type') || 'INDIVIDUAL').toUpperCase();
    const startCode = opt.getAttribute('data-start-code') || '';
    const endCode = opt.getAttribute('data-end-code') || '';
    const packQty = parseInt(opt.getAttribute('data-pack-qty') || 0);
    const totalEffectiveStock = stock;

    // Update dynamic 3-tier hierarchy labels
    const lblCat = document.getElementById('lblDynamicCat');
    const lblSubcat = document.getElementById('lblDynamicSubcat');
    const lblSubSubcat = document.getElementById('lblDynamicSubSubcat');

    if (lblCat) lblCat.textContent = cat;
    if (lblSubcat) lblSubcat.textContent = subcat;
    if (lblSubSubcat) lblSubSubcat.textContent = subsubcat;

    // Update dynamic spec labels (MRP & Selling Price)
    const lblProd = document.getElementById('lblDynamicProductName');
    const lblAttr = document.getElementById('lblDynamicVariantAttr');
    const lblMrpEl = document.getElementById('lblDynamicMrp');
    const lblCostEl = document.getElementById('lblDynamicCost');
    const lblBrandEl = document.getElementById('lblDynamicBrand');
    const lblStockEl = document.getElementById('lblDynamicStock');

    const finalSellingPrice = (selling > 0 ? selling : (mrp > 0 ? mrp : cost));

    if (lblProd) lblProd.textContent = prodName;
    if (lblAttr) lblAttr.textContent = `${size} / ${color}`;
    if (lblMrpEl) lblMrpEl.textContent = `₹${mrp.toFixed(2)}`;
    if (lblCostEl) lblCostEl.textContent = `₹${finalSellingPrice.toFixed(2)}`;
    if (lblBrandEl) lblBrandEl.textContent = brand;
    if (lblStockEl) {
        lblStockEl.textContent = `${stock} pcs`;
    }

    // Handle Series QR detection & 8-digit range formatting
    const isSeries = qrType === 'SERIES_BATCH';
    const badgeSeries = document.getElementById('badgeDynamicSeriesMode');
    const rangeBox = document.getElementById('lblDynamicSeriesRangeBox');
    const rangeText = document.getElementById('lblDynamicSeriesRangeText');
    const radSeries = document.getElementById('qrModeSeries');
    const radIndividual = document.getElementById('qrModeIndividual');

    // Build Category Prefix + Month/Year (MMYY) + Sequence
    const my = '<?= date('my') ?>';
    let prodPrefix = (opt.getAttribute('data-qr-prefix') || '').trim();
    if (!prodPrefix) {
        const c1 = (opt.getAttribute('data-cat') || '').trim();
        const c2 = (opt.getAttribute('data-subcat') || '').trim();
        const c3 = (opt.getAttribute('data-subsubcat') || '').trim();
        const initials = [c1, c2, c3].filter(Boolean).map(s => s[0]).join('').toUpperCase();
        prodPrefix = initials || 'NEW';
    }
    prodPrefix = prodPrefix.replace(/[^A-Za-z0-9]/g, '').slice(0, 4).toUpperCase();
    const baseTagPrefix = prodPrefix + my;
    const count = (packQty || stock || 1);
    const seqPad = count > 99 ? 3 : 2;
    const dispStart = baseTagPrefix + String(1).padStart(seqPad, '0');
    const dispEnd = baseTagPrefix + String(count).padStart(seqPad, '0');

    if (isSeries) {
        if (badgeSeries) badgeSeries.style.display = 'inline-block';
        if (rangeBox) {
            rangeBox.style.display = 'block';
            rangeText.textContent = `${dispStart} → ${dispEnd} (${count} pcs/pack)`;
        }
        if (radSeries) radSeries.checked = true;
        if (radIndividual) radIndividual.checked = false;
    } else {
        if (badgeSeries) badgeSeries.style.display = 'none';
        if (rangeBox) rangeBox.style.display = 'none';
        if (radIndividual) radIndividual.checked = true;
        if (radSeries) radSeries.checked = false;
    }

    // VALIDATION: Restrict QR Generation if stock count is 0
    const isSeriesSelected = (radSeries && radSeries.checked);
    const copiesInput = document.getElementById('copiesInput');
    const copiesStockHint = document.getElementById('copiesStockHint');
    const submitBtn = document.getElementById('btnSubmitGenerateLabels');
    const zeroStockBanner = document.getElementById('zeroStockValidationBanner');

    if (totalEffectiveStock <= 0) {
        // STOCK COUNT IS 0: BLOCK AND RESTRICT QR GENERATION
        if (copiesInput) {
            copiesInput.value = 0;
            copiesInput.readOnly = true;
            if (copiesStockHint) copiesStockHint.textContent = '0 (Out of Stock)';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-secondary', 'opacity-75');
            submitBtn.title = 'Cannot generate QR labels: product stock count is 0 pcs.';
            const btnText = document.getElementById('btnSubmitLabelText');
            if (btnText) btnText.textContent = 'QR Restricted (0 Stock)';
            const btnIcon = document.getElementById('btnSubmitIcon');
            if (btnIcon) btnIcon.className = 'bi bi-slash-circle me-1';
        }
        if (zeroStockBanner) {
            zeroStockBanner.style.display = 'block';
        }
    } else {
        // POSITIVE STOCK COUNT: ALLOW QR GENERATION
        if (copiesInput) {
            if (isSeriesSelected) {
                copiesInput.value = 1;
                copiesInput.readOnly = true;
                if (copiesStockHint) copiesStockHint.textContent = `1 Master Pack (${packQty || stock} pcs)`;
            } else {
                const effectiveQty = (packQty > 0) ? packQty : stock;
                copiesInput.value = effectiveQty;
                copiesInput.readOnly = true;
                if (copiesStockHint) copiesStockHint.textContent = effectiveQty;
            }
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-secondary', 'opacity-75');
            submitBtn.classList.add('btn-primary');
            submitBtn.title = '';
            const btnText = document.getElementById('btnSubmitLabelText');
            if (btnText) btnText.textContent = 'Generate QR Labels';
            const btnIcon = document.getElementById('btnSubmitIcon');
            if (btnIcon) btnIcon.className = 'bi bi-qr-code me-1';
        }
        if (zeroStockBanner) {
            zeroStockBanner.style.display = 'none';
        }
    }

    if (infoPanel) infoPanel.style.display = 'block';
}

/**
 * QR Mode Change Handler: Updates dynamic count and locking
 */
function onQrModeChange(mode) {
    const skuSelect = document.getElementById('tpVariantSelect');
    const copiesInput = document.getElementById('copiesInput');
    const copiesStockHint = document.getElementById('copiesStockHint');
    const opt = skuSelect ? skuSelect.options[skuSelect.selectedIndex] : null;

    const stock = opt ? parseInt(opt.getAttribute('data-stock') || 0) : 0;
    const packQty = opt ? parseInt(opt.getAttribute('data-pack-qty') || 0) : 0;
    const totalEffectiveStock = stock;

    if (totalEffectiveStock <= 0) {
        if (copiesInput) copiesInput.value = 0;
        if (copiesStockHint) copiesStockHint.textContent = '0 (Out of Stock)';
        return;
    }

    if (mode === 'SERIES_BATCH') {
        if (copiesInput) copiesInput.value = 1;
        if (copiesStockHint) copiesStockHint.textContent = `1 Master Pack (${packQty || stock} pcs)`;
    } else {
        const effectiveQty = (packQty > 0) ? packQty : stock;
        if (copiesInput) copiesInput.value = effectiveQty;
        if (copiesStockHint) copiesStockHint.textContent = effectiveQty;
    }
}

/**
 * Preview Single Piece in 2D QR Code Tag Modal
 */
function previewSingleTag(idx) {
    if (!GENERATED_ITEMS[idx]) return;
    currentPreviewIndex = idx;
    const item = GENERATED_ITEMS[idx];

    const fmtMrp = (item.mrp == parseInt(item.mrp)) ? parseInt(item.mrp) : parseFloat(item.mrp).toFixed(2);
    const sellVal = (item.selling_price && item.selling_price > 0) ? item.selling_price : (item.mrp || 0);
    const fmtSelling = (sellVal == parseInt(sellVal)) ? parseInt(sellVal) : parseFloat(sellVal).toFixed(2);

    document.getElementById('previewDamageCode').textContent = item.tag_code;
    document.getElementById('previewBrandName').textContent = (item.brand_name || 'GRM WAREHOUSE').toUpperCase();
    document.getElementById('previewAttrText').textContent = `${item.size_name}-${item.color_name}${item.is_series ? ` (Pack of ${item.pack_quantity || 1})` : ''}`.toUpperCase();
    document.getElementById('previewBasePrice').textContent = `MRP: ₹${fmtMrp}`;
    document.getElementById('previewDiscPrice').textContent = `Selling: ₹${fmtSelling}`;

    const canvas = document.getElementById('singleQRCanvas');
    canvas.innerHTML = '';
    const qrPayload = item.qr_payload || `${item.tag_code}|${item.sku}|MRP:${fmtMrp}|Sell:${fmtSelling}`;

    new QRCode(canvas, {
        text: qrPayload,
        width: 74,
        height: 74,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
    });

    const modal = new bootstrap.Modal(document.getElementById('singleQRModal'));
    modal.show();
}

/**
 * Print Single Piece directly from the 2D QR Code Tag Preview Modal
 */
function printSinglePieceFromModal() {
    printSinglePieceSticker(currentPreviewIndex);
}

/**
 * Filter Rendered Stickers via live search
 */
function filterRenderedStickers() {
    const input = document.getElementById('filterTagInput');
    const query = input ? input.value.trim().toUpperCase() : '';
    const cols = document.querySelectorAll('.sticker-grid-col');

    cols.forEach(col => {
        const code = col.getAttribute('data-code') || '';
        const sku = col.getAttribute('data-sku') || '';
        if (!query || code.includes(query) || sku.includes(query)) {
            col.style.display = '';
        } else {
            col.style.display = 'none';
        }
    });
}

/**
 * Copy piece tag code to clipboard
 */
function copyTagCode(code, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> Copied!';
            btn.classList.replace('btn-outline-secondary', 'btn-success');
            btn.classList.add('text-white');
            setTimeout(() => {
                btn.innerHTML = origHtml;
                btn.classList.replace('btn-success', 'btn-outline-secondary');
                btn.classList.remove('text-white');
            }, 1500);
        });
    }
}

/**
 * Print a single piece sticker (Direct Thermal TSPL with browser fallback)
 */
function printSinglePieceSticker(idx) {
    if (!GENERATED_ITEMS[idx]) return;
    const item = GENERATED_ITEMS[idx];

    const labelPayload = [{
        sku: item.sku,
        productName: item.product_name,
        categoryHierarchy: item.category_hierarchy || '',
        categoryName: item.category_name || '',
        subcategoryName: item.subcategory_name || '',
        subSubcategoryName: item.sub_subcategory_name || '',
        sizeName: item.size_name || 'Free',
        colorName: item.color_name || 'Std',
        attrText: `${item.size_name || 'Free'}-${item.color_name || 'Std'}`,
        basePrice: parseFloat(item.mrp || 0).toFixed(2),
        discountPrice: parseFloat(item.selling_price || item.mrp || 0).toFixed(2),
        damageCode: item.tag_code,
        title: (item.brand_name || "GRM CLASSIC").toUpperCase(),
        qrPayload: item.qr_payload || `${item.tag_code}|${item.sku}|MRP:${item.mrp}|Sell:${item.selling_price || item.mrp}`,
        quantity: 1
    }];

    printLabel(labelPayload);
}

/**
 * Print all generated stickers to TSC TE244 thermal printer
 */
function printAllGeneratedStickers() {
    if (!GENERATED_ITEMS || GENERATED_ITEMS.length === 0) {
        alert('No stickers generated to print.');
        return;
    }

    const batchLabels = GENERATED_ITEMS.map(item => ({
        sku: item.sku,
        productName: item.product_name,
        categoryHierarchy: item.category_hierarchy || '',
        categoryName: item.category_name || '',
        subcategoryName: item.subcategory_name || '',
        subSubcategoryName: item.sub_subcategory_name || '',
        sizeName: item.size_name || 'Free',
        colorName: item.color_name || 'Std',
        attrText: `${item.size_name || 'Free'}-${item.color_name || 'Std'}`,
        basePrice: parseFloat(item.mrp || 0).toFixed(2),
        discountPrice: parseFloat(item.selling_price || item.mrp || 0).toFixed(2),
        damageCode: item.tag_code,
        title: (item.brand_name || "GRM CLASSIC").toUpperCase(),
        qrPayload: item.qr_payload || `${item.tag_code}|${item.sku}|MRP:${item.mrp}|Sell:${item.selling_price || item.mrp}`,
        quantity: 1
    }));

    printLabel(batchLabels);
}

/**
 * Direct TSPL Thermal Print Function (Port 9191)
 */
async function printLabel(labels) {
    try {
        const response = await fetch('http://127.0.0.1:9191/print', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ labels: labels })
        });

        if (response.ok) {
            const result = await response.json();
            showPrintToast(result.message || 'Printed successfully to TSC TE244!', 'success');
        } else {
            const err = await response.json().catch(() => ({}));
            showPrintToast(`Printer helper error: ${err.error || response.statusText}. Opening browser print sheet...`, 'warning');
            openBrowserPrintSheet(labels);
        }
    } catch (e) {
        console.warn('Local print service not responding on port 9191. Falling back to browser print dialog.', e);
        showPrintToast('Local printer service (port 9191) offline. Using browser print sheet.', 'info');
        openBrowserPrintSheet(labels);
    }
}

/**
 * Fallback browser print sheet modal (Exact Image 1 UI Card Layout)
 */
function openBrowserPrintSheet(labels) {
    const container = document.getElementById('modalPrintArea');
    if (!container) return;
    container.innerHTML = '';

    labels.forEach((lbl, i) => {
        const div = document.createElement('div');
        div.className = 'stock-sticker-item';
        
        const attrText = (lbl.attrText || (lbl.sizeName && lbl.colorName ? `${lbl.sizeName}-${lbl.colorName}` : (lbl.sku || ''))).toUpperCase();
        const fmtMrp = parseFloat(lbl.basePrice || 0).toFixed(2);
        const fmtSelling = parseFloat(lbl.discountPrice || lbl.basePrice || 0).toFixed(2);
        const qrPayload = lbl.qrPayload || `${lbl.damageCode}|${lbl.sku}|MRP:${lbl.basePrice}|Sell:${lbl.discountPrice}`;
        const titleText = (lbl.title || 'GRM CLASSIC').toUpperCase();

        div.innerHTML = `
            <div class="d-flex align-items-start gap-2.5">
                <div class="text-center flex-shrink-0">
                    <div id="modal-qr-${i}" class="bg-white p-1 rounded border shadow-xs d-flex align-items-center justify-content-center" style="width: 76px; height: 76px;"></div>
                    <div class="badge bg-dark text-white font-monospace mt-1 px-1.5 py-0.5 w-100" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        ${lbl.damageCode}
                    </div>
                </div>
                <div class="overflow-hidden ms-1 flex-grow-1" style="margin-top: 2px;">
                    <div class="fw-bold text-uppercase text-dark text-truncate" style="font-size: 0.82rem; letter-spacing: 0.3px;">
                        ${titleText}
                    </div>
                    <div class="fw-bold text-secondary text-truncate mt-0.5" style="font-size: 0.80rem;">
                        ${attrText}
                    </div>
                    <div class="text-muted small text-decoration-line-through mt-0.5" style="font-size: 0.76rem;">
                        MRP: ₹${fmtMrp}
                    </div>
                    <div class="fw-bold text-success mt-0.5" style="font-size: 0.84rem;">
                        Selling: ₹${fmtSelling}
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);

        setTimeout(() => {
            const qrEl = document.getElementById(`modal-qr-${i}`);
            if (qrEl) {
                new QRCode(qrEl, {
                    text: qrPayload,
                    width: 70,
                    height: 70,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            }
        }, 50);
    });

    const modal = new bootstrap.Modal(document.getElementById('warehousePrintSheetModal'));
    modal.show();
}

/**
 * Toast notification for print actions
 */
function showPrintToast(message, type = 'success') {
    let container = document.getElementById('printToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'printToastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-info text-white');
    const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2 fs-6">
                    <i class="bi ${icon} fs-5"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    if (window.bootstrap && bootstrap.Toast) {
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    } else {
        setTimeout(() => toastEl.remove(), 4000);
    }
}

/**
 * Open Printer & Alignment Settings Modal & Load Current Config
 */
async function openPrinterSettingsModal() {
    try {
        const response = await fetch('http://127.0.0.1:9191/config');
        if (response.ok) {
            const data = await response.json();
            if (data && data.config) {
                const cfg = data.config;
                if (document.getElementById('cfgWidth')) document.getElementById('cfgWidth').value = cfg.labelWidthMm || 40;
                if (document.getElementById('cfgHeight')) document.getElementById('cfgHeight').value = cfg.labelHeightMm || 25;
                if (document.getElementById('cfgColumns')) document.getElementById('cfgColumns').value = cfg.columns || 2;
                if (document.getElementById('cfgGap')) document.getElementById('cfgGap').value = cfg.gapMm || 2;
                if (document.getElementById('cfgCol2ShiftDots')) document.getElementById('cfgCol2ShiftDots').value = cfg.col2ShiftDots !== undefined ? cfg.col2ShiftDots : 0;
                
                if (document.getElementById('cfgQrX')) document.getElementById('cfgQrX').value = cfg.qrX !== undefined ? cfg.qrX : 12;
                if (document.getElementById('cfgQrY')) document.getElementById('cfgQrY').value = cfg.qrY !== undefined ? cfg.qrY : 35;
                if (document.getElementById('cfgQrCellSize')) document.getElementById('cfgQrCellSize').value = cfg.qrCellSize || 3;
                if (document.getElementById('cfgCodeX')) document.getElementById('cfgCodeX').value = cfg.codeX !== undefined ? cfg.codeX : 12;
                if (document.getElementById('cfgCodeY')) document.getElementById('cfgCodeY').value = cfg.codeY !== undefined ? cfg.codeY : 125;
                
                if (document.getElementById('cfgTextX')) document.getElementById('cfgTextX').value = cfg.textX !== undefined ? cfg.textX : 115;
                if (document.getElementById('cfgTextFont')) document.getElementById('cfgTextFont').value = cfg.textFont || "2";
                if (document.getElementById('cfgTitleY')) document.getElementById('cfgTitleY').value = cfg.titleY !== undefined ? cfg.titleY : 35;
                if (document.getElementById('cfgSkuY')) document.getElementById('cfgSkuY').value = cfg.skuY !== undefined ? cfg.skuY : 62;
                if (document.getElementById('cfgMrpY')) document.getElementById('cfgMrpY').value = cfg.mrpY !== undefined ? cfg.mrpY : 90;
                if (document.getElementById('cfgDiscY')) document.getElementById('cfgDiscY').value = cfg.discY !== undefined ? cfg.discY : 118;
            }
        }
    } catch (e) {
        console.warn('Could not fetch current printer config:', e);
    }

    updateLivePreview();
    const modal = new bootstrap.Modal(document.getElementById('printerSettingsModal'));
    modal.show();
}

/**
 * Update Live Visual Sticker Layout Preview Box (Dual Sticker Roll)
 */
function updateLivePreview() {
    const widthMm = parseFloat(document.getElementById('cfgWidth')?.value || 40);
    const heightMm = parseFloat(document.getElementById('cfgHeight')?.value || 25);
    const columns = parseInt(document.getElementById('cfgColumns')?.value || 2);
    const col2ShiftDots = parseFloat(document.getElementById('cfgCol2ShiftDots')?.value || 0);

    const badge = document.getElementById('previewDimensionBadge');
    if (badge) badge.textContent = `${columns === 2 ? 'Dual Roll ' : ''}${widthMm}mm × ${heightMm}mm`;

    const card1 = document.getElementById('liveStickerCard1');
    const card2 = document.getElementById('liveStickerCard2');
    if (!card1) return;

    const scale = 240 / widthMm;
    const cardPxWidth = 240;
    const cardPxHeight = Math.max(100, Math.round(heightMm * scale));

    card1.style.width = `${cardPxWidth}px`;
    card1.style.height = `${cardPxHeight}px`;

    if (card2) {
        if (columns === 1) {
            card2.style.display = 'none';
        } else {
            card2.style.display = 'block';
            card2.style.width = `${cardPxWidth}px`;
            card2.style.height = `${cardPxHeight}px`;
        }
    }

    const badge2 = document.getElementById('badgeSticker2');
    if (badge2) {
        if (col2ShiftDots !== 0) {
            badge2.textContent = `Right Sticker #2 (${col2ShiftDots > 0 ? '+' : ''}${col2ShiftDots} dots)`;
            badge2.className = 'badge bg-primary text-white position-absolute top-0 start-0 m-1 border';
        } else {
            badge2.textContent = 'Right Sticker #2';
            badge2.className = 'badge bg-light text-dark position-absolute top-0 start-0 m-1 border';
        }
    }

    const dotScale = scale / 8.12;

    const qrX = parseFloat(document.getElementById('cfgQrX')?.value || 12) * dotScale;
    const qrY = parseFloat(document.getElementById('cfgQrY')?.value || 35) * dotScale;
    const qrSize = parseInt(document.getElementById('cfgQrCellSize')?.value || 3);
    const qrPxDim = Math.max(20, Math.round(qrSize * 8.5 * (scale / 4)));

    const codeX = parseFloat(document.getElementById('cfgCodeX')?.value || 12) * dotScale;
    const codeY = parseFloat(document.getElementById('cfgCodeY')?.value || 125) * dotScale;

    const textX = parseFloat(document.getElementById('cfgTextX')?.value || 115) * dotScale;
    const titleY = parseFloat(document.getElementById('cfgTitleY')?.value || 35) * dotScale;
    const skuY = parseFloat(document.getElementById('cfgSkuY')?.value || 62) * dotScale;
    const mrpY = parseFloat(document.getElementById('cfgMrpY')?.value || 90) * dotScale;
    const discY = parseFloat(document.getElementById('cfgDiscY')?.value || 118) * dotScale;

    // Apply to Left Card
    const qb1 = document.getElementById('prevQRBox1');
    if (qb1) { qb1.style.left = `${qrX}px`; qb1.style.top = `${qrY}px`; qb1.style.width = `${qrPxDim}px`; qb1.style.height = `${qrPxDim}px`; }

    const ct1 = document.getElementById('prevCodeTxt1');
    if (ct1) { ct1.style.left = `${codeX}px`; ct1.style.top = `${codeY}px`; }

    const tt1 = document.getElementById('prevTitleTxt1');
    if (tt1) { tt1.style.left = `${textX}px`; tt1.style.top = `${titleY}px`; }

    const st1 = document.getElementById('prevSkuTxt1');
    if (st1) { st1.style.left = `${textX}px`; st1.style.top = `${skuY}px`; }

    const mt1 = document.getElementById('prevMrpTxt1');
    if (mt1) { mt1.style.left = `${textX}px`; mt1.style.top = `${mrpY}px`; }

    const dt1 = document.getElementById('prevDiscTxt1');
    if (dt1) { dt1.style.left = `${textX}px`; dt1.style.top = `${discY}px`; }

    // Apply to Right Card
    const col2ShiftPx = col2ShiftDots * dotScale;
    const qb2 = document.getElementById('prevQRBox2');
    if (qb2) { qb2.style.left = `${qrX + col2ShiftPx}px`; qb2.style.top = `${qrY}px`; qb2.style.width = `${qrPxDim}px`; qb2.style.height = `${qrPxDim}px`; }

    const ct2 = document.getElementById('prevCodeTxt2');
    if (ct2) { ct2.style.left = `${codeX + col2ShiftPx}px`; ct2.style.top = `${codeY}px`; }

    const tt2 = document.getElementById('prevTitleTxt2');
    if (tt2) { tt2.style.left = `${textX + col2ShiftPx}px`; tt2.style.top = `${titleY}px`; }

    const st2 = document.getElementById('prevSkuTxt2');
    if (st2) { st2.style.left = `${textX + col2ShiftPx}px`; st2.style.top = `${skuY}px`; }

    const mt2 = document.getElementById('prevMrpTxt2');
    if (mt2) { mt2.style.left = `${textX + col2ShiftPx}px`; mt2.style.top = `${mrpY}px`; }

    const dt2 = document.getElementById('prevDiscTxt2');
    if (dt2) { dt2.style.left = `${textX + col2ShiftPx}px`; dt2.style.top = `${discY}px`; }
}

/**
 * Save Printer Settings to local printer service config
 */
async function savePrinterSettings() {
    const configData = {
        labelWidthMm: parseFloat(document.getElementById('cfgWidth')?.value || 40),
        labelHeightMm: parseFloat(document.getElementById('cfgHeight')?.value || 25),
        columns: parseInt(document.getElementById('cfgColumns')?.value || 2),
        gapMm: parseFloat(document.getElementById('cfgGap')?.value || 2),
        col2ShiftDots: parseFloat(document.getElementById('cfgCol2ShiftDots')?.value || 0),
        qrX: parseInt(document.getElementById('cfgQrX')?.value || 12),
        qrY: parseInt(document.getElementById('cfgQrY')?.value || 35),
        qrCellSize: parseInt(document.getElementById('cfgQrCellSize')?.value || 3),
        codeX: parseInt(document.getElementById('cfgCodeX')?.value || 12),
        codeY: parseInt(document.getElementById('cfgCodeY')?.value || 125),
        textX: parseInt(document.getElementById('cfgTextX')?.value || 115),
        textFont: document.getElementById('cfgTextFont')?.value || "2",
        titleY: parseInt(document.getElementById('cfgTitleY')?.value || 35),
        skuY: parseInt(document.getElementById('cfgSkuY')?.value || 62),
        mrpY: parseInt(document.getElementById('cfgMrpY')?.value || 90),
        discY: parseInt(document.getElementById('cfgDiscY')?.value || 118),
    };

    try {
        const response = await fetch('http://127.0.0.1:9191/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(configData)
        });

        if (response.ok) {
            showPrintToast('Printer settings saved successfully!', 'success');
            const modalEl = document.getElementById('printerSettingsModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        } else {
            showPrintToast('Could not save configuration to printer service.', 'warning');
        }
    } catch (e) {
        showPrintToast('Printer service not responding on port 9191.', 'warning');
    }
}

/**
 * Test Print 1 Sticker
 */
function testPrintCurrentSettings() {
    printLabel([
        {
            sku: "TEST-SKU",
            basePrice: "999.00",
            discountPrice: "799.00",
            damageCode: "TST01",
            title: "GRM WAREHOUSE",
            quantity: 1
        }
    ]);
}

/**
 * Confirm Stickered Inward to Inventory (AJAX Handler)
 */
async function confirmStickeredInwardAction(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('formConfirmInward');
    if (!form) return;

    const btn = document.getElementById('btnConfirmInwardSubmit');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Inwarding to Warehouse Stock...';

    const formData = new FormData(form);
    formData.append('is_ajax', '1');

    try {
        const response = await fetch('<?= url('warehouse/labels/confirm-stickered-inward') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            btn.className = 'btn btn-outline-success btn-lg shadow-sm fw-bold px-4 py-3 d-flex align-items-center gap-2';
            btn.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-4"></i><span>Inventory Stock Updated (+' + data.inward_qty + ' Pcs)</span>';

            const liveStockBadge = document.getElementById('liveCurrentWhStock');
            if (liveStockBadge) {
                liveStockBadge.textContent = data.warehouse_stock + ' pcs';
                liveStockBadge.className = 'badge bg-success text-white px-2 py-1 fs-7';
            }

            const statusBadge = document.getElementById('inwardStatusBadge');
            if (statusBadge) {
                statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                statusBadge.innerHTML = '<i class="bi bi-check2-all me-1"></i> Stock Officially Inwarded to ' + (data.warehouse_name || 'Warehouse');
            }

            showPrintToast(data.message || 'Inventory stock updated successfully!', 'success');
        } else {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            alert(data.message || 'Could not update inventory.');
            showPrintToast(data.message || 'Could not update inventory.', 'warning');
        }
    } catch (err) {
        console.error('Inward error:', err);
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('Network connection error while updating inventory.');
        showPrintToast('Network connection error while updating inventory.', 'warning');
    }
}

// -----------------------------------------------------------------------------
// Category, Subcategory & Sub-Subcategory Dynamic Cascading Filters (For Returns)
// -----------------------------------------------------------------------------
const allSubcategoriesData = <?= json_encode($subcategories ?? []) ?>;
const allSubSubcategoriesData = <?= json_encode($subSubcategories ?? []) ?>;

function onCategoryFilterChange(tabType, preselectSubId = null, preselectSubSubId = null) {
    const catSelect = document.getElementById(tabType + 'CatFilter');
    const subcatSelect = document.getElementById(tabType + 'SubcatFilter');
    const subSubcatSelect = document.getElementById(tabType + 'SubSubcatFilter');
    
    if (!catSelect || !subcatSelect) return;
    const catId = catSelect.value ? String(catSelect.value) : '';
    const currentSubVal = preselectSubId !== null ? String(preselectSubId) : subcatSelect.value;
    const currentSubSubVal = preselectSubSubId !== null ? String(preselectSubSubId) : (subSubcatSelect ? subSubcatSelect.value : '');

    subcatSelect.innerHTML = '<option value="">-- All Subcategories --</option>';
    if (subSubcatSelect) {
        subSubcatSelect.innerHTML = '<option value="">-- All Sub-Subcategories --</option>';
    }

    const subsToRender = catId ? allSubcategoriesData.filter(sc => String(sc.category_id) === catId) : allSubcategoriesData;
    subsToRender.forEach(sc => {
        const opt = document.createElement('option');
        opt.value = sc.id;
        opt.textContent = sc.name;
        opt.setAttribute('data-cat', sc.category_id);
        if (currentSubVal && String(sc.id) === String(currentSubVal)) {
            opt.selected = true;
        }
        subcatSelect.appendChild(opt);
    });

    if (subcatSelect.value) {
        onSubcategoryFilterChange(tabType, preselectSubSubId);
    } else {
        if (subSubcatSelect) {
            const validSubIds = catId ? subsToRender.map(sc => String(sc.id)) : null;
            const subSubsToRender = validSubIds ? allSubSubcategoriesData.filter(ssc => validSubIds.includes(String(ssc.subcategory_id))) : allSubSubcategoriesData;
            subSubsToRender.forEach(ssc => {
                const opt = document.createElement('option');
                opt.value = ssc.id;
                opt.textContent = ssc.name;
                opt.setAttribute('data-subcat', ssc.subcategory_id);
                if (currentSubSubVal && String(ssc.id) === String(currentSubSubVal)) {
                    opt.selected = true;
                }
                subSubcatSelect.appendChild(opt);
            });
        }
        filterVariantsByHierarchy(tabType);
    }
}

function onSubcategoryFilterChange(tabType, preselectSubSubId = null) {
    const subcatSelect = document.getElementById(tabType + 'SubcatFilter');
    const subSubcatSelect = document.getElementById(tabType + 'SubSubcatFilter');

    if (!subcatSelect) return;
    const subcatId = subcatSelect.value ? String(subcatSelect.value) : '';
    const currentSubSubVal = preselectSubSubId !== null ? String(preselectSubSubId) : (subSubcatSelect ? subSubcatSelect.value : '');

    if (subSubcatSelect) {
        subSubcatSelect.innerHTML = '<option value="">-- All Sub-Subcategories --</option>';
        const subSubsToRender = subcatId ? allSubSubcategoriesData.filter(ssc => String(ssc.subcategory_id) === subcatId) : allSubSubcategoriesData;
        subSubsToRender.forEach(ssc => {
            const opt = document.createElement('option');
            opt.value = ssc.id;
            opt.textContent = ssc.name;
            opt.setAttribute('data-subcat', ssc.subcategory_id);
            if (currentSubSubVal && String(ssc.id) === String(currentSubSubVal)) {
                opt.selected = true;
            }
            subSubcatSelect.appendChild(opt);
        });
    }

    filterVariantsByHierarchy(tabType);
}

function filterVariantsByHierarchy(tabType) {
    const variantSelect = document.getElementById('returnsVariantSelect');
    if (!variantSelect) return;

    const catVal = (document.getElementById(tabType + 'CatFilter')?.value || '').trim();
    const subcatVal = (document.getElementById(tabType + 'SubcatFilter')?.value || '').trim();
    const subSubcatVal = (document.getElementById(tabType + 'SubSubcatFilter')?.value || '').trim();

    for (let i = 0; i < variantSelect.options.length; i++) {
        const opt = variantSelect.options[i];
        if (!opt.value) {
            opt.style.display = '';
            continue;
        }

        const optCat = opt.getAttribute('data-cat') || '';
        const optSubcat = opt.getAttribute('data-subcat') || '';
        const optSubSubcat = opt.getAttribute('data-subsubcat') || '';

        let match = true;
        if (catVal && optCat !== catVal) match = false;
        if (subcatVal && optSubcat !== subcatVal) match = false;
        if (subSubcatVal && optSubSubcat !== subSubcatVal) match = false;

        opt.style.display = match ? '' : 'none';
    }

    if (variantSelect.selectedOptions.length > 0 && variantSelect.selectedOptions[0].style.display === 'none') {
        variantSelect.value = '';
    }
}

function onReturnVariantChange(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const customBrandInp = document.getElementById('returnsCustomBrand');
    if (selectedOpt && customBrandInp && (!customBrandInp.value || customBrandInp.value === 'GRM RETURN')) {
        const b = selectedOpt.getAttribute('data-brand');
        if (b && b !== 'Third-Party Brand') {
            customBrandInp.value = b;
        }
    }
}

// Auto-initialize SKU details and dynamic count on page load
document.addEventListener('DOMContentLoaded', function() {
    const brandSelect = document.getElementById('brandFilterSelect');
    const skuSelect = document.getElementById('tpVariantSelect');
    if (skuSelect && skuSelect.value) {
        onSkuSelectChange(skuSelect);
    } else if (brandSelect && brandSelect.value) {
        onBrandFilterChange(brandSelect.value);
    }

    // Form submit validation: strictly prevent generating QR labels if count is 0
    const qrForm = document.getElementById('qrGenForm');
    if (qrForm) {
        qrForm.addEventListener('submit', function(e) {
            const currentSku = document.getElementById('tpVariantSelect');
            if (currentSku && currentSku.value) {
                const opt = currentSku.options[currentSku.selectedIndex];
                const stock = opt ? parseInt(opt.getAttribute('data-stock') || '0', 10) : 0;
                if (stock <= 0) {
                    e.preventDefault();
                    alert('QR Label Generation Restricted: This SKU has 0 stock count. Cannot generate QR labels for out-of-stock items.');
                    return false;
                }
            }
        });
    }
});
</script>
