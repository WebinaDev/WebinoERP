<?php

namespace Modules\Core\Services\OrgGit;

class GiteaOrgGitProvider extends AbstractOrgGitProvider
{
    public function provider(): string
    {
        return 'gitea';
    }

    protected function apiBase(): string
    {
        return rtrim($this->baseUrl, '/').'/api/v1';
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'token '.$this->token,
        ];
    }

    public function testConnection(): array
    {
        $res = $this->http()->get($this->apiBase().'/user');
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'Gitea auth failed: HTTP '.$res->status()];
        }

        return ['ok' => true, 'user' => $res->json('login')];
    }

    public function resolveCloneUrl(string $owner, string $repo): string
    {
        return rtrim($this->baseUrl, '/').'/'.$owner.'/'.$repo.'.git';
    }

    public function listOrgRepos(?string $org = null): array
    {
        $org = $org ?: $this->org;
        if (! $org) {
            return [];
        }

        $res = $this->http()->get($this->apiBase().'/orgs/'.rawurlencode($org).'/repos', [
            'limit' => 100,
        ]);
        if (! $res->successful()) {
            return [];
        }

        $out = [];
        foreach ($res->json() ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'full_name' => (string) ($row['full_name'] ?? ''),
                'clone_url' => (string) ($row['clone_url'] ?? ''),
                'default_branch' => (string) ($row['default_branch'] ?? 'main'),
            ];
        }

        return $out;
    }

    public function getDefaultBranch(string $owner, string $repo): ?string
    {
        $res = $this->http()->get($this->apiBase().'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo));
        if (! $res->successful()) {
            return null;
        }
        $branch = $res->json('default_branch');

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    public function getLatestTag(string $owner, string $repo): ?string
    {
        $res = $this->http()->get($this->apiBase().'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/tags', [
            'limit' => 1,
        ]);
        if (! $res->successful()) {
            return null;
        }
        $tag = $res->json('0.name');

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    public function getReadme(string $owner, string $repo, ?string $ref = null): ?string
    {
        $query = $ref ? ['ref' => $ref] : [];
        $res = $this->http()->get(
            $this->apiBase().'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/raw/README.md',
            $query
        );
        if (! $res->successful()) {
            return null;
        }

        return mb_substr($res->body(), 0, 8000);
    }

    public function createDeployToken(string $owner, string $repo, string $name): ?array
    {
        if ($this->token === '') {
            return null;
        }

        return ['token' => $this->token, 'username' => 'oauth2'];
    }
}
