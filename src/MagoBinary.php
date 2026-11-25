<?php

declare(strict_types=1);

namespace PhpSharp\Composer;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use RuntimeException;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_FILE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_NOPROGRESS;
use const CURLOPT_PROGRESSFUNCTION;
use const CURLOPT_UNRESTRICTED_AUTH;
use const CURLOPT_USERAGENT;
use const FILE_IGNORE_NEW_LINES;
use const STDERR;

/**
 * Installs the mago binary of a heyjordanparker/mago-sharp release at the package's `composer/bin/<triple>/mago`, for the
 * target triple of the platform Composer runs on, and writes the release's version to `composer/bin/version`. The
 * package's `composer/bin/mago`, the file its `bin` names, is a shell launcher that runs the binary of the platform it
 * starts on, and downloads it with that version when it is missing, so one `vendor/` serves every platform it is mounted
 * on and a start of mago runs no PHP. The archive's SHA-256 is the one the package version pins in
 * `composer/sha256sums.txt`.
 *
 * @internal
 */
final class MagoBinary
{
    /**
     * Writes release `version` to the package's `composer/bin/version`, and installs that release's binary for this
     * platform in the package at `package` unless it is there.
     *
     * @throws RuntimeException If the platform has no release binary, or the download, checksum or extraction fails.
     */
    public static function install(string $version, string $package, IOInterface $io): void
    {
        $bin = "{$package}/composer/bin";
        file_put_contents("{$bin}/version", "{$version}\n");
        $triple = self::triple();
        $binary = "{$bin}/{$triple}/mago";
        if (is_file($binary)) {
            return;
        }

        $name = "mago-{$version}-{$triple}";
        $checksum = self::archiveChecksum("{$package}/composer/sha256sums.txt", "{$name}.tar.gz");
        $work = "{$bin}/.install";
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($work);
        $filesystem->ensureDirectoryExists("{$bin}/{$triple}");
        try {
            $io->writeError("Downloading mago {$version} for {$triple}...");
            $archive = "{$work}/{$name}.tar.gz";
            self::download(
                "https://github.com/heyJordanParker/php-sharp/releases/download/v{$version}/{$name}.tar.gz",
                $archive,
                $version,
            );
            self::extractArchive($archive, $checksum, $work);
            if (!rename("{$work}/{$name}/mago", $binary)) {
                throw new RuntimeException("The release archive {$name}.tar.gz holds no {$name}/mago.");
            }
            chmod($binary, 0o755);
        } finally {
            $filesystem->removeDirectory($work);
        }
    }

    /**
     * The Rust target triple of this platform's release binary, libc suffix included, as `composer/bin/mago` maps
     * `uname` to it.
     *
     * @throws RuntimeException If the platform has no release binary.
     */
    public static function triple(): string
    {
        $architecture = self::architecture();
        ['os' => $os, 'vendor' => $vendor, 'suffix' => $suffix] = self::platform($architecture);

        return $suffix === '' ? "{$architecture}-{$vendor}-{$os}" : "{$architecture}-{$vendor}-{$os}-{$suffix}";
    }

    /**
     * The CPU architecture, as the Rust target names it: only x86_64 and aarch64 have release binaries.
     *
     * @throws RuntimeException If the architecture is not supported.
     */
    private static function architecture(): string
    {
        $raw = strtolower(php_uname('m'));

        return match ($raw) {
            'x86_64', 'amd64' => 'x86_64',
            'arm64', 'aarch64' => 'aarch64',
            default => throw new RuntimeException(
                "No mago release has a binary for {$raw}: it builds for arm64 and x86_64. "
                . 'Compile mago from source: https://github.com/heyJordanParker/php-sharp/tree/master/sharp/mago',
            ),
        };
    }

    /**
     * The C library of Linux, from `ldd --version`: glibc when `ldd` is not found, as in a minimal glibc container,
     * since every released Linux binary is glibc's.
     */
    private static function linuxLibc(): string
    {
        if (trim((string) shell_exec('command -v ldd 2>/dev/null')) === '') {
            return 'gnu';
        }

        return str_contains((string) shell_exec('ldd --version 2>&1'), 'musl') ? 'musl' : 'gnu';
    }

