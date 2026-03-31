<?php
/**
 * Semi-automated QA runner for Validation Contracts.
 *
 * Usage:
 *   php scripts/qa-runner.php --contract=docs/contracts/validation/39E-4-validation.md
 *   php scripts/qa-runner.php --contract=... --output=text
 *   php scripts/qa-runner.php --contract=... --output=json
 *   php scripts/qa-runner.php --contract=... --output=markdown
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

require __DIR__ . '/common.php';

const SM_QA_STATUS_PASS    = 'PASS';
const SM_QA_STATUS_FAIL    = 'FAIL';
const SM_QA_STATUS_SKIPPED = 'SKIPPED';
const SM_QA_STATUS_NOT_RUN = 'NOT_RUN';

$options = getopt('', ['contract:', 'output:', 'help']);

if (isset($options['help'])) {
    sm_script_out('Uso: php scripts/qa-runner.php --contract=RUTA [--output=text|json|markdown]');
    exit(0);
}

$contractPath = isset($options['contract']) ? sm_script_normalize_path((string) $options['contract']) : '';
$outputMode   = isset($options['output']) ? strtolower((string) $options['output']) : 'text';

if ($contractPath === '') {
    sm_script_err('Error: Debes indicar --contract=RUTA');
    exit(1);
}

if (!in_array($outputMode, ['text', 'json', 'markdown'], true)) {
    sm_script_err('Error: --output debe ser text, json o markdown.');
    exit(1);
}

$contractAbsolute = sm_script_abs_path($contractPath);

if (!is_file($contractAbsolute)) {
    sm_script_err('Error: Contract no encontrado: ' . $contractPath);
    exit(1);
}

$rawContract = file_get_contents($contractAbsolute);

if (!is_string($rawContract) || trim($rawContract) === '') {
    sm_script_err('Error: Contract vacio o ilegible: ' . $contractPath);
    exit(1);
}

$parsedContract = sm_qa_parse_contract_payload($rawContract);

if ($parsedContract['ok'] === false) {
    sm_script_err('Error: Contract mal formado. ' . $parsedContract['error']);
    sm_script_err('Formato esperado: bloque JSON entre QA_CONTRACT_START y QA_CONTRACT_END.');
    exit(1);
}

$contract = $parsedContract['data'];

$validationError = sm_qa_validate_contract_schema($contract);

if ($validationError !== null) {
    sm_script_err('Error: Contract mal formado. ' . $validationError);
    exit(1);
}

$results = [];

foreach ($contract['automated_checks'] as $check) {
    $results[] = sm_qa_execute_check($check);
}

foreach ($contract['manual_checks'] as $manualCheck) {
    $results[] = sm_qa_mark_manual_check($manualCheck);
}

$summary = sm_qa_build_summary($results);

$report = [
    'phase'                  => (string) $contract['phase'],
    'validation_contract_id' => (string) $contract['validation_contract_id'],
    'contract_path'          => $contractPath,
    'timestamp_utc'          => gmdate('c'),
    'runner_version'         => '1.0.0',
    'results'                => $results,
    'summary'                => $summary,
    'note'                   => 'PASS tecnico no implica fase completa. Validacion runtime manual sigue obligatoria cuando aplique.',
];

sm_qa_render_output($report, $outputMode);

exit($summary['fail'] > 0 ? 1 : 0);

/**
 * @return array{ok:bool,data:array<string,mixed>,error:string}
 */
function sm_qa_parse_contract_payload(string $content): array
{
    $pattern = '/QA_CONTRACT_START(.*?)QA_CONTRACT_END/s';

    if (preg_match($pattern, $content, $matches) !== 1) {
        return [
            'ok'    => false,
            'data'  => [],
            'error' => 'No se encontro bloque QA_CONTRACT_START ... QA_CONTRACT_END.',
        ];
    }

    $json = trim((string) $matches[1]);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [
            'ok'    => false,
            'data'  => [],
            'error' => 'El bloque de contrato no contiene JSON valido.',
        ];
    }

    return [
        'ok'    => true,
        'data'  => $data,
        'error' => '',
    ];
}

/**
 * @param array<string,mixed> $contract
 */
