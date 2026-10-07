<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$library = [];
foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
    if (is_file($classMap = $vendorDir . '/composer/autoload_sharp.php')) {
        $classes = require $classMap;
        $own = array_filter($classes, static fn (string $class): bool => str_starts_with($class, 'Sharp\\'), ARRAY_FILTER_USE_KEY);
        $loader->addClassMap(array_diff_key($classes, $own));
        $library += $own;
    }
}

$includeFile = Closure::bind(static function (string $file): void {
    include $file;
}, null, null);

$includeClass = static function (string $class, string $file) use ($includeFile): void {
    static $native = false;
    if (!$native && str_starts_with($class, 'Sharp\\')) {
        if (!function_exists('Sharp\Internal\requireNative')) {
            throw new Error('This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.');
        }
        \Sharp\Internal\requireNative(require __DIR__ . '/native.php');
        $native = true;
    }
    $includeFile($file);
};

spl_autoload_register(static function (string $class) use ($includeClass, $library): void {
    static $missing = [];
    if (isset($missing[$class])) {
        return;
    }

    if (isset($library[$class])) {
        $includeClass($class, $library[$class]);

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
                $includeClass($class, $file);

                return;
            }
        }
    }

    $missing[$class] = true;
});
