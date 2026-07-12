<?php

namespace App\Services;

/**
 * Optional Elasticsearch indexer (activate when ELASTICSEARCH_HOSTS is set).
 */
class SearchIndexer
{
    public function enabled(): bool
    {
        return (bool) env('ELASTICSEARCH_HOSTS');
    }

    /**
     * @param  array<string,mixed>  $document
     */
    public function upsert(string $index, string $id, array $document): void
    {
        if (! $this->enabled()) {
            return;
        }
        // Client wiring: composer require elasticsearch/elasticsearch
    }

    public function delete(string $index, string $id): void
    {
        if (! $this->enabled()) {
            return;
        }
    }
}
