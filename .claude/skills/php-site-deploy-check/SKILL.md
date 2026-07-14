---
name: php-site-deploy-check
description: Diagnose and prevent deployment issues for this project's PHP site (aariz.daanas.online), which is served from a Docker container on the DAA NAS host and populated via NAS auto-sync (not git pull). Use this whenever adding a new PHP page, adding a DB-connecting feature, seeing "Not Found" for a page that was just added, or seeing "could not find driver" / PDO connection errors on the live site.
---

# PHP site deploy check (aariz.daanas.online)

This project's live site is **not** deployed by `git pull` on the server. Files
reach the server via a separate NAS auto-sync process, and are served by a
Docker container whose PHP image may be missing extensions the code needs.
Two failure modes have bitten this project before — check both whenever a
new page doesn't show up correctly.

## Deployment topology (as of this writing)

- Git repo (`marsalanumer93/aariz`) commits are the source of truth for code,
  but the server directory (`/opt/stacks/test-db-site` on host `DAA`) is
  **not a git checkout**. A NAS sync job pulls files from the repo/NAS into
  `/srv/dev-disk-by-uuid-.../web-data/aariz/`.
- The `test-db-site` container (Docker Compose, `compose.yml` in
  `/opt/stacks/test-db-site`) bind-mounts a host directory to
  `/var/www/html`. **Verify the mount targets the `aariz` subfolder
  specifically** (`web-data/aariz:/var/www/html`), not the whole `web-data`
  root — the root also contains sibling site folders (`mysite`,
  `test-auto-subdomain`, `test-sync`, etc.), and if the mount points there,
  every page in this repo will 404 at the clean URL and only be reachable
  under `/aariz/<file>.php`.
- The container previously ran the bare `php:8.2-apache` image, which lacks
  `pdo_mysql`/`mysqli`. Any code using `PDO` with the `mysql:` DSN fails with
  `could not find driver` until the image is rebuilt from a `Dockerfile`
  that runs `docker-php-ext-install pdo_mysql mysqli`, and `compose.yml`
  uses `build: .` instead of `image: php:8.2-apache`.

## Checklist when a page doesn't work as expected

1. **404 "Not Found" (Apache) for a page you just added:**
   - The NAS sync may not have run yet, or the file landed in the wrong
     subfolder. On the server:
     ```bash
     find /srv/dev-disk-by-uuid-*/web-data -iname "<your-file>.php"
     ```
   - Confirm the path found matches what the container's `/var/www/html`
     mount expects. If the file is nested under `web-data/aariz/...` but
     the mount is the whole `web-data` root, either narrow the mount (see
     below) or the page is only reachable at `/aariz/<file>.php`.
   - Also check the container actually sees it:
     ```bash
     docker exec test-db-site ls -la /var/www/html
     ```

2. **"could not find driver" / PDO connection errors:**
   - Check installed extensions in the running container:
     ```bash
     docker exec test-db-site php -m | grep -i pdo
     ```
   - If `pdo_mysql` is missing, this is an **image** problem, not a host
     problem — installing `php-mysql` via `apt` on the host does nothing
     for a containerized PHP. Fix via `Dockerfile` + `compose.yml`
     `build: .`, see below.
   - `apt install php-mysql` on the bare host only matters if PHP itself
     runs on the host (no container) — check `docker ps` first to know
     which case you're in.

3. **Fixing a missing PHP extension (image rebuild):**
   - Ensure a `Dockerfile` exists next to `compose.yml` on the server:
     ```dockerfile
     FROM php:8.2-apache
     RUN docker-php-ext-install pdo_mysql mysqli
     ```
   - Ensure `compose.yml`'s service uses `build: .` instead of
     `image: php:8.2-apache`.
   - Rebuild and recreate:
     ```bash
     cd /opt/stacks/test-db-site
     docker compose up -d --build
     ```
   - Keep this `Dockerfile` committed in the git repo too, so the fix is
     documented and reproducible even though the server doesn't pull from
     git directly.

4. **Fixing a wrong volume mount (nested subfolder instead of root):**
   ```bash
   sed -i 's#web-data:/var/www/html#web-data/aariz:/var/www/html#' \
     /opt/stacks/test-db-site/compose.yml
   cd /opt/stacks/test-db-site
   docker compose up -d
   ```

5. **After any fix, verify end-to-end**, not just that the container
   started — the container starting successfully does not mean the code
   path works:
   ```bash
   curl -s http://aariz.daanas.online/<your-file>.php | tail -30
   ```
   or ask the user to reload the page in a browser and confirm the actual
   rendered result (e.g. a DB connectivity page shows "CONNECTED" and a
   real version string, not just "200 OK").

## When adding a new feature that needs a DB/PHP extension

Before assuming it'll just work in production, ask (or check):
- Does the live container have every PHP extension the new code needs?
  (`docker exec test-db-site php -m`)
- Does the file land where the container's docroot mount expects it?
- Is there a `Dockerfile` + `build:` already wired up, or is the container
  still using a bare upstream image that needs to be rebuilt?

Don't assume a `git push` alone gets code live — always confirm how files
actually reach the server directory for this project (NAS sync, not git
pull) before telling the user a deploy is complete.
