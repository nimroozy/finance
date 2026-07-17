<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\UserUiPreferenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UserUiPreferenceController extends Controller
{
    public function __construct(protected UserUiPreferenceService $preferences) {}

    public function show(Request $request): JsonResponse
    {
        $prefs = $this->preferences->getOrCreate($request->user());

        return ApiResponse::success($prefs->toApiArray());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'nullable', Rule::in(['en', 'fa'])],
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark', 'system'])],
            'default_app_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'favorite_app_ids' => ['sometimes', 'array'],
            'favorite_app_ids.*' => ['string', 'max:64'],
            'recent_app_ids' => ['sometimes', 'array'],
            'recent_app_ids.*' => ['string', 'max:64'],
            'date_format' => ['sometimes', 'nullable', 'string', 'max:64'],
            'calendar_system' => ['sometimes', 'nullable', Rule::in(['gregorian', 'solar_hijri', 'both'])],
            'collapsed_nav_groups' => ['sometimes', 'nullable', 'array'],
            'bottom_nav_overrides' => ['sometimes', 'nullable', 'array'],
            'notification_prefs' => ['sometimes', 'nullable', 'array'],
        ]);

        try {
            $prefs = $this->preferences->update($request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($prefs->toApiArray());
    }

    public function addFavorite(Request $request, string $appId): JsonResponse
    {
        try {
            $prefs = $this->preferences->toggleFavorite($request->user(), $appId, true);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($prefs->toApiArray());
    }

    public function removeFavorite(Request $request, string $appId): JsonResponse
    {
        try {
            $prefs = $this->preferences->toggleFavorite($request->user(), $appId, false);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($prefs->toApiArray());
    }

    public function recordRecent(Request $request, string $appId): JsonResponse
    {
        try {
            $prefs = $this->preferences->recordAppOpen($request->user(), $appId);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($prefs->toApiArray());
    }

    public function reorderFavorites(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string', 'max:64'],
        ]);

        try {
            $prefs = $this->preferences->reorderFavorites($request->user(), $validated['ids']);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($prefs->toApiArray());
    }
}
