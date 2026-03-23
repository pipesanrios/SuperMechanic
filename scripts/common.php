<?php
/**
 * Shared helpers for local quality scripts.
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

defined('STDIN') || exit(1);

/**
 * Return the repository root.
 */
function sm_script_repo_root(): string
{
    return realpath(__DIR__ . '/..') ?: dirname(__DIR__);
}

/**
 * Print a line to stdout.
 *
 * @param string $message Message.
 */
function sm_script_out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/**
 * Print a line to stderr.
 *
 * @param string $message Message.
 */
function sm_script_err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

/**
 * Normalize a repository relative path.
 *
 * @param string $path Raw path.
 */
function sm_script_normalize_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path);

    return ltrim((string) $path, './');
}

/**
 * Convert a repository relative path to an absolute path.
 *
 * @param string $path Relative path.
 */
function sm_script_abs_path(string $path): string
{
    return sm_script_repo_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, sm_script_normalize_path($path));
}

/**
 * Check whether a repository relative path exists.
 *
 * @param string $path Relative path.
 */
function sm_script_path_exists(string $path): bool
{
    return file_exists(sm_script_abs_path($path));
}

/**
 * Build a recursive file iterator while skipping noisy directories.
 *
 * @param array<int, string> $extensions Allowed file extensions without dots.
 * @return array<int, string>
 */
function sm_script_collect_files(array $extensions, array $excludeDirs = []): array
{
    $root     = sm_script_repo_root();
    $exclude  = array_map('sm_script_normalize_path', $excludeDirs);
    $results  = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $relative = sm_script_normalize_path(substr($file->getPathname(), strlen($root) + 1));

        foreach ($exclude as $prefix) {
            if ($prefix !== '' && strpos($relative, $prefix . '/') === 0) {
                continue 2;
            }
        }

        $extension = strtolower($file->getExtension());

        if (!in_array($extension, $extensions, true)) {
            continue;
        }

        $results[] = $relative;
    }

    sort($results);

    return $results;
}

/**
 * Parse repeated CLI values and comma-separated lists.
 *
 * @param mixed $value getopt() value.
 * @return array<int, string>
 */
function sm_script_parse_multi_value($value): array
{
    if ($value === false || $value === null) {
        return [];
    }

    $values = is_array($value) ? $value : [$value];
    $items  = [];

    foreach ($values as $chunk) {
        foreach (explode(',', (string) $chunk) as $item) {
            $item = trim($item);

            if ($item !== '') {
                $items[] = sm_script_normalize_path($item);
            }
        }
    }

    return array_values(array_unique($items));
}

/**
 * Run a local command and capture output.
 *
 * @param array<int, string> $command Command parts.
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function sm_script_run_command(array $command): array
{
    $cmd = [];

    foreach ($command as $part) {
        $cmd[] = escapeshellarg($part);
    }

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(implode(' ', $cmd), $descriptor, $pipes, sm_script_repo_root());

    if (!is_resource($process)) {
        return [
            'exit_code' => 1,
            'stdout'    => '',
            'stderr'    => 'No fue posible ejecutar el comando solicitado.',
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit_code' => proc_close($process),
        'stdout'    => is_string($stdout) ? trim($stdout) : '',
        'stderr'    => is_string($stderr) ? trim($stderr) : '',
    ];
}

/**
 * Detect the PHP binary to use for linting.
 */
function sm_script_detect_php_binary(): string
{
    $candidates = [];

    if (PHP_BINARY !== '') {
        $candidates[] = PHP_BINARY;
    }

    $envPhp = getenv('SM_PHP_BIN');

    if (is_string($envPhp) && $envPhp !== '') {
        array_unshift($candidates, $envPhp);
    }

    $candidates[] = 'php';
    $candidates[] = 'C:/xampp/php/php.exe';

    foreach (array_unique($candidates) as $candidate) {
        $result = sm_script_run_command([$candidate, '-v']);

        if ($result['exit_code'] === 0) {
            return $candidate;
        }
    }

    sm_script_err('No se encontro un binario PHP ejecutable. Usa la variable SM_PHP_BIN si hace falta.');
    exit(1);
}

/**
 * Render a simple summary block.
 *
 * @param string              $title   Title.
 * @param array<int, string>  $entries Lines.
 */
function sm_script_print_block(string $title, array $entries): void
{
    sm_script_out('');
    sm_script_out($title);

    foreach ($entries as $entry) {
        sm_script_out(' - ' . $entry);
    }
}
