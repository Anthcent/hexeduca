<?php

use Modules\Users\Domain\Entities\User;
use Modules\Users\Domain\Events\UserRegistered;
use Modules\Users\Domain\ValueObjects\Email;

test('email validates and exposes its value', function () {
    $email = new Email('student@example.test');

    expect($email->value())->toBe('student@example.test')
        ->and((string) $email)->toBe('student@example.test')
        ->and($email->equals(new Email('student@example.test')))->toBeTrue()
        ->and($email->equals(new Email('other@example.test')))->toBeFalse();
});

test('email rejects invalid addresses', function () {
    new Email('not-an-email');
})->throws(InvalidArgumentException::class, 'Invalid email address: not-an-email');

test('user entity remains framework agnostic and can be renamed', function () {
    $email = new Email('teacher@example.test');
    $user = new User(7, 'Original Name', $email);

    $user->renameTo('Updated Name');

    expect($user->id())->toBe(7)
        ->and($user->name())->toBe('Updated Name')
        ->and($user->email())->toBe($email);
});

test('user registered event carries its domain entity', function () {
    $user = new User(null, 'New User', new Email('new@example.test'));

    expect((new UserRegistered($user))->user)->toBe($user);
});
