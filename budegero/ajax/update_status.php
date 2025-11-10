<?php
include '../../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_status_only'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$id = intval($_POST['product_stock_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$cancelDetails = trim($_POST['cancel_details'] ?? '');

if ($id <= 0 || $status === '') {
    echo json_encode(['error' => 'Missing or invalid parameters']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT product_id FROM product_stocks WHERE product_stock_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new Exception("Batch not found.");
    $product = $res->fetch_assoc();
    $product_id = $product['product_id'];

    // If setting to active, deactivate other active batches
    if (strtolower($status) === 'active') {
        $stmt = $conn->prepare("
            UPDATE product_stocks 
            SET status='pulled out', updated_at=NOW() 
            WHERE product_id=? AND status='active' AND product_stock_id != ?");
        $stmt->bind_param('ii', $product_id, $id);
        $stmt->execute();
    }

    if (strtolower($status) === 'cancelled') {
        $stmt = $conn->prepare("
            UPDATE product_stocks 
            SET status='cancelled', cancel_details=?, updated_at=NOW() 
            WHERE product_stock_id=?");
        $stmt->bind_param('si', $cancelDetails, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            UPDATE product_stocks 
            SET status=?, remarks=?, updated_at=NOW() 
            WHERE product_stock_id=?");
        $stmt->bind_param('ssi', $status, $remarks, $id);
        $stmt->execute();
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(remaining_qty),0) AS total_qty 
        FROM product_stocks WHERE product_id=? AND status='active'");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $qtyResult = $stmt->get_result()->fetch_assoc();
    $totalQty = $qtyResult['total_qty'];

    $stmt = $conn->prepare("UPDATE product SET total_quantity=?, updated_at=NOW() WHERE product_id=?");
    $stmt->bind_param('di', $totalQty, $product_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}
?>
