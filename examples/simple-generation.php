<?php

declare(strict_types=1);

use Kiyosaki\Core\Generation\CombinationGenerator;

require_once __DIR__.'/../vendor/autoload.php';

$combinations = (new CombinationGenerator)->generate(
    previousCombinations: [],
    totalCombinations: 1,
);

foreach ($combinations as $combination) {
    echo '['.implode(', ', $combination).']'.PHP_EOL;
}
