<?php

namespace Tests\Unit;

use Modules\Core\Services\OrgGit\AbstractOrgGitProvider;
use Modules\Core\Services\OrgGit\GiteaOrgGitProvider;
use Modules\Core\Services\OrgGit\GithubOrgGitProvider;
use Modules\Core\Services\OrgGit\GitlabOrgGitProvider;
use Modules\Core\Services\OrgGit\OrgGitProviderFactory;
use PHPUnit\Framework\TestCase;

class OrgGitProviderTest extends TestCase
{
    public function test_parse_owner_repo_from_https_and_short_name(): void
    {
        $this->assertSame(
            ['owner' => 'org', 'repo' => 'demo'],
            AbstractOrgGitProvider::parseOwnerRepo('https://github.com/org/demo.git')
        );
        $this->assertSame(
            ['owner' => 'org', 'repo' => 'demo'],
            AbstractOrgGitProvider::parseOwnerRepo('org/demo')
        );
    }

    public function test_factory_instantiates_three_providers(): void
    {
        $factory = new OrgGitProviderFactory;
        $this->assertInstanceOf(GithubOrgGitProvider::class, $factory->instantiate('github', '', 'tok'));
        $this->assertInstanceOf(GitlabOrgGitProvider::class, $factory->instantiate('gitlab', '', 'tok'));
        $this->assertInstanceOf(GiteaOrgGitProvider::class, $factory->instantiate('gitea', 'https://git.example.test', 'tok'));
    }

    public function test_github_authenticated_clone_url_embeds_token(): void
    {
        $provider = new GithubOrgGitProvider('https://github.com', 'secret-pat', 'org');
        $url = $provider->authenticatedCloneUrl('https://github.com/org/demo.git');
        $this->assertStringContainsString('x-access-token:', $url);
        $this->assertStringContainsString('secret-pat', $url);
        $this->assertStringContainsString('@github.com/org/demo.git', $url);
    }

    public function test_webhook_sha256_verification(): void
    {
        $provider = new GithubOrgGitProvider('https://github.com', 't', null);
        $payload = '{"ref":"refs/heads/main"}';
        $sig = 'sha256='.hash_hmac('sha256', $payload, 'whsec');
        $this->assertTrue($provider->verifyWebhook($payload, $sig, 'whsec'));
        $this->assertFalse($provider->verifyWebhook($payload, $sig, 'wrong'));
    }
}
