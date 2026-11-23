<?php
/**
 * Thin query helpers over PDO.
 *
 * The pattern below appears 79 times across the admin app:
 *
 *     $stmt = getDb()->prepare('SELECT ... WHERE id = :id LIMIT 1');
 *     $stmt->execute(['id' => $id]);
 *     $row = $stmt->fetch();
 *
 * Three lines and a throwaway variable to read one row. These helpers collapse
 * that to one call, which matters less for keystrokes than for the fact that
 * the three-line form has a silent failure mode: forget the execute(), or
 * fetch() from the wrong handle, and you get an empty result rather than an
 * error. There is nothing to forget here.
 *
 * Deliberately NOT a query builder or an ORM. Every helper takes real SQL and
 * bound parameters, so a query in this codebase still reads as the query it
 * runs. The one rule the app already follows — values are always bound, only
 * identifiers are ever interpolated, and those come from allow-lists — is
 * unchanged and unweakened by anything here.
 *
 * getDb() keeps its single shared connection; these are wrappers, not a
 * replacement, so existing raw getDb() calls keep working untouched and can be
 * migrated a file at a time.
 */

require_once __DIR__ . '/database.php';

/**
 * Runs a statement and returns it, ready to fetch from.
 *
 * @param array<string, mixed>|list<mixed> $params
 */
function dbRun(string $sql, array $params = []): PDOStatement
{
    $stmt = getDb()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/**
 * One row, or null when there is no match.
 *
 * Returns null rather than PDO's false so callers can use `?->` and `??`
 * naturally, and so `if ($row === null)` reads as "not found" instead of the
 * ambiguous falsy check that an empty row would also satisfy.
 *
 * @param array<string, mixed>|list<mixed> $params
 * @return array<string, mixed>|null
 */
function dbFetchOne(string $sql, array $params = []): ?array
{
    $row = dbRun($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * All matching rows, as a list. Empty list when nothing matches — never false,
 * so callers can foreach without a guard.
 *
 * @param array<string, mixed>|list<mixed> $params
 * @return list<array<string, mixed>>
 */
function dbFetchAll(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll();
}

/**
 * A single scalar — COUNT(*), MAX(display_order), one column of one row.
 *
 * @param array<string, mixed>|list<mixed> $params
 */
function dbFetchColumn(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = dbRun($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

/**
 * One column from every matching row, as a flat list. For id lists and the
 * "which paths are referenced" style lookups.
 *
 * @param array<string, mixed>|list<mixed> $params
 * @return list<mixed>
 */
function dbFetchColumnAll(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * A key => value map from a two-column result, e.g. content_blocks.
 *
 * @param array<string, mixed>|list<mixed> $params
 * @return array<string, mixed>
 */
function dbFetchPairs(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * INSERT/UPDATE/DELETE. Returns the affected row count, which several callers
 * already use to tell "updated" from "nothing matched".
 *
 * @param array<string, mixed>|list<mixed> $params
 */
function dbExecute(string $sql, array $params = []): int
{
    return dbRun($sql, $params)->rowCount();
}

/**
 * INSERT, returning the new id.
 *
 * @param array<string, mixed>|list<mixed> $params
 */
function dbInsert(string $sql, array $params = []): int
{
    dbRun($sql, $params);

    return (int) getDb()->lastInsertId();
}

/**
 * Runs $work inside a transaction, committing on success and rolling back on
 * any throwable before rethrowing.
 *
 * Nested calls are tolerated: if a transaction is already open the callback
 * simply joins it rather than triggering PDO's "there is already an active
 * transaction" error, so a helper that wraps its own work can safely be called
 * from inside a larger unit.
 *
 * @template T
 * @param callable(): T $work
 * @return T
 */
function dbTransaction(callable $work): mixed
{
    $db = getDb();

    if ($db->inTransaction()) {
        return $work();
    }

    $db->beginTransaction();
    try {
        $result = $work();
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Builds a positional IN (...) placeholder list for a set of ids.
 *
 * Exists because three separate files (bulk.php, reorder.php, trash.php) each
 * build this the same way, and each is a place where an unvalidated id could
 * reach SQL if the cast were ever forgotten. Casting happens here, so the
 * returned fragment is only ever digits and the caller cannot get it wrong.
 *
 * @param list<mixed> $ids
 * @return array{sql: string, ids: list<int>}
 */
function dbIdPlaceholders(array $ids): array
{
    $clean = array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn(int $id): bool => $id > 0
    )));

    return [
        'sql' => $clean === [] ? '' : implode(',', array_fill(0, count($clean), '?')),
        'ids' => $clean,
    ];
}
