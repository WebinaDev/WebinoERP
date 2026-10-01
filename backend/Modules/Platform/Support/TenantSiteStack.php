<?php

namespace Modules\Platform\Support;

/**
 * Isolated per-site Docker Compose + Caddy for WebinoDashboard tenants.
 *
 * Shared images (webino-backend / webino-next) are reused; data, networks, and
 * container names are unique per slug so many sites can run on one host.
 *
 * Service names on the shared webino_sites network MUST be unique (ws-{slug}-*).
 * Generic names "backend"/"frontend" steal Docker DNS from ERP Caddy.
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

    public static function backendService(string $slug): string
    {
        return self::projectName($slug).'-backend';
    }

    public static function frontendService(string $slug): string
    {
        return self::projectName($slug).'-frontend';
    }

    /**
     * URLs to probe from inside the tenant frontend container.
     *
     * The unique service hostname resolves on both the private net and
     * webino_sites (the same name Caddy and the Next app use). "backend" is
     * only an alias on the private net and can resolve to the ERP backend on
     * webino_sites, so it is a probe fallback only.
     *
     * @return list<string>
     */
    public static function frontendBackendProbeUrls(string $slug): array
    {
        $service = self::backendService($slug);

        return array_values(array_unique([
            'http://'.$service.':8080/api/v1/health/metrics',
            'http://backend:8080/api/v1/health/metrics',
        ]));
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
        $backendSvc = $project.'-backend';
        $frontendSvc = $project.'-frontend';
        // Same sanitized slug as ws-{slug}-backend. Dashboard middleware uses
        // WEBINO_SITE_SLUG to rewrite a leftover "backend" host to this name.
        $safeSlug = substr($project, 3);
        $api = 'http://'.$backendSvc.':8080';

        return <<<YAML
# Isolated tenant stack. Images: webino-backend:{$tag}, webino-next:{$tag}
# Built from https://github.com/Webinadev/WebinoDashboard
# Service names are unique (not "backend"/"frontend") so they do not steal
# Docker DNS from ERP Caddy on webino_sites.
# The frontend is on both the private net and webino_sites. On webino_sites
# the short name "backend" is the ERP API, so Next middleware and the API
# proxy must call {$api}. WEBINO_SITE_SLUG={$safeSlug} is the same slug the
# dashboard image uses if a generic "backend" host is still configured.
# A private-net alias "backend" remains for health-probe fallback only.
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
  {$backendSvc}:
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
    networks:
      {$internal}:
        aliases: [backend]
      proxy:
  {$frontendSvc}:
    image: webino-next:{$tag}
    container_name: {$project}-frontend
    restart: unless-stopped
    environment:
      INTERNAL_API_URL: {$api}
      API_PROXY_TARGET: {$api}
      WEBINO_SITE_SLUG: "{$safeSlug}"
    depends_on:
      - {$backendSvc}
    networks:
      {$internal}:
        aliases: [frontend]
      proxy:
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
        return [self::backendService($slug), self::frontendService($slug)];
    }
}
