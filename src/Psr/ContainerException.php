<?php

declare(strict_types=1);

namespace Sharp\Psr;

use Psr\Container\ContainerExceptionInterface;

/** PSR-11's error for a service the container can't build, with the engine's Sharp\ContainerError as its previous. */
final class ContainerException extends \RuntimeException implements ContainerExceptionInterface
{
}
