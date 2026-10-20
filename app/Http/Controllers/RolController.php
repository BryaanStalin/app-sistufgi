<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRolRequest;
use App\Http\Requests\UpdateRolRequest;
use App\Models\Menu;
use App\Models\Rol;
use App\Services\RolService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RolController extends Controller
{
    public function __construct(protected RolService $service) {}

    public function index(): View|RedirectResponse
    {
        try {
            $roles = $this->service->list();

            return view('roles.index', compact('roles'));
        } catch (\Exception $e) {
            logger()->error('Error listando roles: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al cargar los roles');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $menus = Menu::whereNull('padre_id')
                ->where('TB_ACCESO_MENU.status', 1)
                ->orderBy('TB_ACCESO_MENU.orden')
                ->with('hijos.hijos')
                ->get();

            return view('roles.create', compact('menus'));
        } catch (\Exception $e) {
            logger()->error('Error creando rol: '.$e->getMessage());

            return redirect()->route('roles.index')->with('error', 'Error al preparar el formulario');
        }
    }

    public function store(StoreRolRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated(), auth()->id());

            return redirect()->route('roles.index')->with('success', 'Rol creado correctamente');
        } catch (\Exception $e) {
            logger()->error('Error guardando rol: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Error al guardar el rol');
        }
    }

    public function edit(Rol $rol): View|RedirectResponse
    {
        try {
            $menus = Menu::whereNull('padre_id')
                ->where('TB_ACCESO_MENU.status', 1)
                ->orderBy('TB_ACCESO_MENU.orden')
                ->with('hijos.hijos')
                ->get();

            return view('roles.edit', compact('rol', 'menus'));
        } catch (\Exception $e) {
            logger()->error('Error editando rol: '.$e->getMessage());

            return redirect()->route('roles.index')->with('error', 'Error al cargar el rol');
        }
    }

    public function update(UpdateRolRequest $request, Rol $rol): RedirectResponse
    {
        try {
            $this->service->update($rol, $request->validated(), auth()->id());

            return redirect()->route('roles.index')->with('success', 'Rol actualizado correctamente');
        } catch (\Exception $e) {
            logger()->error('Error actualizando rol: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Error al actualizar el rol');
        }
    }

    public function destroy(Rol $rol): JsonResponse
    {
        try {
            $this->service->delete($rol, auth()->id());

            return response()->json(['success' => true, 'message' => 'Rol inactivado correctamente']);
        } catch (\Exception $e) {
            logger()->error('Error eliminando rol: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al inactivar el rol'], 500);
        }
    }
}
