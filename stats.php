<?php
/**
 * Dashboard local des statistiques du rotator
 *
 * Interface web pour visualiser les stats de redirection.
 * Design épuré style GitHub/Notion.
 */

require_once __DIR__ . '/config.php';

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
 * Calcule les statistiques
 */
function getStats(string $period): array
{
    $since = match ($period) {
        '1h' => time() - 60 * 60,
        '6h' => time() - 6 * 60 * 60,
        '24h' => time() - 24 * 60 * 60,
        '48h' => time() - 48 * 60 * 60,
        '7d' => time() - 7 * 24 * 60 * 60,
        '30d' => time() - 30 * 24 * 60 * 60,
        '90d' => time() - 90 * 24 * 60 * 60,
        '1y' => time() - 365 * 24 * 60 * 60,
        default => 0
    };

    $logs = parseLogs($since);

    $stats = [
        'total' => count($logs),
        'urls' => [],
        'countries' => [],
        'recent' => []
    ];

    foreach ($logs as $entry) {
        $url = $entry['url'] ?? 'unknown';
        $country = $entry['country'] ?? 'XX';

        $stats['urls'][$url] = ($stats['urls'][$url] ?? 0) + 1;
        $stats['countries'][$country] = ($stats['countries'][$country] ?? 0) + 1;
    }

    arsort($stats['urls']);
    arsort($stats['countries']);

    // 10 dernières redirections
    $stats['recent'] = array_slice(array_reverse($logs), 0, 10);

    return $stats;
}

// Récupérer la période demandée
$period = $_GET['period'] ?? '24h';
$validPeriods = ['1h', '6h', '24h', '48h', '7d', '30d', '90d', '1y', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = '24h';
}

$stats = getStats($period);

// Récupérer les URLs actives
$activeUrls = [];
if (file_exists(URLS_FILE)) {
    $data = json_decode(file_get_contents(URLS_FILE), true);
    $activeUrls = $data['urls'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VintDress Rotator - Stats</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'bg-primary': '#0d1117',
                        'bg-secondary': '#161b22',
                        'bg-tertiary': '#21262d',
                        'border': '#30363d',
                        'text-primary': '#c9d1d9',
                        'text-secondary': '#8b949e',
                        'accent': '#58a6ff',
                        'success': '#3fb950',
                        'warning': '#d29922',
                        'danger': '#f85149'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #0d1117;
            color: #c9d1d9;
        }
        .card {
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
        }
        .table-row:hover {
            background-color: #21262d;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-white">VintDress Rotator</h1>
                    <p class="text-text-secondary text-sm mt-1">Statistiques de redirection</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success/20 text-success">
                        <span class="w-2 h-2 bg-success rounded-full mr-1.5"></span>
                        En ligne
                    </span>
                </div>
            </div>
        </header>

        <!-- Period Selector -->
        <div class="card p-1 inline-flex mb-6">
            <?php foreach ($validPeriods as $p): ?>
                <a href="?period=<?= $p ?>"
                   class="px-3 py-1.5 text-sm rounded <?= $period === $p ? 'bg-accent text-white' : 'text-text-secondary hover:text-white' ?>">
                    <?= match($p) {
                        '1h' => '1h',
                        '6h' => '6h',
                        '24h' => '24h',
                        '48h' => '48h',
                        '7d' => '7j',
                        '30d' => '30j',
                        '90d' => '90j',
                        '1y' => '1 an',
                        'all' => 'Tout'
                    } ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="card p-4">
                <p class="text-text-secondary text-sm">Total clics</p>
                <p class="text-3xl font-semibold text-white mt-1"><?= number_format($stats['total']) ?></p>
            </div>
            <div class="card p-4">
                <p class="text-text-secondary text-sm">URLs actives</p>
                <p class="text-3xl font-semibold text-white mt-1"><?= count($activeUrls) ?></p>
            </div>
            <div class="card p-4">
                <p class="text-text-secondary text-sm">Pays uniques</p>
                <p class="text-3xl font-semibold text-white mt-1"><?= count($stats['countries']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top URLs -->
            <div class="card">
                <div class="px-4 py-3 border-b border-border">
                    <h2 class="font-medium text-white">Top URLs</h2>
                </div>
                <div class="divide-y divide-border">
                    <?php if (empty($stats['urls'])): ?>
                        <p class="px-4 py-8 text-center text-text-secondary">Aucune donnée</p>
                    <?php else: ?>
                        <?php foreach (array_slice($stats['urls'], 0, 5, true) as $url => $count): ?>
                            <div class="px-4 py-3 table-row flex items-center justify-between">
                                <span class="text-sm truncate max-w-xs" title="<?= htmlspecialchars($url) ?>">
                                    <?= htmlspecialchars(strlen($url) > 40 ? substr($url, 0, 40) . '...' : $url) ?>
                                </span>
                                <span class="text-text-secondary text-sm font-mono"><?= number_format($count) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Countries -->
            <div class="card">
                <div class="px-4 py-3 border-b border-border">
                    <h2 class="font-medium text-white">Top Pays</h2>
                </div>
                <div class="divide-y divide-border">
                    <?php if (empty($stats['countries'])): ?>
                        <p class="px-4 py-8 text-center text-text-secondary">Aucune donnée</p>
                    <?php else: ?>
                        <?php foreach (array_slice($stats['countries'], 0, 5, true) as $country => $count): ?>
                            <div class="px-4 py-3 table-row flex items-center justify-between">
                                <span class="text-sm"><?= htmlspecialchars($country) ?></span>
                                <span class="text-text-secondary text-sm font-mono"><?= number_format($count) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Redirections -->
        <div class="card mt-6">
            <div class="px-4 py-3 border-b border-border">
                <h2 class="font-medium text-white">Dernières redirections</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-border text-left text-text-secondary text-xs uppercase">
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">URL</th>
                            <th class="px-4 py-3 font-medium">Pays</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if (empty($stats['recent'])): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-text-secondary">
                                    Aucune redirection récente
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stats['recent'] as $entry): ?>
                                <tr class="table-row">
                                    <td class="px-4 py-3 text-sm">
                                        <?= date('d/m H:i', strtotime($entry['timestamp'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm truncate max-w-xs" title="<?= htmlspecialchars($entry['url'] ?? '') ?>">
                                        <?= htmlspecialchars(strlen($entry['url'] ?? '') > 30 ? substr($entry['url'], 0, 30) . '...' : ($entry['url'] ?? '')) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($entry['country'] ?? 'XX') ?></td>
                                    <td class="px-4 py-3 text-sm font-mono text-text-secondary">
                                        <?= htmlspecialchars($entry['ip'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center text-text-secondary text-sm">
            <p>VintDress Rotator v1.0.0 &middot; <?= date('Y') ?></p>
        </footer>
    </div>
</body>
</html>
