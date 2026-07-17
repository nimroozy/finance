<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppConnection;
use App\Models\WhatsApp\WhatsAppTemplate;
use App\Models\WhatsApp\WhatsAppTemplateLanguage;
use Illuminate\Support\Facades\DB;

class WhatsAppTemplateSyncService
{
    public function __construct(
        private WhatsAppClient $client,
        private WhatsAppConnectionService $connections,
    ) {}

    public function sync(?WhatsAppConnection $connection = null): int
    {
        $connection ??= $this->connections->connection();
        $remote = $this->client->templates($connection);
        $count = 0;

        DB::transaction(function () use ($connection, $remote, &$count) {
            foreach ($remote['data'] ?? [] as $item) {
                $template = WhatsAppTemplate::query()->updateOrCreate(
                    ['connection_id' => $connection->id, 'name' => $item['name']],
                    [
                        'meta_template_id' => $item['id'] ?? null,
                        'category' => $item['category'] ?? null,
                        'status' => $item['status'] ?? 'UNKNOWN',
                    ],
                );
                WhatsAppTemplateLanguage::query()->updateOrCreate(
                    ['template_id' => $template->id, 'language_code' => $item['language'] ?? 'en'],
                    ['components' => $item['components'] ?? []],
                );
                $count++;
            }
        });

        return $count;
    }
}
