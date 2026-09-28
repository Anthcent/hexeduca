<?php

namespace Modules\Subjects\Infrastructure\Http\Controllers\Concerns;

use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Subjects\Domain\Exceptions\InvalidAssignment;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\InvalidSubject;
use Modules\Subjects\Domain\Exceptions\ObservationRequired;
use Modules\Subjects\Domain\Exceptions\PeriodClosed;
use Modules\Subjects\Domain\Exceptions\PlanAssignmentNotFound;
use Modules\Subjects\Domain\Exceptions\ReactivationNotApproved;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\RecordInUse;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Exceptions\SubjectNotFound;

/**
 * Maps the module's domain exceptions to HTTP answers: a missing (or
 * another school's) record is 404, invalid input is a field error, and a
 * refused state change is an error flash on the previous page.
 */
trait HandlesDomainErrors
{
    /**
     * Runs the action and redirects with the success flash, or maps the
     * domain exception.
     *
     * @param  Closure(): (RedirectResponse|mixed)  $action
     */
    protected function attempt(Closure $action, string $success): RedirectResponse
    {
        try {
            $result = $action();
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        }

        return ($result instanceof RedirectResponse ? $result : back())->with('success', $success);
    }

    protected function domainErrorResponse(DomainException $e): RedirectResponse
    {
        if ($e instanceof StudyPlanNotFound || $e instanceof SubjectNotFound || $e instanceof PlanAssignmentNotFound) {
            abort(404);
        }

        if ($e instanceof ObservationRequired) {
            throw ValidationException::withMessages([
                'observation' => 'Ya existe un plan con este código. Agrega una observación para diferenciarlos.',
            ]);
        }

        if ($e instanceof InvalidSubject || $e instanceof InvalidAssignment) {
            throw ValidationException::withMessages([$e->field => $this->fieldMessage($e->field)]);
        }

        $message = match (true) {
            $e instanceof RecordArchived => $e->kind === RecordArchived::PLAN
                ? 'El plan está archivado y es de solo lectura.'
                : 'La asignatura está archivada y es de solo lectura.',
            $e instanceof RecordInUse => 'Tiene datos relacionados y no se puede eliminar. Puedes archivarlo.',
            $e instanceof PeriodClosed => 'El periodo está cerrado; sus asignaciones son de solo lectura.',
            $e instanceof InvalidStatusChange => 'El estado ya había cambiado. Se actualizó la página.',
            $e instanceof ReactivationNotApproved => 'Hay asignaciones que se reemplazarían y no fueron aprobadas. No se hizo ningún cambio.',
            default => throw $e,
        };

        return back()->with('error', $message);
    }

    private function fieldMessage(string $field): string
    {
        return match ($field) {
            'weekly_hours' => 'Las horas semanales deben ser un número entero mayor que cero.',
            'grade_level_id' => 'Selecciona un año válido.',
            'period_id' => 'Selecciona un periodo válido.',
            'offer_id' => 'Selecciona una sección válida del periodo.',
            'subject_id' => 'La asignatura no pertenece al plan asignado.',
            'name' => 'El nombre es obligatorio.',
            default => 'El valor no es válido.',
        };
    }
}
