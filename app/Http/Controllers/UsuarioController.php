<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Establecimiento;
use App\Models\Rol;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class UsuarioController extends Controller
{
    public function __construct(protected UsuarioService $service) {}

    public function index(): View|RedirectResponse
    {
        try {
            $usuarios = $this->service->list();

            return view('usuarios.index', compact('usuarios'));
        } catch (\Exception $e) {
            logger()->error('Error listando usuarios: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al cargar los usuarios');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $roles = Rol::where('status', 1)->orderBy('titulo')->get();
            $eess = Establecimiento::where('ACTIVO', 1)
                ->where('NOM_EESS', 'NOT LIKE', '%MENTAL%')
                ->orderBy('NOM_EESS')->get();

            return view('usuarios.create', compact('roles', 'eess'));
        } catch (\Exception $e) {
            logger()->error('Error creando usuario: '.$e->getMessage());

            return redirect()->route('usuarios.index')->with('error', 'Error al preparar el formulario');
        }
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        try {
            $usuario = $this->service->create($request->validated(), auth()->id());

            return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente');
        } catch (\Exception $e) {
            logger()->error('Error guardando usuario: '.$e->getMessage());

            return redirect()->back()->withInput($request->except(['password', 'password_confirmation']))->with('error', 'Error al guardar el usuario');
        }
    }

    public function edit(User $usuario): View|RedirectResponse
    {
        try {
            $roles = Rol::where('status', 1)->orderBy('titulo')->get();
            $eess = Establecimiento::where('ACTIVO', 1)
                ->where('NOM_EESS', 'NOT LIKE', '%MENTAL%')
                ->orderBy('NOM_EESS')->get();

            return view('usuarios.edit', compact('usuario', 'roles', 'eess'));
        } catch (\Exception $e) {
            logger()->error('Error editando usuario: '.$e->getMessage());

            return redirect()->route('usuarios.index')->with('error', 'Error al cargar el usuario');
        }
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        try {
            $this->service->update($usuario, $request->validated(), auth()->id());

            return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente');
        } catch (\Exception $e) {
            logger()->error('Error actualizando usuario: '.$e->getMessage());

            return redirect()->back()->withInput($request->except(['password', 'password_confirmation']))->with('error', 'Error al actualizar el usuario');
        }
    }

    public function destroy(User $usuario): JsonResponse
    {
        try {
            $this->service->delete($usuario, auth()->id());

            return response()->json(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            logger()->error('Error eliminando usuario: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al eliminar el usuario'], 500);
        }
    }
}
