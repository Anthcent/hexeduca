<?php

namespace Tests\Support\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only Eloquent model backed by an ad-hoc table (see
 * PeriodScopeCompositionTest / PeriodScopeNoOpTest, which create and drop
 * `period_scoped_fixtures` per test).
 *
 * Exercises BelongsToTenant + BelongsToActivePeriod stacking together.
 * Mirrors the tenant+period tier of the 3-tier scoping matrix in
 * design.md.
 */
class PeriodScopedFixture extends Model
{
    use BelongsToActivePeriod, BelongsToTenant;

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
