<?php
/**
 * Technical closure checklist for Super Mechanic.
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

require __DIR__ . '/common.php';

$options = getopt('', ['task:', 'help']);

if (isset($options['help'])) {
    sm_script_out('Uso: php scripts/technical-checklist.php [--task=docs/tasks/archivo.md]');
    exit(0);
}

$repoRoot    = sm_script_repo_root();
$taskFile    = sm_script_parse_multi_value($options['task'] ?? null);
$taskPath    = $taskFile[0] ?? '';
$errors      = [];
$warnings    = [];
$checks      = [];

$checks[] = 'Prompt Master presente';
if (!sm_script_path_exists('ai/prompts/PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt')) {
    $errors[] = 'Falta Prompt Master en ai/prompts/.';
}

$baseDocs = [
    'ARCHITECTURE.md',
    'docs/SYSTEM_MAP.md',
    'docs/FINAL_ARCHITECTURE_MAP.md',
    'docs/CURRENT_STATE.md',
    'docs/MODULE_REGISTRY.md',
    'docs/DATABASE_MAP.md',
];

$checks[] = 'Documentacion base presente';
foreach ($baseDocs as $doc) {
    if (!sm_script_path_exists($doc)) {
        $errors[] = 'Falta documento base: ' . $doc;
    }
}

$checks[] = 'Task file cuando aplica';
if ($taskPath !== '') {
    if (!sm_script_path_exists($taskPath)) {
        $errors[] = 'No existe el task file indicado: ' . $taskPath;
    }
} else {
    $warnings[] = 'No se indico --task. El checklist no puede confirmar task file de la fase.';
}

$checks[] = 'Sin cambios inesperados de schema cuando git esta disponible';
$gitResult = sm_script_run_command(['git', 'status', '--porcelain', '--', 'includes/database/class-schema.php']);
if ($gitResult['exit_code'] === 0) {
    if (trim($gitResult['stdout']) !== '') {
        $warnings[] = 'Hay cambios locales en includes/database/class-schema.php. Revisar si son esperados para la fase.';
    }
} else {
    $warnings[] = 'No se pudo consultar git para verificar cambios en schema.';
}

$checks[] = 'PHP lint completo';
$lintResult = sm_script_run_command([sm_script_detect_php_binary(), 'scripts/php-lint.php', '--all']);
if ($lintResult['exit_code'] !== 0) {
    $errors[] = 'Fallo php-lint.php --all';
}

$checks[] = 'Chequeo estructural';
$structureResult = sm_script_run_command([sm_script_detect_php_binary(), 'scripts/structure-check.php']);
if ($structureResult['exit_code'] !== 0) {
    $errors[] = 'Fallo structure-check.php';
}

sm_script_out('Checklist tecnico');
sm_script_out('Repositorio: ' . $repoRoot);

sm_script_print_block('Checks ejecutados', $checks);

if ($lintResult['stdout'] !== '') {
    sm_script_print_block('Salida php-lint', explode(PHP_EOL, $lintResult['stdout']));
}

if ($lintResult['stderr'] !== '') {
    sm_script_print_block('Errores php-lint', explode(PHP_EOL, $lintResult['stderr']));
}

if ($structureResult['stdout'] !== '') {
    sm_script_print_block('Salida structure-check', explode(PHP_EOL, $structureResult['stdout']));
}

if ($structureResult['stderr'] !== '') {
    sm_script_print_block('Errores structure-check', explode(PHP_EOL, $structureResult['stderr']));
}

if ($warnings !== []) {
    sm_script_print_block('Warnings', array_values(array_unique($warnings)));
}

if ($errors !== []) {
    sm_script_print_block('Errores detectados', array_values(array_unique($errors)));
    exit(1);
}

sm_script_out('');
sm_script_out('Checklist tecnico OK');
exit(0);
