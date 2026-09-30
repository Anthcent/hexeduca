<?php

namespace Modules\Grades\Domain\ValueObjects;

use Modules\Grades\Domain\Exceptions\InvalidPlan;

/**
 * The shape of an evaluation plan: referentes, each worth exactly 20 points
 * split into lettered indicators. The teacher chooses how many of each.
 */
final class PlanStructure
{
    public const REFERENT_POINTS = 20;

    public const MAX_REFERENTS = 10;

    public const MAX_INDICATORS = 10;

    /**
     * @param  list<array{topic: string, technique: ?string, indicators: list<array{description: string, maxPoints: int}>}>  $referents
     */
    private function __construct(public readonly array $referents) {}

    /**
     * @param  list<array{topic: string, technique?: ?string, indicators: list<array{description: string, maxPoints: int}>}>  $referents
     *
     * @throws InvalidPlan
     */
    public static function fromArray(array $referents): self
    {
        if (count($referents) < 1 || count($referents) > self::MAX_REFERENTS) {
            throw InvalidPlan::referentCount(self::MAX_REFERENTS);
        }

        $clean = [];

        foreach (array_values($referents) as $index => $referent) {
            $position = $index + 1;
            $topic = trim((string) ($referent['topic'] ?? ''));

            if ($topic === '') {
                throw InvalidPlan::missingTopic($position);
            }

            $indicators = array_values($referent['indicators'] ?? []);

            if (count($indicators) < 1 || count($indicators) > self::MAX_INDICATORS) {
                throw InvalidPlan::indicatorCount($position, self::MAX_INDICATORS);
            }

            $sum = 0;
            $cleanIndicators = [];

            foreach ($indicators as $i => $indicator) {
                $points = (int) ($indicator['maxPoints'] ?? 0);
                $description = trim((string) ($indicator['description'] ?? ''));

                if ($points < 1 || $points > self::REFERENT_POINTS) {
                    throw InvalidPlan::indicatorPoints($position, self::letter($i));
                }

                if ($description === '') {
                    throw InvalidPlan::missingDescription($position, self::letter($i));
                }

                $sum += $points;
                $cleanIndicators[] = ['description' => $description, 'maxPoints' => $points];
            }

            if ($sum !== self::REFERENT_POINTS) {
                throw InvalidPlan::referentSum($position, $sum);
            }

            $technique = trim((string) ($referent['technique'] ?? ''));
            $clean[] = ['topic' => $topic, 'technique' => $technique === '' ? null : $technique, 'indicators' => $cleanIndicators];
        }

        return new self($clean);
    }

    /**
     * A, B, C… for the indicator at that zero-based index.
     */
    public static function letter(int $index): string
    {
        return chr(ord('A') + $index);
    }

    /**
     * Same referentes and the same indicator points in the same order: only
     * texts differ. Once a plan has scores, only such changes are allowed.
     */
    public function sameShapeAs(self $other): bool
    {
        return $this->shape() === $other->shape();
    }

    /**
     * @return list<list<int>>
     */
    private function shape(): array
    {
        return array_map(fn (array $r): array => array_column($r['indicators'], 'maxPoints'), $this->referents);
    }
}
