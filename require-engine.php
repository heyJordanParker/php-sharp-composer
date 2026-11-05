<?php

declare(strict_types=1);

// A test run's bootstrap: phpunit.xml names it as bootstrap="vendor/heyjordanparker/php-sharp-composer/require-engine.php".
// A test runner includes each .sharp test file it lists itself, past this package's loader, and plain PHP prints a .sharp
// file's source as output. PHPUnit, Pest and ParaTest load the bootstrap before any test file, in each child process
// too, so on plain PHP the run stops here with the engine's error, as the PHP file mago compile writes beside a class
// does. The runners have loaded vendor/autoload.php already, which on the engine checks the library's version. A runner
// that hasn't, as PHPUnit's PHAR, loads it here.
class_exists(Composer\Autoload\ClassLoader::class, false) || require dirname(__DIR__, 2) . '/autoload.php';

\function_exists('Sharp\Internal\requireNative')
    || throw new \Error(
        'This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.',
    );
