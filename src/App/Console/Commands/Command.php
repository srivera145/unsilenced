<?php

namespace Keel\App\Console\Commands;

/**
 * Shared plumbing for database/console.php commands: output that tests can
 * capture, and argv parsing into positional arguments and --options.
 */
abstract class Command
{
    /** @var callable(string): void */
    private $output;

    /** @var callable(string): void */
    private $errorOutput;

    public function __construct(?callable $output = null, ?callable $errorOutput = null)
    {
        $this->output = $output ?? static function (string $line): void {
            fwrite(STDOUT, $line . "\n");
        };

        $this->errorOutput = $errorOutput ?? static function (string $line): void {
            fwrite(STDERR, $line . "\n");
        };
    }

    /**
     * @param list<string> $arguments The command's own arguments (after the command name).
     * @return int Process exit code.
     */
    abstract public function handle(array $arguments): int;

    abstract public static function usage(): string;

    protected function line(string $line = ''): void
    {
        ($this->output)($line);
    }

    protected function fail(string $message): int
    {
        foreach (explode("\n", $message) as $line) {
            ($this->errorOutput)($line);
        }

        return 1;
    }

    /**
     * @param list<string> $arguments
     * @return array{0: list<string>, 1: array<string, string|true>}
     */
    protected function parse(array $arguments): array
    {
        $positional = [];
        $options = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--')) {
                $option = substr($argument, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    $options[$name] = $value;
                } else {
                    $options[$option] = true;
                }
                continue;
            }

            $positional[] = $argument;
        }

        return [$positional, $options];
    }

    protected function resolvePath(string $path): ?string
    {
        $candidates = [$path, getcwd() . DIRECTORY_SEPARATOR . $path, dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . $path];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $real = realpath($candidate);

                return $real !== false ? $real : $candidate;
            }
        }

        return null;
    }

    protected function printHeaders(\Keel\App\Support\CsvFile $csv): void
    {
        $this->line('Headers in this file:');
        foreach ($csv->headers() as $position => $header) {
            $this->line(sprintf('  %3d  %s', $position + 1, $header));
        }
    }

    /** Print the outcome of a run that was processed with --now. */
    protected function report(int $runId): int
    {
        $run = \Keel\App\Models\ImportRun::find($runId) ?? [];

        if (($run['status'] ?? '') !== 'complete') {
            return $this->fail('Import failed: ' . ($run['message'] ?? 'unknown error'));
        }

        $this->line((string) $run['message']);
        foreach (array_slice(\Keel\App\Models\ImportRun::errors($run), 0, 10) as $error) {
            $this->line('  ' . $error);
        }

        return 0;
    }
}
