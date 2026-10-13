<?php

declare(strict_types=1);

namespace Sharp\Psr;

use Psr\Container\ContainerInterface;

/**
 * Spec section 32: PHP#'s container as a PSR-11 container, so any framework's container can ask it for a PHP# class.
 * The engine's Sharp\Container can't implement an interface Composer loads after the engine starts, so this adapter
 * does. It throws the container's own failures as PSR-11's, and a constructor's exception unchanged.
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

        try {
            return $this->container->get($id);
        } catch (\Sharp\ContainerError $error) {
            throw new ContainerException($error->getMessage(), previous: $error);
        }
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
