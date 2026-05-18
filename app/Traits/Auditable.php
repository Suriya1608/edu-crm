<?php

namespace App\Traits;

use App\Services\AuditLogService;

/**
 * Automatically records create / update / delete events for any Eloquent model.
 *
 * Models that need to exclude noisy or sensitive fields should declare:
 *   protected array $auditExclude = ['field_a', 'field_b'];
 *
 * Fields excluded by default from every model: password, remember_token, updated_at.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (self $model) {
            AuditLogService::log(
                strtolower(class_basename($model)) . '.created',
                class_basename($model),
                $model->getKey(),
                [],
                $model->filterAuditAttributes($model->getAttributes())
            );
        });

        static::updated(function (self $model) {
            $changed = $model->filterAuditAttributes($model->getDirty());
            if (empty($changed)) {
                return;
            }

            AuditLogService::log(
                strtolower(class_basename($model)) . '.updated',
                class_basename($model),
                $model->getKey(),
                array_intersect_key($model->getOriginal(), $changed),
                $changed
            );
        });

        static::deleted(function (self $model) {
            AuditLogService::log(
                strtolower(class_basename($model)) . '.deleted',
                class_basename($model),
                $model->getKey(),
                $model->filterAuditAttributes($model->getAttributes()),
                []
            );
        });
    }

    protected function filterAuditAttributes(array $attributes): array
    {
        $exclude = array_merge(
            ['password', 'remember_token', 'updated_at'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );

        return array_diff_key($attributes, array_flip($exclude));
    }
}
