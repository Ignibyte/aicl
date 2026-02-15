<?php

namespace Aicl\Workflows\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalLog extends Model
{
    protected $table = 'approval_logs';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'comment',
    ];

    /**
     * The model that was approved/rejected.
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who performed the action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
