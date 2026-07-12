<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Authorization;

final class WorkerVoicePermissions
{
    public const SUBMIT = 'worker_voice.submit';
    public const VIEW = 'worker_voice.view';
    public const VIEW_SENSITIVE = 'worker_voice.view_sensitive';
    public const VIEW_AGGREGATES = 'worker_voice.aggregate.view';
    public const EXPORT = 'worker_voice.export';
}
