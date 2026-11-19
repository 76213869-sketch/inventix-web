<?php

namespace App\Traits;

use App\Models\ActividadSistema;

trait Auditable
{
    /**
     * Boot the trait to hook into Eloquent event cycles.
     */
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            self::logActivity($model, 'creacion', null, self::getAuditableAttributes($model));
        });

        static::updated(function ($model) {
            $changed = $model->getDirty();
            
            // Exclude ignored fields
            $ignored = ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
            foreach ($ignored as $field) {
                unset($changed[$field]);
            }

            if (empty($changed)) {
                return;
            }

            $before = [];
            $after = [];

            foreach ($changed as $key => $val) {
                $before[$key] = $model->getOriginal($key);
                $after[$key] = $val;
            }

            self::logActivity($model, 'actualizacion', $before, $after);
        });

        static::deleted(function ($model) {
            self::logActivity($model, 'eliminacion', self::getAuditableAttributes($model), null);
        });
    }

    /**
     * Get clean auditable attributes of model.
     */
    protected static function getAuditableAttributes($model): array
    {
        $attributes = $model->getAttributes();
        $ignored = ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
        foreach ($ignored as $field) {
            unset($attributes[$field]);
        }
        return $attributes;
    }

    /**
     * Log system activity in Database.
     */
    protected static function logActivity($model, string $event, ?array $before, ?array $after): void
    {
        $userId = auth()->id();
        $empresaId = auth()->check() ? auth()->user()->empresa_id : $model->empresa_id;

        // Skip logging audit itself to avoid infinite loops
        if ($model->getTable() === 'actividad_sistema') {
            return;
        }

        ActividadSistema::create([
            'empresa_id' => $empresaId,
            'usuario_id' => $userId,
            'evento' => $event,
            'tabla' => $model->getTable(),
            'registro_id' => $model->getKey(),
            'valores_anteriores' => $before,
            'valores_nuevos' => $after,
            'ip' => request()->ip(),
            'agente_usuario' => request()->userAgent(),
        ]);
    }
}
