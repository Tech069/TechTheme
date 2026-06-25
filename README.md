# Hyper Game Panel - Auto Installer

One command to install the full Hyper Game Panel on any fresh VPS.

## Requirements
- Fresh Ubuntu 20.04/22.04/24.04 VPS
- Root access
- At least 2GB RAM

## Install Command

```bash
bash <(curl -s https://raw.githubusercontent.com/CodeByCruel/AvtixTheme/main/install.sh)
```

Or with wget:

```bash
bash <(wget -qO- https://raw.githubusercontent.com/CodeByCruel/AvtixTheme/main/install.sh)
```

## What It Installs
- PHP 8.4 + IonCube Loader
- Composer
- Nginx
- MariaDB
- Redis
- Hyper Game Panel (no license required)

## During Installation
You'll be asked for:
- Panel URL (e.g., https://panel.example.com)
- Admin email
- Admin password
- Admin name

Credentials are saved to `/var/www/pterodactyl/credentials.txt` after install.

## License
This is a pre-cracked version. No license key required. DGEN auto-update system is disabled.
