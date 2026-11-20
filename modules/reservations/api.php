<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'waiter', 'cashier', 'customer'], json: true);
$fail = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $a = $_GET['action'] ?? '';
    if ($a === 'list') {
        $f = (string) ($_GET['from'] ?? ''); $t = (string) ($_GET['to'] ?? '');
        json_response(['success' => true, 'data' => ReservationModel::list($user, $f !== '' && valid_date($f) ? $f : null, $t !== '' && valid_date($t) ? $t : null, (string) ($_GET['status'] ?? ''))]);
    }
    if ($a === 'slots') {
        $d = (string) ($_GET['date'] ?? ''); $p = (int) ($_GET['party'] ?? 0);
        if (!valid_date($d) || $p < 1 || $p > 30) { $fail('Choose a date and party size.'); }
        if ($d < date('Y-m-d') || $d > date('Y-m-d', strtotime('+' . RES_DAYS_AHEAD . ' days'))) { $fail('Reservations open for the next ' . RES_DAYS_AHEAD . ' days.'); }
        json_response(['success' => true, 'data' => ReservationModel::slots($d, $p)]);
    }
    if ($a === 'tables') {
        if (!in_array($user['role'], Auth::FLOOR, true)) { $fail('Forbidden.', 403); }
        json_response(['success' => true, 'data' => ReservationModel::freeTables((string) ($_GET['date'] ?? ''), substr((string) ($_GET['time'] ?? ''), 0, 5), (int) ($_GET['party'] ?? 1))]);
    }
    $fail('Unknown action.', 400);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('Method not allowed.', 405); }
if (!$_POST) { $j = json_input(); if (is_array($j)) { $_POST = $j; } }
require_csrf();
$in = $_POST; $a = (string) ($in['action'] ?? ''); $id = (int) ($in['id'] ?? 0);
if ($a === 'book') { [$nid, $err] = ReservationModel::book($user, $in); $err ? $fail($err) : json_response(['success' => true, 'id' => $nid, 'message' => 'Table reserved.']); }
if ($a === 'cancel' || $a === 'no_show') { $err = ReservationModel::setStatus($id, $a === 'cancel' ? 'Cancelled' : 'No-show', $user); $err ? $fail($err) : json_response(['success' => true, 'message' => $a === 'cancel' ? 'Reservation cancelled.' : 'Marked as no-show.']); }
if ($a === 'seat') { [$oid, $err] = ReservationModel::seat($id, (int) ($in['table_id'] ?? 0) ?: null, $user); $err ? $fail($err) : json_response(['success' => true, 'order_id' => $oid, 'message' => 'Guests seated, order opened.']); }
$fail('Unknown action.', 400);
