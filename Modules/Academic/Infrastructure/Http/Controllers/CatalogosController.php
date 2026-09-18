<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\NivelAcademico;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;

/**
 * Read-only index for the four academic catalogs (niveles, grados,
 * secciones, periodos). Mutations are handled per-catalog by their own
 * thin controllers (NivelAcademicoController, GradoController, ...).
 */
class CatalogosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Academic::Catalogos', [
            'niveles' => NivelAcademico::withCount('grados')->orderBy('name')->get(),
            'grados' => Grado::with('nivelAcademico:id,name')->orderBy('order')->get(),
            'secciones' => Seccion::orderBy('name')->get(),
            'periodos' => PeriodoAcademico::orderByDesc('starts_on')->get(),
        ]);
    }
}
