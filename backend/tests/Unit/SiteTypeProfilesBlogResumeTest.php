<?php

namespace Tests\Unit;

use Modules\Platform\Support\SiteTypeProfiles;
use Tests\TestCase;

class SiteTypeProfilesBlogResumeTest extends TestCase
{
    public function test_blog_type_is_registered_with_blog_modules(): void
    {
        $this->assertTrue(SiteTypeProfiles::isValid('blog'));
        $profile = SiteTypeProfiles::all()['blog'];
        $this->assertSame('وبلاگ', $profile['name_fa']);
        $this->assertSame('blog-default', $profile['theme']);
        foreach (['posts', 'categories', 'rss'] as $sub) {
            $this->assertContains($sub, $profile['modules']['blog']);
        }
        $this->assertArrayHasKey('core', $profile['modules']);
        $this->assertArrayNotHasKey('commerce', $profile['modules']);
    }

    public function test_resume_type_covers_cv_sections(): void
    {
        $this->assertTrue(SiteTypeProfiles::isValid('resume'));
        $resume = SiteTypeProfiles::modulesFor('resume')['resume'];
        foreach (['profile', 'experience', 'education', 'skills', 'portfolio', 'contact'] as $sub) {
            $this->assertContains($sub, $resume);
        }
    }

    public function test_every_type_has_profile(): void
    {
        foreach (SiteTypeProfiles::TYPES as $t) {
            $this->assertNotNull(SiteTypeProfiles::modulesFor($t), $t);
        }
    }
}