    /**
     * The target's OS, vendor and ABI suffix. A release builds mago for macOS on Apple silicon and for glibc Linux on
     * x86_64 and aarch64, and every other platform is refused with the reason.
     *
     * @throws RuntimeException If the OS and architecture have no release binary.
     *
     * @return array{os: string, vendor: string, suffix: string}
     */
    private static function platform(string $architecture): array
    {
        $os = strtolower(php_uname('s'));

        return match ($os) {
            'darwin' => match ($architecture) {
                'aarch64' => ['os' => 'darwin', 'vendor' => 'apple', 'suffix' => ''],
                default => throw new RuntimeException(
                    "No mago release has a binary for macOS on {$architecture}: it builds for macOS on Apple silicon. "
                    . 'Compile mago from source: https://github.com/heyJordanParker/php-sharp/tree/master/sharp/mago',
                ),
            },
            'linux' => match (self::linuxLibc()) {
                'gnu' => ['os' => 'linux', 'vendor' => 'unknown', 'suffix' => 'gnu'],
                default => throw new RuntimeException(
                    'No mago release has a binary for musl Linux, such as Alpine: it builds for glibc Linux. '
                    . 'Compile mago from source: https://github.com/heyJordanParker/php-sharp/tree/master/sharp/mago',
                ),
            },
            default => throw new RuntimeException(
                "No mago release has a binary for {$os}: it builds for macOS and Linux. "
                . 'Compile mago from source: https://github.com/heyJordanParker/php-sharp/tree/master/sharp/mago',
            ),
        };
    }

    /**
     * The GitHub token in `GITHUB_TOKEN`, then `GH_TOKEN`, as `mago self-update` reads them, so an authenticated
     * environment downloads past GitHub's anonymous rate limit.
     *
     * @return null|non-empty-string
     */
    private static function githubToken(): ?string
    {
        foreach (['GITHUB_TOKEN', 'GH_TOKEN'] as $variable) {
            $value = getenv($variable);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Downloads with the curl extension, and else with PHP's HTTP stream wrapper when `allow_url_fopen` is on.
     *
     * @throws RuntimeException If the download fails or no download method is available.
     */
    private static function download(string $url, string $destination, string $version): void
    {
        if (extension_loaded('curl')) {
            self::downloadWithCurl($url, $destination, $version);

            return;
        }

        if (ini_get('allow_url_fopen')) {
            self::downloadWithFopen($url, $destination, $version);

            return;
        }

        throw new RuntimeException(
            'Unable to download mago binary. Either install the PHP curl extension or set allow_url_fopen=1 in php.ini.',
        );
    }

    /**
     * Downloads with the curl extension, with a progress line when STDERR is a terminal, as curl and Composer show one.
     * A hook that captures STDERR gets no progress line, where each chunk would add one.
     *
     * @throws RuntimeException If the download fails or the server returns an error status.
     */
    public static function downloadWithCurl(string $url, string $destination, string $version): void
    {
        $showsProgress = stream_isatty(STDERR);
        $ch = curl_init($url);
        $fh = fopen($destination, 'w');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_NOPROGRESS, !$showsProgress);
        curl_setopt($ch, CURLOPT_USERAGENT, "mago-composer/{$version}");

        // The release download redirects to objects.githubusercontent.com. curl 7.58.0 and later send a custom
        // Authorization header only to the host the request starts at, unless CURLOPT_UNRESTRICTED_AUTH is set, so
        // the token never reaches the redirect's host.
        $token = self::githubToken();
        if ($token !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
            curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, false);
        }

        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function (mixed $_resource, int $dlSize, int $dlNow): int {
            if ($dlSize > 0) {
                $pct = (int) (($dlNow / $dlSize) * 100);
                $dlMb = number_format($dlNow / 1_048_576, 1);
                $totalMb = number_format($dlSize / 1_048_576, 1);
                fprintf(STDERR, "\r  %s / %s MB (%d%%)", $dlMb, $totalMb, $pct);
            }

            return 0;
        });

        $success = curl_exec($ch);
        /** @var int<100, 599> */
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        fclose($fh);

        if (!$success || $statusCode >= 400) {
            unlink($destination);

            throw new RuntimeException("Failed to download mago binary (HTTP {$statusCode}): {$error}\nURL: {$url}");
        }

