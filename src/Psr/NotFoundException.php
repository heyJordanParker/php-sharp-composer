<?php

declare(strict_types=1);

namespace Sharp\Psr;

use Psr\Container\NotFoundExceptionInterface;

/** PSR-11's error for an id no module binds. */
final class NotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
}
