<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppContactPreference;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppFailure;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppNotificationRule;
use App\Models\WhatsApp\WhatsAppTemplate;
use App\Services\AuditLogger;
use App\Services\WhatsApp\PhoneNumberNormalizer;
use App\Services\WhatsApp\WhatsAppConnectionService;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppTemplateSyncService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppConnectionService $connections,
        private WhatsAppTemplateSyncService $templates,
        private WhatsAppMessageService $messages,
        private AuditLogger $audit,
        private PhoneNumberNormalizer $phones,
    ) {}

    public function status(): JsonResponse
    {
        return ApiResponse::success($this->connections->status());
    }

    public function testConnection(Request $request): JsonResponse
    {
        $result = $this->connections->test();
        $this->audit->log(
            'whatsapp.test_connection',
            null,
            null,
            ['ok' => $result['ok'], 'error' => $result['error'] ?? null],
            $request->user()?->branchIds()[0] ?? null,
        );

        return $result['ok'] ? ApiResponse::success($result) : ApiResponse::error($result['error'], status: 422);
    }

    public function pause(Request $request): JsonResponse
    {
        $connection = $this->connections->pause();
        $this->audit->log('whatsapp.paused', $connection, null, ['is_paused' => true], null);

        return ApiResponse::success($connection);
    }

    public function resume(Request $request): JsonResponse
    {
        $connection = $this->connections->resume();
        $this->audit->log('whatsapp.resumed', $connection, null, ['is_paused' => false], null);

        return ApiResponse::success($connection);
    }

    public function syncTemplates(Request $request): JsonResponse
    {
        $synced = $this->templates->sync();
        $this->audit->log('whatsapp.templates_synced', null, null, ['synced' => $synced], null);

        return ApiResponse::success(['synced' => $synced]);
    }

    public function templates(Request $request): JsonResponse
    {
        $page = WhatsAppTemplate::query()->orderBy('name')->paginate($request->integer('per_page', 30));

        return $this->page($page);
    }

    public function messages(Request $request): JsonResponse
    {
        $page = $this->visible(WhatsAppMessage::query(), $request->user())
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')->paginate($request->integer('per_page', 30));

        return $this->page($page);
    }

    public function failures(Request $request): JsonResponse
    {
        $page = $this->visible(WhatsAppFailure::query(), $request->user())
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('id')->paginate($request->integer('per_page', 30));

        return $this->page($page);
    }

    public function inbox(Request $request): JsonResponse
    {
        $page = $this->visible(WhatsAppConversation::query(), $request->user())
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('last_message_at')->paginate($request->integer('per_page', 30));

        return $this->page($page);
    }

    public function showConversation(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $conversation->load([
            'inboundMessages' => fn ($q) => $q->orderBy('received_at'),
            'customer:id,name,mobile,phone',
        ]);

        return ApiResponse::success($conversation);
    }

    public function resolveConversation(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $conversation->update(['status' => 'resolved']);
        $this->audit->log('whatsapp.conversation_resolved', $conversation, null, ['status' => 'resolved'], $conversation->branch_id);

        return ApiResponse::success($conversation->fresh());
    }

    public function upsertPreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'messaging_allowed' => ['boolean'],
            'whatsapp_enabled' => ['boolean'],
            'receipts_enabled' => ['boolean'],
            'reminders_enabled' => ['boolean'],
            'promotional_enabled' => ['boolean'],
            'preferred_language' => ['nullable', 'string', 'max:16'],
        ]);
        $phone = $this->phones->normalize($data['phone']);
        if ($phone === null) {
            return ApiResponse::error('Invalid phone number.', status: 422);
        }
        unset($data['phone']);
        $pref = WhatsAppContactPreference::updateOrCreate(
            ['phone_e164' => $phone],
            array_merge($data, ['phone_e164' => $phone]),
        );
        $this->audit->log('whatsapp.preferences_saved', $pref, null, $data, null);

        return ApiResponse::success($pref);
    }

    public function rules(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WhatsAppNotificationRule::query();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->where(fn ($q) => $q->whereIn('branch_id', $user->branchIds())->orWhereNull('branch_id'));
        }

        return ApiResponse::success($query->orderBy('event_type')->get());
    }

    public function saveRules(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'event_type' => ['required', 'string', 'max:100'],
            'in_app_enabled' => ['boolean'],
            'whatsapp_enabled' => ['boolean'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'language_code' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
            'min_amount' => ['nullable', 'numeric'],
            'severity' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin() && ! in_array((int) ($data['branch_id'] ?? 0), $user->branchIds(), true)) {
            abort(403);
        }
        $rule = WhatsAppNotificationRule::updateOrCreate(
            ['branch_id' => $data['branch_id'] ?? null, 'event_type' => $data['event_type']],
            $data,
        );
        $this->audit->log('whatsapp.rules_saved', $rule, null, $data, $data['branch_id'] ?? null);

        return ApiResponse::success($rule);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'template_name' => ['required', 'string'],
            'language_code' => ['nullable', 'string'],
            'components' => ['nullable', 'array'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $message = $this->messages->queueTemplate(
            $data['phone'], $data['template_name'], $data['language_code'] ?? 'fa',
            $data['components'] ?? [], $data['branch_id'] ?? null, null, $request->user()->id,
            'send_test',
        );
        $this->audit->log('whatsapp.send_test', $message, null, [
            'template_name' => $data['template_name'],
            'language_code' => $data['language_code'] ?? 'fa',
            'branch_id' => $data['branch_id'] ?? null,
        ], $data['branch_id'] ?? null);

        return ApiResponse::success($message, status: 202);
    }

    private function authorizeConversation(User $user, WhatsAppConversation $conversation): void
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return;
        }
        if ($conversation->branch_id !== null && ! in_array((int) $conversation->branch_id, $user->branchIds(), true)) {
            abort(403);
        }
    }

    private function visible(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branchIds());
    }

    private function page($page): JsonResponse
    {
        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]);
    }
}
