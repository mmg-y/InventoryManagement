<?php
require('../fpdf.php');
include '../../config.php';

class POSReceipt extends FPDF
{
    function Header()
    {
        // Logo (optional)
        $logo_path = __DIR__ . '/logo.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 27, 5, 25);
            $this->Ln(20);
        } else {
            $this->Ln(5);
        }

        // Store info
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, 'MartIQ Supermarket', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Biglang Awa St Cor 11th Ave Catleya,', 0, 1, 'C');
        $this->Cell(0, 5, 'Grace Park East, Caloocan, 1400 Metro Manila', 0, 1, 'C');
        $this->Ln(2);
        $this->Cell(0, 5, str_repeat('-', 42), 0, 1, 'C');
    }

    function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, str_repeat('-', 42), 0, 1, 'C');
        $this->Cell(0, 5, 'Thank you for shopping at MartIQ!', 0, 1, 'C');
        $this->Cell(0, 5, 'Please come again!', 0, 0, 'C');
    }

    function AddSaleDetails($sale)
    {
        $this->Ln(2);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Date: ' . date("Y-m-d H:i", strtotime($sale['sale_date'])), 0, 1, 'L');
        $this->Cell(0, 5, 'Cashier: ' . $sale['first_name'] . ' ' . $sale['last_name'], 0, 1, 'L');
        $this->Cell(0, 5, 'Sale ID: #' . $sale['sales_id'], 0, 1, 'L');
        $this->Cell(0, 5, str_repeat('-', 42), 0, 1, 'C');
    }

    function AddItemsTable($items)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(40, 6, 'Item', 0, 0, 'L');
        $this->Cell(10, 6, 'Qty', 0, 0, 'C');
        $this->Cell(15, 6, 'Price', 0, 0, 'R');
        $this->Cell(15, 6, 'Total', 0, 1, 'R');
        $this->SetFont('Arial', '', 9);

        $subtotal = 0;
        foreach ($items as $row) {
            $item_total = $row['quantity'] * $row['unit_price'];
            $subtotal += $item_total;

            $this->Cell(40, 5, substr($row['product_name'], 0, 20), 0, 0, 'L');
            $this->Cell(10, 5, $row['quantity'], 0, 0, 'C');
            $this->Cell(15, 5, number_format($row['unit_price'], 2), 0, 0, 'R');
            $this->Cell(15, 5, number_format($item_total, 2), 0, 1, 'R');
        }

        $this->Cell(0, 5, str_repeat('-', 42), 0, 1, 'C');
        return $subtotal;
    }

    function AddSummary($subtotal, $cash, $change)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(42, 5, 'Subtotal:', 0, 0, 'R');
        $this->Cell(25, 5, number_format($subtotal, 2), 0, 1, 'R');

        $this->Cell(42, 5, 'Cash Received:', 0, 0, 'R');
        $this->Cell(25, 5, number_format($cash, 2), 0, 1, 'R');

        $this->Cell(42, 5, 'Change:', 0, 0, 'R');
        $this->Cell(25, 5, number_format($change, 2), 0, 1, 'R');

        $this->Ln(4);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'VAT included where applicable', 0, 1, 'C');
    }
}

// Get sale_id
$sale_id = $_GET['sale_id'] ?? null;
if (!$sale_id) die("Missing sale_id.");

// Fetch sale info (including cash + change)
$sale_query = $conn->prepare("
    SELECT s.sales_id, s.total_amount, s.cash_received, s.change_amount, s.sale_date, u.first_name, u.last_name 
    FROM sales s
    JOIN user u ON s.cashier_id = u.id
    WHERE s.sales_id = ?
");
$sale_query->bind_param("i", $sale_id);
$sale_query->execute();
$sale_result = $sale_query->get_result();
if ($sale_result->num_rows === 0) die("Sale not found.");
$sale = $sale_result->fetch_assoc();

// Fetch sale items
$item_query = $conn->prepare("
    SELECT si.*, p.product_name 
    FROM sales_items si
    JOIN product p ON si.product_id = p.product_id
    WHERE si.sale_id = ?
");
$item_query->bind_param("i", $sale_id);
$item_query->execute();
$items_result = $item_query->get_result();

$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

// Compute subtotal (in case needed)
$subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

// Use actual cash + change from DB
$cash = $sale['cash_received'];
$change = $sale['change_amount'];

// Generate compact 80mm receipt
$pdf = new POSReceipt('P', 'mm', [80, 200]);
$pdf->AddPage();
$pdf->AddSaleDetails($sale);
$pdf->AddItemsTable($items);
$pdf->AddSummary($subtotal, $cash, $change);
$pdf->Output('I', 'receipt_' . $sale_id . '.pdf');
?>

<link rel="icon" href="../images/logo-teal.png" type="images/png">