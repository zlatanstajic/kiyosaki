<?php

declare(strict_types=1);

namespace Kiyosaki\Core\Domain;

use InvalidArgumentException;

final readonly class StoredCombination
{
    /** @param list<int> $numbers */
    public function __construct(
        public int $id,
        public int $draw,
        public int $year,
        public array $numbers,
    ) {
        if ($this->id < 0 || $this->draw < 1 || $this->year < 1900 || $this->year > 9999) {
            throw new InvalidArgumentException('Combination identity is invalid.');
        }

        if (count($this->numbers) !== 7 || count(array_unique($this->numbers)) !== 7) {
            throw new InvalidArgumentException('A combination must contain seven unique numbers.');
        }

        foreach ($this->numbers as $number) {
            if ($number < 1 || $number > 39) {
                throw new InvalidArgumentException('Combination numbers must be between 1 and 39.');
            }
        }
    }
}
