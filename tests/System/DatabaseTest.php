<?php

declare(strict_types=1);

namespace Kiyosaki\Tests\System;

use Kiyosaki\Core\Domain\DrawRecord;
use Kiyosaki\System\Storage\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/kiyosaki-'.bin2hex(random_bytes(8)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_database_bootstraps_and_round_trips_the_pipeline_data(): void
    {
        $database = new Database($this->path);
        $draw = new DrawRecord(
            1,
            2026,
            '2026-01-02',
            [1, 5, 10, 15, 20, 25, 30],
            [['hit_type' => 7, 'num_wins' => 1, 'amount_dinars' => 123.5]],
            ['total_paid' => 999.5],
        );

        self::assertSame($this->path, $database->path());
        self::assertFalse($database->drawExists(1, 2026));
        self::assertTrue($database->insertDraw($draw));
        self::assertFalse($database->insertDraw($draw));
        self::assertTrue($database->drawExists(1, 2026));
        self::assertSame($draw->numbers, $database->latestDraw()?->numbers);
        self::assertCount(1, $database->draws());
        self::assertCount(1, $database->draws(2026));
        self::assertSame(2, $database->insertCombinations(2, 2026, [
            [1, 4, 8, 12, 20, 27, 39],
            [2, 6, 11, 17, 23, 31, 38],
        ]));
        self::assertSame(0, $database->insertCombinations(2, 2026, [[1, 4, 8, 12, 20, 27, 39]]));
        self::assertCount(2, $database->combinations(2026));
        self::assertSame([
            'draws' => 1,
            'combinations' => 2,
            'first_year' => 2026,
            'last_year' => 2026,
        ], $database->statistics());
    }

    public function test_bundled_database_contains_the_expected_history(): void
    {
        $database = new Database;
        $statistics = $database->statistics();

        self::assertSame(Database::defaultPath(), $database->path());
        self::assertSame(1477, $statistics['draws']);
        self::assertSame(140, $statistics['combinations']);
        self::assertSame(2012, $statistics['first_year']);
        self::assertSame(2026, $statistics['last_year']);
    }
}
