<?php

use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Files\Domain\Repositories\FileStorageInterface;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

// Smallest content that `finfo` recognises as each type.
const FILES_TEST_PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
const FILES_TEST_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

beforeEach(function () {
    Storage::fake();

    $this->seed(RoleAndPermissionSeeder::class);

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();

    // Syncs module.json permissions onto the roles, enables mature modules
    // and entitles the schools created above.
    $this->seed(ModulePlatformSeeder::class);

    $this->staff = filesUser($this->school, 'staff/admin', 'Ana Directora');
    $this->teacher = filesUser($this->school, 'teacher', 'Luis Docente');
});

function filesUrl(School $school, string $path = ''): string
{
    return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/files'.$path;
}

function filesUser(School $school, string $role, ?string $name = null): User
{
    $user = User::factory()->create(array_filter(['school_id' => $school->id, 'name' => $name]));
    $user->assignRole($role);

    return $user;
}

/**
 * A real temporary upload whose content decides its detected type, unlike
 * UploadedFile::fake(), which reports whatever MIME type it is given.
 */
function realUpload(string $clientName, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'files-test-');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $clientName, null, null, true);
}

/**
 * Inserts a file row and its physical file directly, bypassing the use case.
 */
function storedFileRow(School $school, ?User $uploader, array $overrides = []): int
{
    $row = array_merge([
        'school_id' => $school->id,
        'original_name' => 'Acta de reunión.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen(FILES_TEST_PDF),
        'storage_path' => 'schools/'.$school->id.'/files/'.bin2hex(random_bytes(20)).'.pdf',
        'uploaded_by' => $uploader?->id,
        'uploader_name' => $uploader?->name ?? 'Usuario eliminado',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);

    Storage::put($row['storage_path'], FILES_TEST_PDF);

    return DB::table('school_files')->insertGetId($row);
}

function storedPathOf(int $id): string
{
    return DB::table('school_files')->where('id', $id)->value('storage_path');
}

test('staff/admin and teachers upload a file stored under their school with a generated name', function (string $who) {
    $user = $this->{$who};

    $response = $this->actingAs($user)->post(filesUrl($this->school), [
        'file' => realUpload('Informe final.pdf', FILES_TEST_PDF),
        // Ignored: the school always comes from the tenant.
        'school_id' => $this->otherSchool->id,
    ]);

    $response->assertRedirect(route('files.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Se subió «Informe final.pdf».');

    $row = DB::table('school_files')->sole();
    expect($row->school_id)->toBe($this->school->id)
        ->and($row->original_name)->toBe('Informe final.pdf')
        ->and($row->mime_type)->toBe('application/pdf')
        ->and((int) $row->size_bytes)->toBe(strlen(FILES_TEST_PDF))
        ->and($row->uploaded_by)->toBe($user->id)
        ->and($row->uploader_name)->toBe($user->name)
        ->and($row->storage_path)->toMatch('#^schools/'.$this->school->id.'/files/[0-9a-f]{40}\.pdf$#');

    Storage::assertExists($row->storage_path);
    expect(Storage::get($row->storage_path))->toBe(FILES_TEST_PDF)
        ->and(Storage::allFiles())->toBe([$row->storage_path]);
})->with(['staff', 'teacher']);

test('the client filename never decides the storage path', function () {
    $this->actingAs($this->staff)
        ->post(filesUrl($this->school), ['file' => realUpload('../../../public/evil.png', base64_decode(FILES_TEST_PNG_BASE64))])
        ->assertSessionHasNoErrors();

    $row = DB::table('school_files')->sole();
    expect($row->original_name)->toBe('evil.png')
        ->and($row->mime_type)->toBe('image/png')
        ->and($row->storage_path)->toMatch('#^schools/'.$this->school->id.'/files/[0-9a-f]{40}\.png$#');
});

test('files that are not an allowed type by content are rejected and nothing is stored', function (string $name, string $contents) {
    $this->actingAs($this->teacher)
        ->from(filesUrl($this->school))
        ->post(filesUrl($this->school), ['file' => realUpload($name, $contents)])
        ->assertRedirect(filesUrl($this->school))
        ->assertSessionHasErrors(['file' => 'El tipo de archivo no está permitido. Formatos permitidos: PDF, JPG, PNG, Word, Excel y PowerPoint.']);

    expect(DB::table('school_files')->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
})->with([
    'windows executable' => ['setup.exe', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00".str_repeat("\x00", 64)],
    'text disguised as a pdf' => ['notas.pdf', "Esto es texto plano, no un PDF.\n"],
    'html disguised as a png' => ['foto.png', '<html><body><script>alert(1)</script></body></html>'],
    'pdf content with a disallowed extension' => ['informe.html', FILES_TEST_PDF],
]);

test('a file is required', function () {
    $this->actingAs($this->staff)
        ->post(filesUrl($this->school), [])
        ->assertSessionHasErrors(['file' => 'Selecciona un archivo para subir.']);
});

test('a file of exactly 10 MB is accepted', function () {
    $this->actingAs($this->staff)
        ->post(filesUrl($this->school), ['file' => UploadedFile::fake()->create('grande.pdf', 10 * 1024, 'application/pdf')])
        ->assertSessionHasNoErrors();

    expect((int) DB::table('school_files')->value('size_bytes'))->toBe(10 * 1024 * 1024);
});

test('a file over 10 MB is rejected', function () {
    $this->actingAs($this->staff)
        ->post(filesUrl($this->school), ['file' => UploadedFile::fake()->create('grande.pdf', 10 * 1024 + 1, 'application/pdf')])
        ->assertSessionHasErrors(['file' => 'El archivo no puede superar los 10 MB.']);

    expect(DB::table('school_files')->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
});

test('download streams the file as an attachment with its original name', function () {
    $id = storedFileRow($this->school, $this->staff, ['original_name' => 'Calendario escolar 2026.pdf']);

    $response = $this->actingAs($this->teacher)->get(filesUrl($this->school, "/{$id}/download"));

    $response->assertOk()
        ->assertDownload('Calendario escolar 2026.pdf')
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->streamedContent())->toBe(FILES_TEST_PDF);
});

test('a name with quotes and non-ASCII characters is encoded safely in the header', function () {
    $this->actingAs($this->staff)
        ->post(filesUrl($this->school), ['file' => realUpload("Acta \"final\"\r\nSet-Cookie: x=1 ñandú %.pdf", FILES_TEST_PDF)])
        ->assertSessionHasNoErrors();

    $id = DB::table('school_files')->value('id');
    $disposition = $this->actingAs($this->staff)
        ->get(filesUrl($this->school, "/{$id}/download"))
        ->assertOk()
        ->headers->get('Content-Disposition');

    expect(DB::table('school_files')->value('original_name'))->toBe('Acta "final"Set-Cookie: x=1 ñandú %.pdf')
        ->and($disposition)->not->toContain("\r")
        ->and($disposition)->not->toContain("\n")
        ->and($disposition)->toContain("filename*=utf-8''".rawurlencode('Acta "final"Set-Cookie: x=1 ñandú %.pdf'));
});

test('a file whose physical copy is missing is not found', function () {
    $id = storedFileRow($this->school, $this->staff);
    Storage::delete(storedPathOf($id));

    $this->actingAs($this->staff)->get(filesUrl($this->school, "/{$id}/download"))->assertNotFound();
});

test('the list shows only this school\'s files, newest first, with who may delete each one', function () {
    $older = storedFileRow($this->school, $this->staff, ['original_name' => 'Antiguo.pdf', 'created_at' => now()->subDays(2)]);
    $newer = storedFileRow($this->school, $this->teacher, ['original_name' => 'Reciente.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'storage_path' => 'schools/'.$this->school->id.'/files/'.str_repeat('a', 40).'.docx', 'size_bytes' => 5 * 1024 * 1024]);
    storedFileRow($this->otherSchool, filesUser($this->otherSchool, 'staff/admin'), ['original_name' => 'Ajeno.pdf']);

    $this->actingAs($this->teacher)
        ->get(filesUrl($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Files::Index', false)
            ->has('files.data', 2)
            ->where('files.data.0.id', $newer)
            ->where('files.data.0.name', 'Reciente.docx')
            ->where('files.data.0.extension', 'docx')
            ->where('files.data.0.sizeBytes', 5 * 1024 * 1024)
            ->where('files.data.0.uploaderName', 'Luis Docente')
            ->where('files.data.0.canDelete', true)
            ->where('files.data.1.id', $older)
            ->where('files.data.1.canDelete', false)
            ->missing('files.data.0.storagePath')
            ->where('canUpload', true)
            ->where('maxSizeBytes', 10 * 1024 * 1024)
            ->where('allowedExtensions', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']));

    $this->actingAs($this->staff)
        ->get(filesUrl($this->school))
        ->assertInertia(fn (Assert $page) => $page
            ->where('files.data.0.canDelete', true)
            ->where('files.data.1.canDelete', true));
});

test('the list is paginated', function () {
    foreach (range(1, 17) as $i) {
        storedFileRow($this->school, $this->staff, ['original_name' => "Archivo {$i}.pdf"]);
    }

    $this->actingAs($this->staff)
        ->get(filesUrl($this->school, '?page=2'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('files.data', 2)
            ->where('files.current_page', 2)
            ->where('files.total', 17));
});

test('another school\'s file is not found for download or delete', function () {
    $foreignStaff = filesUser($this->otherSchool, 'staff/admin');
    $id = storedFileRow($this->otherSchool, $foreignStaff);
    // Even a row claiming this school's user but owned by the other school stays hidden.
    $mislabeled = storedFileRow($this->otherSchool, $this->staff);

    foreach ([$id, $mislabeled] as $foreignId) {
        $this->actingAs($this->staff)->get(filesUrl($this->school, "/{$foreignId}/download"))->assertNotFound();
        $this->actingAs($this->staff)->delete(filesUrl($this->school, "/{$foreignId}"))->assertNotFound();
        Storage::assertExists(storedPathOf($foreignId));
    }

    expect(DB::table('school_files')->count())->toBe(2);
});

test('students have no access and no navigation entry', function () {
    $student = filesUser($this->school, 'student');
    $id = storedFileRow($this->school, $this->staff);

    $this->actingAs($student)->get(filesUrl($this->school))->assertForbidden();
    $this->actingAs($student)->post(filesUrl($this->school), ['file' => realUpload('a.pdf', FILES_TEST_PDF)])->assertForbidden();
    $this->actingAs($student)->get(filesUrl($this->school, "/{$id}/download"))->assertForbidden();
    $this->actingAs($student)->delete(filesUrl($this->school, "/{$id}"))->assertForbidden();

    expect(DB::table('school_files')->count())->toBe(1);
    Storage::assertExists(storedPathOf($id));

    $this->actingAs($student)
        ->get('http://'.$this->school->subdomain.'.'.config('tenancy.base_domain').'/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('moduleNav', fn ($items) => collect($items)->doesntContain('label', 'Archivos')));
});

test('staff/admin and teachers see the navigation entry', function (string $who) {
    $this->actingAs($this->{$who})
        ->get(filesUrl($this->school))
        ->assertInertia(fn (Assert $page) => $page->where(
            'moduleNav',
            fn ($items) => collect($items)->contains(fn ($item) => $item['label'] === 'Archivos'
                && $item['icon'] === 'FolderOpen'
                && $item['href'] === route('files.index')),
        ));
})->with(['staff', 'teacher']);

test('a teacher deletes their own file, row and physical file', function () {
    $id = storedFileRow($this->school, $this->teacher);
    $path = storedPathOf($id);

    $this->actingAs($this->teacher)
        ->from(filesUrl($this->school))
        ->delete(filesUrl($this->school, "/{$id}"))
        ->assertRedirect(filesUrl($this->school))
        ->assertSessionHas('success', 'Archivo eliminado.');

    expect(DB::table('school_files')->count())->toBe(0);
    Storage::assertMissing($path);
});

test('a teacher cannot delete a file someone else uploaded', function (string $uploader) {
    $owner = $uploader === 'staff' ? $this->staff : filesUser($this->school, 'teacher');
    $id = storedFileRow($this->school, $owner);

    $this->actingAs($this->teacher)->delete(filesUrl($this->school, "/{$id}"))->assertForbidden();

    expect(DB::table('school_files')->count())->toBe(1);
    Storage::assertExists(storedPathOf($id));
})->with(['staff', 'another teacher']);

test('staff/admin deletes any file of their school, row and physical file', function () {
    $id = storedFileRow($this->school, $this->teacher);
    $orphan = storedFileRow($this->school, null);
    $paths = [storedPathOf($id), storedPathOf($orphan)];

    $this->actingAs($this->staff)->delete(filesUrl($this->school, "/{$id}"))->assertRedirect();
    $this->actingAs($this->staff)->delete(filesUrl($this->school, "/{$orphan}"))->assertRedirect();

    expect(DB::table('school_files')->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
    Storage::assertMissing($paths);
});

test('the row is kept when the physical file cannot be deleted', function () {
    $id = storedFileRow($this->school, $this->staff);

    $this->app->bind(FileStorageInterface::class, fn () => new class implements FileStorageInterface
    {
        public function put(string $sourcePath, string $targetPath): void {}

        public function delete(string $path): bool
        {
            return false;
        }
    });

    $this->actingAs($this->staff)
        ->from(filesUrl($this->school))
        ->delete(filesUrl($this->school, "/{$id}"))
        ->assertRedirect(filesUrl($this->school))
        ->assertSessionHas('error', 'No se pudo eliminar el archivo. Inténtalo de nuevo.');

    expect(DB::table('school_files')->where('id', $id)->exists())->toBeTrue();
    Storage::assertExists(storedPathOf($id));
});

test('the module returns 404 and hides its navigation when the school is not entitled', function () {
    $id = storedFileRow($this->school, $this->teacher);
    app(ModuleRegistry::class)->revoke('files', $this->school);

    $this->actingAs($this->staff)->get(filesUrl($this->school))->assertNotFound();
    $this->actingAs($this->staff)->post(filesUrl($this->school), ['file' => realUpload('a.pdf', FILES_TEST_PDF)])->assertNotFound();
    $this->actingAs($this->teacher)->get(filesUrl($this->school, "/{$id}/download"))->assertNotFound();
    $this->actingAs($this->teacher)->delete(filesUrl($this->school, "/{$id}"))->assertNotFound();
    // 404, not 403, even for a role without access.
    $this->actingAs(filesUser($this->school, 'student'))->get(filesUrl($this->school))->assertNotFound();

    expect(DB::table('school_files')->count())->toBe(1);

    $this->actingAs($this->staff)
        ->get('http://'.$this->school->subdomain.'.'.config('tenancy.base_domain').'/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('moduleNav', fn ($items) => collect($items)->doesntContain('label', 'Archivos')));
});

test('the module returns 404 when it is disabled', function () {
    app(ModuleRegistry::class)->disable('files');

    $this->actingAs($this->staff)->get(filesUrl($this->school))->assertNotFound();
});

test('guests are redirected to the login page', function () {
    $this->get(filesUrl($this->school))->assertRedirect();
});
