<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Traits;

use IFRS\Context\EntityContext;
use IFRS\Exceptions\EntityContextMismatch;
use IFRS\Scopes\EntityScope;
use IFRS\Models\Entity;

trait Segregating
{

    /**
     * Register EntityScope for Model.
     *
     * @return null
     *
     * @codeCoverageIgnore
     */
    public static function bootSegregating()
    {
        static::addGlobalScope(new EntityScope);

        static::creating(
            function ($model) {
                $entityId = app(EntityContext::class)->requireEntity()->getKey();

                if (is_null($model->entity_id)) {
                    $model->entity_id = $entityId;
                } elseif ((string) $model->entity_id !== (string) $entityId) {
                    throw new EntityContextMismatch(
                        sprintf(
                            'Model entity [%s] does not match active accounting entity [%s].',
                            $model->entity_id,
                            $entityId
                        )
                    );
                }
            }
        );
        return null;
    }

    /**
     * Model's Parent Entity.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entity()
    {
        return $this->BelongsTo(Entity::class);
    }
}
