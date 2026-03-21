<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Traits\HasSocialAccounts;
use PHPUnit\Framework\TestCase;

class HasSocialAccountsTest extends TestCase
{
    public function test_trait_exists(): void
    {
        $this->assertTrue(trait_exists(HasSocialAccounts::class));
    }

    public function test_trait_defines_social_accounts_method(): void
    {
        $ref = new \ReflectionClass(HasSocialAccounts::class);
        $this->assertTrue($ref->hasMethod('socialAccounts'));
    }

    public function test_trait_defines_has_social_account_method(): void
    {
        $ref = new \ReflectionClass(HasSocialAccounts::class);
        $this->assertTrue($ref->hasMethod('hasSocialAccount'));
    }

    public function test_trait_defines_get_social_account_method(): void
    {
        $ref = new \ReflectionClass(HasSocialAccounts::class);
        $this->assertTrue($ref->hasMethod('getSocialAccount'));
    }

    public function test_trait_defines_link_social_account_method(): void
    {
        $ref = new \ReflectionClass(HasSocialAccounts::class);
        $this->assertTrue($ref->hasMethod('linkSocialAccount'));
    }

    public function test_trait_defines_unlink_social_account_method(): void
    {
        $ref = new \ReflectionClass(HasSocialAccounts::class);
        $this->assertTrue($ref->hasMethod('unlinkSocialAccount'));
    }
}
