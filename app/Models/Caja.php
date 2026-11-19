<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caja extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cajas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'fecha_apertura',
        'hora_apertura',
        'fecha_cierre',
        'hora_cierre',
        'monto_inicial',
        'monto_final',
        'monto_esperado',
        'diferencia',
        'motivo_diferencia',
        'observacion_apertura',
        'observacion_cierre',
        'estado',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'date',
            'fecha_cierre' => 'date',
            'monto_inicial' => 'decimal:2',
            'monto_final' => 'decimal:2',
            'monto_esperado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    /**
     * Relationship with Usuario.
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Relationship with Movimientos.
     */
    public function movimientos()
    {
        return $this->hasMany(MovimientoCaja::class, 'caja_id');
    }

    /**
     * Relationship with Gastos.
     */
    public function gastos()
    {
        return $this->hasMany(GastoCaja::class, 'caja_id');
    }

    /**
     * Relationship with Retiros.
     */
    public function retiros()
    {
        return $this->hasMany(RetiroCaja::class, 'caja_id');
    }

    /**
     * Relationship with Ingresos.
     */
    public function ingresos()
    {
        return $this->hasMany(IngresoCaja::class, 'caja_id');
    }

    /**
     * Relationship with Ventas.
     */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'caja_id');
    }
}
