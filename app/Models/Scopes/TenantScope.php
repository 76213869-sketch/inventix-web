<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Flag to prevent infinite recursion when resolving the authenticated user.
     *
     * @var bool
     */
    protected static bool $resolving = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolving) {
            return;
        }

        self::$resolving = true;

        if (auth()->check()) {
            $user = auth()->user();
            if ($user->rol === 'super_admin') {
                if (session()->has('impersonated_empresa_id')) {
                    $builder->where($model->getTable() . '.empresa_id', session('impersonated_empresa_id'));
                } else {
                    // Si el modelo es Usuario, no filtramos por empresa_id = 0 para permitir cargar el Super Admin
                    if ($model instanceof \App\Models\Usuario) {
                        // No aplicar filtro
                    } else {
                        $builder->where($model->getTable() . '.empresa_id', 0);
                    }
                }
            } elseif ($user && $user->empresa_id) {
                $builder->where($model->getTable() . '.empresa_id', $user->empresa_id);
            }
        }

        self::$resolving = false;
    }
}
