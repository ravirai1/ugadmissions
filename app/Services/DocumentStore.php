<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\SecurityEvent;
use App\Support\DocumentTypes;
use App\Support\PhotoRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        if (self::hidesCode($file)) {
            SecurityEvent::create([
                'type' => 'traversal', 'ip' => (string) request()?->ip(), 'method' => (string) request()?->method(),
                'path' => '/'.ltrim((string) request()?->path(), '/'), 'field' => $field,
                'sample' => 'Upload with hidden code: '.Str::limit($file->getClientOriginalName(), 120),
                'user_agent' => Str::limit((string) request()?->userAgent(), 255, ''),
            ]);
            app(SecurityAlerts::class)->send('upload-code:'.$app->id, 'Upload with hidden PHP/script code refused', [
                'Application' => $app->application_no ?: 'ID '.$app->id,
                'Applicant' => (string) $app->user?->email,
                'Document' => DocumentTypes::label($type),
                'File name' => $file->getClientOriginalName(),
                'IP' => (string) request()?->ip(),
            ], route('admin.security.threats', ['q' => request()?->ip()]));
            throw ValidationException::withMessages([$field => 'This file cannot be accepted. Upload a plain scan or photo (JPG, PNG or PDF).']);
        }
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
        $name = self::uniqueName($app, $type, $ext);
        $path = $file->storeAs('applications/'.$app->id, $name, 'documents');

        return DB::transaction(function () use ($app, $type, $file, $path, $name, $mime, $replace, $queryId) {
            if ($replace) {
                $app->allDocuments()->where('type', $type)->update(['is_current' => false]);
            }

            return $app->allDocuments()->create([
                'type' => $type,
                'path' => $path,
                'stored_name' => $name,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
                'mime' => $mime,
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'query_id' => $queryId,
            ]);
        });
    }

    /** True when a file carries PHP or script code (e.g. a JPG with PHP appended), whatever its type looks like. */
    public static function hidesCode(UploadedFile $file): bool
    {
        $data = (string) file_get_contents($file->getRealPath());

        return (bool) preg_match('/<\?php|<script\b|\beval\s*\(\s*(base64_decode|gzinflate|str_rot13)|\b(passthru|shell_exec|system|proc_open|popen)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', $data);
    }

    /**
     * The name the file gets the moment it is uploaded: CUET no., document type, upload time (IST) and a random code,
     * e.g. CU27001_photo_20261114-213105_k3x9q2.jpg. The random code keeps two uploads in the same second apart;
     * the database also has a unique index on the name.
     */
    public static function uniqueName(Application $app, string $type, string $ext): string
    {
        $who = preg_replace('/[^A-Za-z0-9]/', '', (string) ($app->user?->cuet_no ?: 'APP'.$app->id)) ?: 'APP'.$app->id;
        $base = $who.'_'.preg_replace('/[^a-z0-9]/', '', strtolower($type)).'_'.now('Asia/Kolkata')->format('Ymd-His').'_';

        do {
            $name = $base.strtolower(Str::random(6)).'.'.strtolower($ext);
        } while (ApplicationDocument::where('stored_name', $name)->exists());

        return $name;
    }
}
