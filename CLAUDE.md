# VintDress Rotator

## Description
Rotator de liens avec redirection aléatoire, logging et API stats.
Déployé sur plusieurs domaines, géré par le dashboard central.

## Stack
- **Backend**: PHP 8.0+
- **Logs**: Fichiers JSON
- **Géoloc**: ip-api.com (gratuit, cache 24h)

## Structure
```
vintdress-rotator/
├── config.php          # Token API, URLs fallback, constantes
├── index.php           # Rotation + redirection + logging
├── api-logs.php        # Stats et logs récents
├── api-sync.php        # Réception sync depuis dashboard
├── data/
│   ├── urls.json       # URLs actives (sync depuis dashboard)
│   └── geo_cache/      # Cache géoloc IP (24h)
└── logs/
    └── redirections.log  # Logs JSON des redirections
```

## Fonctionnement

### Rotation (index.php)
1. Charge URLs depuis `data/urls.json` ou `FALLBACK_URLS`
2. Sélectionne URL aléatoire
3. Récupère IP réelle (CloudFlare, X-Forwarded-For, etc.)
4. Géolocalise IP (pays + ville via ip-api.com)
5. Log la redirection en JSON
6. Redirige avec `Referrer-Policy: no-referrer`

### Format des logs
```json
{
  "timestamp": "2024-12-20T00:40:00+01:00",
  "url": "https://example.com/page",
  "ip": "192.168.1.1",
  "user_agent": "Mozilla/5.0...",
  "referer": "https://source.com",
  "country": "FR",
  "city": "Paris"
}
```

## API Endpoints

### GET /api-logs.php

**Paramètres:**
- `format`: `json` (défaut), `summary`, `logs`
- `period`: `24h` (défaut), `7d`, `30d`, `all`
- `limit`: 5-50 (défaut: 20)

**Réponses:**

`format=logs` (utilisé par le dashboard):
```json
{
  "success": true,
  "logs": [
    {
      "timestamp": "2024-12-20T00:40:00+01:00",
      "url": "https://...",
      "ip": "xxx.xxx.xxx.xxx",
      "country": "FR",
      "city": "Paris",
      "referer": "...",
      "device": "mobile",
      "browser": "Chrome",
      "os": "Android"
    }
  ]
}
```

`format=json` (stats complètes):
```json
{
  "success": true,
  "period": "24h",
  "stats": {
    "total_clicks": 150,
    "unique_ips": 80,
    "top_urls": {"url1": 50, "url2": 30},
    "top_countries": {"FR": 60, "US": 30},
    "browsers": {"Chrome": 70, "Safari": 30},
    "devices": {"mobile": 80, "desktop": 40}
  }
}
```

### POST /api-sync.php
Reçoit les URLs depuis le dashboard central.

**Headers:** `Authorization: Bearer <API_TOKEN>`

**Body:**
```json
{
  "urls": ["https://url1.com", "https://url2.com"],
  "action": "sync"
}
```

## Fonctions clés

- `getActiveUrls()`: Charge URLs depuis JSON ou fallback
- `getRealIp()`: IP via CloudFlare/proxy/REMOTE_ADDR
- `detectGeoInfo($ip)`: Retourne {country, city}
- `getGeoLocation($ip)`: Appel ip-api.com avec cache
- `logRedirection($url, $info)`: Log JSON avec rotation fichier
- `detectDevice($ua)`: Retourne mobile/tablet/desktop/bot
- `detectBrowser($ua)`: Retourne Chrome/Firefox/Safari/Edge/Bot/Other
- `detectOS($ua)`: Retourne Windows/macOS/iOS/Android/Linux
- `getRecentLogs($limit)`: Dernières entrées avec parsing UA

## Déploiement
1. Copier `config.example.php` → `config.php`
2. Définir `API_TOKEN` (doit matcher token dans dashboard)
3. Définir `FALLBACK_URLS` (URLs par défaut)
4. S'assurer que `data/` et `logs/` sont en écriture (755)

## Sécurité
- Token API pour la synchronisation
- Cache géoloc pour limiter requêtes externes
- Rotation auto des logs si > 10MB
- Header `Referrer-Policy: no-referrer`
