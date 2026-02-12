<?php

namespace Aicl\Rlm;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class SemanticValidator
{
    /** @var SemanticResult[] */
    protected array $results = [];

    /**
     * @param  array<string, string>  $files  target => file path
     * @param  array<string, mixed>  $entityContext
     */
    public function __construct(
        protected string $entityName,
        protected array $files = [],
        protected array $entityContext = [],
        protected ?SemanticCache $cache = null,
    ) {}

    /**
     * Run all applicable semantic checks.
     *
     * @return SemanticResult[]
     */
    public function validate(): array
    {
        $this->results = [];
        $checks = SemanticCheckRegistry::applicable($this->entityContext);
        $useCache = config('aicl.rlm.semantic.use_cache', true);

        // Split checks into cached vs uncached
        $uncached = [];
        $fileContentsMap = [];

        foreach ($checks as $check) {
            $contents = $this->loadFileContents($check);
            if ($contents === null) {
                $this->results[] = new SemanticResult(
                    check: $check,
                    passed: false,
                    message: 'Required files not available',
                    skipped: true,
                );

                continue;
            }

            $fileContentsMap[$check->name] = $contents;

            if ($useCache && $this->cache) {
                $key = $this->cache->cacheKey($check, $contents);
                $cached = $this->cache->get($key);
                if ($cached !== null) {
                    $this->results[] = $cached;

                    continue;
                }
            }

            $uncached[] = $check;
        }

        if ($uncached === []) {
            return $this->results;
        }

        // Execute uncached checks via LLM API
        $apiKey = config('aicl.rlm.semantic.api_key');
        if (! $apiKey) {
            foreach ($uncached as $check) {
                $this->results[] = new SemanticResult(
                    check: $check,
                    passed: false,
                    message: 'No API key configured (set AICL_SEMANTIC_API_KEY)',
                    skipped: true,
                );
            }

            return $this->results;
        }

        // Execute checks in parallel
        $llmResults = $this->executeChecks($uncached, $fileContentsMap);

        foreach ($llmResults as $result) {
            $this->results[] = $result;

            // Cache the result
            if ($useCache && $this->cache && ! $result->skipped) {
                $contents = $fileContentsMap[$result->check->name] ?? [];
                $key = $this->cache->cacheKey($result->check, $contents);
                $this->cache->put($key, $result, $this->entityName);
            }
        }

        return $this->results;
    }

    /**
     * Calculate semantic score as a percentage (skipped checks excluded from denominator).
     */
    public function score(): float
    {
        if ($this->results === []) {
            return 100.0;
        }

        $totalWeight = 0.0;
        $earnedWeight = 0.0;

        foreach ($this->results as $result) {
            if ($result->skipped) {
                continue;
            }
            $totalWeight += $result->check->weight;
            if ($result->passed) {
                $earnedWeight += $result->check->weight;
            }
        }

        if ($totalWeight === 0.0) {
            return 100.0;
        }

        return round(($earnedWeight / $totalWeight) * 100, 1);
    }

