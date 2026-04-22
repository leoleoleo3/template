<?php

/**
 * Page/Resource Manager Class
 * Handles page/resource CRUD operations and hierarchical structure
 */
class PageManager
{
    private DB $db;
    private static ?PageManager $instance = null;

    private function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(DB $db = null): self
    {
        if (self::$instance === null) {
            if ($db === null) {
                throw new Exception('Database instance required for first initialization');
            }
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Get all pages
     */
    public function getAllPages(bool $includeHidden = false): array
    {
        $result = $this->db->select('pages', '*', [], 'ORDER BY sort_order ASC', $includeHidden);
        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get page by ID
     */
    public function getPageById(int $id): ?array
    {
        $result = $this->db->select('pages', '*', ['id' => $id]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Get page by name
     */
    public function getPageByName(string $name): ?array
    {
        $result = $this->db->select('pages', '*', ['name' => $name]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Get page by route
     */
    public function getPageByRoute(string $route): ?array
    {
        $result = $this->db->select('pages', '*', ['route' => $route]);
        return $result['success'] && !empty($result['result']) ? $result['result'][0] : null;
    }

    /**
     * Get pages as hierarchical tree
     */
    public function getPagesTree(bool $includeHidden = false): array
    {
        $pages = $this->getAllPages($includeHidden);
        return $this->buildTree($pages);
    }

    /**
     * Build hierarchical tree from flat array
     */
    private function buildTree(array $pages, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($pages as $page) {
            if ($page['parent_id'] == $parentId) {
                $page['children'] = $this->buildTree($pages, $page['id']);
                $tree[] = $page;
            }
        }

        return $tree;
    }

    /**
     * Get child pages
     */
    public function getChildPages(int $parentId, bool $includeHidden = false): array
    {
        $result = $this->db->select('pages', '*', ['parent_id' => $parentId], 'ORDER BY sort_order ASC', $includeHidden);
        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get parent pages (pages without parent)
     */
    public function getParentPages(bool $includeHidden = false): array
    {
        $where = $includeHidden ? '' : 'WHERE hidden = 0';
        $result = $this->db->query("
            SELECT * FROM pages
            WHERE parent_id IS NULL $where
            ORDER BY sort_order ASC
        ");
        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Create new page
     */
    public function createPage(array $data): array
    {
        // Validate required fields
        if (empty($data['name']) || empty($data['display_name'])) {
            return [
                'success' => false,
                'error' => 'Page name and display name are required'
            ];
        }

        // Check if page name already exists
        $existing = $this->getPageByName($data['name']);
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Page name already exists'
            ];
        }

        // Validate parent_id if provided
        if (!empty($data['parent_id'])) {
            $parent = $this->getPageById($data['parent_id']);
            if (!$parent) {
                return [
                    'success' => false,
                    'error' => 'Parent page not found'
                ];
            }
        }

        // Insert page
        $pageData = [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => strtolower(trim(str_replace(' ', '_', $data['name']))),
            'display_name' => trim($data['display_name']),
            'description' => $data['description'] ?? null,
            'route' => $data['route'] ?? null,
            'icon' => $data['icon'] ?? 'fas fa-file',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_menu_item' => isset($data['is_menu_item']) ? (int)$data['is_menu_item'] : 1,
            'hidden' => 0
        ];

        return $this->db->insert('pages', $pageData);
    }

    /**
     * Update page
     */
    public function updatePage(int $id, array $data): array
    {
        // Check if page exists
        $page = $this->getPageById($id);
        if (!$page) {
            return [
                'success' => false,
                'error' => 'Page not found'
            ];
        }

        // If changing name, check for duplicates
        if (isset($data['name']) && $data['name'] !== $page['name']) {
            $existing = $this->getPageByName($data['name']);
            if ($existing) {
                return [
                    'success' => false,
                    'error' => 'Page name already exists'
                ];
            }
        }

        // Validate parent_id if being changed
        if (isset($data['parent_id']) && $data['parent_id'] !== $page['parent_id']) {
            // Prevent circular reference
            if ($data['parent_id'] == $id) {
                return [
                    'success' => false,
                    'error' => 'Page cannot be its own parent'
                ];
            }

            // Check if new parent exists
            if ($data['parent_id'] !== null) {
                $parent = $this->getPageById($data['parent_id']);
                if (!$parent) {
                    return [
                        'success' => false,
                        'error' => 'Parent page not found'
                    ];
                }

                // Check for circular reference in tree
                if ($this->wouldCreateCircularReference($id, $data['parent_id'])) {
                    return [
                        'success' => false,
                        'error' => 'Cannot create circular reference in page hierarchy'
                    ];
                }
            }
        }

        // Build update data
        $updateData = [];
        if (isset($data['parent_id'])) $updateData['parent_id'] = $data['parent_id'];
        if (isset($data['name'])) $updateData['name'] = strtolower(trim(str_replace(' ', '_', $data['name'])));
        if (isset($data['display_name'])) $updateData['display_name'] = trim($data['display_name']);
        if (isset($data['description'])) $updateData['description'] = trim($data['description']);
        if (isset($data['route'])) $updateData['route'] = trim($data['route']);
        if (isset($data['icon'])) $updateData['icon'] = trim($data['icon']);
        if (isset($data['sort_order'])) $updateData['sort_order'] = (int)$data['sort_order'];
        if (isset($data['is_menu_item'])) $updateData['is_menu_item'] = (int)$data['is_menu_item'];

        if (empty($updateData)) {
            return [
                'success' => false,
                'error' => 'No data to update'
            ];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('pages', $updateData, ['id' => $id]);
    }

    /**
     * Check if setting parent would create circular reference
     */
    private function wouldCreateCircularReference(int $pageId, int $newParentId): bool
    {
        $currentId = $newParentId;

        // Traverse up the tree
        while ($currentId !== null) {
            if ($currentId === $pageId) {
                return true; // Circular reference detected
            }

            $parent = $this->getPageById($currentId);
            $currentId = $parent['parent_id'] ?? null;
        }

        return false;
    }

    /**
     * Delete page (soft delete)
     */
    public function deletePage(int $id): array
    {
        // Check if page exists
        $page = $this->getPageById($id);
        if (!$page) {
            return [
                'success' => false,
                'error' => 'Page not found'
            ];
        }

        // Check if page has children
        $children = $this->getChildPages($id);
        if (!empty($children)) {
            return [
                'success' => false,
                'error' => 'Cannot delete page that has child pages. Please delete or reassign children first.'
            ];
        }

        // Soft delete
        return $this->db->softDelete('pages', ['id' => $id]);
    }

    /**
     * Hard delete page (permanent)
     */
    public function hardDeletePage(int $id): array
    {
        // Check for children first
        $children = $this->getChildPages($id, true);
        if (!empty($children)) {
            return [
                'success' => false,
                'error' => 'Cannot delete page that has child pages'
            ];
        }

        // Delete page and cascade will delete permissions
        return $this->db->delete('pages', ['id' => $id]);
    }

    /**
     * Get page breadcrumb (path from root to page)
     */
    public function getBreadcrumb(int $pageId): array
    {
        $breadcrumb = [];
        $currentId = $pageId;

        while ($currentId !== null) {
            $page = $this->getPageById($currentId);
            if (!$page) break;

            array_unshift($breadcrumb, [
                'id' => $page['id'],
                'name' => $page['name'],
                'display_name' => $page['display_name'],
                'route' => $page['route']
            ]);

            $currentId = $page['parent_id'];
        }

        return $breadcrumb;
    }

    /**
     * Get page depth in hierarchy
     */
    public function getPageDepth(int $pageId): int
    {
        $depth = 0;
        $currentId = $pageId;

        while ($currentId !== null) {
            $page = $this->getPageById($currentId);
            if (!$page) break;

            $depth++;
            $currentId = $page['parent_id'];
        }

        return $depth - 1; // Subtract 1 because root is depth 0
    }

    /**
     * Check if role is Superadmin
     */
    private function isSuperAdmin(int $roleId): bool
    {
        $result = $this->db->select('roles', 'name', ['id' => $roleId]);
        if ($result['success'] && !empty($result['result'])) {
            return strtolower($result['result'][0]['name']) === 'superadmin';
        }
        return false;
    }

    /**
     * Get pages accessible by role (with view permission)
     * Superadmin gets access to ALL pages automatically
     */
    public function getAccessiblePages(int $roleId, bool $menuItemsOnly = true): array
    {
        // Superadmin has access to all pages
        if ($this->isSuperAdmin($roleId)) {
            $menuFilter = $menuItemsOnly ? 'AND is_menu_item = 1' : '';
            $result = $this->db->query("
                SELECT * FROM pages
                WHERE hidden = 0
                $menuFilter
                ORDER BY sort_order
            ");
            return $result['success'] ? $result['result'] : [];
        }

        // For other roles, check explicit permissions
        $menuFilter = $menuItemsOnly ? 'AND p.is_menu_item = 1' : '';

        $result = $this->db->query("
            SELECT DISTINCT p.*
            FROM pages p
            JOIN role_page_permissions rpp ON p.id = rpp.page_id
            JOIN permission_types pt ON rpp.permission_type_id = pt.id
            WHERE rpp.role_id = ?
              AND pt.name = 'view'
              AND rpp.granted = 1
              AND p.hidden = 0
              $menuFilter
            ORDER BY p.sort_order
        ", [$roleId]);

        return $result['success'] ? $result['result'] : [];
    }

    /**
     * Get menu tree for role
     */
    public function getMenuTree(int $roleId): array
    {
        $pages = $this->getAccessiblePages($roleId, true);

        // Grouping parent nodes (e.g. "System", "Settings") have is_menu_item = 0
        // and are excluded by getAccessiblePages, but buildTree needs them to nest
        // children correctly. Fetch any missing ancestors here.
        $existingIds  = array_column($pages, 'id');
        $parentIds    = array_unique(array_filter(array_column($pages, 'parent_id')));
        $missingIds   = array_values(array_diff($parentIds, $existingIds));

        if (!empty($missingIds)) {
            $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
            $result = $this->db->query(
                "SELECT * FROM pages WHERE id IN ($placeholders) AND hidden = 0",
                $missingIds
            );
            if ($result['success'] && !empty($result['result'])) {
                $pages = array_merge($pages, $result['result']);
                usort($pages, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
            }
        }

        return $this->buildTree($pages);
    }

    /**
     * Get the first accessible page route for a role
     * Used for login redirect and fallback when dashboard is not accessible
     */
    public function getFirstAccessibleRoute(int $roleId): ?string
    {
        $pages = $this->getAccessiblePages($roleId, false);
        foreach ($pages as $page) {
            if (!empty($page['route'])) {
                return $page['route'];
            }
        }
        return null;
    }

    /**
     * Reorder pages
     */
    public function reorderPages(array $pageOrders): array
    {
        $errors = [];
        $success = 0;

        foreach ($pageOrders as $pageId => $sortOrder) {
            $result = $this->db->update('pages', ['sort_order' => $sortOrder], ['id' => $pageId]);
            if ($result['success']) {
                $success++;
            } else {
                $errors[] = "Failed to update page ID $pageId";
            }
        }

        return [
            'success' => empty($errors),
            'updated' => $success,
            'errors' => $errors
        ];
    }

    /**
     * Get page count
     */
    public function getPageCount(bool $includeHidden = false): int
    {
        $where = $includeHidden ? '' : 'WHERE hidden = 0';
        $result = $this->db->query("SELECT COUNT(*) as count FROM pages $where");
        return $result['success'] && !empty($result['result']) ? (int)$result['result'][0]['count'] : 0;
    }

    /**
     * Restore soft-deleted page
     */
    public function restorePage(int $id): array
    {
        return $this->db->update('pages', ['hidden' => 0], ['id' => $id]);
    }
}
