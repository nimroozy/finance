<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\Tickets\Ticket;
use App\Services\Tickets\TicketIntakeService;

class CustomerBotService
{
    public function __construct(private TicketIntakeService $intake) {}

    /**
     * @return array{fa: list<array{id: string, title: string}>, en: list<array{id: string, title: string}>}
     */
    public function menuOptions(): array
    {
        return [
            'fa' => [
                ['id' => 'new_ticket', 'title' => 'ثبت تیکت جدید'],
                ['id' => 'my_tickets', 'title' => 'تیکت‌های من'],
                ['id' => 'check_status', 'title' => 'پیگیری وضعیت'],
                ['id' => 'verify_customer', 'title' => 'تأیید هویت'],
                ['id' => 'talk_agent', 'title' => 'گفتگو با پشتیبان'],
            ],
            'en' => [
                ['id' => 'new_ticket', 'title' => 'Open a ticket'],
                ['id' => 'my_tickets', 'title' => 'My tickets'],
                ['id' => 'check_status', 'title' => 'Check status'],
                ['id' => 'verify_customer', 'title' => 'Verify identity'],
                ['id' => 'talk_agent', 'title' => 'Talk to support'],
            ],
        ];
    }

    /**
     * @return array{verified: bool, customer_id: ?int, message_en: string, message_fa: string}
     */
    public function verifyCustomerStub(?string $phoneE164, ?string $customerNumber = null): array
    {
        $customer = null;
        if ($customerNumber) {
            $customer = Customer::query()
                ->where('customer_number', $customerNumber)
                ->orWhere('zoho_contact_id', $customerNumber)
                ->first();
        }

        if (! $customer && $phoneE164) {
            $suffix = substr(preg_replace('/\D/', '', $phoneE164), -9);
            if ($suffix !== '') {
                $customer = Customer::query()
                    ->where(function ($q) use ($suffix) {
                        $q->where('mobile', 'like', '%'.$suffix)
                            ->orWhere('phone', 'like', '%'.$suffix);
                    })
                    ->first();
            }
        }

        if ($customer) {
            return [
                'verified' => true,
                'customer_id' => $customer->id,
                'message_en' => 'Customer matched. Full OTP verification ships with bot UX.',
                'message_fa' => 'مشتری یافت شد. تأیید کامل OTP در مرحله رابط ربات اضافه می‌شود.',
            ];
        }

        return [
            'verified' => false,
            'customer_id' => null,
            'message_en' => 'Could not verify customer. An agent will follow up.',
            'message_fa' => 'هویت مشتری تأیید نشد. پشتیبان پیگیری خواهد کرد.',
        ];
    }

    /**
     * @return list<Ticket>
     */
    public function listOpenTicketsForCustomer(int $customerId, int $limit = 10): array
    {
        return Ticket::query()
            ->where('customer_id', $customerId)
            ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function handleMenuAction(string $action, int $inboundMessageId, array $context = []): array
    {
        return match ($action) {
            'new_ticket' => [
                'suggestion' => $this->intake->handleInboundMessage(
                    $inboundMessageId,
                    (bool) ($context['auto_create'] ?? false),
                ),
                'reply_en' => 'Thanks — we received your request.',
                'reply_fa' => 'درخواست شما دریافت شد.',
            ],
            'my_tickets' => [
                'tickets' => isset($context['customer_id'])
                    ? $this->listOpenTicketsForCustomer((int) $context['customer_id'])
                    : [],
                'reply_en' => 'Open tickets listed.',
                'reply_fa' => 'تیکت‌های باز فهرست شد.',
            ],
            'verify_customer' => $this->verifyCustomerStub(
                $context['phone'] ?? null,
                $context['customer_number'] ?? null,
            ),
            default => [
                'reply_en' => 'Unsupported action.',
                'reply_fa' => 'عملیات پشتیبانی نمی‌شود.',
            ],
        };
    }
}
