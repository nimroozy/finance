<?php

namespace App\Services\Evidence;

use App\Models\CollectionVisit;
use App\Models\UploadedVisitFile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitFileService
{
    public function __construct(protected AuditLogger $audit) {}

    public function store(CollectionVisit $visit, UploadedFile $file, User $actor): UploadedVisitFile
    {
        $dir = 'visit-evidence/'.$visit->id;
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $filename, 'local');

        $record = UploadedVisitFile::create([
            'visit_id' => $visit->id,
            'uploaded_by' => $actor->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        $this->audit->log('evidence.uploaded', $record, null, [
            'visit_id' => $visit->id,
            'path' => $path,
        ], $visit->branch_id);

        return $record;
    }

    public function download(UploadedVisitFile $file): StreamedResponse
    {
        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
