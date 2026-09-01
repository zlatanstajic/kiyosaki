<?php

declare(strict_types=1);

namespace Kiyosaki\Tests\Core;

use InvalidArgumentException;
use Kiyosaki\Core\Analysis\SuccessRateAnalyzer;
use Kiyosaki\Core\Domain\DrawRecord;
use Kiyosaki\Core\Domain\StoredCombination;
use Kiyosaki\Core\Generation\CombinationGenerator;
use Kiyosaki\Core\Statistics\FrequencyAnalyzer;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

final class ModernPipelineTest extends TestCase
{
    public function test_domain_records_validate_their_data(): void
    {
        $draw = $this->draw(1, 2026, [1, 5, 10, 15, 20, 25, 30]);
        $combination = new StoredCombination(1, 1, 2026, [1, 5, 10, 15, 20, 25, 30]);

        self::assertSame('2026-01-01', $draw->date);
        self::assertSame(1, $combination->id);

        $this->expectException(InvalidArgumentException::class);
        new DrawRecord(0, 2026, null, [1, 2, 3, 4, 5, 6, 7]);
    }

    public function test_invalid_combination_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StoredCombination(1, 1, 2026, [1, 1, 2, 3, 4, 5, 6]);
    }

    public function test_frequency_analyzer_ranks_all_numbers_deterministically(): void
    {
        $analyzer = new FrequencyAnalyzer;
        $result = $analyzer->extremes([
            $this->draw(1, 2026, [1, 2, 3, 4, 5, 6, 7]),
            $this->draw(2, 2026, [1, 8, 9, 10, 11, 12, 13]),
        ], 2);

        self::assertSame(1, $result['most'][0]['number']);
        self::assertSame(100.0, $result['most'][0]['percentage']);
        self::assertSame(14, $result['least'][0]['number']);
        self::assertSame(['most' => [], 'least' => []], $analyzer->extremes([], 1));

        $this->expectException(InvalidArgumentException::class);
        $analyzer->extremes([], 0);
    }

    public function test_generator_produces_unique_filtered_combinations(): void
    {
        $generator = new CombinationGenerator(new Randomizer(new Mt19937(1234)));
        $generated = $generator->generate(
            [[1, 5, 10, 15, 20, 25, 30]],
            5,
            [39],
            [7, 13],
        );

        self::assertCount(5, $generated);
        self::assertCount(5, array_unique(array_map(static fn (array $item): string => implode('-', $item), $generated)));
        foreach ($generated as $combination) {
            self::assertCount(7, $combination);
            self::assertContains(7, $combination);
            self::assertContains(13, $combination);
            self::assertNotContains(39, $combination);
            self::assertFalse($generator->hasConsecutiveSequence($combination));
            self::assertFalse($generator->hasSymmetricPattern($combination));
        }

        self::assertTrue($generator->hasConsecutiveSequence([1, 2, 3, 8, 12, 20, 31]));
        self::assertTrue($generator->hasSymmetricPattern([1, 3, 5, 7, 12, 20, 31]));
    }

    public function test_generator_rejects_impossible_requests_without_recursion(): void
    {
        $generator = new CombinationGenerator(new Randomizer(new Mt19937(1)));

        try {
            $generator->generate([], 1, range(1, 33), []);
            self::fail('Expected an invalid pool exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Not enough', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $generator->generate([], 1, [], [1, 2, 3, 4, 5, 6, 7], 2);
    }

    public function test_success_rates_use_every_evaluated_combination_as_denominator(): void
    {
        $draws = [
            $this->draw(1, 2026, [1, 2, 3, 4, 5, 6, 7]),
            $this->draw(2, 2026, [10, 11, 12, 13, 14, 15, 16]),
        ];
        $combinations = [
            new StoredCombination(1, 1, 2026, [1, 2, 3, 20, 21, 22, 23]),
            new StoredCombination(2, 2, 2026, [10, 11, 12, 13, 30, 31, 32]),
            new StoredCombination(3, 3, 2026, [1, 8, 15, 22, 29, 36, 39]),
        ];

        $report = (new SuccessRateAnalyzer)->analyze($draws, $combinations);

        self::assertSame(2, $report['evaluated']);
        self::assertSame(1, $report['pending']);
        self::assertSame(1, $report['results'][3]['matches']);
        self::assertSame(50.0, $report['results'][3]['rate']);
        self::assertSame(1, $report['results'][4]['matches']);
        self::assertSame(50.0, $report['results'][4]['rate']);
    }

    /** @param list<int> $numbers */
    private function draw(int $draw, int $year, array $numbers): DrawRecord
    {
        return new DrawRecord($draw, $year, sprintf('%d-01-01', $year), $numbers);
    }
}
