<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@auto' => true,
        // Symfony presets
        '@Symfony' => true,
        // Ensures count($x) !== 1 instead of 1 !== count($x)
        'yoda_style' => [
            'equal'            => false,
            'identical'        => false,
            'less_and_greater' => false,
        ],
        // Ensures ! isset(...) instead of !isset(...)
        'not_operator_with_successor_space' => true,
        // Ensure new classes are instantiated without parentheses if possible
        'new_with_parentheses' => [
            'anonymous_class' => false,
            'named_class'     => false,
        ],
    ])
    ->setFinder(
        new Finder()->in(['src', 'tests'])
    )
;
