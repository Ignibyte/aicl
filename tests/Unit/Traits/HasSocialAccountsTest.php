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
        $this->assertTrue(
            method_exists(HasSocialAccounts::class, 'socialAccounts'),
        );
    }

    public function test_trait_defines_has_social_account_method(): void
    {
        $this->assertTrue(
            method_exists(HasSocialAccounts::class, 'hasSocialAccount'),
        );
    }

    public function test_trait_defines_get_social_account_method(): void
    {
        $this->assertTrue(
            method_exists(HasSocialAccounts::class, 'getSocialAccount'),
        );
    }

    public function test_trait_defines_link_social_account_method(): void
    {
        $this->assertTrue(
            method_exists(HasSocialAccounts::class, 'linkSocialAccount'),
        );
    }

    public function test_trait_defines_unlink_social_account_method(): void
    {
        $this->assertTrue(
            method_exists(HasSocialAccounts::class, 'unlinkSocialAccount'),
        );
    }
}
