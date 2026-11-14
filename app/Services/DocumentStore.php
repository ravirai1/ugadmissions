<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Support\DocumentTypes;
use App\Support\PhotoRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validates and stores applicant documents on the private disk. The file type is taken from the
 * file's contents, never from its name, and images/PDFs must actually open.
 */
class DocumentStore
{
    public static function rules(string $type, bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable', 'file',
            'mimes:'.implode(',', DocumentTypes::exts($type)),
            'mimetypes:'.implode(',', DocumentTypes::mimes($type)),
            'max:'.DocumentTypes::maxKb($type),
        ];
    }

    public static function messages(string $field, string $type): array
    {
        return [
            "$field.mimes" => DocumentTypes::label($type).': allowed formats are '.strtoupper(implode(', ', DocumentTypes::exts($type))).'.',
            "$field.mimetypes" => DocumentTypes::label($type).': allowed formats are '.strtoupper(implode(', ', DocumentTypes::exts($type))).'.',
            "$field.max" => DocumentTypes::label($type).': file is too large (maximum '.DocumentTypes::maxKb($type).' KB).',
        ];
    }

    /**
     * @param  bool  $replace  true: becomes the current document of this type (older versions are kept but not current)
     */
    public function store(Application $app, UploadedFile $file, string $type, string $field = 'file', bool $replace = true, ?int $queryId = null): ApplicationDocument
    {
        $mime = $file->getMimeType(); // detected from file contents
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([$field => 'The image file appears to be damaged.']);
        }
        if ($error = PhotoRules::check($type, $file)) {
            throw ValidationException::withMessages([$field => $error]);
        }
        if ($mime === 'application/pdf' && ! str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-')) {
            throw ValidationException::withMessages([$field => 'The PDF file appears to be damaged.']);
        }

        $ext = array_search($mime, DocumentTypes::MIMES, true) ?: $file->extension();
        $path = $file->storeAs('applications/'.$app->id, $type.'-'.bin2hex(random_bytes(8)).'.'.$ext, 'documents');

        return DB::transaction(function () use ($app, $type, $file, $path, $mime, $replace, $queryId) {
            if ($replace) {
                $app->allDocuments()->where('type', $type)->update(['is_current' => false]);
            }

            return $app->allDocuments()->create([
                'type' => $type,
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
                'mime' => $mime,
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'query_id' => $queryId,
            ]);
        });
    }
}
