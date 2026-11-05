<?php

declare(strict_types=1);

use Pest\Rector\Rules\ChainExpectCallsRector;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Rector\FuncCall\UnlinkFuncCallToFileFacadeDeleteRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        RestoreDefaultNullToNullableTypePropertyRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        ChainExpectCallsRector::class,
        // The facade is mocked in this test, so its cleanup must bypass it.
        UnlinkFuncCallToFileFacadeDeleteRector::class => [__DIR__ . '/tests/Feature/RemoveChecksumTest.php'],
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::PRIVATIZATION,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::RECTOR_PRESET,
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        PestSetList::CODING_STYLE,
    ]);
