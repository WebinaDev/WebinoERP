<?php

namespace Modules\Core\Services;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Parity with webinocrm class-cache-optimizer.php.
 *
 * - Uses a tagged `webinocrm` namespace (emulated with a key prefix + per-tag index when the
 *   underlying cache store does not support tags).
 * - Exposes `remember`, `forget`, `flushTag`, `flushAll`, `stats`, `clearEntity` helpers.
 */
class CoreCacheService
{
    public const PREFIX = 'webinocrm';

    public const TAG_INDEX_PREFIX = self::PREFIX.':_tag:';

    /**
     * @template T
     *
     * @param  Closure():T  $callback
     * @param  list<string>  $tags
     * @return T
     */
    public function remember(string $key, int|DateInterval|DateTimeInterface $ttl, Closure $callback, array $tags = []): mixed
    {
        $full = $this->key($key);
        foreach ($tags as $tag) {
            $this->attachKeyToTag($full, $tag);
        }

        return Cache::remember($full, $ttl, $callback);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, int|DateInterval|DateTimeInterface|null $ttl = null, array $tags = []): void
    {
        Cache::put($this->key($key), $value, $ttl);
        foreach ($tags as $tag) {
            $this->attachKeyToTag($this->key($key), $tag);
        }
    }

    public function forget(string $key): void
    {
        Cache::forget($this->key($key));
    }

    /**
     * Invalidate every cached entry associated with the tag.
     */
    public function flushTag(string $tag): int
    {
        $index = $this->tagIndexKey($tag);
        $keys = (array) Cache::get($index, []);
        foreach ($keys as $k) {
            Cache::forget($k);
        }
        Cache::forget($index);

        return count($keys);
    }

    /**
     * Parity with `clear_entity_cache`: forgets by pattern `{entity}_{id}`, `{entity}_list`, and tag.
     */
    public function clearEntity(string $entity, ?int $id = null): void
    {
        $this->forget($entity.'_list');
        if ($id !== null) {
            $this->forget($entity.'_'.$id);
        }
        $this->flushTag('entity:'.$entity);
    }

    /**
     * Parity with `clear_all`: drop every `webinocrm:*` transient.
     * Works for the database cache store — for other stores falls back to full flush.
     */
    public function flushAll(): int
    {
        if (config('cache.default') === 'database') {
            $table = config('cache.stores.database.table', 'cache');
            $prefix = config('cache.prefix', '').self::PREFIX;
            $affected = DB::table($table)->where('key', 'like', $prefix.'%')->delete();

            return (int) $affected;
        }

        Cache::flush();

        return -1;
    }

    public function stats(): array
    {
        if (config('cache.default') !== 'database') {
            return ['driver' => config('cache.default'), 'entries' => null, 'size_bytes' => null];
        }
        $table = config('cache.stores.database.table', 'cache');
        $prefix = config('cache.prefix', '').self::PREFIX;
        $row = DB::table($table)
            ->where('key', 'like', $prefix.'%')
            ->selectRaw('COUNT(*) as c, SUM(LENGTH(value)) as s')
            ->first();

        return [
            'driver' => 'database',
            'entries' => (int) ($row->c ?? 0),
            'size_bytes' => (int) ($row->s ?? 0),
        ];
    }

    private function key(string $key): string
    {
        if (str_starts_with($key, self::PREFIX.':')) {
            return $key;
        }

        return self::PREFIX.':'.$key;
    }

    private function tagIndexKey(string $tag): string
    {
        return self::TAG_INDEX_PREFIX.$tag;
    }

    private function attachKeyToTag(string $key, string $tag): void
    {
        $index = $this->tagIndexKey($tag);
        $keys = (array) Cache::get($index, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($index, $keys, now()->addDay());
        }
    }
}
