<?php
/**
 * Shop Products & Stock Seeder for GRM Enterprise Inventory Management System
 * Seeds 10 rich, retail-ready apparel products with variants and live stock balances for the Shop module.
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

echo "========================================================\n";
echo "  GRM INVENTORY — SEEDING 10 SHOP PRODUCTS & STOCK\n";
echo "========================================================\n\n";

$shop = Database::fetchOne("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
if (!$shop) {
    echo "Creating default shop...\n";
    Database::execute("INSERT INTO shops (name, code, address, phone, gst_number, is_active, created_at) VALUES ('GRM Retail Flagship Store', 'SH-01', 'Main Road, Tirupur, Tamil Nadu', '+91 98765 43210', '33AAAAA0000A1Z5', 1, NOW())");
    $shop = Database::fetchOne("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
}
$shopId = (int)$shop['id'];
echo "Active Shop: [ID: {$shopId}] {$shop['name']}\n\n";

// Ensure prerequisite Categories
$categories = [
    [1, 'Maternity Wear', 'MW', 'maternity-wear', 'Maternity and nursing wear'],
    [2, 'Infant Wear', 'IW', 'infant-wear', 'Newborn and infant baby apparel'],
    [3, 'Girls Wear', 'GW', 'girls-wear', 'Girls ethnic and casual dresses'],
    [4, 'Boys Wear', 'BW', 'boys-wear', 'Boys t-shirts, shorts and polos'],
    [5, "Men's Wear", 'MEN', 'mens-wear', "Men's apparel and activewear"],
    [6, 'Baby Accessories', 'BA', 'baby-accessories', 'Swaddles, mittens, bibs & caps'],
];
foreach ($categories as $cat) {
    Database::execute("INSERT INTO categories (id, name, code, slug, description, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug), description=VALUES(description)", $cat);
}

// Ensure prerequisite Brands
$brands = [
    [1, 'GRM Infants', 'INF', 'Premium infant and newborn collection'],
    [2, 'GRM Maternity', 'MAT', 'Ergonomic maternity & feeding wear'],
    [3, 'GRM BabyCare', 'BC', 'Organic muslin & cotton baby essentials'],
    [13, 'GRM Boys Club', 'BOYS', 'Durable playwear for boys'],
    [15, 'GRM Little Princess', 'GIRLS', 'Charming party and casual frocks'],
];
foreach ($brands as $b) {
    Database::execute("INSERT INTO brands (id, name, code, description, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name), code=VALUES(code), description=VALUES(description)", $b);
}

// Ensure prerequisite Subcategories
$subcategories = [
    [1, 1, 'Feeding Kurtis', 'MAT-KURTI', 'feeding-kurtis'],
    [3, 1, 'Maternity Bottoms', 'MAT-BTM', 'maternity-bottoms'],
    [4, 1, 'Maternity Nightwear', 'MAT-NIGHT', 'maternity-nightwear'],
    [7, 2, 'Infant Rompers', 'INF-ROMP', 'infant-rompers'],
    [8, 2, 'Infant Bodysuits', 'INF-BODY', 'infant-bodysuits'],
    [10, 3, 'Girls Frocks', 'GIRL-FROCK', 'girls-frocks'],
    [12, 3, 'Girls Sets', 'GIRL-SET', 'girls-sets'],
    [13, 4, 'Boys T-Shirts', 'BOY-TEE', 'boys-t-shirts'],
    [14, 4, 'Boys Shorts', 'BOY-SHORTS', 'boys-shorts'],
    [21, 6, 'Swaddles & Blankets', 'BABY-SWD', 'swaddles-blankets'],
];
foreach ($subcategories as $sc) {
    Database::execute("INSERT INTO subcategories (id, category_id, name, code, slug, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), name=VALUES(name), code=VALUES(code), slug=VALUES(slug)", $sc);
}

// Ensure prerequisite Sizes
$sizes = [
    [1, 'S', 'S', 1],
    [2, 'M', 'M', 2],
    [3, 'L', 'L', 3],
    [4, 'XL', 'XL', 4],
    [5, 'XXL', 'XXL', 5],
    [6, '3XL', '3XL', 6],
    [7, 'Newborn', 'NB', 7],
    [8, '0-3 Months', '0-3M', 8],
    [9, '3-6 Months', '3-6M', 9],
    [10, '6-12 Months', '6-12M', 10],
    [11, '1-2 Years', '1-2Y', 11],
    [12, '2-3 Years', '2-3Y', 12],
    [13, '3-4 Years', '3-4Y', 13],
    [14, '4-5 Years', '4-5Y', 14],
    [15, '5-6 Years', '5-6Y', 15],
    [16, 'Free Size', 'FS', 16],
];
foreach ($sizes as $sz) {
    Database::execute("INSERT INTO sizes (id, name, code, sort_order, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name), code=VALUES(code)", $sz);
}

// Ensure prerequisite Colors
$colors = [
    [1, 'Royal Blue', '#1E40AF'],
    [2, 'Crimson Red', '#DC2626'],
    [3, 'Navy Blue', '#0F172A'],
    [4, 'Pearl White', '#F8FAFC'],
    [5, 'Olive Green', '#4D7C0F'],
    [9, 'Sky Blue', '#38BDF8'],
    [10, 'Mint Green', '#34D399'],
    [11, 'Sunshine Yellow', '#FBBF24'],
    [12, 'Forest Olive', '#3F6212'],
    [13, 'Lavender Purple', '#C084FC'],
    [14, 'Coral Pink', '#F43F5E'],
    [25, 'Lemon Yellow', '#FDE047'],
    [26, 'Ruby Red', '#B91C1C'],
    [27, 'Pure White', '#FFFFFF'],
    [28, 'Pitch Black', '#000000'],
    [29, 'Blush Peach', '#FDBA74'],
];
foreach ($colors as $col) {
    Database::execute("INSERT INTO colors (id, name, hex_code, is_active, created_at)
        VALUES (?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name), hex_code=VALUES(hex_code)", $col);
}

echo "Prerequisite Masters Seeded (Categories, Brands, Subcategories, Sizes, Colors).\n\n";

$products = [
    [
        'code'             => 'MAK-0101',
        'name'             => 'Maternity Pure Cotton Feeding Anarkali Kurti',
        'category_id'      => 1,
        'subcategory_id'   => 1,
        'brand_id'         => 2,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '100% Breathable Cotton with Concealed Feeding Zips',
        'description'      => 'Premium ethnic maternity feeding kurti designed with dual side concealed invisible zippers for comfortable nursing.',
        'hsn_code'         => '6204',
        'gst_percentage'   => 5.00,
        'min_stock'        => 5,
        'max_stock'        => 150,
        'variants'         => [
            ['size_id' => 3, 'color_id' => 3,  'sku' => 'MAK0101-M-NVY',  'qr' => 'MAK0101MNVY',  'mrp' => 1299.00, 'sp' => 899.00, 'cost' => 420.00, 'shop_qty' => 35, 'wh_qty' => 120],
            ['size_id' => 4, 'color_id' => 3,  'sku' => 'MAK0101-L-NVY',  'qr' => 'MAK0101LNVY',  'mrp' => 1299.00, 'sp' => 899.00, 'cost' => 420.00, 'shop_qty' => 40, 'wh_qty' => 140],
            ['size_id' => 5, 'color_id' => 26, 'sku' => 'MAK0101-XL-RED', 'qr' => 'MAK0101XLRED', 'mrp' => 1399.00, 'sp' => 949.00, 'cost' => 440.00, 'shop_qty' => 25, 'wh_qty' => 90],
        ]
    ],
    [
        'code'             => 'IBR-0202',
        'name'             => 'Infant Organic Bio-Wash Cotton Romper Set',
        'category_id'      => 2,
        'subcategory_id'   => 7,
        'brand_id'         => 1,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '100% Certified Organic Interlock Cotton',
        'description'      => 'Ultra-soft bio-washed newborn baby rompers with nickel-free snaps and expandable envelope necklines.',
        'hsn_code'         => '6111',
        'gst_percentage'   => 5.00,
        'min_stock'        => 8,
        'max_stock'        => 200,
        'variants'         => [
            ['size_id' => 8,  'color_id' => 10, 'sku' => 'IBR0202-03M-MNT',  'qr' => 'IBR020203MMNT',  'mrp' => 699.00, 'sp' => 499.00, 'cost' => 210.00, 'shop_qty' => 50, 'wh_qty' => 180],
            ['size_id' => 9,  'color_id' => 9,  'sku' => 'IBR0202-36M-BLU',  'qr' => 'IBR020236MBLU',  'mrp' => 699.00, 'sp' => 499.00, 'cost' => 210.00, 'shop_qty' => 45, 'wh_qty' => 160],
            ['size_id' => 10, 'color_id' => 25, 'sku' => 'IBR0202-612M-YEL', 'qr' => 'IBR0202612MYEL', 'mrp' => 749.00, 'sp' => 549.00, 'cost' => 230.00, 'shop_qty' => 30, 'wh_qty' => 110],
        ]
    ],
    [
        'code'             => 'BCT-0303',
        'name'             => 'Boys Bio-Wash Crew Neck Graphic T-Shirt',
        'category_id'      => 4,
        'subcategory_id'   => 13,
        'brand_id'         => 13,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '100% Combed Cotton Single Jersey 180 GSM',
        'description'      => 'Vibrant casual round neck graphic print t-shirt for boys, breathable and color-fast with reinforced stitching.',
        'hsn_code'         => '6109',
        'gst_percentage'   => 5.00,
        'min_stock'        => 10,
        'max_stock'        => 250,
        'variants'         => [
            ['size_id' => 11, 'color_id' => 28, 'sku' => 'BCT0303-12Y-BLK', 'qr' => 'BCT030312YBLK', 'mrp' => 499.00, 'sp' => 349.00, 'cost' => 150.00, 'shop_qty' => 60, 'wh_qty' => 220],
            ['size_id' => 13, 'color_id' => 26, 'sku' => 'BCT0303-34Y-RED', 'qr' => 'BCT030334YRED', 'mrp' => 499.00, 'sp' => 349.00, 'cost' => 150.00, 'shop_qty' => 55, 'wh_qty' => 200],
            ['size_id' => 15, 'color_id' => 11, 'sku' => 'BCT0303-56Y-YEL', 'qr' => 'BCT030356YYEL', 'mrp' => 549.00, 'sp' => 399.00, 'cost' => 170.00, 'shop_qty' => 40, 'wh_qty' => 150],
        ]
    ],
    [
        'code'             => 'GFF-0404',
        'name'             => 'Girls Floral Cotton Tiered Party Frock',
        'category_id'      => 3,
        'subcategory_id'   => 10,
        'brand_id'         => 15,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => 'Cotton Cambric with Breathable Voile Lining',
        'description'      => 'Charming tiered party frock for girls with hand-printed floral motifs, flutter sleeves, and bow waistband.',
        'hsn_code'         => '6204',
        'gst_percentage'   => 5.00,
        'min_stock'        => 6,
        'max_stock'        => 180,
        'variants'         => [
            ['size_id' => 11, 'color_id' => 29, 'sku' => 'GFF0404-12Y-PCH', 'qr' => 'GFF040412YPCH', 'mrp' => 899.00, 'sp' => 649.00, 'cost' => 280.00, 'shop_qty' => 35, 'wh_qty' => 130],
            ['size_id' => 13, 'color_id' => 13, 'sku' => 'GFF0404-34Y-LAV', 'qr' => 'GFF040434YLAV', 'mrp' => 899.00, 'sp' => 649.00, 'cost' => 280.00, 'shop_qty' => 45, 'wh_qty' => 150],
            ['size_id' => 15, 'color_id' => 27, 'sku' => 'GFF0404-56Y-WHT', 'qr' => 'GFF040456YWHT', 'mrp' => 999.00, 'sp' => 699.00, 'cost' => 310.00, 'shop_qty' => 25, 'wh_qty' => 100],
        ]
    ],
    [
        'code'             => 'MSL-0505',
        'name'             => 'Maternity Stretch Over-Belly Ankle Leggings',
        'category_id'      => 1,
        'subcategory_id'   => 3,
        'brand_id'         => 2,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '95% Combed Cotton 5% Spandex 4-Way Stretch',
        'description'      => 'Seamless high-waist over-belly maternity support leggings providing gentle compression and belly support.',
        'hsn_code'         => '6104',
        'gst_percentage'   => 5.00,
        'min_stock'        => 8,
        'max_stock'        => 200,
        'variants'         => [
            ['size_id' => 3, 'color_id' => 28, 'sku' => 'MSL0505-M-BLK',  'qr' => 'MSL0505MBLK',  'mrp' => 699.00, 'sp' => 499.00, 'cost' => 220.00, 'shop_qty' => 50, 'wh_qty' => 170],
            ['size_id' => 4, 'color_id' => 28, 'sku' => 'MSL0505-L-BLK',  'qr' => 'MSL0505LBLK',  'mrp' => 699.00, 'sp' => 499.00, 'cost' => 220.00, 'shop_qty' => 65, 'wh_qty' => 210],
            ['size_id' => 5, 'color_id' => 3,  'sku' => 'MSL0505-XL-NVY', 'qr' => 'MSL0505XLNVY', 'mrp' => 749.00, 'sp' => 549.00, 'cost' => 240.00, 'shop_qty' => 35, 'wh_qty' => 120],
        ]
    ],
    [
        'code'             => 'BCS-0606',
        'name'             => 'Boys Elastic Waist Cotton Twill Chino Shorts',
        'category_id'      => 4,
        'subcategory_id'   => 14,
        'brand_id'         => 13,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => 'Heavyweight Cotton Twill with Drawstring Waist',
        'description'      => 'Durable everyday casual chino shorts for boys with dual slant pockets and comfortable elastic waistband.',
        'hsn_code'         => '6203',
        'gst_percentage'   => 5.00,
        'min_stock'        => 6,
        'max_stock'        => 160,
        'variants'         => [
            ['size_id' => 12, 'color_id' => 12, 'sku' => 'BCS0606-23Y-OLV', 'qr' => 'BCS060623YOLV', 'mrp' => 599.00, 'sp' => 429.00, 'cost' => 190.00, 'shop_qty' => 40, 'wh_qty' => 140],
            ['size_id' => 14, 'color_id' => 3,  'sku' => 'BCS0606-45Y-NVY', 'qr' => 'BCS060645YNVY', 'mrp' => 599.00, 'sp' => 429.00, 'cost' => 190.00, 'shop_qty' => 45, 'wh_qty' => 150],
        ]
    ],
    [
        'code'             => 'MSB-0707',
        'name'             => 'Newborn Soft Muslin Swaddle Blankets 3-Pack',
        'category_id'      => 6,
        'subcategory_id'   => 21,
        'brand_id'         => 3,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '100% Pre-Washed Organic Muslin Cotton',
        'description'      => 'Breathable, lightweight 2-layer muslin cotton swaddle wraps for infants, pre-washed for extreme softness.',
        'hsn_code'         => '6301',
        'gst_percentage'   => 12.00,
        'min_stock'        => 5,
        'max_stock'        => 120,
        'variants'         => [
            ['size_id' => 16, 'color_id' => 10, 'sku' => 'MSB0707-FS-MNT', 'qr' => 'MSB0707FSMNT', 'mrp' => 899.00, 'sp' => 649.00, 'cost' => 290.00, 'shop_qty' => 30, 'wh_qty' => 90],
            ['size_id' => 16, 'color_id' => 29, 'sku' => 'MSB0707-FS-PCH', 'qr' => 'MSB0707FSPCH', 'mrp' => 899.00, 'sp' => 649.00, 'cost' => 290.00, 'shop_qty' => 30, 'wh_qty' => 90],
        ]
    ],
    [
        'code'             => 'GLS-0808',
        'name'             => 'Girls Soft Cotton Printed Leggings & Top Set',
        'category_id'      => 3,
        'subcategory_id'   => 12,
        'brand_id'         => 15,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '100% Bio-Wash Combed Cotton Knit',
        'description'      => 'Comfortable 2-piece lounge and play set for young girls with elasticated stretch leggings and peplum top.',
        'hsn_code'         => '6108',
        'gst_percentage'   => 5.00,
        'min_stock'        => 7,
        'max_stock'        => 170,
        'variants'         => [
            ['size_id' => 12, 'color_id' => 13, 'sku' => 'GLS0808-23Y-LAV', 'qr' => 'GLS080823YLAV', 'mrp' => 649.00, 'sp' => 469.00, 'cost' => 200.00, 'shop_qty' => 35, 'wh_qty' => 120],
            ['size_id' => 14, 'color_id' => 9,  'sku' => 'GLS0808-45Y-BLU', 'qr' => 'GLS080845YBLU', 'mrp' => 649.00, 'sp' => 469.00, 'cost' => 200.00, 'shop_qty' => 40, 'wh_qty' => 130],
        ]
    ],
    [
        'code'             => 'MFN-0909',
        'name'             => 'Maternity & Nursing Rayon Flared Nightdress',
        'category_id'      => 1,
        'subcategory_id'   => 4,
        'brand_id'         => 2,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => 'Ultra-Soft Breathable Liva Rayon with Invisible Nursing Zips',
        'description'      => 'Feather-light flowing rayon maternity and nursing nighty designed with discreet vertical feeding openings.',
        'hsn_code'         => '6208',
        'gst_percentage'   => 5.00,
        'min_stock'        => 5,
        'max_stock'        => 140,
        'variants'         => [
            ['size_id' => 4, 'color_id' => 14, 'sku' => 'MFN0909-L-COR',   'qr' => 'MFN0909LCOR',   'mrp' => 1099.00, 'sp' => 799.00, 'cost' => 360.00, 'shop_qty' => 30, 'wh_qty' => 110],
            ['size_id' => 5, 'color_id' => 3,  'sku' => 'MFN0909-XL-NVY',  'qr' => 'MFN0909XLNVY',  'mrp' => 1149.00, 'sp' => 849.00, 'cost' => 380.00, 'shop_qty' => 35, 'wh_qty' => 125],
            ['size_id' => 6, 'color_id' => 28, 'sku' => 'MFN0909-XXL-BLK', 'qr' => 'MFN0909XXLBLK', 'mrp' => 1199.00, 'sp' => 899.00, 'cost' => 400.00, 'shop_qty' => 20, 'wh_qty' => 80],
        ]
    ],
    [
        'code'             => 'ISB-1010',
        'name'             => 'Infant Unisex Sleeveless Cotton Bodysuit 2-Pack',
        'category_id'      => 2,
        'subcategory_id'   => 8,
        'brand_id'         => 1,
        'source_type'      => 'OWN_PRODUCTION',
        'material'         => '100% Breathable Ribbed Cotton with Nickel-Free Snaps',
        'description'      => 'Summer sleeveless infant onesies made with 100% fine ribbed cotton for all-day breathability and comfort.',
        'hsn_code'         => '6111',
        'gst_percentage'   => 5.00,
        'min_stock'        => 8,
        'max_stock'        => 220,
        'variants'         => [
            ['size_id' => 8,  'color_id' => 27, 'sku' => 'ISB1010-03M-WHT',  'qr' => 'ISB101003MWHT',  'mrp' => 499.00, 'sp' => 349.00, 'cost' => 140.00, 'shop_qty' => 55, 'wh_qty' => 190],
            ['size_id' => 9,  'color_id' => 10, 'sku' => 'ISB1010-36M-MNT',  'qr' => 'ISB101036MMNT',  'mrp' => 499.00, 'sp' => 349.00, 'cost' => 140.00, 'shop_qty' => 50, 'wh_qty' => 180],
            ['size_id' => 10, 'color_id' => 9,  'sku' => 'ISB1010-612M-BLU', 'qr' => 'ISB1010612MBLU', 'mrp' => 549.00, 'sp' => 399.00, 'cost' => 160.00, 'shop_qty' => 40, 'wh_qty' => 150],
        ]
    ]
];

$insertedProducts = 0;
$insertedVariants = 0;
$totalShopStock = 0;
$totalWhStock = 0;

foreach ($products as $pData) {
    // Check if product with code exists
    $existing = Database::fetchOne("SELECT id FROM products WHERE code = ?", [$pData['code']]);
    if ($existing) {
        $prodId = (int)$existing['id'];
        Database::execute(
            "UPDATE products 
             SET name = ?, category_id = ?, subcategory_id = ?, brand_id = ?, source_type = ?, 
                 material = ?, description = ?, hsn_code = ?, gst_percentage = ?, min_stock = ?, max_stock = ?, is_active = 1, updated_at = NOW()
             WHERE id = ?",
            [
                $pData['name'], $pData['category_id'], $pData['subcategory_id'], $pData['brand_id'], $pData['source_type'],
                $pData['material'], $pData['description'], $pData['hsn_code'], $pData['gst_percentage'], $pData['min_stock'], $pData['max_stock'],
                $prodId
            ]
        );
        echo "Updated Product: [{$pData['code']}] {$pData['name']}\n";
    } else {
        Database::execute(
            "INSERT INTO products (code, name, category_id, subcategory_id, brand_id, source_type, material, description, hsn_code, gst_percentage, min_stock, max_stock, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                $pData['code'], $pData['name'], $pData['category_id'], $pData['subcategory_id'], $pData['brand_id'], $pData['source_type'],
                $pData['material'], $pData['description'], $pData['hsn_code'], $pData['gst_percentage'], $pData['min_stock'], $pData['max_stock']
            ]
        );
        $prodId = (int)Database::lastInsertId();
        $insertedProducts++;
        echo "Created Product: [{$pData['code']}] {$pData['name']} (ID: {$prodId})\n";
    }

    // Insert/Update Variants
    $prodTotalQty = 0;
    foreach ($pData['variants'] as $v) {
        $vRow = Database::fetchOne("SELECT id FROM product_variants WHERE sku = ? OR qr_code = ?", [$v['sku'], $v['qr']]);
        if ($vRow) {
            $variantId = (int)$vRow['id'];
            Database::execute(
                "UPDATE product_variants 
                 SET product_id = ?, size_id = ?, color_id = ?, sku = ?, qr_code = ?, mrp = ?, selling_price = ?, purchase_price = ?, production_cost = ?, is_active = 1, updated_at = NOW()
                 WHERE id = ?",
                [
                    $prodId, $v['size_id'], $v['color_id'], $v['sku'], $v['qr'],
                    $v['mrp'], $v['sp'], $v['cost'], $v['cost'],
                    $variantId
                ]
            );
        } else {
            Database::execute(
                "INSERT INTO product_variants (product_id, size_id, color_id, sku, qr_code, qr_type, pack_quantity, mrp, selling_price, purchase_price, production_cost, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 'INDIVIDUAL', 1, ?, ?, ?, ?, 1, NOW())",
                [
                    $prodId, $v['size_id'], $v['color_id'], $v['sku'], $v['qr'],
                    $v['mrp'], $v['sp'], $v['cost'], $v['cost']
                ]
            );
            $variantId = (int)Database::lastInsertId();
            $insertedVariants++;
        }

        // Shop Stock Balance
        $shopStockRow = Database::fetchOne(
            "SELECT id FROM stock_balances WHERE variant_id = ? AND location_type = 'SHOP' AND location_id = ? AND stock_state = 'AVAILABLE'",
            [$variantId, $shopId]
        );
        if ($shopStockRow) {
            Database::execute("UPDATE stock_balances SET quantity = ?, last_updated = NOW() WHERE id = ?", [$v['shop_qty'], $shopStockRow['id']]);
        } else {
            Database::execute(
                "INSERT INTO stock_balances (variant_id, location_type, location_id, stock_state, quantity, last_updated, created_at)
                 VALUES (?, 'SHOP', ?, 'AVAILABLE', ?, NOW(), NOW())",
                [$variantId, $shopId, $v['shop_qty']]
            );
        }
        $totalShopStock += $v['shop_qty'];

        // Warehouse Stock Balance
        $whStockRow = Database::fetchOne(
            "SELECT id FROM stock_balances WHERE variant_id = ? AND location_type = 'WAREHOUSE' AND location_id = 1 AND stock_state = 'AVAILABLE'",
            [$variantId]
        );
        if ($whStockRow) {
            Database::execute("UPDATE stock_balances SET quantity = ?, last_updated = NOW() WHERE id = ?", [$v['wh_qty'], $whStockRow['id']]);
        } else {
            Database::execute(
                "INSERT INTO stock_balances (variant_id, location_type, location_id, stock_state, quantity, last_updated, created_at)
                 VALUES (?, 'WAREHOUSE', 1, 'AVAILABLE', ?, NOW(), NOW())",
                [$variantId, $v['wh_qty']]
            );
        }
        $totalWhStock += $v['wh_qty'];
        $prodTotalQty += ($v['shop_qty'] + $v['wh_qty']);
    }

    // Update product overall quantity
    Database::execute("UPDATE products SET quantity = ? WHERE id = ?", [$prodTotalQty, $prodId]);
}

echo "\n========================================================\n";
echo "  FEED COMPLETED SUCCESSFULLY!\n";
echo "  Products Processed : 10\n";
echo "  Total Variants     : " . count($products) . " styles with full size/color matrix\n";
echo "  Total Shop Stock   : " . number_format($totalShopStock) . " pcs seeded in Shop [ID: {$shopId}]\n";
echo "  Total Wh Stock     : " . number_format($totalWhStock) . " pcs seeded in Main Warehouse\n";
echo "========================================================\n";
