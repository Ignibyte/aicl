<?php

namespace Aicl\Database\Seeders;

use Aicl\Enums\AnnotationCategory;
use Aicl\Models\GoldenAnnotation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class GoldenAnnotationSeeder extends Seeder
{
    public function run(): void
    {
        $goldenDir = base_path('.claude/golden-example');

        if (! is_dir($goldenDir)) {
            $this->command?->info('Golden example directory not found — skipping annotations.');

            return;
        }

        $ownerId = User::first()?->id ?? 1;

        $finder = (new Finder)
            ->files()
            ->name('*.php')
            ->name('*.blade.php')
            ->in($goldenDir)
            ->sortByName();

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $lines = file($file->getRealPath());

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineIndex => $line) {
                if (preg_match('/\/\/\s*PATTERN:\s*(.+)/', $line, $matches)) {
                    $annotationText = trim($matches[1]);
                    $lineNumber = $lineIndex + 1;

                    GoldenAnnotation::query()->updateOrCreate(
                        [
                            'file_path' => $relativePath,
                            'annotation_text' => $annotationText,
                        ],
                        [
                            'annotation_key' => $this->generateKey($relativePath, $annotationText),
                            'line_number' => $lineNumber,
                            'category' => $this->categorize($relativePath),
                            'feature_tags' => $this->featureTags($relativePath, $annotationText),
                            'is_active' => true,
                            'owner_id' => $ownerId,
                        ]
                    );
                }
            }
        }
    }

    private function generateKey(string $filePath, string $text): string
    {
        $fileBase = Str::before(basename($filePath), '.');
        $slug = Str::slug(Str::limit($text, 60, ''));

        return $fileBase.'.'.$slug;
    }

    private function categorize(string $filePath): AnnotationCategory
    {
        $basename = basename($filePath, '.php');
        $dir = dirname($filePath);

        return match (true) {
            $basename === 'model' => AnnotationCategory::Model,
            $basename === 'migration' => AnnotationCategory::Migration,
            $basename === 'factory' => AnnotationCategory::Factory,
            $basename === 'policy' => AnnotationCategory::Policy,
            $basename === 'observer' => AnnotationCategory::Observer,
            $basename === 'test' => AnnotationCategory::Test,
            $basename === 'seeder' => AnnotationCategory::Model,
            $basename === 'enum', $basename === 'state' => AnnotationCategory::Model,
            str_contains($filePath, 'filament') => AnnotationCategory::Filament,
            str_contains($filePath, 'api-') => AnnotationCategory::Api,
            str_contains($dir, 'widgets') => AnnotationCategory::Filament,
            str_contains($dir, 'notifications') => AnnotationCategory::Notification,
            str_contains($dir, 'pdf') => AnnotationCategory::Pdf,
            $basename === 'exporter' => AnnotationCategory::Filament,
            default => AnnotationCategory::Model,
        };
    }

    /**
     * @return array<int, string>
     */
    private function featureTags(string $filePath, string $annotationText): array
    {
        $tags = [];
        $lowerText = strtolower($annotationText);

        // File-based tags
        if (str_contains($filePath, 'state')) {
            $tags[] = 'states';
        }
        if (str_contains($filePath, 'notifications/')) {
            $tags[] = 'notifications';
        }
        if (str_contains($filePath, 'widgets/')) {
            $tags[] = 'widgets';
        }
        if (str_contains($filePath, 'pdf/')) {
            $tags[] = 'pdf';
        }
        if (str_contains($filePath, 'exporter')) {
            $tags[] = 'export';
        }

        // Content-based tags
        if (str_contains($lowerText, 'softdelete') || str_contains($lowerText, 'soft delete')) {
            $tags[] = 'universal';
        }
        if (str_contains($lowerText, 'fillable') || str_contains($lowerText, '$fillable')) {
            $tags[] = 'universal';
        }
        if (str_contains($lowerText, 'enum')) {
            $tags[] = 'enums';
        }
        if (str_contains($lowerText, 'state') && ! str_contains($lowerText, 'formatstate')) {
            $tags[] = 'states';
        }
        if (str_contains($lowerText, 'pivot') || str_contains($lowerText, 'many-to-many')) {
            $tags[] = 'relationships';
        }
        if (str_contains($lowerText, 'money') || str_contains($lowerText, 'decimal')) {
            $tags[] = 'money';
        }
        if (str_contains($lowerText, 'media') || str_contains($lowerText, 'image')) {
            $tags[] = 'media';
        }
        if (str_contains($lowerText, 'searchable')) {
            $tags[] = 'search';
        }
        if (str_contains($lowerText, 'eager load') || str_contains($lowerText, 'n+1')) {
            $tags[] = 'performance';
        }

        // Universal patterns from core files
        $basename = basename($filePath, '.php');
        if (in_array($basename, ['model', 'migration', 'factory', 'policy', 'observer', 'test'])) {
            $tags[] = 'universal';
        }

        return array_values(array_unique($tags));
    }
}
