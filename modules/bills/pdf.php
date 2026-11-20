<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once APP_ROOT . '/includes/PdfBase.php';
$user = Auth::guard(['admin', 'cashier', 'waiter', 'customer']);
$b = BillModel::find((int) ($_GET['id'] ?? 0));
if (!$b || !BillModel::canAccess($user, $b)) { http_response_code(404); exit('Bill not found.'); }
$items = BillModel::items((int) $b['order_id']); $pays = BillModel::payments((int) $b['id']);
$pdf = new DfPdf('P', 'mm', 'A4'); $pdf->docTitle = 'Tax Invoice'; $pdf->AliasNbPages(); $pdf->SetMargins(14, 14, 14); $pdf->SetAutoPageBreak(true, 18); $pdf->AddPage();
$pdf->SetFont('Helvetica', 'B', 12); $pdf->Cell(95, 7, pdf_text('Bill ' . $b['bill_no']), 0, 0); $pdf->SetFont('Helvetica', '', 10); $pdf->Cell(87, 7, pdf_text(date('d M Y H:i', strtotime($b['created_at']))), 0, 1, 'R');
$pdf->SetFont('Helvetica', '', 9.5);
$pdf->Cell(0, 5, pdf_text('Order ' . $b['order_no'] . '  |  ' . $b['type'] . ($b['table_no'] ? '  |  Table ' . $b['table_no'] : '')), 0, 1);
$pdf->Cell(0, 5, pdf_text('Customer: ' . $b['customer_name'] . ($b['waiter_name'] ? '  |  Waiter: ' . $b['waiter_name'] : '')), 0, 1); $pdf->Ln(4);
$pdf->tableHead([['Item', 92, 'L'], ['Price', 30, 'R'], ['Qty', 20, 'C'], ['Amount', 40, 'R']]);
foreach ($items as $i) { $pdf->Cell(92, 7, pdf_text($i['name']), 'B'); $pdf->Cell(30, 7, number_format((float) $i['unit_price'], 2), 'B', 0, 'R'); $pdf->Cell(20, 7, (string) (int) $i['qty'], 'B', 0, 'C'); $pdf->Cell(40, 7, number_format((float) $i['amount'], 2), 'B', 1, 'R'); }
$pdf->Ln(3);
$row = static function (string $l, string $v, bool $bold = false) use ($pdf): void { $pdf->SetFont('Helvetica', $bold ? 'B' : '', $bold ? 11 : 9.5); $pdf->Cell(142, 6.5, pdf_text($l), 0, 0, 'R'); $pdf->Cell(40, 6.5, pdf_text($v), 0, 1, 'R'); };
$row('Subtotal', money($b['subtotal']));
if ((float) $b['discount'] > 0) { $row('Discount', '-' . money($b['discount'])); }
if ((int) $b['points_used'] > 0) { $row('Loyalty points (' . (int) $b['points_used'] . ')', '-' . money($b['points_used'] * POINT_VALUE)); }
if ((float) $b['service_charge'] > 0) { $row('Service charge (' . SERVICE_CHARGE_PCT . '%)', money($b['service_charge'])); }
$row('VAT (' . TAX_PCT . '% on base + service)', money($b['tax'])); $row('TOTAL', money($b['total']), true);
$row('Paid', money($b['paid'])); $row('Balance', money($b['balance']));
if ($pays) { $pdf->Ln(4); $pdf->heading('Payments'); $pdf->SetFont('Helvetica', '', 9); foreach ($pays as $p) { $pdf->Cell(0, 5.5, pdf_text(fdatetime($p['paid_at'] ?? $p['created_at'] ?? null) . '   ' . $p['method'] . '   ' . number_format((float) $p['amount'], 2) . ($p['reference'] ? '   ref ' . $p['reference'] : '')), 0, 1); } }
$pdf->Ln(6); $pdf->SetFont('Helvetica', 'I', 9); $pdf->Cell(0, 5, 'Thank you for dining with us!', 0, 1, 'C');
header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . $b['bill_no'] . '.pdf"');
$pdf->Output('I', $b['bill_no'] . '.pdf');
