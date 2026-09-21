<?php
/**
 * FaDbAdapter — FA runtime connection adapter for the BR-007 timesheets flow.
 *
 * Bridges the service's DbConnectionInterface-style calls to FA's procedural
 * database layer. HARD RULE (see AGENTS.md): inside FA only native db_*
 * functions are used — never PDO, never raw mysqli handles. `?` placeholders
 * (FA has NO prepared statements) are resolved to escaped literals, exactly
 * like the ksf-common-db FaDbAdapter.
 *
 * @since 2.4.3
 * @BABOK Related: BR-007, FR-TIME-007-002, FR-TIME-007-003
 */

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Timesheets\Adapter;

/**
 * @package ksfraser\FrontAccounting\Timesheets\Adapter
 */
class FaDbAdapter
{
    /**
     * @param string $sql    SQL with `?` positional placeholders
     * @param array  $params
     * @return string SQL with placeholders replaced by escaped literals
     */
    private function resolve(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }
        foreach ($params as $value) {
            $literal = $this->literal($value);
            $pos = strpos($sql, '?');
            if ($pos === false) {
                break;
            }
            $sql = substr($sql, 0, $pos) . $literal . substr($sql, $pos + 1);
        }
        return $sql;
    }

    /**
     * @param mixed $value
     * @return string SQL literal
     */
    private function literal($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . db_escape((string) $value) . "'";
    }

    /**
     * @param string $sql
     * @param array  $params
     * @return array|null single row or null
     */
    public function fetchAssoc(string $sql, array $params = array()): ?array
    {
        $result = db_query($this->resolve($sql, $params));
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);
        return $row === false ? null : $row;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @return array[] all rows
     */
    public function fetchAll(string $sql, array $params = array()): array
    {
        $result = db_query($this->resolve($sql, $params));
        $rows = array();
        if ($result) {
            while (($row = db_fetch_assoc($result)) !== false) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @return mixed first column of first row, or null
     */
    public function fetchScalar(string $sql, array $params = array())
    {
        $row = $this->fetchAssoc($sql, $params);
        if ($row === null) {
            return null;
        }
        $values = array_values($row);
        return $values[0] ?? null;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @return bool
     */
    public function executeUpdate(string $sql, array $params = array()): bool
    {
        return (bool) db_query($this->resolve($sql, $params));
    }

    /**
     * @return int last auto-increment id
     */
    public function lastInsertId(): int
    {
        return (int) db_insert_id();
    }

    /**
     * @return void
     */
    public function beginTransaction(): void
    {
        db_query('START TRANSACTION');
    }

    /**
     * @return void
     */
    public function commit(): void
    {
        db_query('COMMIT');
    }

    /**
     * @return void
     */
    public function rollBack(): void
    {
        db_query('ROLLBACK');
    }
}