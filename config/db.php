<?php

// $host = 'aws-1-ap-northeast-1.pooler.supabase.com';
// $port = '6543';
// $dbname = 'postgres';
// $user = 'postgres.okwnknzodfvbsbgetvzh';
// $password = 'taliresq67_';

$host = 'aws-0-ap-southeast-2.pooler.supabase.com';
$port = '6543';
$dbname = 'postgres';
$user = 'postgres.llfhttyqxogagirvdxzs';
$password = 'taliresq67_';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
