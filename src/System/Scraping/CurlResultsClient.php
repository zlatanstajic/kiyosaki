<?php

declare(strict_types=1);

namespace Kiyosaki\System\Scraping;

use Closure;
use JsonException;
use RuntimeException;

final class CurlResultsClient implements ResultsClient
{
    private const string RESULTS_URL = 'https://lutrija.rs/api/results';

    private const int PAGE_SIZE = 200;

    /** @var array<int, array<int, string>> */
    private array $yearCache = [];

    /** @var null|Closure(string): string */
    private readonly ?Closure $transport;

    /** @param null|callable(string): string $transport */
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        ?callable $transport = null,
    ) {
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    public function fetch(int $draw, int $year): string
    {
        $this->yearCache[$year] ??= $this->fetchYear($year);

        return $this->yearCache[$year][$draw]
            ?? throw new RuntimeException(sprintf('Draw %d (%d) was not published by the official results API.', $draw, $year));
    }

    /** @return array<int, string> */
    private function fetchYear(int $year): array
    {
        $draws = [];
        $page = 1;

        do {
            $response = $this->requestPage($year, $page);
            $totalPages = $response['TotalPages'];

            foreach ($response['data'] as $result) {
                $round = $result['Round'] ?? null;
                if (! is_int($round)) {
                    throw new RuntimeException('Official results API returned a draw without a numeric round.');
                }
                $draws[$round] = self::encode($result);
            }

            $page++;
        } while ($page <= $totalPages);

        return $draws;
    }

    /**
     * @return array{data: list<array<string, mixed>>, TotalPages: int}
     */
    private function requestPage(int $year, int $page): array
    {
        $payload = self::encode([
            'game' => 'loto',
            'startDate' => sprintf('%d-01-01', $year),
            'endDate' => sprintf('%d-12-31', $year),
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'lang' => 'sr-Latn-RS',
        ]);
        $response = $this->post($payload);

        try {
            $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Official results API returned invalid JSON.', previous: $exception);
        }

        if (
            ! is_array($decoded)
            || ! isset($decoded['data'], $decoded['TotalPages'])
            || ! is_array($decoded['data'])
            || ! is_int($decoded['TotalPages'])
        ) {
            throw new RuntimeException('Official results API returned an unexpected response.');
        }

        /** @var array{data: list<array<string, mixed>>, TotalPages: int} $decoded */
        return $decoded;
    }

    private function post(string $payload): string
    {
        if ($this->transport instanceof Closure) {
            return ($this->transport)($payload);
        }

        $handle = curl_init(self::RESULTS_URL);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the HTTP client.');
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => 'Kiyosaki/2.0 (+https://github.com/zlatanstajic/kiyosaki)',
        ]);
        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        if (! is_string($response) || $status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'Unable to fetch official results: HTTP %d%s.',
                $status,
                $error === '' ? '' : ' - '.$error,
            ));
        }

        return $response;
    }

    private static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode results API data.', previous: $exception);
        }
    }
}
