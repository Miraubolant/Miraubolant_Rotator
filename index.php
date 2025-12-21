<?php
/**
 * VintDress Rotator - Point d'entrée principal
 *
 * Ce script effectue une rotation aléatoire parmi les URLs configurées.
 * Il log chaque redirection pour les statistiques.
 *
 * Priorité des sources d'URLs :
 * 1. data/urls.json (géré par le dashboard central)
 * 2. FALLBACK_URLS dans config.php
 */

require_once __DIR__ . '/config.php';

/**
 * Récupère les URLs actives
 */
function getActiveUrls(): array
{
    // Essayer de lire urls.json en premier
    if (file_exists(URLS_FILE)) {
        $content = file_get_contents(URLS_FILE);
        $data = json_decode($content, true);

        if ($data && isset($data['urls']) && is_array($data['urls']) && !empty($data['urls'])) {
            return $data['urls'];
        }
    }

    // Fallback sur les URLs par défaut
    return FALLBACK_URLS;
}

/**
 * Log une redirection
 */
function logRedirection(string $url, array $info): void
{
    if (!LOG_ENABLED) {
        return;
    }

    // Créer le dossier logs si nécessaire
    if (!is_dir(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }

    // Rotation du fichier de log si trop gros
    if (file_exists(LOGS_FILE) && filesize(LOGS_FILE) > LOG_MAX_SIZE) {
        $backupFile = LOGS_FILE . '.' . date('Y-m-d_H-i-s') . '.bak';
        rename(LOGS_FILE, $backupFile);
    }

    $logEntry = [
        'timestamp' => date('c'),
        'url' => $url,
        'ip' => $info['ip'],
        'user_agent' => $info['user_agent'],
        'referer' => $info['referer'],
        'country' => $info['country'],
        'city' => $info['city'] ?? ''
    ];

    $line = json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents(LOGS_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Détecte le pays et la ville à partir de l'IP via headers ou API gratuite
 */
function detectGeoInfo(string $ip): array
{
    $result = ['country' => 'XX', 'city' => ''];

    // CloudFlare header (prioritaire car plus rapide)
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $result['country'] = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
    } else {
        // Autres proxys
        $headers = ['HTTP_X_COUNTRY', 'HTTP_X_GEOIP_COUNTRY'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $result['country'] = strtoupper($_SERVER[$header]);
                break;
            }
        }
    }

    // Utiliser l'API gratuite ip-api.com pour la géolocalisation
    // Note: Limite de 45 requêtes/minute pour usage gratuit
    if ($ip && $ip !== '0.0.0.0' && $ip !== '127.0.0.1') {
        $geoData = getGeoLocation($ip);
        if ($geoData) {
            if (isset($geoData['countryCode'])) {
                $result['country'] = strtoupper($geoData['countryCode']);
            }
            if (isset($geoData['city'])) {
                $result['city'] = $geoData['city'];
            }
        }
    }

    return $result;
}

/**
 * Récupère les informations de géolocalisation via ip-api.com
 * API gratuite: 45 requêtes/minute max
 */
function getGeoLocation(string $ip): ?array
{
    // Cache simple basé sur fichier pour éviter trop de requêtes
    $cacheDir = __DIR__ . '/data/geo_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    $cacheFile = $cacheDir . '/' . md5($ip) . '.json';

    // Vérifier le cache (valide 24h)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            return $cached;
        }
    }

    // Appeler l'API ip-api.com
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,region";

    $context = stream_context_create([
        'http' => [
            'timeout' => 2, // Timeout court pour ne pas ralentir les redirections
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            // Sauvegarder en cache
            @file_put_contents($cacheFile, json_encode($data));
            return $data;
        }
    }

    return null;
}

/**
 * Récupère l'IP réelle du visiteur
 */
function getRealIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Prendre la première IP si plusieurs (X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

// Récupérer les URLs
$urls = getActiveUrls();

if (empty($urls)) {
    // Aucune URL disponible - afficher une page d'erreur simple
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><title>Service temporairement indisponible</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
    echo '<h1>Service temporairement indisponible</h1>';
    echo '<p>Veuillez réessayer plus tard.</p>';
    echo '</body></html>';
    exit;
}

// Sélectionner une URL aléatoire
$selectedUrl = $urls[array_rand($urls)];

// Préparer les informations pour le log
$realIp = getRealIp();
$geoInfo = detectGeoInfo($realIp);
$info = [
    'ip' => $realIp,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    'country' => $geoInfo['country'],
    'city' => $geoInfo['city']
];

// Logger la redirection
logRedirection($selectedUrl, $info);

// Effectuer la redirection
// Referrer-Policy: no-referrer empêche l'envoi du header Referer
// Cela évite les pages d'avertissement de YouTube et autres sites
header('Referrer-Policy: no-referrer');
header('Location: ' . $selectedUrl, true, 302);
exit;
