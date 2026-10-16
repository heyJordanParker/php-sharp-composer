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
                        if ($class === ($declared[0] ?? null)) {
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
     * The classes a .sharp file declares: none when it declares no type named like it, spec section 27, as a module's
     * or an extern declaration's file, or else the one its namespace and name give, then, when it is an enum with a case
     * that carries data, the class each case runs as, named in the enum's namespace, as App\PaymentResult\Paid, or, when
     * it is an interface, the trait of its default bodies, as App\HasDesign\Defaults, spec section 22. An interface
     * without a default body has no such trait, and its entry finds the interface's file, which declares none.
     *
     * @return list<string>
     */
    private static function declaredClasses(SplFileInfo $file): array
    {
        $code = (string) file_get_contents($file->getPathname());
        $name = $file->getBasename('.sharp');
        if (preg_match('{^(?:\w+\s+)*(class|interface|enum|struct|event)\s+' . preg_quote($name) . '\b}m', $code, $type) !== 1) {
            return [];
        }
        $namespace = preg_match('{^namespace\s+([\w.]+)\s*;}m', $code, $match) === 1 ? strtr($match[1], '.', '\\') . '\\' : '';
        $class = $namespace . $name;
        if ($type[1] === 'interface') {
            return [$class, "$class\\Defaults"];
        }
        preg_match_all('{^\s*case\s+(\w+)\s*(\(?)}m', $code, $cases);
        if ($type[1] !== 'enum' || !in_array('(', $cases[2], true)) {
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
