<?php

namespace Aicl\Tests\Unit\Misc;

use Aicl\Http\Controllers\SocialAuthController;
use PHPUnit\Framework\TestCase;

class SocialAuthControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(SocialAuthController::class));
    }

    public function test_has_redirect_method(): void
    {
        $this->assertTrue(method_exists(SocialAuthController::class, 'redirect'));
    }

    public function test_has_callback_method(): void
    {
        $this->assertTrue(method_exists(SocialAuthController::class, 'callback'));
    }

    public function test_has_validate_provider_method(): void
    {
        $reflection = new \ReflectionMethod(SocialAuthController::class, 'validateProvider');
        $this->assertTrue($reflection->isProtected());
    }

    public function test_has_get_redirect_url_method(): void
    {
        $reflection = new \ReflectionMethod(SocialAuthController::class, 'getRedirectUrl');
        $this->assertTrue($reflection->isProtected());
    }

    public function test_enabled_providers_default(): void
    {
        $reflection = new \ReflectionClass(SocialAuthController::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertContains('google', $defaults['enabledProviders']);
        $this->assertContains('github', $defaults['enabledProviders']);
    }

    public function test_redirect_returns_redirect_response(): void
    {
        $reflection = new \ReflectionMethod(SocialAuthController::class, 'redirect');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('Illuminate\Http\RedirectResponse', $returnType->getName());
    }

    public function test_callback_returns_redirect_response(): void
    {
        $reflection = new \ReflectionMethod(SocialAuthController::class, 'callback');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('Illuminate\Http\RedirectResponse', $returnType->getName());
    }

    public function test_redirect_accepts_provider_string(): void
    {
        $reflection = new \ReflectionMethod(SocialAuthController::class, 'redirect');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('provider', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function test_callback_accepts_provider_string(): void
    {
        $reflection = new \ReflectionMethod(SocialAuthController::class, 'callback');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('provider', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }
}
