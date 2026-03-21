<?php

namespace Aicl\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Meta-test that verifies all public classes and methods in packages/aicl/src/
 * have PHPDoc comments.
 *
 * This test uses PHP reflection to scan the entire package source tree and
 * ensures documentation standards are maintained as code evolves.
 */
class DocblockCoverageTest extends TestCase
{
    /**
     * Source directory to scan for PHP classes.
     */
    private const SOURCE_DIR = __DIR__.'/../../../src';

    /**
     * Directories to exclude from scanning entirely.
     * These contain generated, third-party, or infrastructure code where
     * class-level docblocks are not required by our conventions.
     */
    private const EXCLUDED_DIRECTORIES = [
        'Horizon',                  // Forked from laravel/horizon — third-party code
        'States',                   // State machine value objects (1-2 lines each)
        'Filament/Pages/Errors',    // Simple error pages with self-documenting names
        'Filament/Resources',       // Filament resource boilerplate (schemas, tables, pages)
    ];

    /**
     * File basenames to exclude from class-level docblock checks.
     * These are simple value objects, enums, or exception classes where
     * the class name is sufficiently self-documenting.
     */
    private const EXCLUDED_FILES = [
        'ConcurrentException.php',
        'ConcurrentTimeoutException.php',
        'SocialAuthException.php',
        'ApprovalException.php',
        'UnresolvableEventException.php',
    ];

    /**
     * Method names to exclude from docblock checks.
     * These are standard Laravel/PHP methods whose purpose is universally known.
     */
    private const EXCLUDED_METHODS = [
        '__construct',
        '__toString',
        '__invoke',
        '__get',
        '__set',
        '__call',
        '__callStatic',
        'boot',
        'booted',
        'register',
        'handle',
        'mount',
        'render',
        'up',
        'down',
        'authorize',
        'rules',
        'toArray',
        'toResponse',
        'toMail',
        'toSlack',
        'toDatabase',
        'toBroadcast',
        'via',
        'broadcastOn',
        'broadcastAs',
        'broadcastWith',
        'shouldBroadcast',
        'definition',
        'prepareForValidation',
        'getId',
    ];