    /**
     * @return SemanticResult[]
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (SemanticResult $r): bool => ! $r->passed && ! $r->skipped,
        ));
    }

    /**
     * @return SemanticResult[]
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (SemanticResult $r): bool => ! $r->passed && ! $r->skipped && $r->check->isError(),
        ));
    }

    /**
     * @return SemanticResult[]
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (SemanticResult $r): bool => ! $r->passed && ! $r->skipped && $r->check->isWarning(),
        ));
    }

    /**
     * @return SemanticResult[]
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * @return SemanticResult[]
     */
    public function skipped(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (SemanticResult $r): bool => $r->skipped,
        ));
    }

    /**
     * Load file contents for a check's required targets.
     *
     * @return array<string, string>|null null if any required file is missing
     */
    protected function loadFileContents(SemanticCheck $check): ?array
    {
        $contents = [];

        foreach ($check->targets as $target) {
            if (! isset($this->files[$target])) {
                return null;
            }

            $path = $this->files[$target];
            if (! file_exists($path)) {
                return null;
            }

            $content = file_get_contents($path); // nosemgrep: file-get-contents-url
            if ($content === false) {
                return null;
            }

            $contents[$target] = $content;
        }

        return $contents;
    }

    /**
     * Execute semantic checks via LLM API calls in parallel.
     *
     * @param  SemanticCheck[]  $checks
     * @param  array<string, array<string, string>>  $fileContentsMap  check name => [target => content]
     * @return SemanticResult[]
     */
    protected function executeChecks(array $checks, array $fileContentsMap): array
    {
        $apiKey = config('aicl.rlm.semantic.api_key');
        $model = config('aicl.rlm.semantic.model', 'claude-haiku-4-5-20251001');
        $maxTokens = (int) config('aicl.rlm.semantic.max_tokens', 1024);
        $timeout = (int) config('aicl.rlm.semantic.timeout', 30);

        $responses = Http::pool(function (Pool $pool) use ($checks, $fileContentsMap, $apiKey, $model, $maxTokens, $timeout) {
            foreach ($checks as $check) {
                $contents = $fileContentsMap[$check->name] ?? [];
                $prompt = $this->buildPrompt($check, $contents);

                $pool->as($check->name)
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                    ])
                    ->timeout($timeout)
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ]);
            }
        });

        $results = [];
        $confidenceThreshold = (float) config('aicl.rlm.semantic.confidence_threshold', 0.3);

        foreach ($checks as $check) {
            $response = $responses[$check->name] ?? null;
            $files = array_keys($fileContentsMap[$check->name] ?? []);

            if ($response === null || $response instanceof \Throwable) {
                $results[] = new SemanticResult(
                    check: $check,
                    passed: false,
                    message: 'API request failed',
                    files: $files,
                    skipped: true,
                );

                continue;
            }

            if (! $response->successful()) {
                $status = $response->status();
                $results[] = new SemanticResult(
                    check: $check,
                    passed: false,
                    message: "API returned HTTP {$status}",
                    files: $files,
                    skipped: true,
                );

                continue;
            }

            $result = $this->parseResponse($response->json(), $check, $files, $confidenceThreshold);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Build the LLM prompt for a specific check.
     *
     * @param  array<string, string>  $fileContents  target => content
     */
    public function buildPrompt(SemanticCheck $check, array $fileContents): string
    {
        $systemInstruction = "You are a code review assistant. You analyze Laravel source files for cross-file consistency.\nRespond with EXACTLY this JSON format (no markdown fences, no extra text):\n{\"passed\": true, \"message\": \"brief explanation\", \"confidence\": 0.95}";

        $fileSections = '';
        foreach ($fileContents as $target => $content) {
            $fileSections .= "\n### File: {$target}\n```php\n{$content}\n```\n";
        }

        $prompt = trim($check->prompt);

        return "{$systemInstruction}\n\n## Check: {$check->description}\n{$fileSections}\n### Question\n{$prompt}\n\nAnswer with JSON only.";
    }

    /**
     * Parse the LLM's structured JSON response.
     *
     * @param  array<string, mixed>|null  $json
     * @param  string[]  $files
     */
    public function parseResponse(?array $json, SemanticCheck $check, array $files = [], float $confidenceThreshold = 0.3): SemanticResult
    {
        if ($json === null) {
            return new SemanticResult(
                check: $check,
                passed: false,
                message: 'Invalid API response (null)',
                files: $files,
                skipped: true,
            );
        }

        // Extract text content from Messages API response
        $text = $this->extractTextFromResponse($json);
        if ($text === null) {
            return new SemanticResult(
                check: $check,
                passed: false,
                message: 'Could not extract text from API response',
                files: $files,
                skipped: true,
            );
        }

        // Parse JSON from the text content
        $parsed = $this->parseJsonFromText($text);
        if ($parsed === null) {
            return new SemanticResult(
                check: $check,
                passed: false,
                message: 'Could not parse JSON from response: '.substr($text, 0, 200),
                files: $files,
                skipped: true,
            );
        }

        $passed = (bool) ($parsed['passed'] ?? false);
        $message = (string) ($parsed['message'] ?? '');
        $confidence = (float) ($parsed['confidence'] ?? 1.0);

        // Low confidence = unreliable result, treat as skipped
        if ($confidence < $confidenceThreshold) {
            return new SemanticResult(
                check: $check,
                passed: false,
                message: "Low confidence ({$confidence}): {$message}",
                confidence: $confidence,
                files: $files,
                skipped: true,
            );
        }

        return new SemanticResult(
            check: $check,
            passed: $passed,
            message: $message,
            confidence: $confidence,
            files: $files,
        );
    }

    /**
     * Extract text content from Anthropic Messages API response format.
     *
     * @param  array<string, mixed>  $json
     */
    protected function extractTextFromResponse(array $json): ?string
    {
        $content = $json['content'] ?? [];
        if (! is_array($content)) {
            return null;
        }

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                return $block['text'] ?? null;
            }
        }

        return null;
    }

    /**
     * Parse JSON from text that may contain markdown fences or extra whitespace.
     *
     * @return array<string, mixed>|null
     */
    protected function parseJsonFromText(string $text): ?array
    {
        $text = trim($text);

        // Try direct parse first
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try extracting from markdown code fence
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $text, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try extracting first JSON object
        if (preg_match('/\{[^{}]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
