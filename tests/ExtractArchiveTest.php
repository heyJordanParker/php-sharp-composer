<?php

declare(strict_types=1);

namespace PhpSharp\Composer\Tests;

use FilesystemIterator;
use PhpSharp\Composer\MagoBinary;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function fclose;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function fopen;
use function fwrite;
use function hash;
use function hash_file;
use function mkdir;
use function proc_close;
use function proc_open;
use function rmdir;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

use const PHP_BINARY;

/**
 * The plugin installs the `mago` it downloads from the GitHub release, so it extracts an archive only when the archive's
 * SHA-256 is the one the heyjordanparker/mago-sharp release pins in composer/sha256sums.txt.
 */
final class ExtractArchiveTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('mago-extract-', true);
        mkdir("{$this->directory}/release", 0o755, true);
        mkdir("{$this->directory}/mago-1.0.0-test");
    }

    protected function tearDown(): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($this->directory);
    }

    public function testAnArchiveWithTheSha256TheReleasePinsInstalls(): void
    {
        file_put_contents("{$this->directory}/mago-1.0.0-test/mago", 'the mago binary');
        $archive = $this->archive();

        MagoBinary::extractArchive($archive, (string) hash_file('sha256', $archive), "{$this->directory}/release");

        self::assertSame('the mago binary', file_get_contents("{$this->directory}/release/mago-1.0.0-test/mago"));
        self::assertFileDoesNotExist($archive);
    }

    public function testAnArchiveLargerThanPhpsDefaultMemoryLimitInstalls(): void
    {
        $binary = fopen("{$this->directory}/mago-1.0.0-test/mago", 'wb');
        for ($megabyte = 0; $megabyte < 200; $megabyte++) {
            fwrite($binary, str_repeat("\0", 1_048_576));
        }
        fclose($binary);
        $archive = $this->archive();

        $log = "{$this->directory}/php.log";
        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                '-d',
                'memory_limit=128M',
                '-r',
                'require '
                    . var_export(dirname(__DIR__, 2) . '/build/php-sharp-composer/vendor/autoload.php', true)
                    . ';'
                    . 'PhpSharp\\Composer\\MagoBinary::extractArchive('
                    . var_export($archive, true)
                    . ', '
                    . var_export((string) hash_file('sha256', $archive), true)
                    . ', '
                    . var_export("{$this->directory}/release", true)
                    . ');',
            ],
            [2 => ['file', $log, 'w']],
            $pipes,
        );

        self::assertIsResource($process);
        self::assertSame(0, proc_close($process), (string) file_get_contents($log));
        self::assertSame(209_715_200, filesize("{$this->directory}/release/mago-1.0.0-test/mago"));
        self::assertFileDoesNotExist($archive);
    }

    public function testAnArchiveWithAnotherSha256IsRefusedWithBothHashesAndNothingIsExtracted(): void
    {
        file_put_contents("{$this->directory}/mago-1.0.0-test/mago", 'the mago binary');
        $archive = $this->archive();
        $actual = hash_file('sha256', $archive);
        $pinned = hash('sha256', 'the archive the release built');

        try {
            MagoBinary::extractArchive($archive, $pinned, "{$this->directory}/release");
            self::fail('A tampered archive was extracted.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                "The downloaded mago-1.0.0-test.tar.gz has SHA-256 {$actual}, and heyjordanparker/mago-sharp pins "
                . "{$pinned} for it, so it is not the archive the release built. It was deleted, and nothing was "
                . 'extracted.',
                $exception->getMessage(),
            );
        }

        self::assertFalse(file_exists("{$this->directory}/release/mago-1.0.0-test"));
        self::assertFileDoesNotExist($archive);
    }

    public function testAnArchiveTarCannotReadIsRefusedWithTarsErrorAndDeleted(): void
    {
        $archive = "{$this->directory}/mago-1.0.0-test.tar.gz";
        file_put_contents($archive, 'not a gzip stream');

        try {
            MagoBinary::extractArchive($archive, (string) hash_file('sha256', $archive), "{$this->directory}/release");
            self::fail('An archive tar cannot read was extracted.');
        } catch (RuntimeException $exception) {
            self::assertMatchesRegularExpression(
                '/^tar could not extract mago-1\.0\.0-test\.tar\.gz \(exit code [1-9]\d*\): \S/',
                $exception->getMessage(),
            );
        }

        self::assertFileDoesNotExist($archive);
    }

    public function testTheChecksumIsTheOneTheReleaseFileNamesForTheArchive(): void
    {
        $pinned = hash('sha256', 'linux');
        file_put_contents(
            "{$this->directory}/sha256sums.txt",
            hash('sha256', 'darwin')
            . "  mago-1.0.0-aarch64-apple-darwin.tar.gz\n"
            . "{$pinned}  mago-1.0.0-x86_64-unknown-linux-gnu.tar.gz\n",
        );

        self::assertSame($pinned, MagoBinary::archiveChecksum(
            "{$this->directory}/sha256sums.txt",
            'mago-1.0.0-x86_64-unknown-linux-gnu.tar.gz',
        ));
    }

    public function testAnArchiveTheReleaseFileDoesNotNameIsRefused(): void
    {
        file_put_contents(
            "{$this->directory}/sha256sums.txt",
            hash('sha256', 'darwin') . "  mago-1.0.0-aarch64-apple-darwin.tar.gz\n",
        );

        $this->expectExceptionObject(
            new RuntimeException(
                "{$this->directory}/sha256sums.txt pins no SHA-256 for mago-1.0.0-x86_64-unknown-linux-gnu.tar.gz, so "
                . 'its download cannot be verified.',
            ),
        );

        MagoBinary::archiveChecksum("{$this->directory}/sha256sums.txt", 'mago-1.0.0-x86_64-unknown-linux-gnu.tar.gz');
    }

    public function testAPackageWithoutTheReleaseFileIsRefused(): void
    {
        $this->expectExceptionObject(
            new RuntimeException(
                "{$this->directory}/sha256sums.txt does not exist, so no mago download can be verified. Only a "
                . 'heyjordanparker/mago-sharp release ships it: require a release version.',
            ),
        );

        MagoBinary::archiveChecksum("{$this->directory}/sha256sums.txt", 'mago-1.0.0-x86_64-unknown-linux-gnu.tar.gz');
    }

    /**
     * A release archive as release.yml packs it with the system `tar`: the `mago-1.0.0-test` directory, holding the
     * binary the test wrote. The directory is deleted, so only the archive's extraction can bring it back.
     */
    private function archive(): string
    {
        $archive = "{$this->directory}/mago-1.0.0-test.tar.gz";
        $log = "{$this->directory}/tar.log";
        $pipes = [];
        $process = proc_open(
            ['tar', '-czf', $archive, '-C', $this->directory, 'mago-1.0.0-test'],
            [2 => ['file', $log, 'w']],
            $pipes,
        );

        self::assertIsResource($process);
        self::assertSame(0, proc_close($process), (string) file_get_contents($log));
        unlink("{$this->directory}/mago-1.0.0-test/mago");
        rmdir("{$this->directory}/mago-1.0.0-test");

        return $archive;
    }
}
