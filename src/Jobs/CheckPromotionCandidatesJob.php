<?php

namespace Aicl\Jobs;

use Aicl\Models\RlmFailure;
use Aicl\Notifications\FailurePromotionCandidateNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckPromotionCandidatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public RlmFailure $failure,
    ) {}

    public function handle(): void
    {
        // Re-check promotion criteria (may have changed since dispatch)
        if ($this->failure->promoted_to_base) {
            return;
        }

        if ($this->failure->report_count < 3 || $this->failure->project_count < 2) {
            return;
        }

        // Notify the failure owner (or first admin if no owner)
        $recipient = $this->failure->owner
            ?? User::role('admin')->first();

        if ($recipient) {
            $recipient->notify(new FailurePromotionCandidateNotification($this->failure));
        }
    }
}
