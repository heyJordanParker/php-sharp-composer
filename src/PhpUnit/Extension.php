<?php

declare(strict_types=1);

namespace Sharp\PhpUnit;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Runner\Extension\Extension as PhpUnitExtension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Spec section 32: while a test runs, the container uses the module its test file declares, and builds every service
 * again for each test. A project registers it in phpunit.xml, as <bootstrap class="Sharp\PhpUnit\Extension"/>.
 */
final class Extension implements PhpUnitExtension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(
            new class implements PreparationStartedSubscriber {
                public function notify(PreparationStarted $event): void
                {
                    $test = $event->test();
                    \Sharp\Internal\startTest($test instanceof TestMethod ? $test->className() : '');
                }
            },
            new class implements FinishedSubscriber {
                public function notify(Finished $event): void
                {
                    \Sharp\Internal\endTest();
                }
            },
        );
    }
}
