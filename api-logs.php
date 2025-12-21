<?php
/**
 * API pour récupérer les statistiques et logs de redirections
 *
 * Méthode : GET
 * Paramètres optionnels :
 * - period : "24h" (défaut), "7d", "30d", "all"
 * - format : "json" (défaut), "summary", "logs"
 * - limit : nombre de logs (pour format=logs, défaut: 20, max: 50)
 *
 * Réponse : JSON avec les statistiques ou logs récents
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

/**
 * Parse les logs et retourne les entrées
 * Inclut le fichier principal ET les fichiers backup (.bak)
 */
function parseLogs(int $since = 0): array
{
    $logs = [];
    $logFiles = [];

    // Ajouter le fichier principal s'il existe
    if (file_exists(LOGS_FILE)) {
        $logFiles[] = LOGS_FILE;
    }

    // Ajouter les fichiers backup (.bak) du même répertoire
    $backupPattern = LOGS_FILE . '.*.bak';
    $backupFiles = glob($backupPattern);
    if ($backupFiles) {
        // Trier les backups du plus récent au plus ancien (par nom de fichier qui contient la date)
        rsort($backupFiles);
        $logFiles = array_merge($logFiles, $backupFiles);
    }

    if (empty($logFiles)) {
        return $logs;
    }

    foreach ($logFiles as $logFile) {
        $handle = fopen($logFile, 'r');
        if (!$handle) {
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $entry = json_decode($line, true);
            if (!$entry || !isset($entry['timestamp'])) {
                continue;
            }

            $timestamp = strtotime($entry['timestamp']);
            if ($since > 0 && $timestamp < $since) {
                continue;
            }

            $logs[] = $entry;
        }

        fclose($handle);
    }

    return $logs;
}

/**
 * Calcule les statistiques à partir des logs
 */
function calculateStats(array $logs): array
{
    $stats = [
        'total_clicks' => count($logs),
        'urls' => [],
        'countries' => [],
        'cities' => [],
        'hourly' => [],
        'user_agents' => [],
        'devices' => [],
        'os' => [],
        'unique_ips' => []
    ];

    foreach ($logs as $entry) {
        // Stats par URL
        $url = $entry['url'] ?? 'unknown';
        if (!isset($stats['urls'][$url])) {
            $stats['urls'][$url] = 0;
        }
        $stats['urls'][$url]++;

        // Stats par pays
        $country = $entry['country'] ?? 'XX';
        if (!isset($stats['countries'][$country])) {
            $stats['countries'][$country] = 0;
        }
        $stats['countries'][$country]++;

        // Stats par ville
        $city = $entry['city'] ?? '';
        if (!empty($city)) {
            if (!isset($stats['cities'][$city])) {
                $stats['cities'][$city] = 0;
            }
            $stats['cities'][$city]++;
        }

        // Stats par heure
        $hour = date('Y-m-d H:00', strtotime($entry['timestamp']));
        if (!isset($stats['hourly'][$hour])) {
            $stats['hourly'][$hour] = 0;
        }
        $stats['hourly'][$hour]++;

        // Stats par type d'agent
        $ua = $entry['user_agent'] ?? '';
        $browser = detectBrowser($ua);
        if (!isset($stats['user_agents'][$browser])) {
            $stats['user_agents'][$browser] = 0;
        }
        $stats['user_agents'][$browser]++;

        // Stats par appareil
        $device = detectDevice($ua);
        if (!isset($stats['devices'][$device])) {
            $stats['devices'][$device] = 0;
        }
        $stats['devices'][$device]++;

        // Stats par OS
        $os = detectOS($ua);
        if (!empty($os)) {
            if (!isset($stats['os'][$os])) {
                $stats['os'][$os] = 0;
            }
            $stats['os'][$os]++;
        }

        // IPs uniques (utiliser un hash pour éviter les doublons)
        $ip = $entry['ip'] ?? '';
        if ($ip) {
            $ipHash = md5($ip);
            if (!isset($stats['unique_ips'][$ipHash])) {
                $stats['unique_ips'][$ipHash] = true;
            }
        }
    }

    // Trier les résultats
    arsort($stats['urls']);
    arsort($stats['countries']);
    arsort($stats['cities']);
    arsort($stats['user_agents']);
    arsort($stats['devices']);
    arsort($stats['os']);
    ksort($stats['hourly']);

    // Ne pas limiter les stats pour permettre l'agrégation complète côté dashboard
    // Le dashboard se charge de l'affichage limité avec recherche

    return $stats;
}

/**
 * Détecte le navigateur à partir du User-Agent
 */
function detectBrowser(string $ua): string
{
    $ua = strtolower($ua);

    if (strpos($ua, 'bot') !== false || strpos($ua, 'crawler') !== false || strpos($ua, 'spider') !== false) {
        return 'Bot';
    }
    if (strpos($ua, 'chrome') !== false && strpos($ua, 'edg') !== false) {
        return 'Edge';
    }
    if (strpos($ua, 'chrome') !== false) {
        return 'Chrome';
    }
    if (strpos($ua, 'firefox') !== false) {
        return 'Firefox';
    }
    if (strpos($ua, 'safari') !== false) {
        return 'Safari';
    }
    if (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) {
        return 'Internet Explorer';
    }

    return 'Other';
}

