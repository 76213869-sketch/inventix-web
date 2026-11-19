<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'empresas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'ruc',
        'direccion',
        'telefono',
        'correo',
        'tipo_negocio',
        'creado_por',
        'modificado_por',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * Accessor for the company logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($extensions as $ext) {
            $path = "logos/empresa_{$this->id}.{$ext}";
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return \Illuminate\Support\Facades\Storage::url($path);
            }
        }
        return null;
    }

    /**
     * Relationship with the Users.
     */
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'empresa_id');
    }

    /**
     * Relationship with the Subscriptions.
     */
    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'empresa_id');
    }
}
