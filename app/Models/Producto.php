<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, Blameable, Auditable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'productos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'parent_id',
        'nombre',
        'descripcion',
        'codigo_barras',
        'tipo_producto',
        'tipo_venta',
        'unidad_medida',
        'precio_compra',
        'precio_venta',
        'stock_minimo',
        'stock_actual',
        'metadatos',
        'estado',
        'creado_por',
        'modificado_por',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['imagen_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadatos' => 'array',
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'stock_minimo' => 'decimal:3',
            'stock_actual' => 'decimal:3',
        ];
    }

    /**
     * Relationship with Categoria.
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * Relationship with Parent Product (for grouping variations).
     */
    public function parent()
    {
        return $this->belongsTo(Producto::class, 'parent_id');
    }

    /**
     * Relationship with Child Product Variations.
     */
    public function variantes()
    {
        return $this->hasMany(Producto::class, 'parent_id');
    }

    /**
     * Relationship with Recipes where this product is the final composite product.
     */
    public function recetas()
    {
        return $this->hasMany(Receta::class, 'producto_padre_id');
    }

    /**
     * Relationship with Recipes where this product is used as an input/ingredient.
     */
    public function insumos()
    {
        return $this->hasMany(Receta::class, 'producto_insumo_id');
    }

    /**
     * Relationship with Stock Movements.
     */
    public function movimientosStock()
    {
        return $this->hasMany(MovimientoStock::class, 'producto_id');
    }

    /**
     * Accessor for public product image URL.
     */
    public function getImagenUrlAttribute()
    {
        $imagen = $this->metadatos['imagen'] ?? null;
        if ($imagen) {
            if (filter_var($imagen, FILTER_VALIDATE_URL) || str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
                return $imagen;
            }
            return asset('storage/' . $imagen);
        }
        return null;
    }
}
