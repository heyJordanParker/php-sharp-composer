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

if (!class_exists(PluginManager::class, false)) {
    $requireEngine();
}

$classMap = [];
foreach (array_keys(ClassLoader::getRegisteredLoaders()) as $vendorDir) {
    if (is_file($file = $vendorDir . '/composer/autoload_sharp.php')) {
        $classMap += require $file;
    }
}

$includeFile = Closure::bind(static function (string $file) use ($requireEngine): void {
    $requireEngine();
    include $file;
}, null, null);

spl_autoload_register(static function (string $class) use ($includeFile, $classMap): void {
    static $missing = [];
    if (isset($missing[$class])) {
        return;
    }

    if (isset($classMap[$class])) {
        $includeFile($classMap[$class]);

        return;
    }

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
                $includeFile($file);

                return;
            }
        }
    }

    $missing[$class] = true;
});
