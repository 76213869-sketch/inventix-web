<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, Blameable, Auditable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clientes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'documento_tipo',
        'documento_numero',
        'direccion',
        'telefono',
        'correo',
        'creado_por',
        'modificado_por',
    ];

    /**
     * Relationship with Sales.
     */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }
}
