<?php

use Modules\Files\Domain\Entities\StoredFile;

function storedFileUploadedBy(?int $uploaderId): StoredFile
{
    return new StoredFile(
        id: 1,
        schoolId: 1,
        originalName: 'Acta.pdf',
        mimeType: 'application/pdf',
        sizeBytes: 100,
        storagePath: 'schools/1/files/abc.pdf',
        uploadedBy: $uploaderId,
        uploaderName: 'Ana',
    );
}

test('the uploader may delete their own file', function () {
    expect(storedFileUploadedBy(7)->canBeDeletedBy(7, mayDeleteAny: false))->toBeTrue();
});

test('someone else may not delete it without delete-any', function () {
    expect(storedFileUploadedBy(7)->canBeDeletedBy(8, mayDeleteAny: false))->toBeFalse();
});

test('a user with delete-any may delete any file', function () {
    expect(storedFileUploadedBy(7)->canBeDeletedBy(8, mayDeleteAny: true))->toBeTrue()
        ->and(storedFileUploadedBy(null)->canBeDeletedBy(8, mayDeleteAny: true))->toBeTrue();
});

test('a file whose uploader was removed can only be deleted with delete-any', function () {
    expect(storedFileUploadedBy(null)->canBeDeletedBy(7, mayDeleteAny: false))->toBeFalse();
});
