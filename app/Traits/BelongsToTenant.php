<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;

trait BelongsToTenant
{
    /**
     * Boot the trait to add creating listener and global scope.
     */
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->rol === 'super_admin' && session()->has('impersonated_empresa_id')) {
                    if (!$model->empresa_id) {
                        $model->empresa_id = session('impersonated_empresa_id');
                    }
                } elseif ($user->empresa_id) {
                    if (!$model->empresa_id) {
                        $model->empresa_id = $user->empresa_id;
                    }
                }
            }
        });

        static::addGlobalScope(new TenantScope);
    }
}
