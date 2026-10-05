<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
    if (is_file($classMap = $vendorDir . '/composer/autoload_sharp.php')) {
        $loader->addClassMap(require $classMap);
    }
}

spl_autoload_register(static function (string $class): void {
    $path = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.sharp';

    foreach (ClassLoader::getRegisteredLoaders() as $loader) {
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
                include $file;

                return;
            }
        }
    }
});
