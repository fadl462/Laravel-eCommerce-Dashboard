<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Single call site for the audit trail shown on the Activity Log page.
 * Controllers call this after any sensitive write — never rely on remembering
 * to log manually in more than one place per action.
 */
class ActivityLogger
{
    public function log(?User $user, string $action, string $module, ?Model $subject = null, ?string $subjectLabel = null, array $meta = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'ip_address' => Request::ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
