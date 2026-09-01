<?php

declare(strict_types=1);

namespace Kiyosaki\System\Storage;

use JsonException;
use Kiyosaki\Core\Domain\DrawRecord;
use Kiyosaki\Core\Domain\StoredCombination;
use PDO;
use RuntimeException;

final class Database
{
    public const string PATH_VARIABLE = 'KIYOSAKI_DATABASE_PATH';

    private readonly string $path;

    private ?PDO $connection = null;

    public function __construct(?string $path = null)
    {
        $configuredPath = getenv(self::PATH_VARIABLE);
        $this->path = $path
            ?? (is_string($configuredPath) && $configuredPath !== '' ? $configuredPath : self::defaultPath());
    }

    public static function defaultPath(): string
    {
        return dirname(__DIR__, 3).'/database/kiyosaki.sqlite';
    }

    public function path(): string
    {
        return $this->path;
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if ($this->path !== ':memory:') {
            $directory = dirname($this->path);
            if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create database directory: %s', $directory));
            }
        }

        $connection = new PDO('sqlite:'.$this->path, options: [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $connection->exec('PRAGMA busy_timeout = 5000');
        $connection->exec('PRAGMA foreign_keys = ON');
        $this->connection = $connection;
        $this->createSchema();

        return $connection;
    }

    public function drawExists(int $draw, int $year): bool
    {
        $statement = $this->connection()->prepare(
            'SELECT 1 FROM draws WHERE draw = :draw AND year = :year LIMIT 1'
        );
        $statement->execute(['draw' => $draw, 'year' => $year]);

        return $statement->fetchColumn() !== false;
    }

    public function insertDraw(DrawRecord $draw): bool
    {
        $statement = $this->connection()->prepare(
            <<<'SQL'
                INSERT OR IGNORE INTO draws
                    (draw, year, date, numbers, prize_breakdown, payments)
                VALUES
                    (:draw, :year, :date, :numbers, :prize_breakdown, :payments)
                SQL
        );
        $statement->execute([
            'draw' => $draw->draw,
            'year' => $draw->year,
            'date' => $draw->date,
            'numbers' => self::encode($draw->numbers),
            'prize_breakdown' => self::encode($draw->prizeBreakdown),
            'payments' => self::encode($draw->payments),
        ]);

        return $statement->rowCount() === 1;
    }

    /** @return list<DrawRecord> */
    public function draws(?int $year = null): array
    {
        $query = 'SELECT draw, year, date, numbers, prize_breakdown, payments FROM draws';
        $parameters = [];

        if ($year !== null) {
            $query .= ' WHERE year = :year';
            $parameters['year'] = $year;
        }

        $statement = $this->connection()->prepare($query.' ORDER BY year, draw');
        $statement->execute($parameters);

        $draws = [];
        while (($row = $statement->fetch()) !== false) {
            /** @var array{draw: int, year: int, date: ?string, numbers: string, prize_breakdown: ?string, payments: ?string} $row */
            $draws[] = new DrawRecord(
                $row['draw'],
                $row['year'],
                $row['date'],
                self::decodeIntegerList($row['numbers']),
                self::decodeObjectList($row['prize_breakdown']),
                self::decodeObject($row['payments']),
            );
        }

        return $draws;
    }

    public function latestDraw(): ?DrawRecord
    {
        $statement = $this->connection()->query(
            'SELECT draw, year, date, numbers, prize_breakdown, payments FROM draws ORDER BY year DESC, draw DESC LIMIT 1'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to query the latest draw.');
        }
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        /** @var array{draw: int, year: int, date: ?string, numbers: string, prize_breakdown: ?string, payments: ?string} $row */
        return new DrawRecord(
            $row['draw'],
            $row['year'],
            $row['date'],
            self::decodeIntegerList($row['numbers']),
            self::decodeObjectList($row['prize_breakdown']),
            self::decodeObject($row['payments']),
        );
    }

    /**
     * @param  list<list<int>>  $combinations
     */
    public function insertCombinations(int $draw, int $year, array $combinations): int
    {
        $connection = $this->connection();
        $statement = $connection->prepare(
            'INSERT OR IGNORE INTO combinations (draw, year, numbers) VALUES (:draw, :year, :numbers)'
        );
        $inserted = 0;

        $connection->beginTransaction();
        try {
            foreach ($combinations as $numbers) {
                new StoredCombination(0, $draw, $year, $numbers);
                $statement->execute([
                    'draw' => $draw,
                    'year' => $year,
                    'numbers' => self::encode($numbers),
                ]);
                $inserted += $statement->rowCount();
            }
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

        return $inserted;
    }

    /** @return list<StoredCombination> */
    public function combinations(int $year): array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, draw, year, numbers FROM combinations WHERE year = :year ORDER BY draw, id'
        );
        $statement->execute(['year' => $year]);

        $combinations = [];
        while (($row = $statement->fetch()) !== false) {
            /** @var array{id: int, draw: int, year: int, numbers: string} $row */
            $combinations[] = new StoredCombination(
                $row['id'],
                $row['draw'],
                $row['year'],
                self::decodeIntegerList($row['numbers']),
            );
        }

        return $combinations;
    }