        if ($showsProgress) {
            fprintf(STDERR, "\n");
        }
    }

    /**
     * Downloads with PHP's HTTP stream wrapper. The release download redirects to objects.githubusercontent.com, and
     * the wrapper sends a context's headers to every host it follows, so this follows each redirect itself, and only
     * the first request carries the GitHub token, as curl sends it.
     *
     * @throws RuntimeException If the download fails or redirects more than 20 times.
     */
    public static function downloadWithFopen(string $url, string $destination, string $version): void
    {
        $token = self::githubToken();
        for ($redirects = 0; $redirects <= 20; $redirects++) {
            $stream = self::openUrl($url, $redirects === 0 ? $token : null, $version);
            $location = self::redirectLocation($stream);
            if ($location === null) {
                file_put_contents($destination, $stream);
                fclose($stream);

                return;
            }

            fclose($stream);
            $url = $location;
        }

        throw new RuntimeException("Failed to download mago binary: it redirected more than 20 times.\nURL: {$url}");
    }

    /**
     * Opens a URL with PHP's HTTP stream wrapper, without following a redirect.
     *
     * @param null|non-empty-string $token The GitHub token to send, or null to send none.
     *
     * @throws RuntimeException If the request fails.
     *
     * @return resource
     */
    private static function openUrl(string $url, ?string $token, string $version): mixed
    {
        $headers = ["User-Agent: mago-composer/{$version}"];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'follow_location' => 0,
            ],
        ]);

        $stream = fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw new RuntimeException("Failed to download mago binary.\nURL: {$url}");
        }

        return $stream;
    }

    /**
     * The URL a response redirects to, or null for a response that is no redirect.
     *
     * @param resource $stream A stream `openUrl` opened.
     *
     * @throws RuntimeException If a redirect names no Location.
     */
    private static function redirectLocation(mixed $stream): ?string
    {
        /** @var list<string> $headers */
        $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        if (preg_match('{^HTTP/\S+ 3\d\d\b}', $headers[0] ?? '') !== 1) {
            return null;
        }

        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                return trim(substr($header, 9));
            }
        }

        throw new RuntimeException("The download answered {$headers[0]} with no Location.");
    }

    /**
     * The SHA-256 a heyjordanparker/mago-sharp release pins for one of its release's archives. release.yml writes
     * `composer/sha256sums.txt`, in `sha256sum`'s format, into the package version it publishes, so the package version
     * pins the exact binary. A hash read from the release itself would not catch a replaced asset.
     *
     * @throws RuntimeException If the file does not exist or pins no SHA-256 for the archive.
     *
     * @return string The archive's SHA-256, in lowercase hex.
     */
    public static function archiveChecksum(string $checksumFile, string $archiveName): string
    {
        $lines = file_exists($checksumFile) ? file($checksumFile, FILE_IGNORE_NEW_LINES) : false;
        if ($lines === false) {
            throw new RuntimeException(
                "{$checksumFile} does not exist, so no mago download can be verified. Only a heyjordanparker/mago-sharp "
                . 'release ships it: require a release version.',
            );
        }

        foreach ($lines as $line) {
            [$checksum, $name] = explode('  ', $line, 2) + ['', ''];
            if ($name === $archiveName) {
                return $checksum;
            }
        }

        throw new RuntimeException(
            "{$checksumFile} pins no SHA-256 for {$archiveName}, so its download cannot be verified.",
        );
    }

    /**
     * Extracts a `.tar.gz` archive with the system `tar`, if its SHA-256 is the one the release pins, and deletes the
     * archive either way. `tar` streams the archive, so a release of any size unpacks within PHP's memory limit, which
     * `PharData` does not: it holds the whole decompressed archive in memory.
     *
     * @throws RuntimeException If the archive's SHA-256 differs, or `tar` cannot start or extract it.
     */
    public static function extractArchive(string $archiveFile, string $checksum, string $destination): void
    {
        $actual = (string) hash_file('sha256', $archiveFile);
        if (!hash_equals($checksum, $actual)) {
            unlink($archiveFile);

            throw new RuntimeException(
                'The downloaded '
                . basename($archiveFile)
                . " has SHA-256 {$actual}, and heyjordanparker/mago-sharp pins {$checksum} for it, so it is not the "
                . 'archive the release built. It was deleted, and nothing was extracted.',
            );
        }

        $pipes = [];
        $process = proc_open(['tar', '-xzf', $archiveFile, '-C', $destination], [2 => ['pipe', 'w']], $pipes);
        $stderr = $pipes[2] ?? null;
        if (!is_resource($process) || !is_resource($stderr)) {
            unlink($archiveFile);

            throw new RuntimeException('Unable to start tar to extract ' . basename($archiveFile) . '.');
        }

        $error = (string) stream_get_contents($stderr);
        fclose($stderr);
        $exitCode = proc_close($process);
        unlink($archiveFile);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'tar could not extract ' . basename($archiveFile) . " (exit code {$exitCode}): " . trim($error),
            );
        }
    }
}
