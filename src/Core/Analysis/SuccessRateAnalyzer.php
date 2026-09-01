<?php

declare(strict_types=1);

namespace Kiyosaki\Core\Analysis;

use Kiyosaki\Core\Domain\DrawRecord;
use Kiyosaki\Core\Domain\StoredCombination;

final class SuccessRateAnalyzer
{
    private const array SIGNIFICANT_MATCHES = [3, 4, 5, 6, 7];

    /**
     * @param  list<DrawRecord>  $draws
     * @param  list<StoredCombination>  $combinations
     * @return array{
     *     evaluated: int,
     *     pending: int,
     *     results: array<int, array{matches: int, rate: float, details: list<array{draw: int, year: int, combination: list<int>, drawn: list<int>}>}>
     * }
     */
    public function analyze(array $draws, array $combinations): array
    {
        $drawLookup = [];
        foreach ($draws as $draw) {
            $drawLookup[$this->drawKey($draw->draw, $draw->year)] = $draw;
        }

        $evaluated = 0;
        $pending = 0;
        $details = array_fill_keys(self::SIGNIFICANT_MATCHES, []);

        foreach ($combinations as $combination) {
            $draw = $drawLookup[$this->drawKey($combination->draw, $combination->year)] ?? null;
            if (! $draw instanceof DrawRecord) {
                $pending++;

                continue;
            }

            $evaluated++;
            $matches = count(array_intersect($draw->numbers, $combination->numbers));
            if (in_array($matches, self::SIGNIFICANT_MATCHES, true)) {
                $details[$matches][] = [
                    'draw' => $draw->draw,
                    'year' => $draw->year,
                    'combination' => $combination->numbers,
                    'drawn' => $draw->numbers,
                ];
            }
        }

        $results = [];
        foreach (self::SIGNIFICANT_MATCHES as $matchCount) {
            $matchDetails = $details[$matchCount];
            $matchTotal = count($matchDetails);
            $results[$matchCount] = [
                'matches' => $matchTotal,
                'rate' => $evaluated === 0 ? 0.0 : round(($matchTotal / $evaluated) * 100, 2),
                'details' => $matchDetails,
            ];
        }

        return ['evaluated' => $evaluated, 'pending' => $pending, 'results' => $results];
    }

    private function drawKey(int $draw, int $year): string
    {
        return $year.'-'.$draw;
    }
}
