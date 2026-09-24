<?php

use Modules\Files\Application\DTOs\UploadFileData;
use Modules\Files\Application\UseCases\UploadFile;
use Modules\Files\Domain\Entities\StoredFile;
use Modules\Files\Domain\Exceptions\InvalidUpload;
use Modules\Files\Domain\Repositories\FileStorageInterface;
use Modules\Files\Domain\Repositories\StoredFileRepositoryInterface;

/**
 * In-memory storage that records what was written and deleted.
 */
function fakeFileStorage(): FileStorageInterface
{
    return new class implements FileStorageInterface
    {
        /** @var array<string, string> */
        public array $stored = [];

        /** @var list<string> */
        public array $deleted = [];

        public function put(string $sourcePath, string $targetPath): void
        {
            $this->stored[$targetPath] = $sourcePath;
        }

        public function delete(string $path): bool
        {
            unset($this->stored[$path]);
            $this->deleted[] = $path;

            return true;
        }
    };
}

function fakeStoredFileRepository(bool $failOnSave = false): StoredFileRepositoryInterface
{
    return new class($failOnSave) implements StoredFileRepositoryInterface
    {
        /** @var list<StoredFile> */
        public array $saved = [];

        public function __construct(private readonly bool $failOnSave) {}

        public function save(StoredFile $file): StoredFile
        {
            if ($this->failOnSave) {
                throw new RuntimeException('Database is down.');
            }

            return $this->saved[] = $file;
        }

        public function findInSchool(int $id, int $schoolId): ?StoredFile
        {
            return null;
        }

        public function delete(int $id, int $schoolId): void {}
    };
}

function uploadFileData(array $overrides = []): UploadFileData
{
    return new UploadFileData(...array_merge([
        'schoolId' => 3,
        'uploaderId' => 9,
        'uploaderName' => 'Luis Docente',
        'originalName' => '../Planificación anual.pdf',
        'sourcePath' => '/tmp/php1234',
        'detectedExtension' => 'pdf',
        'mimeType' => 'application/pdf',
        'sizeBytes' => 2048,
    ], $overrides));
}

test('it stores the file under the school path and saves the row', function () {
    $storage = fakeFileStorage();
    $repository = fakeStoredFileRepository();

    $file = (new UploadFile($repository, $storage))->handle(uploadFileData());

    expect($file->storagePath())->toMatch('#^schools/3/files/[0-9a-f]{40}\.pdf$#')
        ->and($file->originalName())->toBe('Planificación anual.pdf')
        ->and($file->schoolId())->toBe(3)
        ->and($file->uploadedBy())->toBe(9)
        ->and($file->uploaderName())->toBe('Luis Docente')
        ->and($file->sizeBytes())->toBe(2048)
        ->and($storage->stored)->toBe([$file->storagePath() => '/tmp/php1234'])
        ->and($repository->saved)->toHaveCount(1);
});

test('the physical file is removed when the row cannot be saved', function () {
    $storage = fakeFileStorage();

    expect(fn () => (new UploadFile(fakeStoredFileRepository(failOnSave: true), $storage))->handle(uploadFileData()))
        ->toThrow(RuntimeException::class, 'Database is down.');

    expect($storage->stored)->toBe([])
        ->and($storage->deleted)->toHaveCount(1);
});

test('nothing is stored when the upload breaks a rule', function (array $overrides) {
    $storage = fakeFileStorage();
    $repository = fakeStoredFileRepository();

    expect(fn () => (new UploadFile($repository, $storage))->handle(uploadFileData($overrides)))
        ->toThrow(InvalidUpload::class);

    expect($storage->stored)->toBe([])
        ->and($repository->saved)->toBe([]);
})->with([
    'disallowed type' => [['detectedExtension' => 'exe']],
    'too large' => [['sizeBytes' => 10 * 1024 * 1024 + 1]],
    'empty name' => [['originalName' => '  ']],
]);
