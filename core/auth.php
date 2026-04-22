<?php
function authorize(DB $db, string $permission = 'view'): void
{
    if (!isset($_SESSION['role_id'])) {
        header('Location: login.php');
        exit;
    }

    $page = basename($_SERVER['PHP_SELF']);
    $permColumn = "can_$permission";

    $res = $db->query("
        SELECT 1
        FROM menus m 
        JOIN role_menu_permissions p ON p.menu_id = m.id
        WHERE p.role_id = ?
          AND m.route = ?
          AND p.$permColumn = 1
          AND m.hidden = 0
        LIMIT 1
    ", [$_SESSION['role_id'], $page]);

    if (!$res['success'] || empty($res['result'])) {
        http_response_code(403);
        exit("403 | Permission Denied ($permission)");
    }
}
?>