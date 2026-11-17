<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FileAsset;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploaded and generated documents. Files go to the default filesystem disk: storage/app/private
 * locally, or S3 when FILESYSTEM_DISK=s3. Ported from server/src/lib/storage.ts and the /files routes.
 */
class Files
{
    public const ALLOWED = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    public const IMAGES = ['image/jpeg', 'image/png', 'image/webp'];

    public function storeContents(string $contents, string $storageKey, string $fileName, string $mimeType, string $ownerType, string $ownerId, bool $confidential = false, ?string $categoryId = null): FileAsset
    {
        Storage::put($storageKey, $contents);

        return FileAsset::create([
            'storage_key' => $storageKey,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'category_id' => $categoryId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'confidential' => $confidential,
            'uploaded_by_id' => auth()->id(),
        ]);
    }

    public function storeUpload(UploadedFile $upload, string $ownerType, string $ownerId, bool $confidential = false, ?string $categoryId = null): FileAsset
    {
        $key = strtolower($ownerType).'/'.$ownerId.'/'.Str::ulid();
        Storage::putFileAs(dirname($key), $upload, basename($key));

        return FileAsset::create([
            'storage_key' => $key,
            'file_name' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
            'category_id' => $categoryId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'confidential' => $confidential,
            'uploaded_by_id' => auth()->id(),
        ]);
    }

    public function canView(User $user, FileAsset $file): bool
    {
        if ($file->confidential && ! Access::canSeeDiscipline($user) && $user->employee_id !== $file->owner_id) {
            return false;
        }
        if ($file->owner_type === 'EMPLOYEE') {
            $employee = Employee::find($file->owner_id);

            return Access::isHr($user) || ($employee && Access::canSeeEmployee($user, $employee));
        }

        return Access::isHr($user) || $user->role->value === 'RECRUITMENT_OFFICER';
    }

    public function delete(FileAsset $file): void
    {
        Storage::delete($file->storage_key);
        $file->delete();
    }
}
