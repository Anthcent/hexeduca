<?php

namespace Modules\Users\Public\Enums;

/**
 * What a person IS for academic purposes, independent of the access roles
 * they hold: only teachers can be assigned subjects or a homeroom, and only
 * students can be enrolled. Roles answer what a user may do; the type
 * answers who they are, so renaming a role never loses a school's teachers.
 */
enum UserType: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Staff = 'staff';

    /**
     * The type implied by one of the fixed seeded roles. Used while roles
     * are global; anything that is not a teacher or student is staff.
     */
    public static function forRole(string $role): self
    {
        return match ($role) {
            'teacher' => self::Teacher,
            'student' => self::Student,
            default => self::Staff,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Estudiante',
            self::Teacher => 'Docente',
            self::Staff => 'Personal',
        };
    }
}
