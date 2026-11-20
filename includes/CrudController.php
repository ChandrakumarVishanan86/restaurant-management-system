<?php
declare(strict_types=1);

/**
 * Generic JSON endpoint for any BaseModel.
 *   GET  ?action=list | get&id= | check&field=&value=&exclude_id=
 *   POST action=save (id optional) | delete | <custom action in Model::action()>
 */
final class CrudController
{
    /**
     * @param class-string<BaseModel> $model
     * @param array{read:string[], write:string[]} $perm
     */
    public static function handle(string $model, array $perm): never
    {
        $user = Auth::guard($perm['read'], json: true);
        $label = $perm['label'] ?? 'Record';
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $action = $_GET['action'] ?? '';
                if ($action === 'list') { json_response(['success' => true, 'data' => $model::all($user)]); }
                if ($action === 'get') {
                    $row = $model::find((int) ($_GET['id'] ?? 0));
                    if (!$row || !$model::canAccess($user, $row)) { json_response(['success' => false, 'message' => "$label not found."], 404); }
                    json_response(['success' => true, 'data' => $row]);
                }
                if ($action === 'check') {
                    $v = trim((string) ($_GET['value'] ?? ''));
                    $f = $model::fields()[$_GET['field'] ?? ''] ?? null;
                    if ($f && !empty($f['upper'])) { $v = strtoupper($v); }
                    if ($f && !empty($f['lower'])) { $v = strtolower($v); }
                    json_response(['success' => true, 'exists' => $f && !empty($f['unique']) && $v !== '' && $model::exists($_GET['field'], $v, (int) ($_GET['exclude_id'] ?? 0))]);
                }
                json_response(['success' => false, 'message' => 'Unknown action.'], 400);
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'Method not allowed.'], 405); }
            Auth::guard($perm['write'], json: true);
            if (!$_POST) { $j = json_input(); if (is_array($j)) { $_POST = $j; } }
            require_csrf();
            $action = $_POST['action'] ?? '';

            if ($action === 'save') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $existing = $model::find($id);
                    if (!$existing || !$model::canAccess($user, $existing)) { json_response(['success' => false, 'message' => "$label not found."], 404); }
                }
                $d = $model::clean($_POST);
                $errors = $model::validate($d, $id);
                if ($errors) { json_response(['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422); }
                $newId = $model::save($d, $id, $_POST);
                json_response(['success' => true, 'id' => $newId, 'message' => $id ? "$label updated." : "$label added."]);
            }

            if ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $row = $model::find($id);
                if (!$row || !$model::canAccess($user, $row)) { json_response(['success' => false, 'message' => "$label not found."], 404); }
                if ($err = $model::beforeDelete($id)) { json_response(['success' => false, 'message' => $err], 409); }
                try {
                    $ok = $model::delete($id);
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        json_response(['success' => false, 'message' => "This $label is linked to other records (jobs, invoices…) and cannot be deleted. Mark it inactive instead."], 409);
                    }
                    throw $e;
                }
                json_response($ok ? ['success' => true, 'message' => "$label deleted."] : ['success' => false, 'message' => "$label not found."], $ok ? 200 : 404);
            }

            if (method_exists($model, 'action')) { $model::action((string) $action, $user, $_POST); }
            json_response(['success' => false, 'message' => 'Unknown action.'], 400);
        } catch (Throwable $e) {
            error_log('[crud ' . $model . '] ' . $e->getMessage());
            json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
        }
    }
}
