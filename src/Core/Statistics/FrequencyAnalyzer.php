<?php

declare(strict_types=1);

namespace Kiyosaki\Core\Statistics;

use InvalidArgumentException;
use Kiyosaki\Core\Domain\DrawRecord;

final class FrequencyAnalyzer
{
    /**
     * @param  list<DrawRecord>  $draws
     * @return array{most: list<array{number: int, percentage: float}>, least: list<array{number: int, percentage: float}>}
     */
    public function extremes(array $draws, int $size = 1): array
    {
        if ($size < 1 || $size > 39) {
            throw new InvalidArgumentException('Frequency result size must be between 1 and 39.');
        }

        if ($draws === []) {
            return ['most' => [], 'least' => []];
        }

        $counts = array_fill(1, 39, 0);
        foreach ($draws as $draw) {
            foreach ($draw->numbers as $number) {
                $counts[$number]++;
            }
        }

        $totalDraws = count($draws);
        $ranked = [];
        foreach ($counts as $number => $count) {
            $ranked[] = [
                'number' => $number,
                'percentage' => round(($count / $totalDraws) * 100, 2),
            ];
        }

        usort($ranked, static fn (array $left, array $right): int => $right['percentage'] <=> $left['percentage'] ?: $left['number'] <=> $right['number']);
        $most = array_slice($ranked, 0, $size);

        usort($ranked, static fn (array $left, array $right): int => $left['percentage'] <=> $right['percentage'] ?: $left['number'] <=> $right['number']);

        return ['most' => $most, 'least' => array_slice($ranked, 0, $size)];
    }
}
