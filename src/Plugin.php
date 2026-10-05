<?php

declare(strict_types=1);

namespace PhpSharp\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => 'dumpClassMap'];
    }

    public function dumpClassMap(Event $event): void
    {
        $composerDir = $event->getComposer()->getConfig()->get('vendor-dir') . '/composer';
        $classMapFile = $composerDir . '/autoload_sharp.php';
        $filesystem = new Filesystem();

        $classes = [];
        if ($event->getFlags()['optimize']) {
            foreach (require $composerDir . '/autoload_psr4.php' as $namespace => $dirs) {
                foreach (array_filter($dirs, 'is_dir') as $dir) {
                    $dir = rtrim($dir, '/');
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                    foreach ($files as $file) {
                        if ($file->getExtension() !== 'sharp') {
                            continue;
                        }

                        $class = $namespace . strtr(substr($file->getPathname(), strlen($dir) + 1, -strlen('.sharp')), '/', '\\');
                        if ($class === self::declaredClass($file)) {
                            $classes[$class] = $file->getPathname();
                        }
                    }
                }
            }
        }
        ksort($classes);

        $entries = '';
        foreach ($classes as $class => $path) {
            $entries .= '    ' . var_export($class, true) . ' => ' . $filesystem->findShortestPathCode($classMapFile, $path, false, true) . ",\n";
        }
        $filesystem->filePutContentsIfModified($classMapFile, "<?php\n\nreturn [\n$entries];\n");
    }

    private static function declaredClass(SplFileInfo $file): string
    {
        $namespace = preg_match('{^namespace\s+([\w.]+)\s*;}m', (string) file_get_contents($file->getPathname()), $match) === 1
            ? strtr($match[1], '.', '\\') . '\\'
            : '';

        return $namespace . $file->getBasename('.sharp');
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
