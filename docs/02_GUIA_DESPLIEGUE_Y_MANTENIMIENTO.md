# Guía de Despliegue y Mantenimiento v3.0 — Plataforma PWA CeCap (SURÍ)

## 1. Requisitos del Servidor (Producción Costa Rica)
- **Sistema Operativo:** Ubuntu 24.04 LTS
- **PHP:** 8.3 / 8.4 con extensiones `pgsql`, `pdo_pgsql`, `mbstring`, `xml`, `dom`, `gd`, `curl`, `zip`, `bcmath`, `intl`
- **Base de Datos:** PostgreSQL 15+ / 16 con paquete `postgresql-contrib` (`pgcrypto`, `btree_gist`)
- **Node.js:** 22 LTS + NPM (para compilación Vite 8 + Tailwind CSS v4)
- **HTTPS obligatorio:** Certificado TLS (Let's Encrypt / Nginx) requerido para activación completa de Service Worker e instalación PWA.

## 2. Preparación de PostgreSQL
```sql
CREATE USER cecap WITH PASSWORD 'CONTRASEÑA_FUERTE_PRODUCCION';
CREATE DATABASE cecap OWNER cecap ENCODING 'UTF8';
\c cecap
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

Para ambiente de pruebas automatizadas (Pest 4), crear adicionalmente:
```sql
CREATE DATABASE cecap_testing OWNER cecap ENCODING 'UTF8';
\c cecap_testing
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

## 3. Despliegue Limpio en Producción
```bash
git clone https://github.com/yajairamoralesbarrera/cecap-suri.git
cd cecap-suri
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
# Editar .env con credenciales de producción, APP_ENV=production, APP_DEBUG=false, APP_URL=https://tu-dominio.or.cr
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # Arranque limpio: solo catálogos CR + superadmin@cecap.or.cr
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **IMPORTANTE:** En producción, cambie la contraseña inicial de `superadmin@cecap.or.cr` inmediatamente después del primer ingreso mediante `/cambiar-contrasena` y nunca ejecute `DemoDataSeeder`.

## 4. Respaldos y Retención Documental
Dado que la plataforma opera bajo política estricta **Sin Borrado Físico**, los respaldos deben cubrir tanto PostgreSQL como el almacenamiento privado de adjuntos:
1. **Base de datos diaria (`pg_dump`):**
   ```bash
   pg_dump -Fc -U cecap -h 127.0.0.1 cecap > /backups/cecap_$(date +%Y%m%d).dump
   ```
2. **Adjuntos privados (`storage/app/private/attachments`):**
   Sincronizar diariamente con `rsync` o almacenamiento cifrado externo (S3/Backblaze) verificando integridad mediante los hashes `sha256` registrados en `record_attachments`.

## 5. Mantenimiento Periódico
- **Limpieza de archivos temporales de Livewire:** automática cada 24h (`config/livewire.php`).
- **Verificación de logs:** `storage/logs/laravel.log`.
- **Actualización de versión:**
  ```bash
  git pull origin main
  composer install --no-dev --optimize-autoloader
  npm ci && npm run build
  php artisan migrate --force
  php artisan optimize:clear && php artisan optimize
  ```
- **Monitoreo de salud:** endpoint `GET /up` retorna HTTP 200.
