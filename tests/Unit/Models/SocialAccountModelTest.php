<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\SocialAccount;
use Tests\TestCase;

class SocialAccountModelTest extends TestCase
{
    public function test_fillable_contains_required_attributes(): void
    {
        $account = new SocialAccount;
        $fillable = $account->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('provider', $fillable);
        $this->assertContains('provider_id', $fillable);
        $this->assertContains('token', $fillable);
    }

    public function test_user_relationship_method_exists(): void
    {
        $account = new SocialAccount;

        $this->assertTrue((new \ReflectionClass($account))->hasMethod('user'));
    }

    public function test_is_expired_returns_false_when_no_expiry(): void
    {
        $account = new SocialAccount;
        $account->token_expires_at = null;

        $this->assertFalse($account->isExpired());
    }

    public function test_is_expired_returns_true_for_past_date(): void
    {
        $account = new SocialAccount;
        $account->token_expires_at = now()->subDay();

        $this->assertTrue($account->isExpired());
    }

    public function test_is_expired_returns_false_for_future_date(): void
    {
        $account = new SocialAccount;
        $account->token_expires_at = now()->addDay();

        $this->assertFalse($account->isExpired());
    }

    public function test_id_is_not_fillable(): void
    {
        $account = new SocialAccount;

        $this->assertNotContains('id', $account->getFillable());
    }
}
