<?php

namespace App\Services\Notes;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class CustomerNoteService
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): CustomerNote
    {
        $customer = Customer::findOrFail($data['customer_id']);

        $note = CustomerNote::create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'author_id' => $actor->id,
            'assignment_id' => $data['assignment_id'] ?? null,
            'body' => $data['body'],
            'edit_history' => [],
        ]);

        $this->audit->log('note.created', $note, null, $note->toArray(), $note->branch_id);

        return $note->fresh(['author']);
    }

    public function update(CustomerNote $note, string $body, User $actor): CustomerNote
    {
        if ($actor->isCollectorOnly() && (int) $note->author_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['note' => ['Collectors can only edit their own notes.']]);
        }

        $history = $note->edit_history ?? [];
        $history[] = [
            'body' => $note->body,
            'edited_by' => $actor->id,
            'edited_at' => now()->toIso8601String(),
        ];

        $note->update([
            'body' => $body,
            'edit_history' => $history,
        ]);

        $this->audit->log('note.updated', $note, null, ['body' => $body], $note->branch_id);

        return $note->fresh(['author']);
    }
}
