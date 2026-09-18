<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Modules\Academic\Infrastructure\Http\Requests\StoreNivelAcademicoRequest;
use Modules\Academic\Infrastructure\Models\NivelAcademico;

class NivelAcademicoController extends Controller
{
    public function store(StoreNivelAcademicoRequest $request): RedirectResponse
    {
        NivelAcademico::create($request->validated());

        return back()->with('success', 'Nivel académico creado.');
    }

    public function update(StoreNivelAcademicoRequest $request, NivelAcademico $nivel): RedirectResponse
    {
        $nivel->update($request->validated());

        return back()->with('success', 'Nivel académico actualizado.');
    }

    public function destroy(NivelAcademico $nivel): RedirectResponse
    {
        try {
            $nivel->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: hay grados que dependen de este nivel.');
        }

        return back()->with('success', 'Nivel académico eliminado.');
    }
}
