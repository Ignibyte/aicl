<?php

namespace Aicl\Console\Commands;

use Aicl\AI\Jobs\CompactConversationJob;
use Aicl\Models\AiConversation;
use Illuminate\Console\Command;

class CompactConversationsCommand extends Command
{
    protected $signature = 'aicl:compact-conversations
        {--conversation= : Compact a specific conversation by ID}
        {--dry-run : Show which conversations would be compacted without doing it}';

    protected $description = 'Compact conversations that exceed the message threshold by summarizing old messages';

    public function handle(): int
    {
        $conversationId = $this->option('conversation');
        $dryRun = $this->option('dry-run');

        if ($conversationId) {
            return $this->compactSingle($conversationId, $dryRun);
        }

        return $this->compactAll($dryRun);
    }

    private function compactSingle(string $conversationId, bool $dryRun): int
    {
        $conversation = AiConversation::with('agent')->find($conversationId);

        if (! $conversation) {
            $this->error("Conversation {$conversationId} not found.");

            return self::FAILURE;
        }

        if (! $conversation->is_compactable) {
            $this->info('Conversation is not eligible for compaction.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would compact: {$conversation->display_title} ({$conversation->message_count} messages)");

            return self::SUCCESS;
        }

        CompactConversationJob::dispatch($conversationId);
        $this->info('Compaction job dispatched.');

        return self::SUCCESS;
    }

    private function compactAll(bool $dryRun): int
    {
        $threshold = (int) config('aicl.ai.assistant.compaction_threshold', 50);

        $conversations = AiConversation::query()
            ->whereNull('summary')
            ->where('message_count', '>', $threshold)
            ->with('agent')
            ->get();

        if ($conversations->isEmpty()) {
            $this->info('No conversations eligible for compaction.');

            return self::SUCCESS;
        }

        $this->info("Found {$conversations->count()} conversation(s) eligible for compaction.");

        foreach ($conversations as $conversation) {
            if ($dryRun) {
                $this->line("  - {$conversation->display_title} ({$conversation->message_count} messages)");

                continue;
            }

            CompactConversationJob::dispatch($conversation->id);
            $this->line("  Dispatched: {$conversation->display_title}");
        }

        if (! $dryRun) {
            $this->info('All compaction jobs dispatched.');
        }

        return self::SUCCESS;
    }
}
