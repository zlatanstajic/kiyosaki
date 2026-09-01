<?php

declare(strict_types=1);

namespace Kiyosaki\Console;

use Closure;
use Kiyosaki\Core\Analysis\SuccessRateAnalyzer;
use Kiyosaki\Core\Generation\CombinationGenerator;
use Kiyosaki\Core\Import\DrawImporter;
use Kiyosaki\Core\Statistics\FrequencyAnalyzer;
use Kiyosaki\System\Scraping\CurlResultsClient;
use Kiyosaki\System\Scraping\LotteryResultsParser;
use Kiyosaki\System\Storage\Database;
use Throwable;

final readonly class Application
{
    /** @var Closure(string): void */
    private Closure $output;

    /** @var Closure(string): void */
    private Closure $errorOutput;

    /**
     * @param  null|callable(string): void  $output
     * @param  null|callable(string): void  $errorOutput
     */
    public function __construct(
        private Database $database = new Database,
        ?callable $output = null,
        ?callable $errorOutput = null,
    ) {
        $this->output = $output === null
            ? static function (string $message): void {
                fwrite(STDOUT, $message.PHP_EOL);
            }
        : Closure::fromCallable($output);
        $this->errorOutput = $errorOutput === null
            ? static function (string $message): void {
                fwrite(STDERR, $message.PHP_EOL);
            }
        : Closure::fromCallable($errorOutput);
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? 'help';
        $options = $this->options(array_slice($arguments, 2));

        try {
            return match ($command) {
                'analyse', 'analyze' => $this->analyse($options),
                'database:stats', 'stats' => $this->databaseStats(),
                'generate' => $this->generate($options),
                'scrape' => $this->scrape($options),
                'help', '--help', '-h' => $this->help(),
                default => $this->unknown($command),
            };
        } catch (Throwable $throwable) {
            ($this->errorOutput)('Error: '.$throwable->getMessage());

            return 1;
        }
    }

    /** @param array<string, string|bool> $options */
    private function generate(array $options): int
    {
        $draws = $this->database->draws();
        if ($draws === []) {
            throw new \RuntimeException('The database contains no draw history.');
        }

        $mostSize = $this->integerOption($options, 'disable-most', 'd', 0);
        $leastSize = $this->integerOption($options, 'enable-least', 'e', 0);
        $frequency = new FrequencyAnalyzer;
        $disabled = $mostSize === 0 ? [] : array_column($frequency->extremes($draws, $mostSize)['most'], 'number');
        $enabled = $leastSize === 0 ? [] : array_column($frequency->extremes($draws, $leastSize)['least'], 'number');
        $favorites = $this->numberList($options['favorites'] ?? $options['n'] ?? '');

        $overlap = array_intersect($disabled, $favorites);
        if ($overlap !== []) {
            throw new \InvalidArgumentException('Disabled and favorite numbers overlap: '.implode(', ', $overlap).'.');
        }
        $enabled = array_values(array_unique([...$enabled, ...$favorites]));

        $total = $this->integerOption($options, 'combinations', 'c', 1);
        $generator = new CombinationGenerator;
        $combinations = $generator->generate(
            array_map(static fn ($draw): array => $draw->numbers, $draws),
            $total,
            $disabled,
            $enabled,
        );

        $latest = array_last($draws);
        $targetDraw = $this->integerOption($options, 'draw', 'id', $latest->draw + 1);
        $targetYear = $this->integerOption($options, 'year', 'y', $latest->year);
        $inserted = $this->database->insertCombinations($targetDraw, $targetYear, $combinations);

        ($this->output)(sprintf('Generated %d combination(s) for draw #%d (%d).', $total, $targetDraw, $targetYear));
        foreach ($combinations as $index => $combination) {
            ($this->output)(sprintf('%d. [%s]', $index + 1, implode(', ', $combination)));
        }
        ($this->output)(sprintf('Saved %d new combination(s) to %s.', $inserted, $this->database->path()));

        return 0;
    }

    /** @param array<string, string|bool> $options */
    private function analyse(array $options): int
    {
        $year = $this->integerOption($options, 'year', 'y', (int) date('Y'));
        $report = (new SuccessRateAnalyzer)->analyze(
            $this->database->draws($year),
            $this->database->combinations($year),
        );

        ($this->output)(sprintf(
            'Evaluated %d combination(s) for %d; %d await a draw result.',
            $report['evaluated'],
            $year,
            $report['pending'],
        ));
        foreach (array_reverse($report['results'], true) as $matches => $result) {
            ($this->output)(sprintf('%d matches: %d (%.2f%%)', $matches, $result['matches'], $result['rate']));
        }

        return 0;
    }

    /** @param array<string, string|bool> $options */
    private function scrape(array $options): int
    {
        $year = $this->requiredIntegerOption($options, 'year', 'y');
        $start = $this->requiredIntegerOption($options, 'start', 's');
        $end = $this->requiredIntegerOption($options, 'end', 'e');
        $result = new DrawImporter(
            $this->database,
            new CurlResultsClient,
            new LotteryResultsParser,
        )->import($year, $start, $end);

        ($this->output)(sprintf(
            'Imported %d draw(s), skipped %d existing draw(s). Database: %s',
            $result['inserted'],
            $result['skipped'],
            $this->database->path(),
        ));

        return 0;
    }

    private function databaseStats(): int
    {
        $statistics = $this->database->statistics();
        ($this->output)(sprintf(
            '%d draws (%s-%s), %d generated combinations. Database: %s',
            $statistics['draws'],
            $statistics['first_year'] ?? 'n/a',
            $statistics['last_year'] ?? 'n/a',
            $statistics['combinations'],
            $this->database->path(),
        ));

        return 0;
    }

    private function help(): int
    {
        ($this->output)(<<<'TEXT'
            Kiyosaki lottery toolkit

            Usage:
              kiyosaki database:stats
              kiyosaki generate [-c 5] [-d 3] [-e 2] [-n 7,13,21] [--draw 22] [--year 2026]
              kiyosaki analyse [--year 2026]
              kiyosaki scrape --year 2026 --start 1 --end 10

            Environment:
              KIYOSAKI_DATABASE_PATH  Override the bundled SQLite database path.
            TEXT);

        return 0;
    }

    private function unknown(string $command): int
    {
        ($this->errorOutput)(sprintf('Unknown command "%s". Run "kiyosaki help".', $command));

        return 2;
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, string|bool>
     */
    private function options(array $arguments): array
    {
        $options = [];
        for ($index = 0; $index < count($arguments); $index++) {
            $argument = $arguments[$index];
            if (! str_starts_with($argument, '-')) {
                throw new \InvalidArgumentException(sprintf('Unexpected argument: %s', $argument));
            }

            $trimmed = ltrim($argument, '-');
            if (str_contains($trimmed, '=')) {
                [$key, $value] = explode('=', $trimmed, 2);
                $options[$key] = $value;

                continue;
            }

            $next = $arguments[$index + 1] ?? null;
            if ($next !== null && ! str_starts_with($next, '-')) {
                $options[$trimmed] = $next;
                $index++;
            } else {
                $options[$trimmed] = true;
            }
        }

        return $options;
    }

    /** @param array<string, string|bool> $options */
    private function integerOption(array $options, string $long, string $short, int $default): int
    {
        $value = $options[$long] ?? $options[$short] ?? $default;
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < 0) {
            throw new \InvalidArgumentException(sprintf('Option --%s must be a non-negative integer.', $long));
        }

        return $validated;
    }

    /** @param array<string, string|bool> $options */
    private function requiredIntegerOption(array $options, string $long, string $short): int
    {
        if (! isset($options[$long]) && ! isset($options[$short])) {
            throw new \InvalidArgumentException(sprintf('Option --%s is required.', $long));
        }

        return $this->integerOption($options, $long, $short, 0);
    }

    /** @return list<int> */
    private function numberList(string|bool $value): array
    {
        if ($value === '' || $value === false) {
            return [];
        }
        if ($value === true) {
            throw new \InvalidArgumentException('Option --favorites requires a comma-separated value.');
        }

        $numbers = [];
        foreach (explode(',', $value) as $number) {
            $validated = filter_var(trim($number), FILTER_VALIDATE_INT);
            if ($validated === false) {
                throw new \InvalidArgumentException('Favorite numbers must be comma-separated integers.');
            }
            $numbers[] = $validated;
        }

        return $numbers;
    }
}
