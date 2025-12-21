# VintDress Rotator

Rotateur d'URLs pour la gestion des backlinks SEO VintDress.

## Description

Ce rotator redirige les visiteurs aléatoirement vers différentes URLs configurées. Il peut être géré manuellement ou via le dashboard central VintDress Link Manager.

## Installation

### Avec Docker

```bash
docker build -t vintdress-rotator .
docker run -d -p 80:80 \
  -e ROTATOR_TOKEN=votre_token_secret \
  -e ROTATOR_FALLBACK_URLS=https://vintdress.fr,https://vintdress.fr/blog \
  vintdress-rotator
```

### Sur Coolify

1. Créer une nouvelle application depuis ce repo
2. Configurer les variables d'environnement :
   - `ROTATOR_TOKEN` : Token secret pour l'API
   - `ROTATOR_FALLBACK_URLS` : URLs par défaut (séparées par des virgules)

## Variables d'environnement

| Variable | Description | Défaut |
|----------|-------------|--------|
| `ROTATOR_TOKEN` | Token d'authentification API | `change_me_in_production` |
| `ROTATOR_FALLBACK_URLS` | URLs de fallback | `https://vintdress.fr` |

## Endpoints

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/` | GET | Redirection aléatoire |
| `/stats.php` | GET | Dashboard des statistiques |
| `/api-health.php` | GET | Health check |
| `/api-logs.php` | GET | Statistiques JSON |
| `/api-update-urls.php` | POST | Mise à jour des URLs |

## API

### Health Check

```bash
curl https://votre-rotator.com/api-health.php
```

### Récupérer les stats

```bash
curl "https://votre-rotator.com/api-logs.php?period=24h"
```

### Mettre à jour les URLs

```bash
curl -X POST https://votre-rotator.com/api-update-urls.php \
  -H "Authorization: Bearer votre_token" \
  -H "Content-Type: application/json" \
  -d '{"urls": ["https://url1.com", "https://url2.com"]}'
```

## Sécurité

- L'API de mise à jour nécessite un token Bearer
- Rate limiting : 10 requêtes par minute
- Les dossiers `data/` et `logs/` sont protégés par `.htaccess`

## Licence

Projet privé VintDress.
