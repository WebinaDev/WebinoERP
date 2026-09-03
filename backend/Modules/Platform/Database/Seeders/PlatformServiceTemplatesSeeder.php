<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Entities\PlatformServiceTemplate;

class PlatformServiceTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['postgresql', 'PostgreSQL', 'databases', 'image: postgres:16-alpine
environment:
  POSTGRES_PASSWORD: secret
ports: [\'5432:5432\']
'],
            ['mysql', 'MySQL', 'databases', 'image: mysql:8
environment:
  MYSQL_ROOT_PASSWORD: secret
ports: [\'3306:3306\']
'],
            ['mariadb', 'MariaDB', 'databases', 'image: mariadb:11
environment:
  MARIADB_ROOT_PASSWORD: secret
'],
            ['mongodb', 'MongoDB', 'databases', 'image: mongo:7
ports: [\'27017:27017\']
'],
            ['redis', 'Redis', 'databases', 'image: redis:7-alpine
ports: [\'6379:6379\']
'],
            ['keydb', 'KeyDB', 'databases', 'image: eqalpha/keydb
'],
            ['dragonfly', 'Dragonfly', 'databases', 'image: docker.dragonflydb.io/dragonflydb/dragonfly
'],
            ['clickhouse', 'ClickHouse', 'databases', 'image: clickhouse/clickhouse-server
'],
            ['plausible', 'Plausible Analytics', 'analytics', 'image: plausible/analytics
'],
            ['umami', 'Umami', 'analytics', 'image: ghcr.io/umami-software/umami:postgresql-latest
'],
            ['n8n', 'n8n', 'automation', 'image: n8nio/n8n
ports: [\'5678:5678\']
'],
            ['ghost', 'Ghost', 'cms', 'image: ghost:5-alpine
'],
            ['wordpress', 'WordPress', 'cms', 'image: wordpress:6-apache
'],
            ['minio', 'MinIO', 'storage', 'image: minio/minio
command: server /data
'],
            ['meilisearch', 'Meilisearch', 'search', 'image: getmeili/meilisearch:v1.8
'],
            ['uptime-kuma', 'Uptime Kuma', 'monitoring', 'image: louislam/uptime-kuma:1
'],
            ['grafana', 'Grafana', 'monitoring', 'image: grafana/grafana
'],
            ['homepage', 'Homepage', 'dashboards', 'image: ghcr.io/gethomepage/homepage:latest
'],
            ['webino-dashboard', 'Webino Dashboard', 'webino', '# Provisioned via Platform Webino product
services: {}
'],
        ];

        foreach ($rows as [$slug, $name, $category, $compose]) {
            PlatformServiceTemplate::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => $name,
                    'compose' => "services:\n  app:\n" . preg_replace('/^/m', '    ', trim($compose)) . "\n",
                ]
            );
        }
    }
}
