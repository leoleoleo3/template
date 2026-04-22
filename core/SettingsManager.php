<?php
/**
 * SettingsManager Class
 *
 * Manages system settings including web settings (logo, favicon, etc.)
 * Follows the TEMPLATE Framework singleton pattern
 *
 * @package TEMPLATE
 * @version 1.0.0
 */

class SettingsManager
{
    private DB $db;
    private static ?SettingsManager $instance = null;
    private array $cache = [];

    private function __construct(DB $db)
    {
        $this->db = $db;
    }

    public static function getInstance(DB $db = null): self
    {
        if (self::$instance === null) {
            if ($db === null) {
                throw new Exception('Database instance required for first instantiation');
            }
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Get a setting value by key
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function get(string $key, $default = null)
    {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $result = $this->db->query(
            "SELECT setting_value, setting_type FROM set_settings WHERE setting_key = ? AND hidden = 0",
            [$key]
        );

        if ($result['success'] && !empty($result['result'])) {
            $row = $result['result'][0];
            $value = $this->castValue($row['setting_value'], $row['setting_type']);
            $this->cache[$key] = $value;
            return $value;
        }

        return $default;
    }

    /**
     * Set a setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param string $type Value type (string, int, bool, json)
     * @param string $group Setting group
     * @param string $label Human-readable label
     * @param string $description Setting description
     * @return array Result
     */
    public function set(string $key, $value, string $type = 'string', string $group = 'general', string $label = '', string $description = ''): array
    {
        // Convert value to string for storage
        $stringValue = $this->valueToString($value, $type);

        // Check if setting exists
        $existing = $this->db->query(
            "SELECT id FROM set_settings WHERE setting_key = ?",
            [$key]
        );

        if ($existing['success'] && !empty($existing['result'])) {
            // Update existing
            $result = $this->db->update(
                'set_settings',
                [
                    'setting_value' => $stringValue,
                    'setting_type' => $type,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                ['setting_key' => $key]
            );
        } else {
            // Insert new
            $result = $this->db->insert('set_settings', [
                'setting_key' => $key,
                'setting_value' => $stringValue,
                'setting_type' => $type,
                'setting_group' => $group,
                'setting_label' => $label ?: $this->keyToLabel($key),
                'description' => $description,
                'hidden' => 0
            ]);
        }

        // Update cache
        if ($result['success']) {
            $this->cache[$key] = $value;
        }

        return $result;
    }

    /**
     * Get all settings by group
     *
     * @param string $group Setting group
     * @return array Settings
     */
    public function getByGroup(string $group): array
    {
        $result = $this->db->query(
            "SELECT * FROM set_settings WHERE setting_group = ? AND hidden = 0 ORDER BY setting_label",
            [$group]
        );

        if (!$result['success']) {
            return [];
        }

        $settings = [];
        foreach ($result['result'] as $row) {
            $settings[$row['setting_key']] = [
                'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                'type' => $row['setting_type'],
                'label' => $row['setting_label'],
                'description' => $row['description']
            ];
        }

        return $settings;
    }

    /**
     * Get all settings groups
     *
     * @return array Groups
     */
    public function getGroups(): array
    {
        $result = $this->db->query(
            "SELECT DISTINCT setting_group FROM set_settings WHERE hidden = 0 ORDER BY setting_group"
        );

        if (!$result['success']) {
            return [];
        }

        return array_column($result['result'], 'setting_group');
    }

    /**
     * Delete a setting
     *
     * @param string $key Setting key
     * @return array Result
     */
    public function delete(string $key): array
    {
        unset($this->cache[$key]);
        return $this->db->softDelete('set_settings', ['setting_key' => $key]);
    }

    /**
     * Get web settings (logo, favicon, site name, etc.)
     *
     * @return array Web settings
     */
    public function getWebSettings(): array
    {
        return [
            'site_name' => $this->get('site_name', 'TEMPLATE'),
            'site_tagline' => $this->get('site_tagline', 'Enrollment System'),
            'site_logo' => $this->get('site_logo', ''),
            'site_favicon' => $this->get('site_favicon', ''),
            'site_logo_dark' => $this->get('site_logo_dark', ''),
            'primary_color' => $this->get('primary_color', '#0d6efd'),
            'footer_text' => $this->get('footer_text', ''),
        ];
    }

    /**
     * Update web settings
     *
     * @param array $settings Settings to update
     * @return array Result
     */
    public function updateWebSettings(array $settings): array
    {
        $webSettingsKeys = [
            'site_name' => 'Site Name',
            'site_tagline' => 'Site Tagline',
            'site_logo' => 'Site Logo',
            'site_favicon' => 'Site Favicon',
            'site_logo_dark' => 'Dark Mode Logo',
            'primary_color' => 'Primary Color',
            'footer_text' => 'Footer Text',
        ];

        $updated = 0;
        $errors = [];

        foreach ($settings as $key => $value) {
            if (isset($webSettingsKeys[$key])) {
                $result = $this->set($key, $value, 'string', 'web', $webSettingsKeys[$key]);
                if ($result['success']) {
                    $updated++;
                } else {
                    $errors[] = $key;
                }
            }
        }

        if (empty($errors)) {
            return ['success' => true, 'updated' => $updated];
        }

        return [
            'success' => false,
            'error' => 'Failed to update some settings: ' . implode(', ', $errors),
            'updated' => $updated
        ];
    }

    /**
     * Cast value to appropriate type
     *
     * @param string $value Stored value
     * @param string $type Value type
     * @return mixed Casted value
     */
    private function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'float':
            case 'double':
                return (float)$value;
            case 'json':
            case 'array':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }

    /**
     * Convert value to string for storage
     *
     * @param mixed $value Value
     * @param string $type Value type
     * @return string String value
     */
    private function valueToString($value, string $type): string
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
            case 'array':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }

    /**
     * Convert key to human-readable label
     *
     * @param string $key Setting key
     * @return string Label
     */
    private function keyToLabel(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * Clear settings cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Initialize default web settings if not exist
     */
    public function initializeDefaults(): void
    {
        $defaults = [
            ['site_name', 'TEMPLATE', 'string', 'web', 'Site Name', 'The name of your site displayed in the header'],
            ['site_tagline', 'Enrollment System', 'string', 'web', 'Site Tagline', 'A short tagline or description'],
            ['site_logo', '', 'string', 'web', 'Site Logo', 'Path to the site logo image'],
            ['site_favicon', '', 'string', 'web', 'Site Favicon', 'Path to the favicon'],
            ['site_logo_dark', '', 'string', 'web', 'Dark Mode Logo', 'Logo for dark mode (optional)'],
            ['primary_color', '#0d6efd', 'string', 'web', 'Primary Color', 'Primary brand color'],
            ['footer_text', '', 'string', 'web', 'Footer Text', 'Custom footer text'],
        ];

        foreach ($defaults as $setting) {
            $existing = $this->db->query(
                "SELECT id FROM set_settings WHERE setting_key = ?",
                [$setting[0]]
            );

            if (!$existing['success'] || empty($existing['result'])) {
                $this->db->insert('set_settings', [
                    'setting_key' => $setting[0],
                    'setting_value' => $setting[1],
                    'setting_type' => $setting[2],
                    'setting_group' => $setting[3],
                    'setting_label' => $setting[4],
                    'description' => $setting[5],
                    'hidden' => 0
                ]);
            }
        }
    }
}
