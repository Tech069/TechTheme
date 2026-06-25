# Pterodactyl Panel - Docker Setup

Run the Pterodactyl game server panel using Docker.

## Requirements
- Docker Desktop (Windows/Mac) or Docker Engine (Linux)
- Docker Compose v2+

## Quick Start

```bash
cd docker
docker compose up -d
```

Panel will be available at: **http://localhost:8080**

## Create Admin User

After containers start, run:

```bash
docker compose exec panel php artisan p:user:make \
  --email="admin@example.com" \
  --name-first="Admin" \
  --name-last="User" \
  --username="admin" \
  --password="admin123" \
  --admin=1 \
  --no-interaction
```

## Login

- **URL:** http://localhost:8080
- **Email:** admin@example.com
- **Password:** admin123

## Stop

```bash
docker compose down
```

## Data

All data is stored in `./data/`. Delete it to start fresh:

```bash
docker compose down
rm -rf data
docker compose up -d
```

## Note

This is vanilla Pterodactyl panel. For the full Hyper panel with all features, use the VPS installer instead:

```bash
bash <(curl -s https://raw.githubusercontent.com/CodeByCruel/AvtixTheme/main/install.sh)
```
