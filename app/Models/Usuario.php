<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, BelongsToTenant, Blameable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'correo',
        'password',
        'rol',
        'estado',
        'creado_por',
        'modificado_por',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationship with the Empresa (Tenant).
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Role checking helpers.
     */
    public function isSuperAdmin(): bool
    {
        return $this->rol === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->rol === 'administrador';
    }

    public function isCajero(): bool
    {
        return $this->rol === 'cajero';
    }

    public function isAlmacenero(): bool
    {
        return $this->rol === 'almacenero';
    }

    public function isVendedor(): bool
    {
        return $this->rol === 'vendedor';
    }

    public function isGerente(): bool
    {
        return $this->rol === 'gerente';
    }

    public function isEmpleado(): bool
    {
        return $this->rol === 'empleado' || $this->rol === 'vendedor' || $this->rol === 'almacenero';
    }

    /**
     * Get the user's email address (for Breeze compatibility).
     */
    public function getEmailAttribute(): string
    {
        return $this->correo;
    }

    /**
     * Check if user has any of the given roles.
     * 
     * @param array<string> $roles
     */
    public function hasRole(array $roles): bool
    {
        return in_array($this->rol, $roles);
    }
}
