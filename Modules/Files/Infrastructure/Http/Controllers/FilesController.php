<?php

namespace Modules\Files\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Files\Application\DTOs\UploadFileData;
use Modules\Files\Application\UseCases\DeleteFile;
use Modules\Files\Application\UseCases\UploadFile;
use Modules\Files\Domain\Exceptions\FileDeletionDenied;
use Modules\Files\Domain\Exceptions\FileNotFound;
use Modules\Files\Domain\Exceptions\InvalidUpload;
use Modules\Files\Domain\Repositories\StoredFileRepositoryInterface;
use Modules\Files\Domain\ValueObjects\UploadRules;
use Modules\Files\Infrastructure\Http\Requests\StoreFileRequest;
use Modules\Files\Infrastructure\Models\SchoolFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $user = $request->user();
        $mayDeleteAny = $user->can('files.delete-any');

        $files = SchoolFile::query()
            ->where('school_id', $tenantContext->current()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (SchoolFile $file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'extension' => pathinfo($file->storage_path, PATHINFO_EXTENSION),
                'sizeBytes' => $file->size_bytes,
                'uploaderName' => $file->uploader_name,
                'createdAt' => $file->created_at?->toIso8601String(),
                'canDelete' => $file->toEntity()->canBeDeletedBy($user->id, $mayDeleteAny),
            ]);

        return Inertia::render('Files::Index', [
            'files' => $files,
            'canUpload' => $user->can('files.upload'),
            'maxSizeBytes' => UploadRules::MAX_SIZE_BYTES,
            'allowedExtensions' => UploadRules::ALLOWED_EXTENSIONS,
        ]);
    }

    public function store(StoreFileRequest $request, UploadFile $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $upload = $request->file('file');

        try {
            $stored = $useCase->handle(new UploadFileData(
                schoolId: $tenantContext->current()->id,
                uploaderId: $request->user()->id,
                uploaderName: (string) $request->user()->name,
                originalName: $upload->getClientOriginalName(),
                sourcePath: (string) $upload->getRealPath(),
                detectedExtension: (string) $upload->guessExtension(),
                mimeType: (string) $upload->getMimeType(),
                sizeBytes: (int) $upload->getSize(),
            ));
        } catch (InvalidUpload) {
            throw ValidationException::withMessages(['file' => 'El archivo no es válido.']);
        }

        return redirect()->route('files.index')->with('success', "Se subió «{$stored->originalName()}».");
    }

    public function download(int $file, StoredFileRepositoryInterface $files, TenantContext $tenantContext): StreamedResponse
    {
        $stored = $files->findInSchool($file, $tenantContext->current()->id);
        $disk = Storage::disk();

        abort_if($stored === null || ! $disk->exists($stored->storagePath()), 404);

        // Always an attachment: the browser never renders an uploaded file
        // inline, and the name is encoded by Symfony's makeDisposition.
        return $disk->download($stored->storagePath(), $stored->originalName(), [
            'Content-Type' => $stored->mimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, int $file, DeleteFile $useCase, TenantContext $tenantContext): RedirectResponse
    {
        try {
            $useCase->handle($file, $tenantContext->current()->id, $request->user()->id, $request->user()->can('files.delete-any'));
        } catch (FileNotFound) {
            abort(404);
        } catch (FileDeletionDenied) {
            abort(403);
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'No se pudo eliminar el archivo. Inténtalo de nuevo.');
        }

        return back()->with('success', 'Archivo eliminado.');
    }
}
