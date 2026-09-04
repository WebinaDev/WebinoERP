<?php

namespace Modules\Platform\Support;

/**
 * Isolated per-site Docker Compose + Caddy for WebinoDashboard tenants.
 *
 * Shared images (webino-backend / webino-next) are reused; data, networks, and
 * container names are unique per slug so many sites can run on one host.
 *
 * Channel tags: latest (default) | beta (git HEAD). Stable is reserved for later.
 */
final class TenantSiteStack
{
    public static function projectName(string $slug): string
    {
        $safe = strtolower(preg_replace('/[^a-z0-9-]/', '', $slug) ?: 'site');

        return 'ws-'.$safe;
    }

    /** Normalize product channel to a docker image tag suffix. */
    public static function imageTag(string $channel = 'latest'): string
    {
        $channel = strtolower(trim($channel));
        if ($channel === 'beta') {
            return 'beta';
        }

        // stable is not wired yet — fall back to latest for compose until shipped.
        return 'latest';
    }

    public static function composeYaml(string $slug, string $channel = 'latest'): string
    {
        $project = self::projectName($slug);
        $internal = $project.'_net';
        $tag = self::imageTag($channel);

        return <<<YAML
# Isolated tenant stack. Images: webino-backend:{$tag}, webino-next:{$tag}
# Built from https://github.com/Webinadev/WebinoDashboard
# Internal net for db/redis; proxy (webino_sites) so ERP Caddy can reach
# container names. Browser /api → Caddy → *-backend:8080; pages → *-frontend:3000.
services:
  db:
    image: postgres:15-alpine
    container_name: {$project}-db
    restart: unless-stopped
    environment:
      POSTGRES_DB: webino
      POSTGRES_USER: webino
      POSTGRES_PASSWORD: \${DB_PASSWORD}
    volumes:
      - db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U webino -d webino"]
      interval: 5s
      timeout: 5s
      retries: 12
      start_period: 10s
    networks: [{$internal}]
  redis:
    image: redis:7-alpine
    container_name: {$project}-redis
    restart: unless-stopped
    networks: [{$internal}]
  backend:
    image: webino-backend:{$tag}
    container_name: {$project}-backend
    restart: unless-stopped
    env_file: .env
    volumes:
      - ./.env:/var/www/html/.env:ro
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_started
    networks: [{$internal}, proxy]
  frontend:
    image: webino-next:{$tag}
    container_name: {$project}-frontend
    restart: unless-stopped
    environment:
      INTERNAL_API_URL: http://backend:8080
      API_PROXY_TARGET: http://backend:8080
    depends_on: [backend]
    networks: [{$internal}, proxy]
volumes:
  db:
networks:
  {$internal}:
    driver: bridge
    name: {$internal}
  proxy:
    external: true
    name: webino_sites
YAML;
    }

    public static function caddySnippet(string $domain, string $slug): string
    {
        $project = self::projectName($slug);

        // Same split as ERP Caddyfile: /api → Octane backend, rest → Next.
        // Backend must be on webino_sites or Caddy returns 502 while HTML still loads.
        return <<<CADDY
{$domain} {
  encode gzip
  handle /api/* {
    reverse_proxy {$project}-backend:8080 {
      header_up Host {host}
      header_up X-Forwarded-Proto {scheme}
    }
  }
  handle {
    reverse_proxy {$project}-frontend:3000 {
      header_up Host {host}
    }
  }
}
CADDY;
    }

    /**
     * @return list<string>
     */
    public static function proxyContainerNames(string $slug): array
    {
        $project = self::projectName($slug);

        return [$project.'-backend', $project.'-frontend'];
    }
}