/**
 * Détecte le type d'appareil à partir du User-Agent
 */
function detectDevice(string $ua): string
{
    $ua = strtolower($ua);

    // Bots
    if (strpos($ua, 'bot') !== false || strpos($ua, 'crawler') !== false || strpos($ua, 'spider') !== false) {
        return 'bot';
    }

    // Mobile phones
    $mobileKeywords = ['iphone', 'android', 'mobile', 'phone', 'ipod', 'blackberry', 'windows phone', 'opera mini', 'opera mobi'];
    foreach ($mobileKeywords as $keyword) {
        if (strpos($ua, $keyword) !== false) {
            // Check if it's a tablet (Android tablets don't have 'mobile' in UA)
            if (strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false) {
                return 'tablet';
            }
            return 'mobile';
        }
    }

    // Tablets
    $tabletKeywords = ['ipad', 'tablet', 'kindle', 'silk', 'playbook'];
    foreach ($tabletKeywords as $keyword) {
        if (strpos($ua, $keyword) !== false) {
            return 'tablet';
        }
    }

    return 'desktop';
}

/**
 * Détecte le système d'exploitation à partir du User-Agent
 */
function detectOS(string $ua): string
{
    $ua = strtolower($ua);

    if (strpos($ua, 'windows nt 10') !== false) {
        return 'Windows 10';
    }
    if (strpos($ua, 'windows') !== false) {
        return 'Windows';
    }
    if (strpos($ua, 'mac os x') !== false) {
        return 'macOS';
    }
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
        return 'iOS';
    }
    if (strpos($ua, 'android') !== false) {
        return 'Android';
    }
    if (strpos($ua, 'linux') !== false) {
        return 'Linux';
    }

    return '';
}

/**
 * Lit les dernières lignes d'un fichier efficacement
 */
function getRecentLogs(int $limit): array
{
    if (!file_exists(LOGS_FILE)) {
        return [];
    }

    $lines = file(LOGS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }

    // Prendre les dernières lignes
    $lines = array_slice($lines, -$limit);
    $logs = [];

    foreach (array_reverse($lines) as $line) {
        $entry = json_decode($line, true);
        if ($entry && isset($entry['timestamp'])) {
            $ua = $entry['user_agent'] ?? '';
            $logs[] = [
                'timestamp' => $entry['timestamp'],
                'url' => $entry['url'] ?? '',
                'ip' => $entry['ip'] ?? '',
                'country' => $entry['country'] ?? 'XX',
                'city' => $entry['city'] ?? '',
                'referer' => $entry['referer'] ?? '',
                'device' => detectDevice($ua),
                'browser' => detectBrowser($ua),
                'os' => detectOS($ua)
            ];
        }
    }

    return $logs;
}

// Récupérer les paramètres
$period = $_GET['period'] ?? '24h';
$format = $_GET['format'] ?? 'json';
$limit = min(50, max(5, (int) ($_GET['limit'] ?? 20)));

// Si format=logs, retourner les logs récents directement
if ($format === 'logs') {
    $recentLogs = getRecentLogs($limit);
    echo json_encode([
        'success' => true,
        'generated_at' => date('c'),
        'count' => count($recentLogs),
        'logs' => $recentLogs
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Calculer le timestamp de début selon la période
$since = 0;
switch ($period) {
    case '24h':
        $since = time() - (24 * 60 * 60);
        break;
    case '7d':
        $since = time() - (7 * 24 * 60 * 60);
        break;
    case '30d':
        $since = time() - (30 * 24 * 60 * 60);
        break;
    case 'all':
    default:
        $since = 0;
        break;
}

// Parser les logs
$logs = parseLogs($since);

// Calculer les statistiques
$stats = calculateStats($logs);

// Construire la réponse
$response = [
    'success' => true,
    'period' => $period,
    'generated_at' => date('c'),
    'stats' => [
        'total_clicks' => $stats['total_clicks'],
        'unique_ips' => count($stats['unique_ips']),
        'unique_countries' => count($stats['countries']),
        'top_urls' => $stats['urls'],
        'top_countries' => $stats['countries'],
        'top_cities' => $stats['cities'],
        'browsers' => $stats['user_agents'],
        'devices' => $stats['devices'],
        'os' => $stats['os'],
        'hourly_distribution' => $stats['hourly']
    ]
];

// Format résumé simplifié
if ($format === 'summary') {
    $response = [
        'success' => true,
        'period' => $period,
        'total_clicks' => $stats['total_clicks'],
        'top_url' => array_key_first($stats['urls']) ?? null,
        'top_country' => array_key_first($stats['countries']) ?? null
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
