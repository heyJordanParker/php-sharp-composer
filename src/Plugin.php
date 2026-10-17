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
        // Composer runs on any engine, so a library the engine was not built for is an error line, and Composer goes on.
        // An update runs the new version's plugin here, which Composer's own autoloader can't: it skips the new
        // version's autoload.php, whose files identifier stays the same.
        if (function_exists('Sharp\Internal\requireNative')) {
            ['fingerprint' => $fingerprint, 'bodies' => $bodies] = require dirname(__DIR__) . '/native.php';
            $installed = $composer->getRepositoryManager()->getLocalRepository()->findPackage('heyjordanparker/php-sharp-composer', '*');
            try {
                \Sharp\Internal\requireNative($fingerprint, $bodies, $installed?->getPrettyVersion() ?? '');
            } catch (\Error $refusal) {
                $event->getIO()->writeError('<error>' . $refusal->getMessage() . '</error>');
            }
        }

        // `composer run-script post-autoload-dump` runs the event with no flags and dumps no autoloader, so the class map
        // stays as the last dump wrote it.
        if (!array_key_exists('optimize', $event->getFlags())) {
            return;
        }

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
     * A type line may start with attributes, as `[Attribute] public class Marker`, then a type's modifiers. An `extern`
     * line declares no type, so `extern event Failed;` in Failed.sharp lists nothing.
     *
     * @return list<string>
     */
    private static function declaredClasses(SplFileInfo $file): array
    {
        $code = (string) file_get_contents($file->getPathname());
        $name = $file->getBasename('.sharp');
        $typeLine = '{^(?:\[.*\]\s*)*(?:(?:public|private|protected|internal|abstract|final|sealed|static|readonly|foreign)\s+)*(class|interface|enum|struct|event)\s+' . preg_quote($name) . '\b}m';
        if (preg_match($typeLine, $code, $type) !== 1) {
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
