<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- 表行数 ---\n";
foreach (['users','products','orders','categories','coupons','cart_items','subscription_plans','user_subscriptions','payments','webhook_events','order_status_logs','user_preferences','notification_preferences'] as $t) {
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo str_pad($t, 28) . " = $n rows\n";
    } catch (Throwable $e) {
        echo str_pad($t, 28) . " = (missing)\n";
    }
}

echo "\n--- admin user ---\n";
$u = $pdo->query("SELECT name,email,is_admin,locale FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($u);
