<?php

declare(strict_types=1);

namespace Aicl\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates inline SVG data-URI avatars from user initials.
 *
 * Replaces the default ui-avatars.com provider to avoid
 * external HTTP requests and CSP img-src violations.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);

        $initials = str($name)
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_strtoupper(mb_substr($segment, 0, 1)) : '')
            ->take(2)
            ->join('');

        if ($initials === '') {
            // @codeCoverageIgnoreStart — Filament Livewire rendering
            $initials = '?';
            // @codeCoverageIgnoreEnd
        }

        $background = $this->generateColor($name);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="'.$background.'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" '
            .'font-family="system-ui, sans-serif" font-size="24" font-weight="600" fill="#FFFFFF">'
            .htmlspecialchars($initials)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Generate a deterministic background color from a name.
     */
    protected function generateColor(string $name): string
    {
        $hash = crc32($name);

        $hue = abs($hash) % 360;
        $saturation = 45 + (abs($hash >> 8) % 20); // 45-65%
        $lightness = 35 + (abs($hash >> 16) % 15);  // 35-50%

        return "hsl({$hue}, {$saturation}%, {$lightness}%)";
    }
}
