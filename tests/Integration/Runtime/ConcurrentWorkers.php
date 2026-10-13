<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

/**
 * Runs one public command per child process, all released at the same instant, each on its own
 * MySQL connection, and returns the printed outcome of each worker.
 */
trait ConcurrentWorkers
{
    /**
     * @param list<array{string, array<string, mixed>}> $commands one worker per entry: operation and JSON arguments
     *
     * @return list<string>
     */
    private function runCommandWorkers(array $commands): array
    {
        $startAt = microtime(true) + 2.0;
        $environment = array_merge(getenv(), ['PRODUCT_TEST_DB_NAME' => self::$database]);
        $script = dirname(__DIR__, 2) . '/Support/command-worker.php';

        $running = [];
        foreach ($commands as $index => [$operation, $arguments]) {
            $stdout = sys_get_temp_dir() . '/product-worker-' . getmypid() . '-' . $index . '.out';
            $stderr = sys_get_temp_dir() . '/product-worker-' . getmypid() . '-' . $index . '.err';
            $process = proc_open(
                [PHP_BINARY, $script, sprintf('%.6F', $startAt), $operation, json_encode($arguments, JSON_THROW_ON_ERROR)],
                [1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']],
                $pipes,
                null,
                $environment,
            );
            self::assertIsResource($process, 'A worker process must start.');
            $running[] = [$process, $stdout, $stderr];
        }

        $outcomes = [];
        foreach ($running as [$process, $stdout, $stderr]) {
            $exit = proc_close($process);
            $output = (string) file_get_contents($stdout);
            $errors = (string) file_get_contents($stderr);
            unlink($stdout);
            unlink($stderr);
            self::assertSame(0, $exit, 'Worker failed: ' . $errors . $output);
            $outcomes[] = $output;
        }

        return $outcomes;
    }
}
