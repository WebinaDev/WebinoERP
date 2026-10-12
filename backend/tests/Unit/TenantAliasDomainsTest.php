<?php

namespace Tests\Unit;

use Modules\Platform\Support\TenantAliasDomains;
use Modules\Platform\Support\TenantSiteStack;
use PHPUnit\Framework\TestCase;

class TenantAliasDomainsTest extends TestCase
{
    public function test_hostname_derives_from_primary(): void
    {
        $this->assertSame('contact.shop.example.com', TenantAliasDomains::hostname('shop.example.com', 'contact'));
        $this->assertSame('bio.example.com', TenantAliasDomains::hostname('www.example.com', 'Bio!'));
    }

    public function test_caddy_snippet_includes_aliases(): void
    {
        $snippet = TenantSiteStack::caddySnippet('shop.example.com', 'demo', ['contact.shop.example.com']);
        $this->assertStringContainsString('shop.example.com, contact.shop.example.com {', $snippet);
        $this->assertStringContainsString('ws-demo-backend:8080', $snippet);
        $this->assertStringContainsString('ws-demo-frontend:3000', $snippet);
    }

    public function test_push_legacy_domain_keeps_previous_primary_and_contact(): void
    {
        $legacy = TenantAliasDomains::pushLegacyDomain([], 'bluecafe.webinaagency.ir', 'contact');
        $this->assertContains('bluecafe.webinaagency.ir', $legacy);
        $this->assertContains('contact.bluecafe.webinaagency.ir', $legacy);
    }

    public function test_for_provision_includes_legacy_domains(): void
    {
        $provision = new \Modules\SiteBuilder\Entities\WebinoSiteProvision([
            'domain' => 'cafebluedez.ir',
            'alias_subdomain' => 'contact',
            'wizard_payload' => [
                'legacy_domains' => ['bluecafe.webinaagency.ir', 'contact.bluecafe.webinaagency.ir'],
            ],
        ]);
        $aliases = TenantAliasDomains::forProvision($provision);
        $this->assertContains('contact.cafebluedez.ir', $aliases);
        $this->assertContains('bluecafe.webinaagency.ir', $aliases);
        $this->assertNotContains('cafebluedez.ir', $aliases);
    }
}
