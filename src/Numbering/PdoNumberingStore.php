<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Numbering;

/**
 * Transactional store on any PDO database (MySQL/MariaDB, PostgreSQL, SQLite,
 * SQL Server). One row per numbering range:
 *
 *   CREATE TABLE dian_numbering (
 *       range_key   VARCHAR(120) NOT NULL PRIMARY KEY,
 *       last_number BIGINT       NOT NULL,
 *       updated_at  VARCHAR(32)  NOT NULL
 *   );
 *
 * createTable() creates it. next() increments with a single conditional
 * UPDATE inside a transaction, so concurrent PHP workers never share a number
 * (the row lock serializes them).
 */
final class PdoNumberingStore implements NumberingStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $table = 'dian_numbering',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $table) !== 1) {
            throw new \InvalidArgumentException("Nombre de tabla inválido: {$table}");
        }
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function createTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                range_key VARCHAR(120) NOT NULL PRIMARY KEY,
                last_number BIGINT NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )"
        );
    }

    public function next(string $key, int $from, int $to): ?int
    {
        return $this->transaction(function () use ($key, $from, $to): ?int {
            $this->ensureRow($key, $from - 1);

            $update = $this->pdo->prepare(
                "UPDATE {$this->table}
                    SET last_number = CASE WHEN last_number < :floor THEN :from ELSE last_number + 1 END, updated_at = :now
                  WHERE range_key = :key AND last_number < :to"
            );
            $update->execute(['floor' => $from - 1, 'from' => $from, 'now' => date('c'), 'key' => $key, 'to' => $to]);
            if ($update->rowCount() === 0) {
                return null;
            }

            return $this->current($key);
        });
    }

    public function current(string $key): ?int
    {
        $select = $this->pdo->prepare("SELECT last_number FROM {$this->table} WHERE range_key = :key");
        $select->execute(['key' => $key]);
        $value = $select->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function set(string $key, int $last): void
    {
        $this->transaction(function () use ($key, $last): void {
            $this->ensureRow($key, $last);
            $this->pdo->prepare("UPDATE {$this->table} SET last_number = :last, updated_at = :now WHERE range_key = :key")
                ->execute(['last' => $last, 'now' => date('c'), 'key' => $key]);
        });
    }

    private function ensureRow(string $key, int $initial): void
    {
        if ($this->current($key) !== null) {
            return;
        }
        try {
            $this->pdo->prepare("INSERT INTO {$this->table} (range_key, last_number, updated_at) VALUES (:key, :last, :now)")
                ->execute(['key' => $key, 'last' => $initial, 'now' => date('c')]);
        } catch (\PDOException) {
            // Another worker inserted it first: the UPDATE that follows serializes on that row.
        }
    }

    /**
     * @template T
     * @param callable():T $work
     * @return T
     */
    private function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
