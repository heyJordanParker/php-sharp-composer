<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Composer\Plugin\PluginManager;

$requireEngine = static function (): void {
    static $required = false;
    if ($required) {
        return;
    }
    if (!function_exists('Sharp\Internal\requireNative')) {
        throw new Error('This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.');
    }
    $package = 'heyjordanparker/php-sharp-composer';
    $version = InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : 'unknown';
    ['fingerprint' => $fingerprint, 'bodies' => $bodies] = require __DIR__ . '/native.php';
    \Sharp\Internal\requireNative($fingerprint, $bodies, preg_replace('/^v(?=[0-9])/', '', $version));
    $required = true;
};

// The container reads the library's module at its first lookup, so naming it reads nothing yet. Composer's own process
// names it too, where a script reaches a PHP# class through the PHP file mago compile writes beside it, which Composer's
// loader includes without this one. An engine without the function is older than the library, which requireNative
// refuses. Composer's process runs on any engine, and once requireNative refuses, the engine refuses each .sharp file.
if (function_exists('Sharp\Internal\readModule')) {
    \Sharp\Internal\readModule(__DIR__ . '/library/Sharp/Module.sharp');
}
if (function_exists('Sharp\Internal\requireNative')) {
    try {
        $requireEngine();
    } catch (Error $refusal) {
        class_exists(PluginManager::class, false) || throw $refusal;
    }
}

$classMap = [];
foreach (array_keys(ClassLoader::getRegisteredLoaders()) as $vendorDir) {
    if (is_file($file = $vendorDir . '/composer/autoload_sharp.php')) {
        $classMap += require $file;
    }
}

// A file declares more than its own class: an interface's file declares the trait of its default bodies, spec section
// 22, and an enum's file the class of each case that carries data. A name the file already read lacks, as the Defaults
// trait of an interface without a default body, reads it no second time.
$includeFile = Closure::bind(static function (string $file) use ($requireEngine): void {
    $requireEngine();
    include_once $file;
}, null, null);

$findFile = static function (string $class): ?string {
    $path = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.sharp';

    foreach (ClassLoader::getRegisteredLoaders() as $loader) {
        if ($loader->isClassMapAuthoritative()) {
            continue;
        }

        $files = [];
        $prefixes = $loader->getPrefixesPsr4();
        $namespace = $class;
        while (false !== $end = strrpos($namespace, '\\')) {
            $namespace = substr($namespace, 0, $end);
            foreach ($prefixes[$namespace . '\\'] ?? [] as $dir) {
                $files[] = $dir . DIRECTORY_SEPARATOR . substr($path, $end + 1);
            }
        }
        foreach ($loader->getFallbackDirsPsr4() as $dir) {
            $files[] = $dir . DIRECTORY_SEPARATOR . $path;
        }

        foreach ($files as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }
    }

    return null;
};

spl_autoload_register(static function (string $class) use ($includeFile, $classMap, $findFile): void {
    static $missing = [];
    if (isset($missing[$class])) {
        return;
    }

    if (isset($classMap[$class])) {
        $includeFile($classMap[$class]);

        return;
    }

    // A case of an enum that carries data runs as a class its enum's file declares, as App\PaymentResult\Paid in
    // App/PaymentResult.sharp. That file is read only when no file has the class's own name and the enum is not
    // loaded yet.
    $enum = substr($class, 0, (int) strrpos($class, '\\'));
    $file = $findFile($class) ?? ($enum !== '' && !class_exists($enum, false) ? $findFile($enum) : null);
    if ($file !== null) {
        $includeFile($file);

        return;
    }

    $missing[$class] = true;
});
