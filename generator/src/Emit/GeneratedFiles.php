<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Closure;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * Writes generated files, removes generated files that are no longer produced, and checks a
 * checkout for drift. Everything is formatted with Pint, exactly as committed code is.
 */
final readonly class GeneratedFiles
{
    private const SCANNED = ['src', 'tests/Contract/Generated'];

    /** @var Closure(string, list<string>): void */
    private Closure $format;

    private string $scratch;

    /**
     * @param  array<string, string>  $files  Contents by path relative to the root.
     * @param  (Closure(string, list<string>): void)|null  $format  Formats the given files in place; runs Pint by default.
     * @param  string|null  $scratch  Where check() renders for comparison; removed afterwards.
     */
    public function __construct(
        private string $root,
        private array $files,
        ?Closure $format = null,
        ?string $scratch = null,
    ) {
        $this->format = $format ?? self::pint(...);
        // Any unused directory works; its name is not observable.
        $this->scratch = $scratch ?? sys_get_temp_dir() . '/productive-generate-' . bin2hex(random_bytes(6)); // @pest-mutate-ignore
    }

    public function write(): void
    {
        foreach ($this->stale($this->root) as $path) {
            unlink($this->root . '/' . $path);
        }

        $this->render($this->root);
    }

    /**
     * @return list<string>  One message per file that is missing, different or no longer generated.
     */
    public function check(): array
    {
        $temporary = $this->scratch;
        $this->render($temporary);
        $problems = [];

        foreach (array_keys($this->files) as $path) {
            $problems[] = self::compare($path, $this->root . '/' . $path, $temporary . '/' . $path);
        }

        foreach ($this->stale($this->root) as $path) {
            $problems[] = $path . ' is generated but no longer produced.';
        }

        self::remove($temporary);

        return array_values(array_filter($problems));
    }

    private function render(string $root): void
    {
        foreach ($this->files as $path => $contents) {
            $file = $root . '/' . $path;

            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0o777, true);
            }

            file_put_contents($file, $contents);
        }

        ($this->format)($root, array_keys($this->files));
    }

    /**
     * Files that carry the generated marker but are not produced anymore.
     *
     * @return list<string>
     */
    private function stale(string $root): array
    {
        $stale = [];

        foreach (self::SCANNED as $directory) {
            foreach (self::phpFiles($root . '/' . $directory) as $file) {
                $stale[] = $this->isStale($file) ? substr($file, strlen($root) + 1) : null;
            }
        }

        $stale = array_values(array_filter($stale));
        sort($stale);

        return $stale;
    }

    private function isStale(string $file): bool
    {
        $path = substr($file, strlen($this->root) + 1);

        return ! isset($this->files[$path]) && str_contains((string) file_get_contents($file), PhpFile::MARKER);
    }

    private static function compare(string $path, string $committed, string $expected): ?string
    {
        return match (true) {
            ! is_file($committed) => $path . ' is missing.',
            file_get_contents($committed) !== file_get_contents($expected) => $path . ' differs from the generator output.',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $files = [];

        foreach (self::entries($directory) as $file) {
            $files[] = $file instanceof SplFileInfo && $file->getExtension() === 'php' ? $file->getPathname() : null;
        }

        return array_values(array_filter($files));
    }

    /**
     * @return iterable<mixed>
     */
    private static function entries(string $directory): iterable
    {
        return is_dir($directory) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) : [];
    }

    private static function remove(string $directory): void
    {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            if ($entry instanceof SplFileInfo) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * @param  list<string>  $paths
     */
    private static function pint(string $root, array $paths): void
    {
        $project = dirname(__DIR__, 3);
        $process = new Process([
            PHP_BINARY,
            $project . '/vendor/bin/pint',
            '--config=' . $project . '/pint.json',
            ...array_map(fn(string $path): string => $root . '/' . $path, $paths),
        ]);

        if ($process->run() !== 0) {
            // Diagnostic text only: the order of the two streams is not observable.
            throw new RuntimeException("Pint failed:\n" . $process->getOutput() . $process->getErrorOutput()); // @pest-mutate-ignore
        }
    }
}
