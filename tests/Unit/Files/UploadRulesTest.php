<?php

use Modules\Files\Domain\Exceptions\InvalidUpload;
use Modules\Files\Domain\ValueObjects\UploadRules;

test('every allowed type is accepted, case-insensitively', function (string $extension) {
    UploadRules::assertAcceptable($extension, 1024);
    UploadRules::assertAcceptable(strtoupper($extension), 1024);

    expect(UploadRules::allowsExtension($extension))->toBeTrue();
})->with(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);

test('any other type is rejected', function (string $extension) {
    UploadRules::assertAcceptable($extension, 1024);
})->with(['exe', 'txt', 'html', 'svg', 'php', 'zip', 'gif', ''])
    ->throws(InvalidUpload::class);

test('a file of exactly 10 MB is accepted', function () {
    UploadRules::assertAcceptable('pdf', 10 * 1024 * 1024);

    expect(UploadRules::MAX_SIZE_BYTES)->toBe(10_485_760)
        ->and(UploadRules::MAX_SIZE_KILOBYTES)->toBe(10_240);
});

test('a file one byte over 10 MB is rejected', function () {
    UploadRules::assertAcceptable('pdf', 10 * 1024 * 1024 + 1);
})->throws(InvalidUpload::class, 'The file may not be larger than 10485760 bytes.');

test('an empty file is rejected', function () {
    UploadRules::assertAcceptable('pdf', 0);
})->throws(InvalidUpload::class, 'The file is empty.');
