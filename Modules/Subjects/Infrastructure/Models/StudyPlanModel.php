<?php

namespace Modules\Subjects\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

class StudyPlanModel extends Model
{
    use BelongsToTenant;

    protected $table = 'study_plans';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'code',
        'name',
        'observation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $plan): void {
            $plan->search_text = self::searchText($plan->code.' '.$plan->name.' '.$plan->observation);
        });
    }

    /**
     * Accent- and case-folded text for the plan search. SQLite's LOWER only
     * folds ASCII and PostgreSQL has no unaccent by default, so the folding
     * happens in PHP, for the stored column and for the search term alike.
     */
    public static function searchText(string $text): string
    {
        return strtolower(Str::ascii($text));
    }

    /**
     * @return HasMany<SubjectModel, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(SubjectModel::class, 'study_plan_id');
    }

    /**
     * @return HasMany<PlanAssignmentModel, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PlanAssignmentModel::class, 'study_plan_id');
    }

    public function toEntity(): StudyPlan
    {
        return new StudyPlan(
            id: $this->id,
            schoolId: $this->school_id,
            code: $this->code,
            name: $this->name,
            observation: $this->observation,
            status: RecordStatus::from($this->status),
        );
    }
}
