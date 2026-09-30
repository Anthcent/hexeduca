<?php

namespace Modules\TeachingAssignments\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\TeachingAssignments\Application\DTOs\AssignTeacherData;
use Modules\TeachingAssignments\Application\Queries\TeachingBoard;
use Modules\TeachingAssignments\Application\Services\PeriodGuard;
use Modules\TeachingAssignments\Application\UseCases\AssignTeacher;
use Modules\TeachingAssignments\Application\UseCases\EndTeachingAssignment;
use Modules\TeachingAssignments\Application\UseCases\SetOfferCoordinator;
use Modules\TeachingAssignments\Domain\Exceptions\AssignmentEnded;
use Modules\TeachingAssignments\Domain\Exceptions\AssignmentNotFound;
use Modules\TeachingAssignments\Domain\Exceptions\InvalidTeachingAssignment;
use Modules\TeachingAssignments\Domain\Exceptions\PeriodClosed;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;
use Modules\TeachingAssignments\Infrastructure\Http\Requests\AssignTeacherRequest;
use Modules\TeachingAssignments\Infrastructure\Http\Requests\CoordinatorRequest;

class TeachingAssignmentsController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, TeachingBoard $board, PeriodGuard $guard): Response
    {
        $schoolId = $tenantContext->current()->id;
        $all = $periods->allForSchool($schoolId);

        usort($all, fn (AcademicPeriodDTO $a, AcademicPeriodDTO $b): int => strcmp($b->startsOn, $a->startsOn));

        $requested = (int) $request->query('period', 0);
        $selected = null;

        foreach ($all as $period) {
            if ($period->id === $requested) {
                $selected = $period;
            }
        }

        $selected ??= array_values(array_filter($all, fn (AcademicPeriodDTO $p): bool => $p->isActive))[0] ?? ($all[0] ?? null);

        return Inertia::render('TeachingAssignments::Index', [
            'periods' => array_map(fn (AcademicPeriodDTO $p): array => ['id' => $p->id, 'name' => $p->name, 'isActive' => $p->isActive], $all),
            'period' => $selected ? [
                'id' => $selected->id,
                'name' => $selected->name,
                'isActive' => $selected->isActive,
                'isOpen' => $guard->isOpen($selected),
            ] : null,
            'board' => $selected ? $board->forPeriod($schoolId, $selected->id) : ['offers' => [], 'teachers' => []],
        ]);
    }

    public function store(AssignTeacherRequest $request, AssignTeacher $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle(new AssignTeacherData(
            schoolId: $tenantContext->current()->id,
            periodId: $request->integer('period_id'),
            offerId: $request->integer('offer_id'),
            subjectId: $request->integer('subject_id'),
            teacherId: $request->integer('teacher_id'),
            role: TeachingRole::from($request->string('role')->toString()),
        )), 'Docente asignado.');
    }

    public function destroy(int $assignment, EndTeachingAssignment $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($assignment, $tenantContext->current()->id), 'Asignación finalizada.');
    }

    public function coordinator(int $offer, CoordinatorRequest $request, SetOfferCoordinator $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $teacherId = $request->filled('teacher_id') ? $request->integer('teacher_id') : null;

        return $this->attempt(
            fn () => $useCase->handle($tenantContext->current()->id, $request->integer('period_id'), $offer, $teacherId),
            $teacherId === null ? 'Coordinador quitado.' : 'Coordinador asignado.',
        );
    }

    private function attempt(Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (AssignmentNotFound) {
            abort(404);
        } catch (InvalidTeachingAssignment $e) {
            throw ValidationException::withMessages([$e->field => match ($e->field) {
                'period_id' => 'Selecciona un periodo válido.',
                'offer_id' => 'La sección no pertenece al periodo.',
                'subject_id' => 'La asignatura no está vigente para esta sección.',
                'teacher_id' => str_contains($e->getMessage(), 'other role')
                    ? 'Este docente ya tiene el otro rol en la asignatura.'
                    : 'Selecciona un docente de la institución.',
                default => 'El valor no es válido.',
            }]);
        } catch (DomainException $e) {
            return back()->with('error', match (true) {
                $e instanceof PeriodClosed => 'El periodo está cerrado; sus asignaciones son de solo lectura.',
                $e instanceof AssignmentEnded => 'La asignación ya había finalizado. Se actualizó la página.',
                default => throw $e,
            });
        }

        return back()->with('success', $success);
    }
}
