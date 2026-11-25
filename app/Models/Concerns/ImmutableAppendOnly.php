<?php

namespace App\Models\Concerns;

use App\Exceptions\PhysicalDeletionForbiddenException;

trait ImmutableAppendOnly
{
    use PreventsPhysicalDeletion;

    public static function bootImmutableAppendOnly(): void
    {
        static::updating(function ($model): void {
            throw new PhysicalDeletionForbiddenException(
                sprintf('Política estricta CeCap: el registro en %s es append-only e inmutable.', $model->getTable())
            );
        });
    }
}
