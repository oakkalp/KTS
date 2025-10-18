<?php
/**
 * Mekan klasöründen sipariş detay sayfasına yönlendirme
 */
$order_id = $_GET['id'] ?? '';
header('Location: ../siparis-detay.php?id=' . urlencode($order_id));
exit;
?>
