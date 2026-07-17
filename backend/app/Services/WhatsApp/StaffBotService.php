<?php

namespace App\Services\WhatsApp;

use App\Models\StaffActionToken;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskAssignmentWorkflowService;
use App\Services\Tickets\TicketService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

    public function generateStaffActionToken(
        User $user,
        string $action,
        Model $subject,
        array $payload = [],
        ?int $ttlMinutes = null,
    ): StaffActionToken {
        $ttl = $ttlMinutes ?? (int) config('ticketing.staff_action_token_ttl_minutes', 60);

        $token = StaffActionToken::query()->create([
            'token' => Str::random(48),
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'payload' => $payload,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->audit->log('staff_action_token.created', $token, null, [
            'action' => $action,
            'subject_type' => $token->subject_type,
            'subject_id' => $token->subject_id,
        ], $payload['branch_id'] ?? null);

        return $token;
    }

    public function signedActionUrl(StaffActionToken $token): string
    {
        return URL::temporarySignedRoute(
            'api.v1.staff-actions.process',
            $token->expires_at,
            ['token' => $token->token],
        );
    }

    /**
     * Process a secure staff action from signed token / interactive payload.
     *
     * @return array{ok: bool, result?: mixed, message: string}
     */
    public function processSecureAction(string $tokenValue, User $actor): array
    {
        $token = StaffActionToken::query()->where('token', $tokenValue)->first();
        if (! $token || ! $token->isValid()) {
            throw new InvalidArgumentException('Staff action token is invalid or expired.');
        }
        if ($token->user_id !== $actor->id) {
            throw new InvalidArgumentException('Staff action token belongs to a different user.');
        }

        $subject = $token->subject;
        if (! $subject) {
            throw new InvalidArgumentException('Staff action subject not found.');
        }

        $result = match ($token->action) {
            'start_task' => $subject instanceof Task
                ? $this->taskWorkflow->start($subject, $actor, $token->payload['reason'] ?? null)
                : throw new InvalidArgumentException('Expected task subject.'),
            'complete_task' => $subject instanceof Task
                ? $this->taskWorkflow->complete($subject, $actor, $token->payload['reason'] ?? null)
                : throw new InvalidArgumentException('Expected task subject.'),
            'accept_task' => $subject instanceof Task
                ? $this->taskWorkflow->accept($subject, $actor, $token->payload['reason'] ?? null)
                : throw new InvalidArgumentException('Expected task subject.'),
            'assign_ticket' => $subject instanceof Ticket
                ? $this->tickets->assign($subject, $actor, $actor, $token->payload['reason'] ?? null)
                : throw new InvalidArgumentException('Expected ticket subject.'),
            default => throw new InvalidArgumentException("Unsupported staff action [{$token->action}]."),
        };

        $token->used_at = now();
        $token->save();

        $this->audit->log('staff_action_token.used', $token, null, [
            'action' => $token->action,
        ], $token->payload['branch_id'] ?? null);

        return ['ok' => true, 'result' => $result, 'message' => 'Action processed.'];
    }

    /**
     * @return array{tasks: list<Task>, tickets: list<Ticket>}
     */
    public function myWork(User $user, int $limit = 20): array
    {
        return [
            'tasks' => Task::query()
                ->where(fn ($q) => $q->where('assignee_id', $user->id)->orWhere('offered_to_id', $user->id))
                ->whereNotIn('status', [Task::STATUS_VERIFIED, Task::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all(),
            'tickets' => Ticket::query()
                ->where('assignee_id', $user->id)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all(),
        ];
    }
}
