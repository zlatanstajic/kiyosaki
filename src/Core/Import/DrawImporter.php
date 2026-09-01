<?php

declare(strict_types=1);

namespace Kiyosaki\Core\Import;

use InvalidArgumentException;
use Kiyosaki\System\Scraping\LotteryResultsParser;
use Kiyosaki\System\Scraping\ResultsClient;
use Kiyosaki\System\Storage\Database;

final readonly class DrawImporter
{
    public function __construct(
        private Database $database,
        private ResultsClient $client,
        private LotteryResultsParser $parser,
    ) {}

    /** @return array{inserted: int, skipped: int} */
    public function import(int $year, int $start, int $end): array
    {
        if ($year < 1900 || $year > 9999 || $start < 1 || $end < $start) {
            throw new InvalidArgumentException('Import range is invalid.');
        }

        $inserted = 0;
        $skipped = 0;
        for ($draw = $start; $draw <= $end; $draw++) {
            if ($this->database->drawExists($draw, $year)) {
                $skipped++;

                continue;
            }

            $record = $this->parser->parse($draw, $year, $this->client->fetch($draw, $year));
            $inserted += (int) $this->database->insertDraw($record);
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }
}
