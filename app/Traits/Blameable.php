<?php

namespace App\Traits;

trait Blameable
{
    /**
     * Boot the trait to add creating and updating listeners.
     */
    public static function bootBlameable(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                // Asignar el ID del usuario creador si no está asignado
                if (!$model->creado_por) {
                    $model->creado_por = auth()->id();
                }
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                // Asignar el ID del usuario modificador
                $model->modificado_por = auth()->id();
            }
        });
    }
}
