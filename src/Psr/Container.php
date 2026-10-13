<?php

declare(strict_types=1);

namespace Sharp\Psr;

use Psr\Container\ContainerInterface;

/**
 * Spec section 32: PHP#'s container as a PSR-11 container, so any framework's container can ask it for a PHP# class.
 * The engine's Sharp\Container can't implement an interface Composer loads after the engine starts, so this adapter
 * does.
 */
final class Container implements ContainerInterface
{
    private readonly \Sharp\Container $container;

    public function __construct()
    {
        $this->container = new \Sharp\Container();
    }

    public function get(string $id): mixed
    {
        if (!$this->container->has($id)) {
            throw new NotFoundException("No module binds $id");
        }

        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
