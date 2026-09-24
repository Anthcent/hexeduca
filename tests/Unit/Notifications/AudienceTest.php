<?php

use Modules\Notifications\Domain\Exceptions\InvalidAnnouncement;
use Modules\Notifications\Domain\ValueObjects\Audience;

test('each audience includes the right groups', function (string $value, bool $teachers, bool $students) {
    $audience = Audience::fromValue($value);

    expect($audience->includesTeachers())->toBe($teachers)
        ->and($audience->includesStudents())->toBe($students);
})->with([
    'teachers' => ['teachers', true, false],
    'students' => ['students', false, true],
    'all means teachers plus students' => ['all', true, true],
]);

test('an unknown audience is rejected', function (string $value) {
    Audience::fromValue($value);
})->with(['staff', 'ALL', ''])->throws(InvalidAnnouncement::class);

test('the accepted values are teachers, students and all', function () {
    expect(Audience::values())->toBe(['teachers', 'students', 'all']);
});
