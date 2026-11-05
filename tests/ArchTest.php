<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

arch('classes are final')
    ->expect('Mahbub\RefreshDatabases')
    ->classes()
    ->toBeFinal()
    ->ignoring('Mahbub\RefreshDatabases\Tests');

arch('comparisons are strict')
    ->expect('Mahbub\RefreshDatabases')
    ->toUseStrictEquality();

arch('config is read through the typed facade, never the helper')
    ->expect('config')
    ->not->toBeUsed();

arch('nothing sleeps')
    ->expect(['sleep', 'usleep'])
    ->not->toBeUsed();
