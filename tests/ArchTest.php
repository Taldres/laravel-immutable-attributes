<?php

declare(strict_types=1);

use Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Taldres\ImmutableAttributes')
    ->toUseStrictTypes();

arch('the exception is a runtime exception')
    ->expect(ImmutableAttributeException::class)
    ->toExtend(RuntimeException::class);

arch('a misdeclared #[Immutable] is an invalid argument')
    ->expect(InvalidImmutableColumnsException::class)
    ->toExtend(InvalidArgumentException::class);
