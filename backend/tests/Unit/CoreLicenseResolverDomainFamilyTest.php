<?php

namespace Tests\Unit;

use Modules\Core\Services\CoreLicenseResolver;
use PHPUnit\Framework\TestCase;

class CoreLicenseResolverDomainFamilyTest extends TestCase
{
    public function test_related_domains_swap_webina_apexes(): void
    {
        $this->assertSame(
            ['bluecafe.webinaagency.ir', 'bluecafe.webina.dev'],
            CoreLicenseResolver::relatedDomains('bluecafe.webinaagency.ir')
        );
        $this->assertSame(
            ['bluecafe.webina.dev', 'bluecafe.webinaagency.ir'],
            CoreLicenseResolver::relatedDomains('https://www.bluecafe.webina.dev/path')
        );
        $this->assertSame(
            ['webinaagency.ir', 'webina.dev'],
            CoreLicenseResolver::relatedDomains('webinaagency.ir')
        );
        $this->assertSame(
            ['shop.example.com'],
            CoreLicenseResolver::relatedDomains('shop.example.com')
        );
    }

    public function test_product_aliases(): void
    {
        $this->assertSame('webinodashboard', CoreLicenseResolver::normalizeProduct('dashboard'));
        $this->assertSame('webino', CoreLicenseResolver::normalizeProduct('erp'));
    }
}
