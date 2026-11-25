<?php

namespace App\Models\Concerns;

use App\Exceptions\PhysicalDeletionForbiddenException;

trait PreventsPhysicalDeletion
{
    public static function bootPreventsPhysicalDeletion(): void
    {
        static::deleting(function ($model): void {
            throw new PhysicalDeletionForbiddenException(
                sprintf('Política estricta CeCap: no se permite el borrado físico en %s. Utilice inactivación, archivado o reverso auditado.', $model->getTable())
            );
        });
    }

    public function delete(): ?bool
    {
        throw new PhysicalDeletionForbiddenException(
            sprintf('Política estricta CeCap: no se permite el borrado físico en %s. Utilice inactivación, archivado o reverso auditado.', $this->getTable())
        );
    }

    public function forceDelete(): ?bool
    {
        throw new PhysicalDeletionForbiddenException(
            sprintf('Política estricta CeCap: no se permite el borrado físico forzado en %s.', $this->getTable())
        );
    }
}
