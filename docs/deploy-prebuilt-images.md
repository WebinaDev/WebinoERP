# Deploying prebuilt images (no builds on the server)

The production server has 8 GB RAM; `next build` there can OOM the box. `.github/workflows/images.yml`
builds the images on every push to `main` and pushes them to GHCR. The existing path
(`update.sh`, `up -d --build`) is unchanged.

## Real stack

Project `webinoerm`, files `docker-compose.yml` + `docker-compose.prod.yml` + `docker-compose.iran.yml`.

| Service | Source today | With prebuilt images |
|---|---|---|
| backend, worker, scheduler, ws (Reverb) | build `docker/php/Dockerfile` | `ghcr.io/webinadev/webino-erp-backend:<sha>` |
| frontend | build `WebinoERP/docker/next/Dockerfile` (context `..`) | `ghcr.io/webinadev/webino-erp-frontend:<sha>` |
| db, redis, web (Caddy), edge (nginx) | upstream images | unchanged |

Tags: full sha, short sha, `latest` (main). Cache `type=gha`. Auth `GITHUB_TOKEN` (`packages: write`).
CI uses Docker Hub base images; the Iran mirror overlay only affects build args, which are ignored with `--no-build`.

**Important:** backend/worker/scheduler/ws bind-mount `./backend` — the PHP code comes from the host
checkout, the image is only the PHP runtime. So the checkout must be at the same sha (the script enforces it).
There is no `migrate` service; migrations run like `update.sh`: pg_dump backup →
`php artisan migrate --force` → `site-builder:assert-schema` (`scripts/apply-erp-migrations.sh`).

## Build-time env (Next.js)

No per-tenant `NEXT_PUBLIC_*` is passed at build time; API calls go to relative `/api`, proxied
server-side via runtime `INTERNAL_API_URL`/`API_PROXY_TARGET`. One image serves all. Note:
`NEXT_PUBLIC_REVERB_*` in compose `environment:` never reached client code (inlined at build); same
as before. If needed, inject at runtime instead of a per-site build matrix.

## One-time server setup

```
echo "<token with read:packages>" | docker login ghcr.io -u <github-user> --password-stdin
docker pull ghcr.io/webinadev/webino-erp-frontend:latest   # checks ghcr.io is reachable from IR
```

## Deploy

```
cd /opt/webinoerp            # the ERP checkout
git fetch origin && git checkout --detach <SHA>
IMAGE_TAG=<SHA> GITHUB_TOKEN=<read token, optional> scripts/deploy-pull.sh
```

`scripts/deploy-pull.sh`:
- requires `IMAGE_TAG` = full 40-char sha (`latest` only with `ALLOW_LATEST=1`);
- if `GITHUB_TOKEN`/`GH_TOKEN` is set, refuses unless the `images.yml` run for that sha succeeded;
- refuses if `git rev-parse HEAD` != `IMAGE_TAG` (`ALLOW_SHA_MISMATCH=1` to override);
- `COMPOSE_FILES` (default `docker-compose.yml:docker-compose.prod.yml:docker-compose.iran.yml`),
  `COMPOSE_PROJECT` (default `webinoerm`); `docker-compose.prod-images.yml` appended last;
- `config -q`, `pull`, `up -d --no-build` (no `--remove-orphans`), then backup + migrations
  (`SKIP_MIGRATE=1` to skip), `ps`.

Rollback: same command with the previous sha (and checkout).

## Tenant site images (webino-backend / webino-next)

Built today by `scripts/build-webino-dashboard-images.sh`. WebinoDashboard CI builds the same
Dockerfiles as `ghcr.io/webinadev/webino-dashboard-{backend,frontend}`.

```
WEBINO_DASHBOARD_IMAGE_TAG=<Dashboard SHA> [WEBINO_IMAGE_TAG=beta] scripts/pull-webino-dashboard-images.sh
```
Pulls and retags to `webino-backend:latest` / `webino-next:latest` (+ channel). Same sha/CI rules.
It does not recreate tenant containers: recreate + migrate each site from the ERP panel, one at a time.
The provisioner (`LocalSameVpsProvisioner::forceBuildImages`) still calls the build script; switching it is a separate change.
