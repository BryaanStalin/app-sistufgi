<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MenuController extends Controller
{
    public function __construct(protected MenuService $service) {}

    public function index(): View|RedirectResponse
    {
        try {
            $menus = $this->service->list();

            return view('menus.index', compact('menus'));
        } catch (\Exception $e) {
            logger()->error('Error listando menús: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error al cargar los menús');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $parents = Menu::where('TB_ACCESO_MENU.status', 1)
                ->orderBy('TB_ACCESO_MENU.orden')
                ->get();

            return view('menus.create', compact('parents'));
        } catch (\Exception $e) {
            logger()->error('Error creando menú: '.$e->getMessage());

            return redirect()->route('menus.index')->with('error', 'Error al preparar el formulario');
        }
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated(), auth()->id());

            return redirect()->route('menus.index')->with('success', 'Menú creado correctamente');
        } catch (\Exception $e) {
            logger()->error('Error guardando menú: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Error al guardar el menú');
        }
    }

    public function edit(Menu $menu): View|RedirectResponse
    {
        try {
            $parents = Menu::where('TB_ACCESO_MENU.status', 1)
                ->where('id', '<>', $menu->id)
                ->orderBy('TB_ACCESO_MENU.orden')
                ->get();

            return view('menus.edit', compact('menu', 'parents'));
        } catch (\Exception $e) {
            logger()->error('Error editando menú: '.$e->getMessage());

            return redirect()->route('menus.index')->with('error', 'Error al cargar el menú');
        }
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        try {
            $this->service->update($menu, $request->validated(), auth()->id());

            return redirect()->route('menus.index')->with('success', 'Menú actualizado correctamente');
        } catch (\Exception $e) {
            logger()->error('Error actualizando menú: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Error al actualizar el menú');
        }
    }

    public function destroy(Menu $menu): JsonResponse
    {
        try {
            $this->service->delete($menu, auth()->id());

            return response()->json(['success' => true, 'message' => 'Menú inactivado correctamente']);
        } catch (\Exception $e) {
            logger()->error('Error eliminando menú: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al inactivar el menú'], 500);
        }
    }
}
