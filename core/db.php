<?php
if (!function_exists('pre_r')) {
    /**
     * Debug helper: print_r wrapped in <pre> tags, then exit.
     * Only active when APP_DEBUG=true; no-op in production.
     */
    function pre_r(mixed ...$vars): void
    {
        $isDebug = false;
        if (defined('BASE_PATH') && file_exists(BASE_PATH . '/.env')) {
            $env = parse_ini_file(BASE_PATH . '/.env');
            $isDebug = ($env['APP_DEBUG'] ?? 'false') === 'true';
        }
        if (!$isDebug) {
            return;
        }
        echo '<pre>';
        foreach ($vars as $v) {
            print_r($v);
            echo "\n";
        }
        echo '</pre>';
        exit;
    }
}

class DB
{
    private mysqli $conn;

    public function __construct(
        string $host,
        string $user,
        string $pass,
        string $db,
        int $port = 3306
    ) {
        $this->conn = new mysqli($host, $user, $pass, $db, $port);
        if ($this->conn->connect_error) {
            throw new Exception("DB Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    /* -------------------
       Input sanitization
       ------------------- */
    private function sanitize(mixed $value): mixed
    {
        if (is_string($value)) $value = trim($value);
        return $value;
    }

    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* -------------------
       Generic query with detailed error
       ------------------- */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception($this->conn->error);
            }

            if (!empty($params)) {
                $types = '';
                $values = [];
                foreach ($params as $v) {
                    $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
                    $values[] = $this->sanitize($v);
                }
                $stmt->bind_param($types, ...$values);
            }

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $result = null;
            $insertId = null;
            $affectedRows = $stmt->affected_rows;

            // If SELECT query, fetch results
            if (stripos(trim($sql), 'select') === 0) {
                $res = $stmt->get_result();
                $result = $res->fetch_all(MYSQLI_ASSOC);
            } elseif (stripos(trim($sql), 'insert') === 0) {
                $insertId = $stmt->insert_id;
            }

            return [
                'success' => true,
                'result' => $result,
                'insert_id' => $insertId,
                'affected_rows' => $affectedRows
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /* -------------------
       SELECT with soft-delete awareness
       ------------------- */
    public function select(
        string $table,
        array|string $columns = '*',
        array $where = [],
        string $extra = '',
        bool $includeHidden = false
    ): array {
        $cols = is_array($columns) ? implode(',', $columns) : $columns;
        $sql = "SELECT $cols FROM `$table`";
        $params = [];

        if (!empty($where) || !$includeHidden) {
            $conditions = [];
            foreach ($where as $k => $v) {
                $conditions[] = "`$k`=?";
                $params[] = $v;
            }
            if (!$includeHidden) $conditions[] = "`hidden` = 0";
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " $extra";
        return $this->query($sql, $params);
    }

    /* -------------------
       INSERT
       ------------------- */
    public function insert(string $table, array $data): array
    {
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $table,
            implode(',', $cols),
            implode(',', $placeholders)
        );
        return $this->query($sql, array_values($data));
    }

    /* -------------------
       UPDATE
       ------------------- */
    public function update(string $table, array $data, array $where): array
    {
        $set = []; $params = [];
        foreach ($data as $k => $v) { $set[] = "`$k`=?"; $params[] = $v; }

        $conds = []; 
        foreach ($where as $k => $v) { $conds[] = "`$k`=?"; $params[] = $v; }

        $sql = sprintf("UPDATE `%s` SET %s WHERE %s", $table, implode(',', $set), implode(' AND ', $conds));
        return $this->query($sql, $params);
    }

    /* -------------------
       DELETE (hard)
       ------------------- */
    public function delete(string $table, array $where): array
    {
        $conds = []; $params = [];
        foreach ($where as $k => $v) { $conds[] = "`$k`=?"; $params[] = $v; }
        $sql = sprintf("DELETE FROM `%s` WHERE %s", $table, implode(' AND ', $conds));
        return $this->query($sql, $params);
    }

    /* -------------------
       SAFE DELETE (soft delete)
       ------------------- */
    public function softDelete(string $table, array $where): array
    {
        return $this->update($table, ['hidden' => 1], $where);
    }
}