<?php
/**
 * Configuration du Rotator VintDress
 *
 * Ce fichier contient les paramètres de configuration du rotator.
 * Les variables d'environnement ont la priorité sur les valeurs par défaut.
 */

// Token d'authentification pour l'API (utilisé par le dashboard central)
define('ROTATOR_TOKEN', getenv('ROTATOR_TOKEN') ?: 'change_me_in_production');

// URLs de fallback si urls.json n'existe pas ou est vide
$fallbackUrls = getenv('ROTATOR_FALLBACK_URLS')
    ? explode(',', getenv('ROTATOR_FALLBACK_URLS'))
    : [
        'https://vintdress.com',
        'https://vintdress.com/blog'
    ];

define('FALLBACK_URLS', $fallbackUrls);

// Chemin vers le fichier de données
define('DATA_DIR', __DIR__ . '/data');
define('URLS_FILE', DATA_DIR . '/urls.json');
define('LOGS_DIR', __DIR__ . '/logs');
define('LOGS_FILE', LOGS_DIR . '/redirections.log');

// Rate limiting pour l'API (requêtes par minute)
define('RATE_LIMIT_MAX', 10);
define('RATE_LIMIT_WINDOW', 60); // secondes

// Configuration du logging
define('LOG_ENABLED', true);
define('LOG_MAX_SIZE', 1024 * 1024 * 1024); // 1 Go max

// Timezone
date_default_timezone_set('Europe/Paris');
