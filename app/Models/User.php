<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
/**
 * @mixin IdeHelperUser
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use  HasFactory, Notifiable;

    protected $table = 'TB_ACCESO_USUARIO';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'user',
        'status',
        'DNI',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** Formato de fecha compatible con SQL Server (sin microsegundos) */
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relación: Un usuario tiene un EESS asignado
     */
    public function establecimientos()
    {
        return $this->belongsToMany(Establecimiento::class, 'TB_ACCESO_USUARIO_X_EESS', 'id_usuario', 'RENIPRESS','id','RENIPRESS')
            ->wherePivot('activo', 1)
            ->withPivot('activo', 'created_by', 'updated_by')
            ->withTimestamps();
    }

    /**
     * Relación: Un usuario tiene muchos roles
     */
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'TB_ACCESO_USUARIO_X_ROL', 'usuario_id', 'rol_id')
            ->where('TB_ACCESO_USUARIO_X_ROL.status', 1)
            ->withPivot('status', 'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by')
            ->withTimestamps();
    }

    /**
     * Obtener el rol principal del usuario
     */
    public function getRolPrincipal()
    {
        return $this->roles()->first();
    }

    /**
     * Obtener menús del usuario según sus roles (con soporte para 3 niveles)
     */
    public function getMenus()
    {
        $rolesIds = $this->roles()->pluck('TB_ACCESO_ROL.id')->toArray();

        if (empty($rolesIds)) {
            return collect();
        }

        return Menu::whereNull('padre_id') // Solo menús de nivel 1 (padres principales)
            ->where('status', 1)
            ->whereHas('roles', function ($query) use ($rolesIds) {
                $query->whereIn('id', $rolesIds);
            })
            ->orderBy('orden')
            ->with([
                'hijos' => function ($query) use ($rolesIds) {
                    $query->where('status', 1)
                        ->whereHas('roles', function ($rolesQuery) use ($rolesIds) {
                            $rolesQuery->whereIn('id', $rolesIds);
                        })
                        ->with([ // Cargar los sub-hijos de cada hijo
                            'hijos' => function ($subQuery) use ($rolesIds) {
                                $subQuery->where('status', 1)
                                    ->whereHas('roles', function ($rolesSubQuery) use ($rolesIds) {
                                        $rolesSubQuery->whereIn('id', $rolesIds);
                                    });
                            }
                        ]);
                }
            ])
            ->get();
    }

    public function isAdministrador(): bool
    {
        return $this->roles()->where('TB_ACCESO_ROL.titulo', 'Administrador')->exists();
    }

    public function getAllowedMenuPaths(): array
    {
        $paths = [];

        foreach ($this->getMenus() as $menu) {
            if (!empty($menu->link)) {
                $paths[] = $this->normalizeMenuPath($menu->link);
            }

            foreach ($menu->hijos as $hijo) {
                if (!empty($hijo->link)) {
                    $paths[] = $this->normalizeMenuPath($hijo->link);
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function normalizeMenuPath(string $link): string
    {
        $path = '/' . trim($link, '/');

        if (str_starts_with($path, '/admin/')) {
            $path = substr($path, 6);
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Obtener el correo utilizado para el restablecimiento de contraseña.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /**
     * Nombre de la llave primaria utilizada por la autenticación.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

}
