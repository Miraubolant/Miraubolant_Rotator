<?php
/**
 * API Health Check
 *
 * Endpoint simple pour vérifier que le rotator est en ligne.
 * Utilisé par le dashboard central pour tester la connexion.
 *
 * Méthode : GET
 * Réponse : JSON avec statut et informations système
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'état des fichiers critiques
$checks = [
    'config' => file_exists(__DIR__ . '/config.php'),
    'data_dir' => is_dir(DATA_DIR) && is_writable(DATA_DIR),
    'logs_dir' => is_dir(LOGS_DIR) && is_writable(LOGS_DIR),
    'urls_file' => file_exists(URLS_FILE)
];

// Compter les URLs actives
$urlsCount = 0;
$lastUpdate = null;

if ($checks['urls_file']) {
    $urlsData = json_decode(file_get_contents(URLS_FILE), true);
    if ($urlsData && isset($urlsData['urls'])) {
        $urlsCount = count($urlsData['urls']);
        $lastUpdate = $urlsData['updated_at'] ?? null;
    }
}

// Vérifier le token (optionnel, sans bloquer)
$hasValidToken = defined('ROTATOR_TOKEN') && ROTATOR_TOKEN !== 'change_me_in_production';

// Statut global
$allChecksPass = $checks['config'] && $checks['data_dir'] && $checks['logs_dir'];
$status = $allChecksPass ? 'healthy' : 'degraded';

$response = [
    'status' => $status,
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => [
        'config' => $checks['config'] ? 'ok' : 'missing',
        'data_directory' => $checks['data_dir'] ? 'ok' : 'not_writable',
        'logs_directory' => $checks['logs_dir'] ? 'ok' : 'not_writable',
        'urls_file' => $checks['urls_file'] ? 'ok' : 'not_found',
        'token_configured' => $hasValidToken ? 'ok' : 'using_default'
    ],
    'stats' => [
        'active_urls' => $urlsCount,
        'last_urls_update' => $lastUpdate
    ],
    'php_version' => PHP_VERSION,
    'server_time' => date('Y-m-d H:i:s')
];

http_response_code($allChecksPass ? 200 : 503);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
