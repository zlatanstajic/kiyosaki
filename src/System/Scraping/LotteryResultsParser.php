<?php

declare(strict_types=1);

namespace Kiyosaki\System\Scraping;

use JsonException;
use Kiyosaki\Core\Domain\DrawRecord;
use RuntimeException;

final class LotteryResultsParser
{
    public function parse(int $draw, int $year, string $json): DrawRecord
    {
        try {
            $result = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Official results API returned invalid JSON.', previous: $exception);
        }

        if (! is_array($result)) {
            throw new RuntimeException('Official results API returned an invalid draw.');
        }

        $round = $result['Round'] ?? null;
        $resultYear = $result['Year'] ?? null;
        $date = $result['Date'] ?? null;
        $numbers = $result['LotoNumbers'] ?? null;
        $prizes = $result['LotoPrizes'] ?? [];

        if ($round !== $draw || $resultYear !== $year || ! is_string($date) || ! is_array($numbers) || ! is_array($prizes)) {
            throw new RuntimeException('Official results API returned an unexpected draw shape.');
        }

        $prizeBreakdown = [];
        foreach ($prizes as $prize) {
            if (! is_array($prize)) {
                continue;
            }

            $category = $prize['Category'] ?? '';
            preg_match('/\d+/', is_string($category) ? $category : '', $categoryMatch);
            $prizeBreakdown[] = [
                'hit_type' => isset($categoryMatch[0]) ? (int) $categoryMatch[0] : 0,
                'num_wins' => is_int($prize['Winners'] ?? null) ? $prize['Winners'] : 0,
                'amount_dinars' => is_int($prize['Amount'] ?? null) || is_float($prize['Amount'] ?? null)
                    ? (float) $prize['Amount']
                    : 0.0,
            ];
        }

        $payments = array_filter([
            'total_paid' => $result['LotoUplata'] ?? null,
            'prize_fund' => $result['LotoFond'] ?? null,
            'loto_plus_fund' => $result['LotoPlusFond'] ?? null,
            'loto_plus_winners' => $result['LotoPlusWinners'] ?? null,
            'joker_total_paid' => $result['JokerUplata'] ?? null,
            'joker_prize_fund' => $result['JokerFond'] ?? null,
            'report_url' => $result['ReportUrl'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        /** @var list<int> $numbers */
        return new DrawRecord(
            $draw,
            $year,
            substr($date, 0, 10),
            $numbers,
            $prizeBreakdown,
            $payments,
        );
    }
}
