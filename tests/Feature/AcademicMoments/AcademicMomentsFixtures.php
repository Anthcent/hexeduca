<?php

namespace Tests\Feature\AcademicMoments;

use App\Tenancy\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Row builders for moments (lapsos). Moments carry no school_id: they belong
 * to a school through their period.
 */
final class AcademicMomentsFixtures
{
    public static function url(School $school, string $path = ''): string
    {
        return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/academic-moments'.$path;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function moment(int $periodId, string $name, int $order = 1, ?string $opensOn = null, ?string $closesOn = null, array $overrides = []): int
    {
        return DB::table('momentos_academicos')->insertGetId(array_merge([
            'periodo_academico_id' => $periodId,
            'name' => $name,
            'order' => $order,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'grading_opens_on' => $opensOn,
            'grading_closes_on' => $closesOn,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