    /** @return array{draws: int, combinations: int, first_year: ?int, last_year: ?int} */
    public function statistics(): array
    {
        $drawStatement = $this->connection()->query(
            'SELECT COUNT(*) AS total, MIN(year) AS first_year, MAX(year) AS last_year FROM draws'
        );
        $combinationStatement = $this->connection()->query('SELECT COUNT(*) FROM combinations');
        if ($drawStatement === false || $combinationStatement === false) {
            throw new RuntimeException('Unable to query database statistics.');
        }
        $draws = $drawStatement->fetch();
        $combinations = $combinationStatement->fetchColumn();
        if ($draws === false || $combinations === false) {
            throw new RuntimeException('Database statistics are unavailable.');
        }

        /** @var array{total: int, first_year: ?int, last_year: ?int} $draws */
        return [
            'draws' => $draws['total'],
            'combinations' => (int) $combinations,
            'first_year' => $draws['first_year'],
            'last_year' => $draws['last_year'],
        ];
    }

    private function createSchema(): void
    {
        $this->connection?->exec(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS draws (
                    id INTEGER PRIMARY KEY,
                    draw INTEGER NOT NULL,
                    year INTEGER NOT NULL,
                    date TEXT,
                    numbers TEXT NOT NULL,
                    prize_breakdown TEXT NOT NULL DEFAULT '[]',
                    payments TEXT NOT NULL DEFAULT '{}',
                    UNIQUE(draw, year)
                )
                SQL
        );
        $this->connection?->exec(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS combinations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    draw INTEGER NOT NULL,
                    year INTEGER NOT NULL,
                    numbers TEXT NOT NULL,
                    UNIQUE(draw, year, numbers)
                )
                SQL
        );
        $this->connection?->exec('CREATE INDEX IF NOT EXISTS draws_year_draw_idx ON draws (year, draw)');
        $this->connection?->exec('CREATE INDEX IF NOT EXISTS combinations_year_draw_idx ON combinations (year, draw)');
    }

    private static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode database value.', previous: $exception);
        }
    }

    /** @return list<int> */
    private static function decodeIntegerList(?string $json): array
    {
        $value = self::decode($json, []);

        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException('Expected a JSON list of numbers.');
        }

        $numbers = [];
        foreach ($value as $number) {
            if (! is_int($number)) {
                throw new RuntimeException('Expected integer lottery numbers.');
            }
            $numbers[] = $number;
        }

        return $numbers;
    }

    /** @return list<array<string, mixed>> */
    private static function decodeObjectList(?string $json): array
    {
        $value = self::decode($json, []);

        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException('Expected a JSON list.');
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }

    /** @return array<string, mixed> */
    private static function decodeObject(?string $json): array
    {
        $value = self::decode($json, []);

        if (! is_array($value)) {
            throw new RuntimeException('Expected a JSON object.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private static function decode(?string $json, mixed $default): mixed
    {
        if ($json === null || $json === '') {
            return $default;
        }

        try {
            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Database contains invalid JSON.', previous: $exception);
        }
    }
}
