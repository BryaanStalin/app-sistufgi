<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaniaEnlaceRequest;
use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Services\CampaniaUsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaniaEnlaceController extends Controller
{
    public function __construct(protected CampaniaUsuarioService $service)
    {
    }

    public function store(StoreCampaniaEnlaceRequest $request, Campania $campania): RedirectResponse
    {
        $this->service->link($campania, $request->validated(), $request->user()->id);

        return back()->with('success', 'Usuarios enlazados correctamente.');
    }

    public function destroy(Request $request, Campania $campania, CampaniaEnlace $enlace): JsonResponse
    {
        $this->service->unlink($campania, $enlace, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Enlace retirado correctamente.']);
    }
}
