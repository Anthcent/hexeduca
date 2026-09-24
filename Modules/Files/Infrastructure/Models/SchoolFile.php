<?php

namespace Modules\Files\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Files\Domain\Entities\StoredFile;

class SchoolFile extends Model
{
    use BelongsToTenant;

    protected $table = 'school_files';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_path',
        'uploaded_by',
        'uploader_name',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    public function toEntity(): StoredFile
    {
        return new StoredFile(
            id: $this->id,
            schoolId: $this->school_id,
            originalName: $this->original_name,
            mimeType: $this->mime_type,
            sizeBytes: $this->size_bytes,
            storagePath: $this->storage_path,
            uploadedBy: $this->uploaded_by,
            uploaderName: $this->uploader_name,
        );
    }
}
