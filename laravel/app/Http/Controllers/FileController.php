<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FileAsset;
use App\Services\Files;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Upload, open and remove documents. Ported from the /files routes in server/src/routes/insights.ts. */
class FileController extends Controller
{
    public function __construct(private Files $files) {}

    public function store(Request $request)
    {
        $user = $request->user();
        $photo = $request->boolean('photo');
        $data = $request->validate([
            'owner_type' => ['required', 'string', 'min:2', 'max:40'],
            'owner_id' => ['required', 'string', 'min:2', 'max:40'],
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'confidential' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'max:'.($photo ? 5120 : 20480), 'mimetypes:'.implode(',', $photo ? Files::IMAGES : Files::ALLOWED)],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.mimetypes' => $photo ? 'Use a JPEG, PNG, or WebP image.' : 'Upload a PDF, image, or Word document.',
            'file.max' => $photo ? 'The picture must be 5 MB or smaller.' : 'The file must be 20 MB or smaller.',
        ]);

        $hr = Access::isHr($user);
        abort_unless($hr || in_array($user->role->value, ['RECRUITMENT_OFFICER', 'EMPLOYEE'], true), 403, 'You cannot upload documents.');
        if ($data['owner_type'] === 'EMPLOYEE') {
            $employee = Employee::findOrFail($data['owner_id']);
            abort_unless($hr || $user->employee_id === $employee->id, 403, 'You can only upload to your own record.');
        }

        $file = $this->files->storeUpload($request->file('file'), $data['owner_type'], $data['owner_id'], (bool) ($data['confidential'] ?? false), $data['category_id'] ?? null);
        if ($photo && $data['owner_type'] === 'EMPLOYEE') {
            $employee->update(['photo_file_id' => $file->id]);
        }

        return back()->with('status', $photo ? 'Photo updated.' : 'Document uploaded.');
    }

    public function show(Request $request, FileAsset $file)
    {
        abort_unless($this->files->canView($request->user(), $file), 403, 'You cannot open this document.');
        abort_unless(Storage::exists($file->storage_key), 404, 'The file is missing from storage.');

        return Storage::response($file->storage_key, str_replace('"', '', $file->file_name), [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'no-store',
        ], 'inline');
    }

    public function destroy(FileAsset $file)
    {
        $this->files->delete($file);

        return back()->with('status', 'Document removed.');
    }
}
