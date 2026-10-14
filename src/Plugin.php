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
        $composer = $event->getComposer();
        $composerDir = $composer->getConfig()->get('vendor-dir') . '/composer';
        $classMapFile = $composerDir . '/autoload_sharp.php';
        $filesystem = new Filesystem();

        $classes = [];
        if ($event->getFlags()['optimize']) {
            $generator = $composer->getAutoloadGenerator();
            $packageMap = $generator->buildPackageMap($composer->getInstallationManager(), $composer->getPackage(), $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages());
            $excluded = $generator->parseAutoloads($packageMap, $composer->getPackage())['exclude-from-classmap'];
            $exclusion = $excluded === [] ? null : '{(' . implode('|', $excluded) . ')}';

            foreach (require $composerDir . '/autoload_psr4.php' as $namespace => $dirs) {
                foreach (array_filter($dirs, 'is_dir') as $dir) {
                    $dir = rtrim($dir, '/');
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                    foreach ($files as $file) {
                        if ($file->getExtension() !== 'sharp' || ($exclusion !== null && preg_match($exclusion, strtr($file->getRealPath(), '\\', '/')) === 1)) {
                            continue;
                        }

                        $class = $namespace . strtr(substr($file->getPathname(), strlen($dir) + 1, -strlen('.sharp')), '/', '\\');
                        $declared = self::declaredClasses($file);
                        if ($class === $declared[0]) {
                            $classes += array_fill_keys($declared, $file->getPathname());
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

    /**
     * The classes a .sharp file declares: the one its namespace and name give, then, when it is an enum with a case
     * that carries data, the class each case runs as, named in the enum's namespace, as App\PaymentResult\Paid.
     *
     * @return non-empty-list<string>
     */
    private static function declaredClasses(SplFileInfo $file): array
    {
        $code = (string) file_get_contents($file->getPathname());
        $namespace = preg_match('{^namespace\s+([\w.]+)\s*;}m', $code, $match) === 1 ? strtr($match[1], '.', '\\') . '\\' : '';
        $class = $namespace . $file->getBasename('.sharp');
        preg_match_all('{^\s*case\s+(\w+)\s*(\(?)}m', $code, $cases);
        if (!in_array('(', $cases[2], true)) {
            return [$class];
        }

        return [$class, ...array_map(static fn (string $case): string => "$class\\$case", $cases[1])];
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
