<?php
$ch = curl_init();
$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Get CSRF token from login page
curl_setopt($ch, CURLOPT_URL, 'http://localhost/grm_inventory/grm_inventory/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$loginPage = curl_exec($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
$csrf = $m[1] ?? '';

// 2. Post login
curl_setopt($ch, CURLOPT_URL, 'http://localhost/grm_inventory/grm_inventory/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'username'   => 'admin',
    'password'   => 'admin123'
]));
$res = curl_exec($ch);

// 3. Fetch batches page
curl_setopt($ch, CURLOPT_URL, 'http://localhost/grm_inventory/grm_inventory/production/batches');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$batchesPage = curl_exec($ch);

curl_close($ch);

echo "Fetched page length: " . strlen($batchesPage) . PHP_EOL;
$pos = strpos($batchesPage, 'batchesPaginationFooter');
if ($pos !== false) {
    echo "FOUND batchesPaginationFooter:\n";
    echo substr($batchesPage, $pos, 800) . PHP_EOL;
} else {
    echo "NOT FOUND batchesPaginationFooter\n";
    if (strpos($batchesPage, 'Login') !== false) {
        echo "Still at Login page! Admin credentials might be different.\n";
    }
}
