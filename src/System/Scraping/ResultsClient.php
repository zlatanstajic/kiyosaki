<?php

declare(strict_types=1);

namespace Kiyosaki\System\Scraping;

interface ResultsClient
{
    public function fetch(int $draw, int $year): string;
}