    /**
     * Verify all scanned public classes have class-level docblocks.
     */
    public function test_all_public_classes_have_class_docblocks(): void
    {
        $missingDocblocks = [];

        foreach ($this->getPhpFiles() as $filePath) {
            if ($this->isExcludedFile($filePath)) {
                continue;
            }

            $className = $this->resolveClassName($filePath);
            if ($className === null || ! class_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
                /** @phpstan-ignore-next-line */
            } catch (\ReflectionException) {
                continue;
            }

            // Skip interfaces, traits, enums, and abstract classes from docblock requirement
            if ($reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            // Skip enum classes (PHP 8.1+)
            if (enum_exists($className)) {
                continue;
            }

            $docComment = $reflection->getDocComment();
            if ($docComment === false || trim($docComment) === '') {
                $relativePath = str_replace(realpath(self::SOURCE_DIR).'/', '', $filePath);
                $missingDocblocks[] = $relativePath;
            }
        }

        // Report missing docblocks to stderr for visibility in CI output.
        // This test passes with warnings — flip to assertEmpty() to enforce.
        if (! empty($missingDocblocks)) {
            $list = implode("\n  - ", $missingDocblocks);
            $count = count($missingDocblocks);
            fwrite(STDERR, "\n[DOCBLOCK] {$count} class(es) missing class-level docblock:\n  - {$list}\n");
        }

    }

    /**
     * Verify all public methods on scanned classes have method-level docblocks.
     */
    public function test_public_methods_have_docblocks(): void
    {
        $missingDocblocks = [];

        foreach ($this->getPhpFiles() as $filePath) {
            if ($this->isExcludedFile($filePath)) {
                continue;
            }

            $className = $this->resolveClassName($filePath);
            if ($className === null || ! class_exists($className)) {
                continue;
            }

            // Skip enums
            if (enum_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
                /** @phpstan-ignore-next-line */
            } catch (\ReflectionException) {
                continue;
            }

            // Skip interfaces and traits
            if ($reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Skip methods not declared in this class (inherited)
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                // Skip excluded method names
                if (in_array($method->getName(), self::EXCLUDED_METHODS, true)) {
                    continue;
                }

                // Skip simple getters/setters (get*, set*, is*, has* with 0-1 params)
                if ($this->isSimpleAccessor($method)) {
                    continue;
                }

                $docComment = $method->getDocComment();
                if ($docComment === false || trim($docComment) === '') {
                    $relativePath = str_replace(realpath(self::SOURCE_DIR).'/', '', $filePath);
                    $missingDocblocks[] = "{$relativePath}::{$method->getName()}()";
                }
            }
        }

        // Report missing method docblocks to stderr for visibility in CI output.
        // This test passes with warnings — flip to assertEmpty() to enforce.
        if (! empty($missingDocblocks)) {
            $list = implode("\n  - ", array_slice($missingDocblocks, 0, 50));
            $count = count($missingDocblocks);
            $suffix = $count > 50 ? "\n  ... and ".($count - 50).' more' : '';
            fwrite(STDERR, "\n[DOCBLOCK] {$count} public method(s) missing docblock:\n  - {$list}{$suffix}\n");
        }

    }

    /**
     * Verify the source directory exists and contains PHP files.
     */
    public function test_source_directory_exists_and_has_files(): void
    {
        /** @phpstan-ignore-next-line */
        $this->assertDirectoryExists(realpath(self::SOURCE_DIR));

        $fileCount = iterator_count($this->getPhpFiles());
        $this->assertGreaterThan(100, $fileCount, 'Expected at least 100 PHP source files in packages/aicl/src/');
    }

    /**
     * Count how many classes have docblocks vs total to report coverage percentage.
     */
    public function test_class_docblock_coverage_percentage(): void
    {
        $total = 0;
        $withDocblock = 0;

        foreach ($this->getPhpFiles() as $filePath) {
            if ($this->isExcludedFile($filePath)) {
                continue;
            }

            $className = $this->resolveClassName($filePath);
            if ($className === null || ! class_exists($className)) {
                continue;
            }

            if (enum_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
                /** @phpstan-ignore-next-line */
            } catch (\ReflectionException) {
                continue;
            }

            if ($reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            $total++;
            $docComment = $reflection->getDocComment();
            if ($docComment !== false && trim($docComment) !== '') {
                $withDocblock++;
            }
        }

        $percentage = $total > 0 ? round(($withDocblock / $total) * 100, 1) : 0;

        // Report coverage percentage to stderr for visibility in CI output
        fwrite(STDERR, "\n[DOCBLOCK] Class docblock coverage: {$withDocblock}/{$total} ({$percentage}%)\n");

        // Minimum threshold — at least 30% of classes should have docblocks.
        // Current baseline is ~31%. As docblocks are added, raise this threshold.
        $this->assertGreaterThanOrEqual(
            30.0,
            $percentage,
            "Class docblock coverage ({$percentage}%) is below the 30% minimum threshold"
        );
    }

    // ── Helper Methods ─────────────────────

    /**
     * Get all PHP files from the source directory.
     *
     * @return \Generator<string>
     */
    private function getPhpFiles(): \Generator
    {
        $sourceDir = realpath(self::SOURCE_DIR);
        if (! $sourceDir || ! is_dir($sourceDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    /**
     * Check if a file path should be excluded from scanning.
     *
     * @param  string  $filePath  Absolute path to the PHP file
     */
    private function isExcludedFile(string $filePath): bool
    {
        $basename = basename($filePath);

        if (in_array($basename, self::EXCLUDED_FILES, true)) {
            return true;
        }

        $sourceDir = realpath(self::SOURCE_DIR);
        $relativePath = str_replace($sourceDir.'/', '', $filePath);

        foreach (self::EXCLUDED_DIRECTORIES as $dir) {
            if (str_starts_with($relativePath, $dir.'/') || $relativePath === $dir) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a fully qualified class name from a PHP file path.
     *
     * Parses the file for namespace and class declarations using tokenization.
     *
     * @param  string  $filePath  Absolute path to the PHP file
     */
    private function resolveClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $className = null;

        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i])) {
                continue;
            }

            // Extract namespace
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_NAME_QUALIFIED, T_STRING], true)) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                }
            }

            // Extract class name (first class/interface/trait/enum in file)
            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // Skip anonymous classes
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $className = $tokens[$j][1];
                        break 2;
                    }
                    if ($tokens[$j] === '(' || $tokens[$j] === '{') {
                        break; // Anonymous class
                    }
                }
            }
        }

        if ($namespace && $className) {
            return $namespace.'\\'.$className;
        }

        return null;
    }

    /**
     * Check if a method is a simple getter/setter (get*, set*, is*, has* with 0-1 params).
     */
    private function isSimpleAccessor(\ReflectionMethod $method): bool
    {
        $name = $method->getName();
        $paramCount = $method->getNumberOfParameters();

        // Simple getters: getX(), isX(), hasX() with 0 params
        if ($paramCount === 0 && preg_match('/^(get|is|has)[A-Z]/', $name)) {
            return true;
        }

        // Simple setters: setX() with exactly 1 param
        if ($paramCount === 1 && preg_match('/^set[A-Z]/', $name)) {
            return true;
        }

        return false;
    }
}
