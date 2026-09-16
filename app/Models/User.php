<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * El segundo factor queda fuera de $fillable a propósito: se activa y se
     * desactiva por su propio flujo verificado, nunca por asignación masiva
     * desde un formulario de perfil.
     */

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            // Cifrados en reposo: quien lea la base sin la APP_KEY no puede
            // generar códigos válidos ni suplantar a un profesional.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Solo cuenta como activo si el usuario llegó a verificar un código.
     *
     * Quien abandona la configuración a la mitad conserva un secreto guardado
     * pero sigue entrando con su contraseña, en vez de quedar fuera de su
     * propia cuenta.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function appointmentsAsDoctor(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }
}
