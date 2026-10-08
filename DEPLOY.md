# Déploiement sur un VPS (Debian / Ubuntu, nginx + PHP-FPM + MySQL)

Ce guide met Codex en ligne sur un seul nom de domaine, comme en développement : l'application Vue, l'API et le serveur MCP partagent la même origine. nginx sert l'application (`frontend/dist`) et envoie `/api`, `/health` et `/mcp` à Symfony (PHP-FPM). Remplacez `codex.example.com` par votre nom de domaine et `/var/www/codex` par le dossier de votre choix.

## 0. Ce qu'il faut avant de commencer

- **PHP 8.4 ou plus** (`composer.json` l'exige). Debian 13 l'a en standard. Debian 12 et Ubuntu 24.04 ont une version plus ancienne : ajoutez le dépôt de Ondřej Surý (`packages.sury.org/php` pour Debian, `ppa:ondrej/php` pour Ubuntu).
- **MySQL 8 ou MariaDB 10.11 ou plus.** Le développement se fait sur MySQL 8.4 ; l'application a aussi été essayée sur MariaDB 11.4 (migrations, import, export, chronologie, recherche plein texte, serveur MCP : tout passe). MariaDB 10.11 (la version de Debian 12) n'a pas été essayée telle quelle, mais elle est très proche. Sur MariaDB, il faut écrire la version dans `DATABASE_URL` (étape 3). Pas besoin de remplacer MariaDB par MySQL.
- Un nom de domaine dont l'enregistrement DNS `A` (et `AAAA` si IPv6) pointe vers l'adresse du VPS. **Attendez que la propagation DNS soit faite** avant l'étape 7.
- Un accès SSH avec un utilisateur qui peut utiliser `sudo`.

## 1. Paquets

```bash
sudo apt update
sudo apt install nginx git unzip curl \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-intl php8.4-curl php8.4-zip php8.4-opcache \
  mariadb-server certbot python3-certbot-nginx   # ou mysql-server si vous préférez MySQL 8
```

Composer : suivez les instructions de https://getcomposer.org/download/ puis `sudo mv composer.phar /usr/local/bin/composer`.
Node.js (version 20 ou plus) n'est utile que pour construire le frontend ; vous pouvez aussi le construire sur votre ordinateur (voir l'étape 4).

## 2. Base de données

```bash
sudo mysql
```

```sql
CREATE DATABASE codex CHARACTER SET utf8mb4;
CREATE USER 'codex'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_LONG';
GRANT ALL PRIVILEGES ON codex.* TO 'codex'@'localhost';
```

## 3. Le code et le backend

```bash
sudo mkdir -p /var/www/codex && sudo chown $USER: /var/www/codex
git clone <adresse-du-dépôt-GitHub> /var/www/codex
cd /var/www/codex/backend
composer install --no-dev --optimize-autoloader
```

