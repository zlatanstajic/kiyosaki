<?php

declare(strict_types=1);

namespace Kiyosaki\Core\Generation;

use InvalidArgumentException;
use Random\Randomizer;
use RuntimeException;

final readonly class CombinationGenerator
{
    public const int NUMBERS_PER_COMBINATION = 7;

    public const int HIGHEST_NUMBER = 39;

    public function __construct(
        private Randomizer $randomizer = new Randomizer,
    ) {}

    /**
     * @param  list<list<int>>  $previousCombinations
     * @param  list<int>  $disabledNumbers
     * @param  list<int>  $enabledNumbers
     * @return list<list<int>>
     */
    public function generate(
        array $previousCombinations,
        int $totalCombinations = 1,
        array $disabledNumbers = [],
        array $enabledNumbers = [],
        int $maximumAttempts = 100_000,
    ): array {
        if ($totalCombinations < 1) {
            throw new InvalidArgumentException('At least one combination must be requested.');
        }

        if ($maximumAttempts < $totalCombinations) {
            throw new InvalidArgumentException('Maximum attempts cannot be smaller than the requested total.');
        }

        $disabledNumbers = $this->normalizeNumbers($disabledNumbers, 'disabled');
        $enabledNumbers = $this->normalizeNumbers($enabledNumbers, 'enabled');
        $overlap = array_values(array_intersect($disabledNumbers, $enabledNumbers));
        if ($overlap !== []) {
            throw new InvalidArgumentException('Disabled and enabled numbers overlap: '.implode(', ', $overlap).'.');
        }

        if (count($enabledNumbers) > self::NUMBERS_PER_COMBINATION) {
            throw new InvalidArgumentException('At most seven numbers may be enabled.');
        }

        $availableNumbers = array_values(array_diff(
            range(1, self::HIGHEST_NUMBER),
            $disabledNumbers,
            $enabledNumbers,
        ));
        $remainingSlots = self::NUMBERS_PER_COMBINATION - count($enabledNumbers);
        if (count($availableNumbers) < $remainingSlots) {
            throw new InvalidArgumentException('Not enough available numbers to build a combination.');
        }

        $seen = [];
        foreach ($previousCombinations as $combination) {
            sort($combination);
            $seen[$this->key($combination)] = true;
        }

        $generated = [];
        for ($attempt = 0; $attempt < $maximumAttempts && count($generated) < $totalCombinations; $attempt++) {
            $shuffled = $this->randomizer->shuffleArray($availableNumbers);
            $combination = [...array_slice($shuffled, 0, $remainingSlots), ...$enabledNumbers];
            sort($combination);
            $key = $this->key($combination);

            if (isset($seen[$key]) || $this->hasConsecutiveSequence($combination) || $this->hasSymmetricPattern($combination)) {
                continue;
            }

            $seen[$key] = true;
            $generated[] = $combination;
        }

        if (count($generated) !== $totalCombinations) {
            throw new RuntimeException(sprintf(
                'Unable to generate %d unique combinations after %d attempts.',
                $totalCombinations,
                $maximumAttempts,
            ));
        }

        return $generated;
    }

    /** @param list<int> $combination */
    public function hasConsecutiveSequence(array $combination, int $minimumLength = 3): bool
    {
        if ($minimumLength < 2) {
            throw new InvalidArgumentException('A sequence must contain at least two numbers.');
        }

        for ($start = 0; $start <= count($combination) - $minimumLength; $start++) {
            for ($offset = 1; $offset < $minimumLength; $offset++) {
                if ($combination[$start + $offset] !== $combination[$start] + $offset) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /** @param list<int> $combination */
    public function hasSymmetricPattern(array $combination, int $minimumEqualDifferences = 3): bool
    {
        if ($minimumEqualDifferences < 2) {
            throw new InvalidArgumentException('A pattern must contain at least two equal differences.');
        }

        $differences = [];
        for ($index = 0; $index < count($combination) - 1; $index++) {
            $differences[] = $combination[$index + 1] - $combination[$index];
        }

        for ($start = 0; $start <= count($differences) - $minimumEqualDifferences; $start++) {
            $slice = array_slice($differences, $start, $minimumEqualDifferences);
            if (count(array_unique($slice)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $numbers
     * @return list<int>
     */
    private function normalizeNumbers(array $numbers, string $label): array
    {
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        foreach ($numbers as $number) {
            if ($number < 1 || $number > self::HIGHEST_NUMBER) {
                throw new InvalidArgumentException(ucfirst($label).' numbers must be between 1 and 39.');
            }
        }

        return $numbers;
    }

    /** @param list<int> $combination */
    private function key(array $combination): string
    {
        return implode('-', $combination);
    }
}
