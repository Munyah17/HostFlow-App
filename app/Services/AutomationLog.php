<?php

declare(strict_types=1);

namespace App\Services;

final class AutomationLog
{
    public static ?int $runId = null;

    public static function record(string $task, string $action, string $status, ?string $message = null, ?string $entityType = null, ?int $entityId = null): void
    {
        try {
            db()->insert('automation_logs', [
                'run_id' => self::$runId,
                'task' => $task,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'status' => $status,
                'message' => $message !== null ? mb_substr($message, 0, 1000) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Automation log failed: ' . $e->getMessage(), [], 'automation');
        }
    }
}
