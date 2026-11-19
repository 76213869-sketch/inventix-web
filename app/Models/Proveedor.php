<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, Blameable, Auditable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'proveedores';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'ruc',
        'direccion',
        'telefono',
        'correo',
        'estado',
        'creado_por',
        'modificado_por',
    ];

    /**
     * Relationship with Purchases.
     */
    public function compras()
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }
}
