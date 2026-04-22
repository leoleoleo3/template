<?php

function getUserMenus(DB $db, int $roleId): array
{
    $res = $db->query("
        SELECT m.*
        FROM menus m
        JOIN role_menu_permissions p ON p.menu_id = m.id
        WHERE p.role_id = ?
          AND p.can_view = 1
          AND m.hidden = 0
        ORDER BY m.parent_id, m.sort_order
    ", [$roleId]);

    return $res['success'] ? $res['result'] : [];
}

function buildMenuTree(array $menus): array
{
    $tree = [];
    $refs = [];

    foreach ($menus as $menu) {
        $menu['children'] = [];
        $refs[$menu['id']] = $menu;
    }

    foreach ($refs as $id => &$menu) {
        if ($menu['parent_id'] === null) {
            $tree[] = &$menu;
        } else {
            $refs[$menu['parent_id']]['children'][] = &$menu;
        }
    }

    return $tree;
}

function renderSidebar(array $menus, string $parentAccordion = 'sidenavAccordion'): void
{
    foreach ($menus as $menu) {
        $hasChildren = !empty($menu['children']);
        $collapseId = 'collapse_' . $menu['id'];

        if ($hasChildren) {
            echo <<<HTML
            <a class="nav-link collapsed" href="#"
               data-bs-toggle="collapse"
               data-bs-target="#$collapseId">
                <div class="sb-nav-link-icon">
                    <i class="{$menu['icon']}"></i>
                </div>
                {$menu['title']}
                <div class="sb-sidenav-collapse-arrow">
                    <i class="fas fa-angle-down"></i>
                </div>
            </a>
            <div class="collapse" id="$collapseId">
                <nav class="sb-sidenav-menu-nested nav">
            HTML;

            renderSidebar($menu['children'], $collapseId);

            echo "</nav></div>";
        } else {
            echo <<<HTML
            <a class="nav-link" href="{$menu['route']}">
                <div class="sb-nav-link-icon">
                    <i class="{$menu['icon']}"></i>
                </div>
                {$menu['title']}
            </a>
            HTML;
        }
    }
}
