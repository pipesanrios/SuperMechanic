<?php
/**
 * Lint PHP files for Super Mechanic.
 *
 * Usage:
 *   php scripts/php-lint.php --all
 *   php scripts/php-lint.php --file=includes/class-plugin.php
 *   php scripts/php-lint.php --files=includes/class-plugin.php,super-mechanic.php
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

require __DIR__ . '/common.php';

$options = getopt('', ['all', 'file:', 'files:', 'help']);

if (isset($options['help'])) {
    sm_script_out('Uso: php scripts/php-lint.php [--all] [--file=RUTA] [--files=RUTA1,RUTA2]');
    exit(0);
}

$targets = [];

if (isset($options['all'])) {
    $targets = sm_script_collect_files(['php'], ['includes/modules', '.git', 'node_modules', 'vendor']);
}

$targets = array_merge($targets, sm_script_parse_multi_value($options['file'] ?? null));
$targets = array_merge($targets, sm_script_parse_multi_value($options['files'] ?? null));
$targets = array_values(array_unique($targets));

if ($targets === []) {
    sm_script_err('Debes indicar --all, --file o --files.');
    exit(1);
}

$phpBinary = sm_script_detect_php_binary();
$errors    = [];
$checked   = 0;

sm_script_out('PHP lint');
sm_script_out('Repositorio: ' . sm_script_repo_root());
sm_script_out('PHP: ' . $phpBinary);

foreach ($targets as $target) {
    $absolute = sm_script_abs_path($target);

    if (!is_file($absolute)) {
        $errors[] = $target . ' -> archivo inexistente';
        continue;
    }

    $checked++;
    $result = sm_script_run_command([$phpBinary, '-l', $absolute]);

    if ($result['exit_code'] !== 0) {
        $errors[] = $target . ' -> ' . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
        continue;
    }

    sm_script_out('OK  ' . $target);
}

sm_script_print_block('Resumen', [
    'Archivos revisados: ' . (string) $checked,
    'Errores: ' . (string) count($errors),
]);

if ($errors !== []) {
    sm_script_print_block('Errores detectados', $errors);
    exit(1);
}

exit(0);
