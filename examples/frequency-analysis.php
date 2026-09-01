<?php

declare(strict_types=1);

use Kiyosaki\Core\Statistics\FrequencyAnalyzer;
use Kiyosaki\System\Storage\Database;

require_once __DIR__.'/../vendor/autoload.php';

$frequencies = (new FrequencyAnalyzer)->extremes(
    draws: (new Database)->draws(),
    size: 5,
);

foreach (['most', 'least'] as $group) {
    echo ucfirst($group).' frequent numbers:'.PHP_EOL;
    foreach ($frequencies[$group] as $frequency) {
        echo sprintf("%d: %.2f%%\n", $frequency['number'], $frequency['percentage']);
    }
}
