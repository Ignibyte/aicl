<?php

namespace Aicl\Tests\Unit\Filament\AvatarProviders;

use Aicl\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialsAvatarProviderTest extends TestCase
{
    use RefreshDatabase;

    private InitialsAvatarProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);
        $this->provider = new InitialsAvatarProvider;
    }

    public function test_implements_avatar_provider_contract(): void
    {
        $this->assertInstanceOf(AvatarProvider::class, $this->provider);
    }

    public function test_returns_svg_data_uri(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $result = $this->provider->get($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $result);
    }

    public function test_svg_contains_initials(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $result = $this->provider->get($user);

        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $result));
        $this->assertStringContainsString('JD', $svg);
    }

    public function test_single_name_produces_single_initial(): void
    {
        $user = User::factory()->create(['name' => 'Admin']);

        $result = $this->provider->get($user);

        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $result));
        $this->assertStringContainsString('>A<', $svg);
    }

    public function test_three_word_name_uses_first_two_initials(): void
    {
        $user = User::factory()->create(['name' => 'John Michael Doe']);

        $result = $this->provider->get($user);

        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $result));
        $this->assertStringContainsString('JM', $svg);
    }

    public function test_same_name_produces_same_color(): void
    {
        $user1 = User::factory()->create(['name' => 'Consistent Name']);
        $user2 = User::factory()->create(['name' => 'Consistent Name']);

        $this->assertEquals($this->provider->get($user1), $this->provider->get($user2));
    }

    public function test_different_names_produce_different_colors(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice Smith']);
        $user2 = User::factory()->create(['name' => 'Bob Jones']);

        $this->assertNotEquals($this->provider->get($user1), $this->provider->get($user2));
    }

    public function test_svg_is_valid_xml(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

        $result = $this->provider->get($user);

        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $result));
        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml);
    }

    public function test_special_characters_are_escaped(): void
    {
        $user = User::factory()->create(['name' => '<script>']);

        $result = $this->provider->get($user);

        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $result));
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&lt;', $svg);
    }
}
