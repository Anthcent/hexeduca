<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Modules\Academic\Infrastructure\Http\Requests\StoreGradoRequest;
use Modules\Academic\Infrastructure\Models\Grado;

class GradoController extends Controller
{
    public function store(StoreGradoRequest $request): RedirectResponse
    {
        Grado::create($request->validated());

        return back()->with('success', 'Grado creado.');
    }

    public function update(StoreGradoRequest $request, Grado $grado): RedirectResponse
    {
        $grado->update($request->validated());

        return back()->with('success', 'Grado actualizado.');
    }

    public function destroy(Grado $grado): RedirectResponse
    {
        try {
            $grado->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: este grado está en uso.');
        }

        return back()->with('success', 'Grado eliminado.');
    }
}