Créez `backend/.env.local` (non versionné, c'est là que vivent les secrets) :

```dotenv
APP_ENV=prod
APP_SECRET=<64 caractères aléatoires>
DATABASE_URL="mysql://codex:UN_MOT_DE_PASSE_LONG@127.0.0.1:3306/codex?serverVersion=mariadb-10.11.14&charset=utf8mb4"
MCP_ALLOWED_HOSTS=codex.example.com
```

- `APP_SECRET` : `php -r "echo bin2hex(random_bytes(32));"`. Il signe les cookies de connexion ; le changer déconnecte tout le monde.
- `DATABASE_URL` : le préfixe reste `mysql://` pour MariaDB et pour MySQL. Avec MariaDB, `serverVersion=mariadb-10.11.14` (votre version exacte : `mariadb --version`) ; avec MySQL 8, `serverVersion=8.0`. Cette valeur sert à Doctrine pour choisir le bon dialecte SQL.
- `MCP_ALLOWED_HOSTS` : les noms de domaine auxquels le serveur MCP répond (séparés par des virgules). **Sans votre domaine ici, le serveur MCP refuse les requêtes** (erreur « Invalid Host header »).
- `CORS_ALLOW_ORIGIN` n'a pas besoin d'être changé : l'application et l'API sont sur la même origine, il n'y a pas de requête entre origines.

Puis :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:user:create <votre-identifiant>     # demande le mot de passe
APP_ENV=prod php bin/console cache:clear

# PHP-FPM (utilisateur www-data) doit pouvoir écrire dans var/ (cache, journaux, sessions du serveur MCP)
sudo chown -R www-data:www-data var
```

Le compte `test` / `test` du développement n'existe pas en production : la base y est neuve. N'en créez jamais de ce genre.

## 4. Le frontend

Sur le serveur (Node.js installé) :

```bash
cd /var/www/codex/frontend
npm ci
npm run build          # produit frontend/dist
```

Ou sur votre ordinateur, puis copie : `npm run build` puis `scp -r dist/* utilisateur@serveur:/var/www/codex/frontend/dist/`.

## 5. nginx

Créez `/etc/nginx/sites-available/codex` :

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name codex.example.com;

    root /var/www/codex/frontend/dist;
    index index.html;

    # Un import peut être gros : la limite par défaut (1 Mo) est trop basse.
    client_max_body_size 10m;

    # L'API et le test de santé : Symfony.
    location ~ ^/(api|health)(/|$) {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/codex/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/codex/backend/public;
        fastcgi_read_timeout 60s;
    }

    # Le serveur MCP : Symfony aussi. La clé du livre fait partie de l'adresse, donc on ne la garde pas dans les journaux d'accès.
    location ~ ^/mcp(/|$) {
        access_log off;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/codex/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/codex/backend/public;
        fastcgi_read_timeout 60s;
    }

    # Les fichiers de l'application portent un nom qui change à chaque version : cache long.
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Toute autre adresse est une page de l'application (routeur côté navigateur).
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/codex /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
curl http://codex.example.com/health        # {"status":"ok"}
```

Adaptez `php8.4-fpm.sock` si votre version de PHP est différente (`ls /run/php/`).

## 6. Pare-feu

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Ne laissez pas le port de la base (3306) ouvert vers l'extérieur : l'application s'y connecte en local.

## 7. HTTPS

```bash
sudo certbot --nginx -d codex.example.com
```

Certbot modifie la configuration nginx (redirection de HTTP vers HTTPS) et renouvelle le certificat tout seul. Vérifiez ensuite : `https://codex.example.com`.

Le cookie de connexion devient `Secure` automatiquement quand la requête arrive en HTTPS ; c'est nginx qui termine le HTTPS sur la même machine, donc aucun réglage de proxy n'est nécessaire.

## 8. Reprendre vos données locales

Le plus simple est d'utiliser l'application elle-même :
1. En local, page **Export** du livre, format **JSON (Codex)**, avec les événements secrets.
2. En ligne, créez un livre du même nom, puis page **Import**, collez le JSON et validez. Les identifiants (`id`) des fiches et des événements sont conservés.

Les anciens numéros de livre ne sont pas conservés (le livre en ligne a son propre numéro) : l'adresse MCP et la clé d'API du livre en ligne sont nouvelles ; copiez-les depuis la page **Clé d'API**.

## 9. Brancher l'IA

Page **Clé d'API** du livre en ligne : copiez l'**adresse du serveur MCP** (`https://codex.example.com/mcp/cdx_…`) et ajoutez-la comme connecteur (serveur MCP personnalisé) dans ChatGPT ou Claude, sans authentification supplémentaire.

## 10. Mettre à jour plus tard

```bash
cd /var/www/codex && git pull
cd backend && composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod php bin/console cache:clear && sudo chown -R www-data:www-data var
cd ../frontend && npm ci && npm run build
sudo systemctl reload php8.4-fpm        # vide le cache d'opcode
```

## 11. Sauvegardes

La base contient tout. Une sauvegarde quotidienne simple (à adapter, à garder hors du serveur) :

```bash
mysqldump --single-transaction codex | gzip > ~/codex-$(date +%F).sql.gz
```

Vous pouvez aussi exporter chaque livre en JSON depuis la page Export.

## En cas de problème

| Symptôme | Où regarder |
|---|---|
| Page blanche ou erreur 500 | `backend/var/log/prod.log`, `/var/log/nginx/error.log` |
| 502 Bad Gateway | PHP-FPM arrêté, ou mauvais nom de socket dans `fastcgi_pass` |
| `Invalid Host header` sur `/mcp` | `MCP_ALLOWED_HOSTS` ne contient pas votre domaine, puis `cache:clear` |
| Déconnecté à chaque visite | `APP_SECRET` absent ou changé ; cookies bloqués |
| Import refusé (413) | `client_max_body_size` trop bas dans nginx |
| Erreur de base | `DATABASE_URL` (dont `serverVersion`), utilisateur de la base, migrations non appliquées |
