<?php

declare(strict_types=1);

use Kiyosaki\Core\Generation\CombinationGenerator;
use Kiyosaki\Core\Statistics\FrequencyAnalyzer;
use Kiyosaki\System\Storage\Database;

require_once __DIR__.'/../vendor/autoload.php';

$draws = (new Database)->draws();
$frequencies = (new FrequencyAnalyzer)->extremes($draws, 3);
$enabledNumbers = [7, 13];
$disabledNumbers = array_values(array_diff(
    array_column($frequencies['most'], 'number'),
    $enabledNumbers,
));

$combinations = (new CombinationGenerator)->generate(
    previousCombinations: array_map(static fn ($draw): array => $draw->numbers, $draws),
    totalCombinations: 5,
    disabledNumbers: $disabledNumbers,
    enabledNumbers: $enabledNumbers,
);

foreach ($combinations as $combination) {
    echo '['.implode(', ', $combination).']'.PHP_EOL;
}
