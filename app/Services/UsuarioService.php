<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    public function list(): Collection
    {
        return User::with('roles')->orderBy('nombre')->get();
    }

    /** @param array{nombre: string, email: string, user: string, password: string, DNI?: ?string, status: int|bool|string, role: int|string, eess?: ?string} $data */
    public function create(array $data, int $actorId): User
    {
        return DB::transaction(function () use ($data, $actorId): User {
            $user = new User;
            $user->nombre = $data['nombre'];
            $user->email = $data['email'];
            $user->user = $data['user'];
            $user->password = $data['password'];
            $user->DNI = $data['DNI'] ?? null;
            $user->status = $data['status'];
            $user->created_by = $actorId;
            $user->updated_by = $actorId;
            $user->save();

            $this->syncRoles($user, [(int) $data['role']], $actorId);
            $this->syncEess($user, [$data['eess'] ?? null], $actorId);

            return $user;
        });
    }

    /** @param array{nombre: string, email: string, user: string, password?: ?string, DNI?: ?string, status: int|bool|string, role: int|string, eess?: ?string} $data */
    public function update(User $usuario, array $data, int $actorId): User
    {
        return DB::transaction(function () use ($usuario, $data, $actorId): User {
            $usuario->nombre = $data['nombre'];
            $usuario->email = $data['email'];
            $usuario->user = $data['user'];
            $usuario->DNI = $data['DNI'] ?? null;
            $usuario->status = $data['status'];
            $usuario->updated_by = $actorId;

            if (isset($data['password']) && $data['password'] !== '') {
                $usuario->password = $data['password'];
            }

            $usuario->save();

            $this->syncRoles($usuario, [(int) $data['role']], $actorId);
            $this->syncEess($usuario, [$data['eess'] ?? null], $actorId);

            return $usuario;
        });
    }

    public function delete(User $usuario, int $actorId): void
    {
        DB::transaction(function () use ($usuario, $actorId): void {
            DB::table('TB_ACCESO_USUARIO_X_ROL')->where('usuario_id', $usuario->id)->update([
                'status' => 0,
                'deleted_by' => $actorId,
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]);

            $usuario->status = 0;
            $usuario->updated_by = $actorId;
            $usuario->deleted_by = $actorId;
            $usuario->save();
        });
    }

    /** @param array{0: int} $roles */
    protected function syncRoles(User $usuario, array $roles, int $actorId): void
    {
        $roleId = $roles[0];
        DB::table('TB_ACCESO_USUARIO_X_ROL')->where('usuario_id', $usuario->id)
            ->where('rol_id', '<>', $roleId)->update([
                'status' => 0, 'updated_by' => $actorId, 'deleted_by' => $actorId, 'updated_at' => now(),
            ]);
        $key = ['usuario_id' => $usuario->id, 'rol_id' => $roleId];
        $existing = DB::table('TB_ACCESO_USUARIO_X_ROL')->where($key)->first();
        $pivot = [
            'status' => 1,
            'updated_by' => $actorId,
            'updated_at' => now(),
            'deleted_by' => null,
        ];
        if ($existing === null) {
            $pivot += ['created_by' => $actorId, 'created_at' => now()];
        }
        DB::table('TB_ACCESO_USUARIO_X_ROL')->updateOrInsert($key, $pivot);
    }

    /** @param array{0: ?string} $eess */
    protected function syncEess(User $usuario, array $eess, int $actorId): void
    {
        $eessIds = array_values(array_filter($eess, fn (?string $code): bool => $code !== null && $code !== ''));
        DB::table('TB_ACCESO_USUARIO_X_EESS')->where('id_usuario', $usuario->id)
            ->whereNotIn('RENIPRESS', $eessIds)->update([
                'activo' => 0, 'updated_by' => $actorId, 'updated_at' => now(),
            ]);
        foreach ($eessIds as $code) {
            $key = ['id_usuario' => $usuario->id, 'RENIPRESS' => $code];
            $existing = DB::table('TB_ACCESO_USUARIO_X_EESS')->where($key)->first();
            $pivot = ['activo' => 1, 'updated_by' => $actorId, 'updated_at' => now()];
            if ($existing === null) {
                $pivot += ['created_by' => $actorId, 'created_at' => now()];
            }
            DB::table('TB_ACCESO_USUARIO_X_EESS')->updateOrInsert($key, $pivot);
        }
    }
}
