<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$from = (string) ($_GET['from'] ?? date('Y-m-01')); $to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!valid_date($from) || !valid_date($to)) { http_response_code(400); exit('Invalid dates.'); }
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="bills_' . $from . '_' . $to . '.csv"');
$o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
$safe = static fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
fputcsv($o, ['Bill', 'Paid on', 'Order', 'Type', 'Customer', 'Subtotal', 'Discount', 'Points', 'Service', 'VAT', 'Total', 'Paid', 'Status']);
foreach (ReportModel::billRows($from, $to) as $r) { fputcsv($o, array_map($safe, array_values($r))); }
fclose($o);
