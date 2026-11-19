<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    use HasFactory, BelongsToTenant, Blameable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recetas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'producto_padre_id',
        'producto_insumo_id',
        'cantidad',
        'creado_por',
        'modificado_por',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
        ];
    }

    /**
     * Relationship with the Parent Product.
     */
    public function productoPadre()
    {
        return $this->belongsTo(Producto::class, 'producto_padre_id');
    }

    /**
     * Relationship with the Input/Ingredient Product.
     */
    public function productoInsumo()
    {
        return $this->belongsTo(Producto::class, 'producto_insumo_id');
    }
}
