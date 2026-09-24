<?php

use Modules\Files\Domain\Exceptions\InvalidUpload;
use Modules\Files\Domain\ValueObjects\OriginalFileName;

test('a normal name is kept as is, including accents and spaces', function () {
    expect((new OriginalFileName('Informe de gestión 2026.pdf'))->value())->toBe('Informe de gestión 2026.pdf');
});

test('directory parts are dropped', function (string $name) {
    expect((new OriginalFileName($name))->value())->toBe('passwd.pdf');
})->with([
    'unix traversal' => '../../etc/passwd.pdf',
    'windows path' => 'C:\\Users\\ana\\passwd.pdf',
    'mixed' => '..\\../passwd.pdf',
]);

test('control and invisible formatting characters are removed so the name is header-safe', function () {
    $name = new OriginalFileName("evil\r\nSet-Cookie: a=b\x00\u{202E}fdp.exe.pdf");

    expect($name->value())->toBe('evilSet-Cookie: a=bfdp.exe.pdf')
        ->and($name->value())->not->toContain("\r")
        ->and($name->value())->not->toContain("\n");
});

test('invalid UTF-8 is scrubbed instead of breaking the name', function () {
    expect((new OriginalFileName("acta\xB1.pdf"))->value())->toBe('acta?.pdf');
});

test('a name that is empty after cleaning is rejected', function (string $name) {
    new OriginalFileName($name);
})->with(['empty' => '', 'spaces' => '   ', 'only a directory' => 'folder/', 'only control characters' => "\r\n\t"])
    ->throws(InvalidUpload::class, 'The file name is required.');

test('a long name is shortened to 255 characters keeping its extension', function () {
    $name = (new OriginalFileName(str_repeat('ñ', 300).'.docx'))->value();

    expect(mb_strlen($name))->toBe(255)
        ->and($name)->toEndWith('.docx')
        ->and($name)->toStartWith('ñññ');
});
