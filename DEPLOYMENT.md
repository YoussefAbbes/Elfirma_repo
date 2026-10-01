# Deployment guide — EL FIRMA

This app is a Symfony 6.4 application. Browsing the storefront is public; logging
in, the back office (`/elfirma`, `/admin`) and all interactions (cart, checkout,
ratings) require authentication.

## 1. Requirements
- PHP 8.2 with the usual Symfony extensions (`ctype`, `iconv`, `pdo_mysql`,
  `gd`, `zip`, and ideally `intl`).
- MySQL/MariaDB.
- Composer.
- (Optional, for AI/face/fingerprint features) the Python sidecar services — see §6.

For Railway/Railpack, make sure the build uses PHP 8.2 rather than the latest
auto-selected version. This repository now pins Composer to PHP 8.2 and declares
`ext-gd` and `ext-zip`, which matches the supported dependency set.

## 2. Get the code & dependencies
```bash
git clone <repo> && cd <repo>
composer install --no-dev --optimize-autoloader
```

## 3. Configure the environment (NEVER commit secrets)
Set these as real server environment variables, or create an untracked
`.env.local`. Use `.env.example` as the list of variables. At minimum:

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<random 32-byte hex>
DATABASE_URL="mysql://USER:PASSWORD@HOST:3306/DBNAME?serverVersion=8.0&charset=utf8mb4"
APP_PUBLIC_BASE_URL=https://your-domain.com   # used for QR-code / absolute links
MAILER_DSN=...                                 # plus the API keys you actually use
```
Then compile env (optional but recommended): `composer dump-env prod`.

> Use a dedicated DB user with a strong password — never `root` / no password.

## 4. Database
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

## 5. Build & cache
```bash
php bin/console asset-map:compile      # AssetMapper: compile assets for prod
php bin/console cache:clear
php bin/console cache:warmup
```

## 6. Web server
- Document root = `public/` only. Front controller is `public/index.php`.
- Serve over **HTTPS**. Session cookies are already set to `cookie_secure: auto`
  (secure automatically under HTTPS).
- If behind a reverse proxy / load balancer, configure Symfony `trusted_proxies`
  so generated URLs use the correct scheme/host.

## 7. Optional Python sidecar services
Several features call external Python services and will be **disabled/broken** if
those services are not running and reachable:
- AI chatbot & supplier analytics → FastAPI, configured via `AI_FASTAPI_BASE_URL`
  (default `http://localhost:8002`).
- Face ID login → `FACE_ID_HOST` / `FACE_ID_PORT` (default `127.0.0.1:8765`).
  When the service is not bound to localhost (e.g. on Railway) set the same random
  `FACE_ID_API_TOKEN` on **both** the Face ID service and the Symfony app; the
  service refuses to start on a public interface without it.
- RAG assistant → local Python (`RAG_*` vars).
- Fingerprint reader → Java/ZK bridge (`FINGERPRINT_HOST` / `FINGERPRINT_PORT`).

The FastAPI services send no CORS headers by default because Symfony calls them
server-side; set `CORS_ALLOWED_ORIGINS` only if a browser must call them directly.

Either deploy these alongside the app (and point the env vars at them) or accept
that those specific features won't work. The core app (auth, storefront, orders,
back office) does not depend on them.

## 8. Post-deploy checklist
- [ ] `APP_ENV=prod`, `APP_DEBUG=0`.
- [ ] Real DB user/password; migrations applied.
- [ ] All secrets set via env, none in committed files.
- [ ] HTTPS enforced; `APP_PUBLIC_BASE_URL` set to the real domain.
- [ ] Leaked API keys rotated at each provider.
- [ ] `FACE_ID_API_TOKEN` set on the app and the Face ID service (if deployed).
- [ ] OAuth redirect URIs (Google/GitHub) updated to the production domain.
