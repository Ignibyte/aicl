<?php

declare(strict_types=1);

namespace Aicl\Database\Seeders;

use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Illuminate\Database\Seeder;

class AiAgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'name' => 'General Assistant',
                'slug' => 'general-assistant',
                'description' => 'A general-purpose AI assistant for answering questions, writing content, and helping with everyday tasks.',
                'provider' => AiProvider::OpenAi,
                'model' => 'gpt-4o-mini',
                'system_prompt' => 'You are a helpful assistant for this application. Answer questions clearly and concisely. When providing information, be accurate and well-organized.',
                'max_tokens' => 4096,
                'temperature' => 0.70,
                'context_window' => 128000,
                'context_messages' => 20,
                'is_active' => false,
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => '#10b981',
                'sort_order' => 0,
                'suggested_prompts' => [
                    'Help me draft an email',
                    'Summarize this for me',
                    'What does this mean?',
                ],
                'capabilities' => ['chat', 'summarize'],
            ],
            [
                'name' => 'Code Assistant',
                'slug' => 'code-assistant',
                'description' => 'A specialized AI assistant for code generation, debugging, and technical guidance.',
                'provider' => AiProvider::Anthropic,
                'model' => 'claude-sonnet-4-20250514',
                'system_prompt' => 'You are an expert software engineer. Help with code generation, debugging, code review, and technical architecture decisions. Provide clear, well-commented code examples. Follow best practices and security guidelines.',
                'max_tokens' => 8192,
                'temperature' => 0.30,
                'context_window' => 200000,
                'context_messages' => 30,
                'is_active' => false,
                'icon' => 'heroicon-o-code-bracket',
                'color' => '#6366f1',
                'sort_order' => 1,
                'suggested_prompts' => [
                    'Help me debug this error',
                    'Write a function that...',
                    'Review this code for issues',
                    'Explain how this works',
                ],
                'capabilities' => ['chat', 'generate_code', 'analyze_data'],
            ],
        ];

        foreach ($agents as $data) {
            AiAgent::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}
