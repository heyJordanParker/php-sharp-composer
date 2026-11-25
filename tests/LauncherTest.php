<?php

declare(strict_types=1);

namespace PhpSharp\Composer\Tests;

use FilesystemIterator;
use PhpSharp\Composer\MagoBinary;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function chmod;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash_file;
use function implode;
use function is_dir;
use function mkdir;
use function proc_close;
use function proc_open;
use function rmdir;
use function stream_get_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * One vendor/ serves every platform it is mounted on: composer/bin/mago runs the binary of the platform it starts on,
 * and downloads a missing platform's binary, checked against the SHA-256 the package pins, before it runs it.
 */
final class LauncherTest extends TestCase
{
    private const LINUX = 'x86_64-unknown-linux-gnu';

    private string $directory = '';

    private string $package = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('mago-launcher-', true);
        $this->package = "{$this->directory}/package";
        mkdir("{$this->package}/composer/bin/aarch64-apple-darwin", 0o755, true);
        mkdir("{$this->directory}/stubs");
        mkdir("{$this->directory}/release/mago-9.9.9-" . self::LINUX, 0o755, true);
        copy(dirname(__DIR__, 2) . '/mago/composer/bin/mago', "{$this->package}/composer/bin/mago");
        chmod("{$this->package}/composer/bin/mago", 0o755);
        file_put_contents("{$this->package}/composer/bin/version", "9.9.9\n");
        $this->executable("{$this->package}/composer/bin/aarch64-apple-darwin/mago", 'echo "darwin $*"');
        $this->executable("{$this->directory}/release/mago-9.9.9-" . self::LINUX . '/mago', 'echo "linux $*"');
        $this->execute(['tar', '-czf', "{$this->directory}/archive.tar.gz", '-C', "{$this->directory}/release", 'mago-9.9.9-' . self::LINUX], []);
        $this->pin((string) hash_file('sha256', "{$this->directory}/archive.tar.gz"));
        $this->executable("{$this->directory}/stubs/ldd", 'echo "ldd (GNU libc) 2.36"');
        $this->executable(
            "{$this->directory}/stubs/curl",
            "while [ \$# -gt 1 ]; do case \$1 in -o) out=\$2; shift ;; esac; shift; done\n"
            . "echo \"\$1\" > {$this->directory}/downloaded\ncp {$this->directory}/archive.tar.gz \"\$out\"",
        );
    }

    protected function tearDown(): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->directory);
    }

    public function testThePlatformTheBinaryWasInstalledForRunsItWithEveryArgument(): void
    {
        [$status, $stdout] = $this->launch('Darwin arm64');

        self::assertSame(0, $status);
        self::assertSame("darwin one two\n", $stdout);
        self::assertFileDoesNotExist("{$this->directory}/downloaded");
    }

    public function testAnotherPlatformDownloadsItsBinaryWhoseSha256MatchesAndRunsIt(): void
    {
        [$status, $stdout, $stderr] = $this->launch('Linux x86_64');

        self::assertSame(0, $status, $stderr);
        self::assertSame("linux one two\n", $stdout);
        self::assertSame(
            'https://github.com/heyJordanParker/php-sharp/releases/download/v9.9.9/mago-9.9.9-' . self::LINUX . ".tar.gz\n",
            file_get_contents("{$this->directory}/downloaded"),
        );
        self::assertFileExists("{$this->package}/composer/bin/" . self::LINUX . '/mago');
        self::assertSame([], glob("{$this->package}/composer/bin/.download-*"));
    }

    public function testADownloadWhoseSha256DiffersExitsNamingBothHashesAndInstallsNothing(): void
    {
        $pinned = str_repeat('0', 64);
        $this->pin($pinned);

        [$status, $stdout, $stderr] = $this->launch('Linux x86_64');

        self::assertSame(1, $status);
        self::assertSame('', $stdout);
        self::assertStringContainsString((string) hash_file('sha256', "{$this->directory}/archive.tar.gz"), $stderr);
        self::assertStringContainsString($pinned, $stderr);
        self::assertFalse(is_dir("{$this->package}/composer/bin/" . self::LINUX));
        self::assertSame([], glob("{$this->package}/composer/bin/.download-*"));
    }

    public function testTheLauncherRunsTheBinaryAtTheTripleThePluginInstallsForThisMachine(): void
    {
        $triple = MagoBinary::triple();
        if (!is_dir("{$this->package}/composer/bin/{$triple}")) {
            mkdir("{$this->package}/composer/bin/{$triple}", 0o755, true);
        }
        $this->executable("{$this->package}/composer/bin/{$triple}/mago", 'echo "this machine $*"');

        [$status, $stdout] = $this->execute(["{$this->package}/composer/bin/mago", 'one', 'two'], []);

        self::assertSame(0, $status);
        self::assertSame("this machine one two\n", $stdout);
    }

    private function pin(string $checksum): void
    {
        file_put_contents("{$this->package}/composer/sha256sums.txt", "{$checksum}  mago-9.9.9-" . self::LINUX . ".tar.gz\n");
    }

    private function executable(string $path, string $body): void
    {
        file_put_contents($path, "#!/bin/sh\n{$body}\n");
        chmod($path, 0o755);
    }

    /** @return array{int, string, string} The launcher's exit status, standard output and standard error. */
    private function launch(string $uname): array
    {
        $this->executable("{$this->directory}/stubs/uname", "echo '{$uname}'");

        return $this->execute(
            ["{$this->package}/composer/bin/mago", 'one', 'two'],
            ['PATH' => implode(':', ["{$this->directory}/stubs", '/usr/bin', '/bin'])],
        );
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment The environment, or none for this process's.
     *
     * @return array{int, string, string}
     */
    private function execute(array $command, array $environment): array
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment === [] ? null : $environment);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
