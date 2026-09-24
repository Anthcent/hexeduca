<?php

namespace Modules\Sections\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Modules\Sections\Application\DTOs\CreateSectionData;
use Modules\Sections\Application\UseCases\CreateSection;
use Modules\Sections\Infrastructure\Http\Requests\StoreSectionRequest;
use Modules\Sections\Infrastructure\Models\Section;

class SectionController extends Controller
{
    public function index(TenantContext $tenantContext)
    {
        return Inertia::render('Sections::Sections', [
            'sections' => Section::where('school_id', $tenantContext->current()->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreSectionRequest $request, CreateSection $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $useCase->handle(new CreateSectionData(
            schoolId: $tenantContext->current()->id,
            name: $request->string('name')->toString(),
        ));

        return back()->with('success', 'Sección creada.');
    }

    public function update(StoreSectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        return back()->with('success', 'Sección actualizada.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        try {
            $section->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: esta sección está en uso.');
        }

        return back()->with('success', 'Sección eliminada.');
    }
}
