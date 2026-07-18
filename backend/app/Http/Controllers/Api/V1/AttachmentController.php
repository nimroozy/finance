<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\Installation;
use App\Models\Tickets\OperationalAttachment;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Services\Attachments\OperationalAttachmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private OperationalAttachmentService $attachments) {}

    public function upload(Request $request): JsonResponse
    {
        $this->authorize('upload', OperationalAttachment::class);

        $data = $request->validate([
            'file' => ['required', 'file'],
            'attachable_type' => ['required', 'string', Rule::in(['ticket', 'task', 'installation'])],
            'attachable_id' => ['required', 'integer'],
            'kind' => ['nullable', 'string', 'max:64'],
        ]);

        $attachable = match ($data['attachable_type']) {
            'ticket' => Ticket::query()->findOrFail($data['attachable_id']),
            'task' => Task::query()->findOrFail($data['attachable_id']),
            'installation' => Installation::query()->findOrFail($data['attachable_id']),
        };

        $this->authorize('view', $attachable);

        try {
            $attachment = $this->attachments->store(
                $attachable,
                $request->file('file'),
                Auth::user(),
                (int) $attachable->branch_id,
                $data['kind'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            'attachment' => $attachment,
            'download_url' => $this->attachments->temporaryDownloadUrl($attachment),
        ], null, 201);
    }

    public function download(Request $request, string $attachment): StreamedResponse|JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error('Invalid or expired download link.', [], 403);
        }

        $model = OperationalAttachment::query()->where('uuid', $attachment)->firstOrFail();

        return Storage::disk($model->disk)->download($model->path, $model->original_name);
    }
}
