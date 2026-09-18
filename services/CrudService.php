<?php
declare(strict_types=1);

final class CrudService
{
    public static function safeTable(string $table): array
    {
        $map = entity_map();
        if (!isset($map[$table]) || !is_array($map[$table])) throw new InvalidArgumentException('Unknown entity');
        return $map[$table];
    }

    public static function list(string $table, string $search = '', int $limit = 25, int $offset = 0): array
    {
        $m = self::safeTable($table); $limit = min(100, max(1, $limit)); $offset = max(0, $offset);
        $columns = implode(',', array_map(static fn(string $x): string => '`' . $x . '`', $m['columns']));
        $sql = 'SELECT ' . $columns . ' FROM `' . $m['table'] . '`'; $where = []; $args = [];
        if (!empty($m['soft_delete'])) $where[] = 'deleted_at IS NULL';
        if ($search !== '' && !empty($m['search'])) { $parts = []; $term = '%' . mb_substr($search, 0, 100) . '%'; foreach ($m['search'] as $column) { $parts[] = '`' . $column . '` LIKE ?'; $args[] = $term; } $where[] = '(' . implode(' OR ', $parts) . ')'; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY `' . $m['order_by'] . '` DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $query = db()->prepare($sql); $query->execute($args); return $query->fetchAll();
    }

    public static function count(string $table, string $search = ''): int
    {
        $m = self::safeTable($table); $sql = 'SELECT COUNT(*) FROM `' . $m['table'] . '`'; $where = []; $args = [];
        if (!empty($m['soft_delete'])) $where[] = 'deleted_at IS NULL';
        if ($search !== '' && !empty($m['search'])) { $parts = []; $term = '%' . mb_substr($search, 0, 100) . '%'; foreach ($m['search'] as $column) { $parts[] = '`' . $column . '` LIKE ?'; $args[] = $term; } $where[] = '(' . implode(' OR ', $parts) . ')'; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $query = db()->prepare($sql); $query->execute($args); return (int)$query->fetchColumn();
    }

    public static function delete(string $table, int $id, bool $hard = false): void
    {
        $m = self::safeTable($table); if ($id < 1) throw new InvalidArgumentException('Invalid record ID');
        $soft = !empty($m['soft_delete']) && !$hard;
        $query = db()->prepare($soft ? 'UPDATE `' . $m['table'] . '` SET deleted_at=NOW(), deleted_by=? WHERE id=?' : 'DELETE FROM `' . $m['table'] . '` WHERE id=?');
        $query->execute($soft ? [actor_id(), $id] : [$id]); AuditService::log($hard ? 'HARD_DELETE' : 'DELETE', $table, $id, 'CRUD delete');
    }

    public static function restore(string $table, int $id): void
    {
        $m = self::safeTable($table); if (empty($m['soft_delete'])) return;
        $query = db()->prepare('UPDATE `' . $m['table'] . '` SET deleted_at=NULL, deleted_by=NULL WHERE id=?'); $query->execute([$id]); AuditService::log('RESTORE', $table, $id, 'CRUD restore');
    }
}

function entity_map(): array
{
    if (!isset($GLOBALS['ENTITY_MAP'])) $GLOBALS['ENTITY_MAP'] = require __DIR__ . '/../admin/entity_map.php';
    return $GLOBALS['ENTITY_MAP'];
}
