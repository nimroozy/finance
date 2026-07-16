<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\Notifications\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $page = AppNotification::query()
            ->where('user_id', $user->id)
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function read(int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', Auth::id())->findOrFail($id);

        return ApiResponse::success($this->notifications->markRead($notification));
    }

    public function readAll(): JsonResponse
    {
        $count = $this->notifications->markAllRead(Auth::user());

        return ApiResponse::success(['marked' => $count]);
    }
}
