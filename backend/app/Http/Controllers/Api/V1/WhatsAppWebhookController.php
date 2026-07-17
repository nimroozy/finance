<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppWebhookService $webhooks) {}

    public function verify(Request $request): Response
    {
        $challenge = $this->webhooks->verifyChallenge(
            $request->query('hub_mode', $request->query('hub.mode')),
            $request->query('hub_verify_token', $request->query('hub.verify_token')),
            $request->query('hub_challenge', $request->query('hub.challenge')),
        );

        return $challenge === null
            ? response('Forbidden', 403)
            : response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): Response
    {
        if (! $this->webhooks->verifySignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        ProcessWhatsAppWebhookJob::dispatchSync($payload);

        return response('EVENT_RECEIVED', 200);
    }
}
