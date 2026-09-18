<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is this
| class, unless you specify a different one for your test suites.
|
*/

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet
| certain conditions. The "expect()" function gives you access to a
| set of "expectations" methods that you can use to assert things.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is compatible with PHPUnit, it also provides a few extra
| conveniences that let you write tests faster and more expressively.
| Feel free to add your own custom helper functions here.
|
*/

function something()
{
    // ..
}
