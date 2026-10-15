<?php

declare(strict_types=1);

namespace Sharp\Psr;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Spec section 15: PHP#'s listeners as a PSR-14 listener provider, so a plain PHP library whose dispatcher follows
 * PSR-14 runs every `on` method and scoped listener of the events it dispatches. The engine's listeners can't implement
 * an interface Composer loads after the engine starts, so this adapter does. Each listener runs on a new object of its
 * class, as for `emit`.
 */
final class ListenerProvider implements ListenerProviderInterface
{
    /** @return list<\Closure(object): void> */
    public function getListenersForEvent(object $event): iterable
    {
        return \Sharp\Internal\listenersFor($event);
    }
}
