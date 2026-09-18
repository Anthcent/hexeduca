<?php

namespace Tests\Support\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Infrastructure\Period\Concerns\BelongsToActivePeriodo;

/**
 * Test-only Eloquent model backed by an ad-hoc table (see
 * PeriodScopeCompositionTest / PeriodScopeNoOpTest, which create and drop
 * `period_scoped_fixtures` per test).
 *
 * Exists solely to exercise BelongsToTenant + BelongsToActivePeriodo
 * stacking together, since no shipped model uses both traits yet — that
 * lands with OfertaAcademica/Matricula in a later batch (Phase 6-7).
 * Mirrors the tenant+period tier of the 3-tier scoping matrix in
 * design.md.
 */
class PeriodScopedFixture extends Model
{
    use BelongsToActivePeriodo, BelongsToTenant;

    protected $table = 'period_scoped_fixtures';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'periodo_academico_id',
        'name',
    ];
}
