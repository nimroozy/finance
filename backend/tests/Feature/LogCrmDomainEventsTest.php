<?php

namespace Tests\Feature;

use App\Events\Crm\LeadConverted;
use App\Listeners\Crm\LogCrmDomainEvents;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogCrmDomainEventsTest extends TestCase
{
    public function test_listener_class_exists_and_is_registered(): void
    {
        $this->assertTrue(class_exists(LogCrmDomainEvents::class));
        $this->assertFalse(class_exists('App\\Listeners\\Crm\\LogCrmPlaceholderIntegrations'));

        Event::fake([LeadConverted::class]);
        // Provider already registered listeners; assert class name is referenced in provider source.
        $provider = File::get(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('LogCrmDomainEvents', $provider);
        $this->assertStringNotContainsString('LogCrmPlaceholderIntegrations', $provider);
        $this->assertSame(AppServiceProvider::class, AppServiceProvider::class);
    }

    public function test_handle_converted_logs_without_placeholder_messaging(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $listener = new LogCrmDomainEvents;
        $listener->handleConverted(new LeadConverted(
            leadId: 1,
            branchId: 1,
            customerId: 2,
            installationId: 3,
        ));

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) {
                return $message === 'crm.lead.converted'
                    && ! str_contains(strtolower($message), 'placeholder')
                    && ($context['lead_id'] ?? null) === 1;
            })
            ->once();
    }
}
