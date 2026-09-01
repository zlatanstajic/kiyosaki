<?php

declare(strict_types=1);

namespace Kiyosaki\Core\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DrawRecord
{
    /**
     * @param  list<int>  $numbers
     * @param  list<array<string, mixed>>  $prizeBreakdown
     * @param  array<string, mixed>  $payments
     */
    public function __construct(
        public int $draw,
        public int $year,
        public ?string $date,
        public array $numbers,
        public array $prizeBreakdown = [],
        public array $payments = [],
    ) {
        if ($this->draw < 1) {
            throw new InvalidArgumentException('Draw number must be positive.');
        }

        if ($this->year < 1900 || $this->year > 9999) {
            throw new InvalidArgumentException('Draw year must have four digits.');
        }

        self::assertNumbers($this->numbers);

        if ($this->date !== null) {
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $this->date);
            if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $this->date) {
                throw new InvalidArgumentException('Draw date must use the YYYY-MM-DD format.');
            }
        }
    }

    /** @param list<int> $numbers */
    private static function assertNumbers(array $numbers): void
    {
        if (count($numbers) !== 7 || count(array_unique($numbers)) !== 7) {
            throw new InvalidArgumentException('A draw must contain seven unique numbers.');
        }

        foreach ($numbers as $number) {
            if ($number < 1 || $number > 39) {
                throw new InvalidArgumentException('Draw numbers must be between 1 and 39.');
            }
        }
    }
}
