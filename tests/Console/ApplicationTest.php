<?php

declare(strict_types=1);

namespace Kiyosaki\Tests\Console;

use Kiyosaki\Console\Application;
use Kiyosaki\Core\Domain\DrawRecord;
use Kiyosaki\System\Storage\Database;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/kiyosaki-cli-'.bin2hex(random_bytes(8)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_console_covers_help_stats_generation_and_analysis(): void
    {
        $database = new Database($this->path);
        $database->insertDraw(new DrawRecord(1, 2026, '2026-01-01', [1, 5, 10, 15, 20, 25, 30]));
        $output = [];
        $errors = [];
        $application = new Application(
            $database,
            static function (string $message) use (&$output): void {
                $output[] = $message;
            },
            static function (string $message) use (&$errors): void {
                $errors[] = $message;
            },
        );

        self::assertSame(0, $application->run(['kiyosaki', 'help']));
        self::assertSame(0, $application->run(['kiyosaki', 'database:stats']));
        self::assertSame(0, $application->run([
            'kiyosaki', 'generate', '--combinations=2', '--favorites=7,13', '--draw=2', '--year=2026',
        ]));
        self::assertSame(0, $application->run(['kiyosaki', 'analyse', '-y', '2026']));
        self::assertCount(2, $database->combinations(2026));
        self::assertStringContainsString('Kiyosaki lottery toolkit', $output[0]);
        self::assertSame([], $errors);
    }

    public function test_console_returns_useful_error_codes(): void
    {
        $errors = [];
        $application = new Application(
            new Database($this->path),
            static function (string $message): void {},
            static function (string $message) use (&$errors): void {
                $errors[] = $message;
            },
        );

        self::assertSame(2, $application->run(['kiyosaki', 'missing']));
        self::assertSame(1, $application->run(['kiyosaki', 'scrape', '--year', '2026']));
        self::assertSame(1, $application->run(['kiyosaki', 'generate']));
        self::assertCount(3, $errors);
    }
}
