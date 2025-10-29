<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config.php';

$search = trim($_GET['search'] ?? '');
$filter_category = (int)($_GET['category_filter'] ?? 0);

// Build WHERE clause
$where = [];
if ($search !== '') {
    $searchSafe = $conn->real_escape_string($search);
    $where[] = "(p.product_code LIKE '%$searchSafe%' OR p.product_name LIKE '%$searchSafe%')";
}
if ($filter_category > 0) {
    $where[] = "p.category = $filter_category";
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

// SQL query
$sql = "
SELECT p.product_id,
       p.product_code,
       p.product_name,
       p.product_picture,        
       p.category,
       c.category_name,
       p.total_quantity,
       COALESCE(p.reserved_qty,0) AS reserved_qty,
       (p.total_quantity - COALESCE(p.reserved_qty,0)) AS available_quantity,
       p.threshold_id,
       p.threshold,
       s.status_label,
       r.name AS retail_type,
       r.percent AS retail_percent,
       COALESCE(ps.cost_price,0) AS cost_price,
       (COALESCE(ps.cost_price,0) * (1 + COALESCE(r.percent,0)/100)) AS retail_price
FROM product p
LEFT JOIN category c ON p.category = c.category_id
LEFT JOIN status s ON p.threshold_id = s.status_id
LEFT JOIN retail_variables r ON p.retail_id = r.retail_id
LEFT JOIN (
    SELECT product_id, cost_price
    FROM product_stocks
    WHERE status='active'
    ORDER BY created_at DESC
) ps ON p.product_id = ps.product_id
$where_sql
ORDER BY p.product_id ASC
";

$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    // default values
    if ($row['status_label'] === null) $row['status_label'] = 'Unknown';
    if ($row['category_name'] === null) $row['category_name'] = 'Uncategorized';
    if ($row['product_picture'] === null) $row['product_picture'] = '';
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data, JSON_UNESCAPED_UNICODE);
