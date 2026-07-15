<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectionVisit;
use App\Models\UploadedVisitFile;
use App\Services\Evidence\VisitFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function __construct(protected VisitFileService $files) {}

    public function store(Request $request, int $visitId): JsonResponse
    {
        $this->authorize('upload', UploadedVisitFile::class);

        $visit = CollectionVisit::findOrFail($visitId);
        $this->authorize('view', $visit);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $record = $this->files->store($visit, $data['file'], Auth::user());

        return ApiResponse::success($record, null, 201);
    }

    public function download(int $id): StreamedResponse
    {
        $file = UploadedVisitFile::with('visit')->findOrFail($id);
        $this->authorize('view', $file);

        return $this->files->download($file);
    }
}
