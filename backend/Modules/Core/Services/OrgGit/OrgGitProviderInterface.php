<?php

namespace Modules\Core\Services\OrgGit;

interface OrgGitProviderInterface
{
    public function provider(): string;

    /** @return array{ok: bool, message?: string, user?: string|null} */
    public function testConnection(): array;

    public function resolveCloneUrl(string $owner, string $repo): string;

    /**
     * @return list<array{full_name: string, clone_url: string, default_branch: string}>
     */
    public function listOrgRepos(?string $org = null): array;

    public function getDefaultBranch(string $owner, string $repo): ?string;

    public function getLatestTag(string $owner, string $repo): ?string;

    public function getReadme(string $owner, string $repo, ?string $ref = null): ?string;

    /**
     * Create a short-lived deploy token when the provider supports it.
     *
     * @return array{token: string, username: string}|null
     */
    public function createDeployToken(string $owner, string $repo, string $name): ?array;

    public function verifyWebhook(string $payload, ?string $signatureHeader, string $secret): bool;

    public function authenticatedCloneUrl(string $cloneUrl): string;

    /** @return list<string> */
    public function allowedHosts(): array;
}
