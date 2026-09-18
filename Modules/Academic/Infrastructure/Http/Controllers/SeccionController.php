<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Modules\Academic\Infrastructure\Http\Requests\StoreSeccionRequest;
use Modules\Academic\Infrastructure\Models\Seccion;

class SeccionController extends Controller
{
    public function store(StoreSeccionRequest $request): RedirectResponse
    {
        Seccion::create($request->validated());

        return back()->with('success', 'Sección creada.');
    }

    public function update(StoreSeccionRequest $request, Seccion $seccion): RedirectResponse
    {
        $seccion->update($request->validated());

        return back()->with('success', 'Sección actualizada.');
    }

    public function destroy(Seccion $seccion): RedirectResponse
    {
        try {
            $seccion->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: esta sección está en uso.');
        }

        return back()->with('success', 'Sección eliminada.');
    }
}
