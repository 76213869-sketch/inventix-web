<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, Blameable, Auditable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'categorias';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'estado',
        'creado_por',
        'modificado_por',
    ];

    /**
     * Relationship with Products.
     */
    public function productos()
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
