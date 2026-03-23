<?php
/**
 * Structural checks for Super Mechanic.
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

require __DIR__ . '/common.php';

$options = getopt('', ['help']);

if (isset($options['help'])) {
    sm_script_out('Uso: php scripts/structure-check.php');
    exit(0);
}

$requiredFiles = [
    'AGENTS.md',
    'AGENTS_BOOTSTRAP.md',
    'ARCHITECTURE.md',
    'super-mechanic.php',
    'includes/class-plugin.php',
    'docs/SYSTEM_MAP.md',
    'docs/FINAL_ARCHITECTURE_MAP.md',
    'docs/CURRENT_STATE.md',
    'docs/MODULE_REGISTRY.md',
    'docs/DATABASE_MAP.md',
    'ai/prompts/PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt',
];

$criticalReferenceSources = [
    'AGENTS.md',
    'AGENTS_BOOTSTRAP.md',
    'ARCHITECTURE.md',
    'docs/SYSTEM_MAP.md',
    'super-mechanic.php',
    'includes/class-plugin.php',
];

$warnings           = [];
$errors             = [];
$phpFiles           = sm_script_collect_files(['php'], ['includes/modules', '.git', 'node_modules', 'vendor']);
$activeRuntimeFiles = [];

foreach ($requiredFiles as $path) {
    if (!sm_script_path_exists($path)) {
        $errors[] = 'Falta archivo obligatorio: ' . $path;
    }
}

foreach ($phpFiles as $path) {
    if (
        strpos($path, 'includes/') === 0 ||
        in_array($path, ['super-mechanic.php', 'uninstall.php'], true)
    ) {
        $activeRuntimeFiles[] = $path;
    }
}

foreach ($activeRuntimeFiles as $path) {
    $contents = file_get_contents(sm_script_abs_path($path));

    if (!is_string($contents)) {
        $warnings[] = 'No fue posible leer: ' . $path;
        continue;
    }

    if (strpos($contents, 'includes/modules/') !== false || strpos($contents, 'includes\\modules\\') !== false) {
        $errors[] = 'Referencia prohibida a includes/modules/* en archivo activo: ' . $path;
    }
}

$pathPattern = '/(?:"|\x27|`)([A-Za-z0-9_\/\\\\.\- ]+\.(?:md|php|txt|css|js))(?:"|\x27|`)/u';

/**
 * Try to resolve a loose documentation reference to an existing repo path.
 */
function sm_structure_resolve_reference(string $reference): string
{
    $reference = sm_script_normalize_path($reference);

    if ($reference === '') {
        return '';
    }

    $candidates = [$reference];

    if (strpos($reference, '/') === false) {
        $extension = strtolower(pathinfo($reference, PATHINFO_EXTENSION));

        if ($extension === 'php' && strpos($reference, 'class-') === 0) {
            $candidates[] = 'includes/' . $reference;
        }

        if ($extension === 'php' && in_array($reference, ['php-lint.php', 'structure-check.php', 'technical-checklist.php'], true)) {
            $candidates[] = 'scripts/' . $reference;
        }
    }

    foreach ($candidates as $candidate) {
        if (sm_script_path_exists($candidate)) {
            return $candidate;
        }
    }

    return $reference;
}

foreach ($criticalReferenceSources as $path) {
    if (!sm_script_path_exists($path)) {
        continue;
    }

    $contents = file_get_contents(sm_script_abs_path($path));

    if (!is_string($contents) || !preg_match_all($pathPattern, $contents, $matches)) {
        continue;
    }

    foreach ($matches[1] as $match) {
        $reference = sm_script_normalize_path($match);

        if (
            $reference === '' ||
            strpos($reference, 'http://') === 0 ||
            strpos($reference, 'https://') === 0 ||
            strpos($reference, '*') !== false ||
            strpos($reference, '{$wpdb->prefix}') !== false
        ) {
            continue;
        }

        $resolved = sm_structure_resolve_reference($reference);

        if (!sm_script_path_exists($resolved)) {
            $warnings[] = 'Referencia potencialmente inexistente en ' . $path . ': ' . $reference;
        }
    }
}

sm_script_out('Chequeo estructural');
sm_script_out('Repositorio: ' . sm_script_repo_root());

sm_script_print_block('Resumen', [
    'Archivos obligatorios revisados: ' . (string) count($requiredFiles),
    'PHP activos escaneados: ' . (string) count($activeRuntimeFiles),
    'Errores: ' . (string) count($errors),
    'Warnings: ' . (string) count($warnings),
]);

if ($warnings !== []) {
    sm_script_print_block('Warnings', array_values(array_unique($warnings)));
}

if ($errors !== []) {
    sm_script_print_block('Errores detectados', array_values(array_unique($errors)));
    exit(1);
}

exit(0);
