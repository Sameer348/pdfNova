<?php
/**
 * Database access — PDO with REAL prepared statements only.
 *
 * Rules for the whole codebase:
 *   - Never concatenate user input into SQL. Always pass values as $params.
 *   - Identifiers (table/column names) are never taken from user input.
 *
 * The connection is lazy: pages that never touch the DB never connect.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $socket = (string) config('db.socket', '');
    $dsn = $socket !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, config('db.name'), config('db.charset', 'utf8mb4'))
        : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            config('db.host', 'localhost'), (int) config('db.port', 3306), config('db.name'), config('db.charset', 'utf8mb4'));

    try {
        $pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real server-side prepares
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Never leak DSN/credentials to the visitor.
        app_log('critical', 'Database connection failed', ['code' => $e->getCode()]);
        throw new RuntimeException('Database connection failed.');
    }
    return $pdo;
}

/** Execute a statement; returns the PDOStatement. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params); // positional (?) or named (:name) parameters
    return $stmt;
}

/** First row or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** All rows. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Single scalar value or null. */
function db_value(string $sql, array $params = []): mixed
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/** INSERT/UPDATE/DELETE — returns affected row count. */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/** INSERT — returns the new auto-increment id. */
function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}

/**
 * Run a callback inside a transaction; rolls back on any exception.
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function db_transaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
