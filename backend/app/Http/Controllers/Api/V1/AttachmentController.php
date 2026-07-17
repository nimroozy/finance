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

    public function forTicket(int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('view', $ticket);

        return ApiResponse::success($this->summarize($ticket->attachments()->orderByDesc('id')->get()));
    }

    public function forTask(int $id): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('view', $task);

        return ApiResponse::success($this->summarize($task->attachments()->orderByDesc('id')->get()));
    }

    public function download(Request $request, string $attachment): StreamedResponse|JsonResponse
    {
        $model = OperationalAttachment::query()->where('uuid', $attachment)->firstOrFail();

        if ($request->hasValidSignature()) {
            return Storage::disk($model->disk)->download($model->path, $model->original_name);
        }

        $user = $request->user('sanctum') ?? Auth::guard('sanctum')->user();
        if (! $user) {
            return ApiResponse::error('Invalid or expired download link.', [], 403);
        }

        Auth::setUser($user);
        $this->authorize('view', $model);

        return Storage::disk($model->disk)->download($model->path, $model->original_name);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OperationalAttachment>  $attachments
     * @return list<array<string, mixed>>
     */
    private function summarize($attachments): array
    {
        return $attachments->map(fn (OperationalAttachment $a) => [
            'id' => $a->id,
            'uuid' => $a->uuid,
            'original_name' => $a->original_name,
            'mime' => $a->mime,
            'size' => $a->size,
            'kind' => $a->kind,
            'virus_scan_status' => $a->virus_scan_status,
            'uploaded_by' => $a->uploaded_by,
            'created_at' => optional($a->created_at)?->toIso8601String(),
            'download_url' => $this->attachments->temporaryDownloadUrl($a),
        ])->values()->all();
    }
}
