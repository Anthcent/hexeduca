<?php

use Modules\Files\Domain\Exceptions\InvalidUpload;
use Modules\Files\Domain\ValueObjects\StoragePath;

test('the path lives under the school folder with a generated name and the detected extension', function () {
    expect(StoragePath::generate(42, 'PDF'))->toMatch('#^schools/42/files/[0-9a-f]{40}\.pdf$#');
});

test('every path is unique', function () {
    $paths = array_map(fn () => StoragePath::generate(1, 'png'), range(1, 50));

    expect(array_unique($paths))->toHaveCount(50);
});

test('an extension that is not allowed never reaches a path', function (string $extension) {
    StoragePath::generate(1, $extension);
})->with(['exe', '../x', 'pdf/../../x', ''])
    ->throws(InvalidUpload::class);
