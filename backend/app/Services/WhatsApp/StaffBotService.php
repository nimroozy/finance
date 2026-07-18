<?php

namespace App\Services\WhatsApp;

use App\Models\Tickets\StaffActionToken;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskAssignmentWorkflowService;
use App\Services\Tickets\TicketService;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class StaffBotService
{
    public function __construct(
        private TaskAssignmentWorkflowService $taskWorkflow,
        private TicketService $tickets,
        private AuditLogger $audit,
    ) {}

    /**
     * @return list<array{id: string, title_en: string, title_fa: string}>
     */
    public function interactiveActions(): array
    {
        return [
            ['id' => 'my_tasks', 'title_en' => 'My tasks', 'title_fa' => 'وظایف من'],
            ['id' => 'my_tickets', 'title_en' => 'My tickets', 'title_fa' => 'تیکت‌های من'],
            ['id' => 'start_task', 'title_en' => 'Start task', 'title_fa' => 'شروع وظیفه'],
            ['id' => 'complete_task', 'title_en' => 'Complete task', 'title_fa' => 'اتمام وظیفه'],
            ['id' => 'escalate_ticket', 'title_en' => 'Escalate', 'title_fa' => 'ارجاع فوری'],
            ['id' => 'open_customer', 'title_en' => 'Open customer', 'title_fa' => 'مشاهده مشتری'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, model: StaffActionToken}
     */
    public function generateStaffActionToken(
        User $user,
        string $action,
        array $payload = [],
        ?int $ttlMinutes = null,
    ): array {
        $ttl = $ttlMinutes ?? (int) config('ticketing.staff_action_token_ttl_minutes', 60);
        $issued = StaffActionToken::issue($user, $action, $payload, $ttl);

        $this->audit->log('staff_action_token.created', $issued['model'], null, [
            'action' => $action,
            'payload' => $payload,
        ], $payload['branch_id'] ?? null);

        return $issued;
    }

    public function signedActionUrl(string $plainToken, StaffActionToken $token): string
    {
        return URL::temporarySignedRoute(
            'api.v1.staff-actions.process',
            $token->expires_at,
            ['token' => $plainToken],
        );
    }

    /**
     * @return array{ok: bool, result?: mixed, message: string}
     */
    public function processSecureAction(string $tokenValue, User $actor): array
    {
        $hash = hash('sha256', $tokenValue);
        $token = StaffActionToken::query()->where('token_hash', $hash)->first();
        if (! $token || ! $token->isValid()) {
            throw new InvalidArgumentException('Staff action token is invalid or expired.');
        }
        if ($token->user_id !== $actor->id) {
            throw new InvalidArgumentException('Staff action token belongs to a different user.');
        }

        $payload = $token->payload ?? [];
        $result = match ($token->action) {
            'start_task' => $this->taskWorkflow->start(
                Task::query()->findOrFail($payload['task_id'] ?? 0),
                $actor,
                $payload['reason'] ?? null,
            ),
            'complete_task' => $this->taskWorkflow->complete(
                Task::query()->findOrFail($payload['task_id'] ?? 0),
                $actor,
                $payload['reason'] ?? null,
            ),
            'accept_task' => $this->taskWorkflow->accept(
                Task::query()->findOrFail($payload['task_id'] ?? 0),
                $actor,
                $payload['reason'] ?? null,
            ),
            'assign_ticket' => $this->tickets->assign(
                Ticket::query()->findOrFail($payload['ticket_id'] ?? 0),
                $actor,
                $actor,
                $payload['reason'] ?? null,
            ),
            default => throw new InvalidArgumentException("Unsupported staff action [{$token->action}]."),
        };

        $token->used_at = now();
        $token->save();

        $this->audit->log('staff_action_token.used', $token, null, [
            'action' => $token->action,
        ], $payload['branch_id'] ?? null);

        return ['ok' => true, 'result' => $result, 'message' => 'Action processed.'];
    }

    /**
     * @return array{tasks: list<Task>, tickets: list<Ticket>}
     */
    public function myWork(User $user, int $limit = 20): array
    {
        return [
            'tasks' => Task::query()
                ->where('assignee_id', $user->id)
                ->whereNotIn('status', Task::TERMINAL_STATUSES)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all(),
            'tickets' => Ticket::query()
                ->where('primary_assignee_id', $user->id)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all(),
        ];
    }
}
