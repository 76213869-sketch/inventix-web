<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    use HasFactory, BelongsToTenant, Blameable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movimientos_stock';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'producto_id',
        'tipo',
        'cantidad',
        'motivo',
        'referencia_tipo',
        'referencia_id',
        'descripcion',
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
            'cantidad' => 'decimal:3',
        ];
    }

    /**
     * Relationship with Producto.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /**
     * Relationship with Usuario (creator).
     */
    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * Polymorphic relationship to get the parent reference model (Compra, Venta, etc.).
     */
    public function referencia()
    {
        return $this->morphTo();
    }
}
