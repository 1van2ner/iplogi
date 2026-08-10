<?php
require_once 'includes/config.php';
$pdo = getDB();
$tables = ['cupones','usuario_cupones'];
$result = [];
foreach ($tables as $t) {
    try {
        $sql = "SHOW TABLES LIKE '" . addslashes($t) . "'";
        $stmt = $pdo->query($sql);
        $found = $stmt->fetchColumn();
        $result[$t] = $found ? true : false;
    } catch (Exception $e) {
        $result[$t] = 'error: '.$e->getMessage();
    }
}
header('Content-Type: text/plain');
print_r($result);