function sm_qa_validate_contract_schema(array $contract): ?string
{
    foreach (['phase', 'validation_contract_id', 'automated_checks', 'manual_checks'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $contract)) {
            return 'Falta campo requerido: ' . $requiredKey;
        }
    }

    if (!is_string($contract['phase']) || trim($contract['phase']) === '') {
        return 'phase debe ser string no vacio.';
    }

    if (!is_string($contract['validation_contract_id']) || trim($contract['validation_contract_id']) === '') {
        return 'validation_contract_id debe ser string no vacio.';
    }

    if (!is_array($contract['automated_checks'])) {
        return 'automated_checks debe ser array.';
    }

    if (!is_array($contract['manual_checks'])) {
        return 'manual_checks debe ser array.';
    }

    foreach ($contract['automated_checks'] as $index => $check) {
        if (!is_array($check)) {
            return 'automated_checks[' . $index . '] debe ser objeto.';
        }

        foreach (['id', 'type'] as $requiredKey) {
            if (!isset($check[$requiredKey]) || !is_string($check[$requiredKey]) || trim((string) $check[$requiredKey]) === '') {
                return 'automated_checks[' . $index . '].' . $requiredKey . ' es requerido.';
            }
        }
    }

    foreach ($contract['manual_checks'] as $index => $check) {
        if (!is_array($check)) {
            return 'manual_checks[' . $index . '] debe ser objeto.';
        }

        if (!isset($check['id']) || !is_string($check['id']) || trim((string) $check['id']) === '') {
            return 'manual_checks[' . $index . '].id es requerido.';
        }
    }

    return null;
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_execute_check(array $check): array
{
    $id   = (string) ($check['id'] ?? 'unknown');
    $type = strtolower((string) ($check['type'] ?? ''));

    switch ($type) {
        case 'php_lint':
            return sm_qa_check_php_lint($check);
        case 'file_exists':
        case 'doc_exists':
            return sm_qa_check_file_exists($check, $type);
        case 'class_exists':
            return sm_qa_check_class_exists($check);
        case 'method_exists':
            return sm_qa_check_method_exists($check);
        case 'hook_registered':
            return sm_qa_check_hook_registered($check);
        default:
            return [
                'id'       => $id,
                'type'     => $type,
                'status'   => SM_QA_STATUS_SKIPPED,
                'message'  => 'Tipo no soportado en QA Runner v1.',
                'evidence' => [],
                'mode'     => 'automated',
            ];
    }
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_check_php_lint(array $check): array
{
    $id      = (string) ($check['id'] ?? 'php_lint');
    $target  = strtolower((string) ($check['target'] ?? 'all'));
    $files   = isset($check['files']) && is_array($check['files']) ? $check['files'] : [];
    $command = ['php', 'scripts/php-lint.php'];

    if ($target === 'all') {
        $command[] = '--all';
    } elseif ($files !== []) {
        $normalizedFiles = [];

        foreach ($files as $file) {
            if (is_string($file) && trim($file) !== '') {
                $normalizedFiles[] = sm_script_normalize_path($file);
            }
        }

        if ($normalizedFiles === []) {
            return [
                'id'       => $id,
                'type'     => 'php_lint',
                'status'   => SM_QA_STATUS_SKIPPED,
                'message'  => 'Check php_lint sin archivos validos.',
                'evidence' => [],
                'mode'     => 'automated',
            ];
        }

        $command[] = '--files=' . implode(',', $normalizedFiles);
    } else {
        return [
            'id'       => $id,
            'type'     => 'php_lint',
            'status'   => SM_QA_STATUS_SKIPPED,
            'message'  => 'php_lint requiere target=all o files=[...].',
            'evidence' => [],
            'mode'     => 'automated',
        ];
    }

    $result = sm_script_run_command($command);
    $ok     = $result['exit_code'] === 0;

    return [
        'id'       => $id,
        'type'     => 'php_lint',
        'status'   => $ok ? SM_QA_STATUS_PASS : SM_QA_STATUS_FAIL,
        'message'  => $ok ? 'PHP lint OK.' : 'PHP lint detecto errores.',
        'evidence' => [
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
        ],
        'mode'     => 'automated',
    ];
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_check_file_exists(array $check, string $type): array
{
    $id      = (string) ($check['id'] ?? $type);
    $targets = sm_qa_extract_targets($check);

    if ($targets === []) {
        return [
            'id'       => $id,
            'type'     => $type,
            'status'   => SM_QA_STATUS_SKIPPED,
            'message'  => 'Sin targets para verificar.',
            'evidence' => [],
            'mode'     => 'automated',
        ];
    }

    $missing = [];

    foreach ($targets as $target) {
        if (!sm_script_path_exists($target)) {
            $missing[] = $target;
        }
    }

    $ok = $missing === [];

    return [
        'id'       => $id,
        'type'     => $type,
        'status'   => $ok ? SM_QA_STATUS_PASS : SM_QA_STATUS_FAIL,
        'message'  => $ok ? 'Todos los archivos existen.' : 'Faltan archivos esperados.',
        'evidence' => [
            'targets' => $targets,
            'missing' => $missing,
        ],
        'mode'     => 'automated',
    ];
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_check_class_exists(array $check): array
{
    $id       = (string) ($check['id'] ?? 'class_exists');
    $class    = isset($check['class']) ? (string) $check['class'] : '';
    $filePath = isset($check['file']) ? sm_script_normalize_path((string) $check['file']) : '';

    if ($class === '' || $filePath === '') {
        return [
            'id'       => $id,
            'type'     => 'class_exists',
            'status'   => SM_QA_STATUS_SKIPPED,
            'message'  => 'class_exists requiere class y file.',
            'evidence' => [],
            'mode'     => 'automated',
        ];
    }

    if (!sm_script_path_exists($filePath)) {
        return [
            'id'       => $id,
            'type'     => 'class_exists',
            'status'   => SM_QA_STATUS_FAIL,
            'message'  => 'Archivo de clase no existe.',
            'evidence' => ['file' => $filePath],
            'mode'     => 'automated',
        ];
    }

    $content = file_get_contents(sm_script_abs_path($filePath));

    if (!is_string($content)) {
        return [
            'id'       => $id,
            'type'     => 'class_exists',
            'status'   => SM_QA_STATUS_FAIL,
            'message'  => 'No se pudo leer archivo de clase.',
            'evidence' => ['file' => $filePath],
            'mode'     => 'automated',
        ];
    }

    $pattern = '/\bclass\s+' . preg_quote($class, '/') . '\b/';
    $found   = preg_match($pattern, $content) === 1;

    return [
        'id'       => $id,
        'type'     => 'class_exists',
        'status'   => $found ? SM_QA_STATUS_PASS : SM_QA_STATUS_FAIL,
        'message'  => $found ? 'Clase encontrada.' : 'Clase no encontrada en archivo esperado.',
        'evidence' => [
            'class' => $class,
            'file'  => $filePath,
        ],
        'mode'     => 'automated',
    ];
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_check_method_exists(array $check): array
{
    $id       = (string) ($check['id'] ?? 'method_exists');
    $class    = isset($check['class']) ? (string) $check['class'] : '';
    $method   = isset($check['method']) ? (string) $check['method'] : '';
    $filePath = isset($check['file']) ? sm_script_normalize_path((string) $check['file']) : '';

    if ($class === '' || $method === '' || $filePath === '') {
        return [
            'id'       => $id,
            'type'     => 'method_exists',
            'status'   => SM_QA_STATUS_SKIPPED,
            'message'  => 'method_exists requiere class, method y file.',
            'evidence' => [],
            'mode'     => 'automated',
        ];
    }

    if (!sm_script_path_exists($filePath)) {
        return [
            'id'       => $id,
            'type'     => 'method_exists',
            'status'   => SM_QA_STATUS_FAIL,
            'message'  => 'Archivo no existe.',
            'evidence' => ['file' => $filePath],
            'mode'     => 'automated',
        ];
    }

    $content = file_get_contents(sm_script_abs_path($filePath));

    if (!is_string($content)) {
        return [
            'id'       => $id,
            'type'     => 'method_exists',
            'status'   => SM_QA_STATUS_FAIL,
            'message'  => 'No se pudo leer archivo.',
            'evidence' => ['file' => $filePath],
            'mode'     => 'automated',
        ];
    }

    $classPattern  = '/\bclass\s+' . preg_quote($class, '/') . '\b/s';
    $methodPattern = '/\bfunction\s+' . preg_quote($method, '/') . '\s*\(/s';
    $classFound    = preg_match($classPattern, $content) === 1;
    $methodFound   = preg_match($methodPattern, $content) === 1;
    $ok            = $classFound && $methodFound;

    return [
        'id'       => $id,
        'type'     => 'method_exists',
        'status'   => $ok ? SM_QA_STATUS_PASS : SM_QA_STATUS_FAIL,
        'message'  => $ok ? 'Metodo encontrado.' : 'No se encontro clase/metodo esperado.',
        'evidence' => [
            'class'        => $class,
            'method'       => $method,
            'file'         => $filePath,
            'class_found'  => $classFound,
            'method_found' => $methodFound,
        ],
        'mode'     => 'automated',
    ];
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_check_hook_registered(array $check): array
{
    $id       = (string) ($check['id'] ?? 'hook_registered');
    $hook     = isset($check['hook']) ? (string) $check['hook'] : '';
    $filePath = isset($check['file']) ? sm_script_normalize_path((string) $check['file']) : '';

    if ($hook === '' || $filePath === '') {
        return [
            'id'       => $id,
            'type'     => 'hook_registered',
            'status'   => SM_QA_STATUS_SKIPPED,
            'message'  => 'hook_registered requiere hook y file.',
            'evidence' => [],
            'mode'     => 'automated',
        ];
    }

    if (!sm_script_path_exists($filePath)) {
        return [
            'id'       => $id,
            'type'     => 'hook_registered',
            'status'   => SM_QA_STATUS_FAIL,
            'message'  => 'Archivo de hook no existe.',
            'evidence' => ['file' => $filePath],
            'mode'     => 'automated',
        ];
    }

    $content = file_get_contents(sm_script_abs_path($filePath));

    if (!is_string($content)) {
        return [
            'id'       => $id,
            'type'     => 'hook_registered',
            'status'   => SM_QA_STATUS_FAIL,
            'message'  => 'No se pudo leer archivo de hook.',
            'evidence' => ['file' => $filePath],
            'mode'     => 'automated',
        ];
    }

    $singleQuotePattern = "/add_(action|filter)\\s*\\(\\s*'" . preg_quote($hook, '/') . "'/";
    $doubleQuotePattern = '/add_(action|filter)\s*\(\s*"' . preg_quote($hook, '/') . '"/';
    $found              = preg_match($singleQuotePattern, $content) === 1 || preg_match($doubleQuotePattern, $content) === 1;

    return [
        'id'       => $id,
        'type'     => 'hook_registered',
        'status'   => $found ? SM_QA_STATUS_PASS : SM_QA_STATUS_FAIL,
        'message'  => $found ? 'Hook encontrado por analisis estatico.' : 'Hook no encontrado de forma confiable en archivo esperado.',
        'evidence' => [
            'hook' => $hook,
            'file' => $filePath,
        ],
        'mode'     => 'automated',
    ];
}

/**
 * @param array<string,mixed> $check
 * @return array<string,mixed>
 */
function sm_qa_mark_manual_check(array $check): array
{
    $id = (string) ($check['id'] ?? 'manual_check');

    return [
        'id'       => $id,
        'type'     => 'manual',
        'status'   => SM_QA_STATUS_NOT_RUN,
        'message'  => 'Check manual: no ejecutado por QA Runner.',
        'evidence' => [],
        'mode'     => 'manual',
    ];
}

/**
 * @param array<string,mixed> $check
 * @return array<int,string>
 */
function sm_qa_extract_targets(array $check): array
{
    $targets = [];

    if (isset($check['target']) && is_string($check['target']) && trim($check['target']) !== '') {
        $targets[] = sm_script_normalize_path((string) $check['target']);
    }

    if (isset($check['targets']) && is_array($check['targets'])) {
        foreach ($check['targets'] as $target) {
            if (is_string($target) && trim($target) !== '') {
                $targets[] = sm_script_normalize_path($target);
            }
        }
    }

    return array_values(array_unique($targets));
}

/**
 * @param array<int,array<string,mixed>> $results
 * @return array<string,int>
 */
function sm_qa_build_summary(array $results): array
{
    $summary = [
        'total'   => count($results),
        'pass'    => 0,
        'fail'    => 0,
        'skipped' => 0,
        'not_run' => 0,
    ];

    foreach ($results as $result) {
        $status = (string) ($result['status'] ?? '');

        if ($status === SM_QA_STATUS_PASS) {
            $summary['pass']++;
        } elseif ($status === SM_QA_STATUS_FAIL) {
            $summary['fail']++;
        } elseif ($status === SM_QA_STATUS_SKIPPED) {
            $summary['skipped']++;
        } elseif ($status === SM_QA_STATUS_NOT_RUN) {
            $summary['not_run']++;
        }
    }

    return $summary;
}

/**
 * @param array<string,mixed> $report
 */
function sm_qa_render_output(array $report, string $outputMode): void
{
    if ($outputMode === 'json') {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        sm_script_out(is_string($json) ? $json : '{}');

        return;
    }

    if ($outputMode === 'markdown') {
        sm_script_out('# QA Runner Result');
        sm_script_out('');
        sm_script_out('- Phase: `' . (string) $report['phase'] . '`');
        sm_script_out('- Validation contract: `' . (string) $report['validation_contract_id'] . '`');
        sm_script_out('- Contract path: `' . (string) $report['contract_path'] . '`');
        sm_script_out('- Timestamp (UTC): `' . (string) $report['timestamp_utc'] . '`');
        sm_script_out('');
        sm_script_out('| Check ID | Type | Mode | Status | Message |');
        sm_script_out('|---|---|---|---|---|');

        foreach ($report['results'] as $result) {
            $line = '| '
                . sm_qa_md_cell((string) $result['id']) . ' | '
                . sm_qa_md_cell((string) $result['type']) . ' | '
                . sm_qa_md_cell((string) $result['mode']) . ' | '
                . sm_qa_md_cell((string) $result['status']) . ' | '
                . sm_qa_md_cell((string) $result['message']) . ' |';
            sm_script_out($line);
        }

        sm_script_out('');
        sm_script_out('## Summary');
        sm_script_out('- Total: ' . (string) $report['summary']['total']);
        sm_script_out('- PASS: ' . (string) $report['summary']['pass']);
        sm_script_out('- FAIL: ' . (string) $report['summary']['fail']);
        sm_script_out('- SKIPPED: ' . (string) $report['summary']['skipped']);
        sm_script_out('- NOT_RUN: ' . (string) $report['summary']['not_run']);
        sm_script_out('');
        sm_script_out('> PASS tecnico no implica fase completa.');

        return;
    }

    sm_script_out('QA Runner');
    sm_script_out('Phase: ' . (string) $report['phase']);
    sm_script_out('Validation contract: ' . (string) $report['validation_contract_id']);
    sm_script_out('Contract path: ' . (string) $report['contract_path']);
    sm_script_out('');

    foreach ($report['results'] as $result) {
        sm_script_out('[' . (string) $result['status'] . '] ' . (string) $result['id'] . ' (' . (string) $result['type'] . ') - ' . (string) $result['message']);
    }

    sm_script_out('');
    sm_script_out('Resumen');
    sm_script_out(' - Total: ' . (string) $report['summary']['total']);
    sm_script_out(' - PASS: ' . (string) $report['summary']['pass']);
    sm_script_out(' - FAIL: ' . (string) $report['summary']['fail']);
    sm_script_out(' - SKIPPED: ' . (string) $report['summary']['skipped']);
    sm_script_out(' - NOT_RUN: ' . (string) $report['summary']['not_run']);
    sm_script_out('');
    sm_script_out('Nota: PASS tecnico no implica fase completa.');
}

function sm_qa_md_cell(string $value): string
{
    return str_replace('|', '\\|', $value);
}
