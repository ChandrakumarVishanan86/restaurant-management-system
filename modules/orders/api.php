<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'waiter', 'cashier', 'customer'], json: true);
$isFloor = in_array($user['role'], Auth::FLOOR, true);

function order_payload(int $id): array
{
    $o = OrderModel::find($id);
    $items = OrderModel::items($id);
    $sub = OrderModel::subtotal($id);
    $bill = null;
    if ($o && $o['bill_id']) { $bill = BillModel::find((int) $o['bill_id']); }
    return ['order' => $o, 'items' => $items, 'subtotal' => $sub, 'bill' => $bill, 'now' => date('Y-m-d H:i:s')];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $a = $_GET['action'] ?? '';
    if ($a === 'list') { json_response(['success' => true, 'data' => OrderModel::list($user, (string) ($_GET['status'] ?? ''))]); }
    if ($a === 'menu') { if (!$isFloor) { json_response(['success' => false, 'message' => 'Forbidden.'], 403); } json_response(['success' => true, 'data' => MenuItemModel::orderable()]); }
    if ($a === 'get') {
        $o = OrderModel::find((int) ($_GET['id'] ?? 0));
        if (!$o || !OrderModel::canAccess($user, $o)) { json_response(['success' => false, 'message' => 'Order not found.'], 404); }
        json_response(['success' => true] + order_payload((int) $o['id']));
    }
    if ($a === 'quote') {
        if (!$isFloor) { json_response(['success' => false, 'message' => 'Forbidden.'], 403); }
        $o = OrderModel::find((int) ($_GET['id'] ?? 0));
        if (!$o) { json_response(['success' => false, 'message' => 'Order not found.'], 404); }
        [$q, $err] = BillModel::quote($o, $_GET, $user);
        json_response(['success' => !$err, 'message' => $err, 'quote' => $q, 'points_available' => (int) $o['loyalty_points']]);
    }
    json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'Method not allowed.'], 405); }
if (!$_POST) { $j = json_input(); if (is_array($j)) { $_POST = $j; } }
require_csrf();
$in = $_POST; $a = (string) ($in['action'] ?? ''); $id = (int) ($in['id'] ?? 0);
$fail = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);

if ($user['role'] === 'customer') {
    if ($a !== 'customer_cancel') { $fail('Forbidden.', 403); }
    $o = OrderModel::find($id);
    if (!$o || !OrderModel::canAccess($user, $o)) { $fail('Order not found.', 404); }
    $err = OrderModel::customerCancel($id, $user);
    $err ? $fail($err) : json_response(['success' => true, 'message' => 'Order cancelled.']);
}

if ($a === 'open') {
    [$nid, $err] = OrderModel::open($user, $in);
    $err ? $fail($err) : json_response(['success' => true, 'id' => $nid, 'message' => 'Order opened.']);
}
$o = OrderModel::find($id);
if (!$o) { $fail('Order not found.', 404); }
switch ($a) {
    case 'add_item':
        $err = OrderModel::addItem($id, (int) ($in['menu_item_id'] ?? 0), (int) ($in['qty'] ?? 1), (string) ($in['note'] ?? ''), $user);
        break;
    case 'set_qty': $err = OrderModel::setQty((int) ($in['item_id'] ?? 0), (int) ($in['qty'] ?? 0)); break;
    case 'remove_item': $err = OrderModel::removeItem((int) ($in['item_id'] ?? 0), $user); break;
    case 'send':
        [$kot, $err] = OrderModel::send($id);
        if (!$err) { json_response(['success' => true, 'message' => "Sent to kitchen (KOT #$kot).", 'kot' => $kot] + order_payload($id)); }
        break;
    case 'serve':
        [$n, $err] = OrderModel::serve($id, isset($in['ids']) ? (array) $in['ids'] : null);
        if (!$err) { json_response(['success' => true, 'message' => "$n item(s) marked served."] + order_payload($id)); }
        break;
    case 'cancel': $err = OrderModel::cancel($id, $user); break;
    case 'move': $err = OrderModel::move($id, (int) ($in['table_id'] ?? 0)); break;
    case 'generate':
        [$bid, $err] = BillModel::generate($id, $in, $user);
        if (!$err) { json_response(['success' => true, 'message' => 'Bill generated.', 'bill_id' => $bid]); }
        break;
    default: $fail('Unknown action.', 400);
}
$err ? $fail($err) : json_response(['success' => true, 'message' => 'Done.'] + order_payload($id));
