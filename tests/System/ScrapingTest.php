<?php

declare(strict_types=1);

namespace Kiyosaki\Tests\System;

use Kiyosaki\Core\Import\DrawImporter;
use Kiyosaki\System\Scraping\CurlResultsClient;
use Kiyosaki\System\Scraping\LotteryResultsParser;
use Kiyosaki\System\Scraping\ResultsClient;
use Kiyosaki\System\Storage\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScrapingTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/kiyosaki-scrape-'.bin2hex(random_bytes(8)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_parser_extracts_the_current_official_json_contract(): void
    {
        $json = json_encode([
            'Round' => 69,
            'Year' => 2026,
            'Date' => '2026-08-28T00:00:00',
            'LotoNumbers' => [8, 9, 10, 12, 13, 28, 34],
            'LotoPrizes' => [
                ['Category' => '6 pogodaka', 'Winners' => 5, 'Amount' => 710487.0],
            ],
            'LotoUplata' => 46627800.0,
            'LotoFond' => 23313900.0,
            'ReportUrl' => '/report.pdf',
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);

        $record = (new LotteryResultsParser)->parse(69, 2026, $json);

        self::assertSame('2026-08-28', $record->date);
        self::assertSame([8, 9, 10, 12, 13, 28, 34], $record->numbers);
        self::assertSame(6, $record->prizeBreakdown[0]['hit_type']);
        self::assertSame(5, $record->prizeBreakdown[0]['num_wins']);
        self::assertSame(46627800.0, $record->payments['total_paid']);
        self::assertSame('/report.pdf', $record->payments['report_url']);
    }

    public function test_parser_rejects_invalid_json(): void
    {
        $this->expectException(RuntimeException::class);
        (new LotteryResultsParser)->parse(1, 2026, 'invalid');
    }

    public function test_importer_skips_existing_rows_without_fetching_again(): void
    {
        $database = new Database($this->path);
        $client = new class implements ResultsClient
        {
            public int $calls = 0;

            public function fetch(int $draw, int $year): string
            {
                $this->calls++;

                return json_encode([
                    'Round' => $draw,
                    'Year' => $year,
                    'Date' => sprintf('%d-01-%02dT00:00:00', $year, $draw),
                    'LotoNumbers' => [1, 5, 10, 15, 20, 25, 30],
                    'LotoPrizes' => [],
                ], JSON_THROW_ON_ERROR);
            }
        };
        $importer = new DrawImporter($database, $client, new LotteryResultsParser);

        self::assertSame(['inserted' => 2, 'skipped' => 0], $importer->import(2026, 1, 2));
        self::assertSame(['inserted' => 0, 'skipped' => 2], $importer->import(2026, 1, 2));
        self::assertSame(2, $client->calls);
        self::assertCount(2, $database->draws());
    }

    public function test_json_client_caches_a_year_and_returns_one_draw(): void
    {
        $calls = 0;
        $client = new CurlResultsClient(30, static function (string $payload) use (&$calls): string {
            $calls++;
            $request = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('loto', $request['game']);
            self::assertSame('2026-01-01', $request['startDate']);

            return json_encode([
                'data' => [[
                    'Round' => 69,
                    'Year' => 2026,
                    'Date' => '2026-08-28T00:00:00',
                    'LotoNumbers' => [8, 9, 10, 12, 13, 28, 34],
                ]],
                'TotalPages' => 1,
            ], JSON_THROW_ON_ERROR);
        });

        self::assertSame(69, json_decode($client->fetch(69, 2026), true, flags: JSON_THROW_ON_ERROR)['Round']);
        self::assertSame(69, json_decode($client->fetch(69, 2026), true, flags: JSON_THROW_ON_ERROR)['Round']);
        self::assertSame(1, $calls);

        $this->expectException(RuntimeException::class);
        $client->fetch(70, 2026);
    }
}
