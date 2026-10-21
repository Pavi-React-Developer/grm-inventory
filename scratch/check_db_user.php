<?php
$pdo = new PDO('mysql:host=localhost;dbname=grm_inventory;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT id, username, email FROM users LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
