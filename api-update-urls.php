<?php
/**
 * API pour recevoir les URLs depuis le dashboard central
 *
 * Méthode : POST
 * Auth : Token dans header Authorization: Bearer <token>
 * Body JSON : { "urls": ["url1", "url2", ...] }
 *
 * Réponses :
 * - 200 : Succès
 * - 400 : Requête invalide
 * - 401 : Non autorisé
 * - 405 : Méthode non autorisée
 * - 429 : Rate limit dépassé
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Fichier pour le rate limiting
$rateLimitFile = DATA_DIR . '/rate_limit.json';

/**
 * Envoie une réponse JSON et termine le script
 */
function jsonResponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Vérifie le rate limiting
 */
function checkRateLimit(string $file): bool
{
    $now = time();
    $data = [];

    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true) ?: [];
    }

    // Nettoyer les anciennes entrées
    $data = array_filter($data, fn($timestamp) => ($now - $timestamp) < RATE_LIMIT_WINDOW);

    // Vérifier la limite
    if (count($data) >= RATE_LIMIT_MAX) {
        return false;
    }

    // Ajouter cette requête
    $data[] = $now;
    file_put_contents($file, json_encode($data));

    return true;
}

/**
 * Valide une URL
 */
function isValidUrl(string $url): bool
{
    // Vérifier le format de base
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    // Vérifier le protocole (https ou http uniquement)
    $parsed = parse_url($url);
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
        return false;
    }

    // Vérifier qu'il n'y a pas de scripts malicieux
    $lowerUrl = strtolower($url);
    $forbidden = ['javascript:', 'data:', 'vbscript:', '<script', 'onclick', 'onerror'];
    foreach ($forbidden as $pattern) {
        if (strpos($lowerUrl, $pattern) !== false) {
            return false;
        }
    }

    return true;
}

/**
 * Vérifie le token d'authentification
 */
function verifyToken(): bool
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        return false;
    }

    // Format attendu : "Bearer <token>"
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return false;
    }

    $token = trim($matches[1]);
    return hash_equals(ROTATOR_TOKEN, $token);
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'error' => 'Méthode non autorisée. Utilisez POST.'
    ]);
}

// Vérifier l'authentification
if (!verifyToken()) {
    jsonResponse(401, [
        'success' => false,
        'error' => 'Token invalide ou manquant.'
    ]);
}

// Vérifier le rate limiting
if (!checkRateLimit($rateLimitFile)) {
    jsonResponse(429, [
        'success' => false,
        'error' => 'Trop de requêtes. Réessayez dans quelques secondes.'
    ]);
}

// Lire le body JSON
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(400, [
        'success' => false,
        'error' => 'JSON invalide : ' . json_last_error_msg()
    ]);
}

// Vérifier la présence du champ urls
if (!isset($input['urls']) || !is_array($input['urls'])) {
    jsonResponse(400, [
        'success' => false,
        'error' => 'Le champ "urls" est requis et doit être un tableau.'
    ]);
}

// Valider chaque URL
$validUrls = [];
$invalidUrls = [];

foreach ($input['urls'] as $url) {
    if (!is_string($url)) {
        $invalidUrls[] = ['url' => $url, 'reason' => 'Doit être une chaîne de caractères'];
        continue;
    }

    $url = trim($url);

    if (empty($url)) {
        continue; // Ignorer les URLs vides
    }

    if (isValidUrl($url)) {
        $validUrls[] = $url;
    } else {
        $invalidUrls[] = ['url' => $url, 'reason' => 'Format URL invalide'];
    }
}

// Vérifier qu'il y a au moins une URL valide
if (empty($validUrls)) {
    jsonResponse(400, [
        'success' => false,
        'error' => 'Aucune URL valide fournie.',
        'invalid_urls' => $invalidUrls
    ]);
}

// Créer le dossier data si nécessaire
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Sauvegarder les URLs
$data = [
    'urls' => array_values(array_unique($validUrls)),
    'updated_at' => date('c'),
    'updated_from_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

$result = file_put_contents(URLS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if ($result === false) {
    jsonResponse(500, [
        'success' => false,
        'error' => 'Erreur lors de l\'écriture du fichier.'
    ]);
}

// Succès
$response = [
    'success' => true,
    'message' => 'URLs mises à jour avec succès.',
    'urls_count' => count($data['urls']),
    'updated_at' => $data['updated_at']
];

if (!empty($invalidUrls)) {
    $response['warnings'] = [
        'invalid_urls' => $invalidUrls
    ];
}

jsonResponse(200, $response);
