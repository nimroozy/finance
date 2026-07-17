<?php

namespace Tests\Feature;

use App\Jobs\EvaluateInventoryAlertsJob;
use App\Jobs\ExpireReservationsJob;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\Location;
use App\Models\Inventory\Product;
use App\Models\Inventory\Reservation;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\Inventory\Transfer;
use App\Models\Tickets\Installation;
use App\Services\Installations\InstallationQueueService;
use App\Services\Inventory\InstallationEquipmentService;
use App\Services\Inventory\LowStockAlertService;
use App\Services\Inventory\StockLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage9DefaultLocationsSeeder;
use Database\Seeders\Stage9ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage9InventoryTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage9ProductCategorySeeder::class);
        Http::fake();
    }

    public function test_product_create_and_category_seeded(): void
    {
        $branch = $this->makeBranch(['code' => 'I9A']);
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $this->getJson('/api/v1/inventory/categories')
            ->assertOk()
            ->assertJsonFragment(['code' => 'CPE']);

        $this->postJson('/api/v1/inventory/products', [
            'code' => 'CPE-ONT-1',
            'name_en' => 'ONT Unit',
            'tracking_mode' => Product::TRACK_QUANTITY,
            'min_stock' => 5,
            'is_installable' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'CPE-ONT-1')
            ->assertJsonPath('data.zoho_sync_status', 'pending');
    }

    public function test_default_locations_seeded_per_branch(): void
    {
        $branch = $this->makeBranch(['code' => 'I9L']);
        $this->seed(Stage9DefaultLocationsSeeder::class);

        $types = Location::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->pluck('type')
            ->all();

        $this->assertContains(Location::TYPE_WAREHOUSE, $types);
        $this->assertContains(Location::TYPE_SALES_STORE, $types);
        $this->assertContains(Location::TYPE_IN_TRANSIT, $types);
        $this->assertContains(Location::TYPE_SCRAP, $types);
    }

    public function test_receipt_posts_immutable_ledger_and_balance(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture();
        $this->actingAsUser($manager);

        $ledger = app(StockLedgerService::class);
        $txn = $ledger->postTransaction([
            'type' => StockTransaction::TYPE_RECEIPT,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'qty' => 10,
            'to_location_id' => $wh->id,
            'idempotency_key' => 'test-receipt-1',
        ], $manager);

        $this->assertSame('10.000', (string) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)
            ->where('product_id', $product->id)
            ->value('on_hand'));

        $this->expectException(LogicException::class);
        $txn->update(['qty' => 99]);
    }

    public function test_idempotency_returns_same_transaction(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture();
        $ledger = app(StockLedgerService::class);

        $a = $ledger->postTransaction([
            'type' => StockTransaction::TYPE_RECEIPT,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'qty' => 3,
            'to_location_id' => $wh->id,
            'idempotency_key' => 'same-key',
        ], $manager);

        $b = $ledger->postTransaction([
            'type' => StockTransaction::TYPE_RECEIPT,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'qty' => 3,
            'to_location_id' => $wh->id,
            'idempotency_key' => 'same-key',
        ], $manager);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, StockTransaction::withoutGlobalScopes()->where('idempotency_key', 'same-key')->count());
        $this->assertEquals(3.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_prevents_negative_stock_unless_allowed(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture();
        $ledger = app(StockLedgerService::class);
        $ledger->postTransaction([
            'type' => StockTransaction::TYPE_RECEIPT,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'qty' => 2,
            'to_location_id' => $wh->id,
            'idempotency_key' => 'neg-setup',
        ], $manager);

        $this->expectException(\InvalidArgumentException::class);
        $ledger->postTransaction([
            'type' => StockTransaction::TYPE_ISSUE,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'qty' => 5,
            'from_location_id' => $wh->id,
            'idempotency_key' => 'neg-issue',
        ], $manager);
    }

    public function test_reservation_reserve_fulfill_release_cycle(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 20);
        $this->actingAsUser($manager);

        $id = $this->postJson('/api/v1/inventory/reservations', [
            'branch_id' => $branch->id,
            'location_id' => $wh->id,
            'items' => [['product_id' => $product->id, 'qty' => 5]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/inventory/reservations/{$id}/reserve")
            ->assertOk()
            ->assertJsonPath('data.status', Reservation::STATUS_APPROVED);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->first();
        $this->assertEquals(5.0, (float) $balance->reserved);
        $this->assertEquals(15.0, $balance->available());

        $this->postJson("/api/v1/inventory/reservations/{$id}/fulfill")
            ->assertOk()
            ->assertJsonPath('data.status', Reservation::STATUS_FULFILLED);

        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->reserved);
        $this->assertEquals(15.0, (float) $balance->on_hand);
    }

    public function test_transfer_dispatch_and_receive(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 10);
        $tech = Location::withoutGlobalScopes()->create([
            'code' => 'TECH-X',
            'branch_id' => $branch->id,
            'type' => Location::TYPE_TECHNICAL_STORE,
            'name' => 'Tech',
            'is_active' => true,
        ]);
        $this->actingAsUser($manager);

        $id = $this->postJson('/api/v1/inventory/transfers', [
            'branch_id' => $branch->id,
            'from_location_id' => $wh->id,
            'to_location_id' => $tech->id,
            'items' => [['product_id' => $product->id, 'qty' => 4]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/inventory/transfers/{$id}/submit")->assertOk();
        $this->postJson("/api/v1/inventory/transfers/{$id}/approve")->assertOk();
        $this->postJson("/api/v1/inventory/transfers/{$id}/dispatch")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_IN_TRANSIT);

        $this->assertEquals(6.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));

        $this->postJson("/api/v1/inventory/transfers/{$id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_RECEIVED);

        $this->assertEquals(4.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $tech->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_serialized_equipment_receive_unique_serial(): void
    {
        $branch = $this->makeBranch(['code' => 'I9S']);
        $manager = $this->makeManager($branch);
        $wh = $this->makeWarehouse($branch);
        $this->actingAsUser($manager);

        $productId = $this->postJson('/api/v1/inventory/products', [
            'code' => 'RAD-1',
            'name_en' => 'Radio',
            'tracking_mode' => Product::TRACK_SERIALIZED,
            'is_installable' => true,
        ])->json('data.id');

        $this->postJson('/api/v1/inventory/equipment/receive', [
            'product_id' => $productId,
            'serial_number' => 'SN-001',
            'branch_id' => $branch->id,
            'location_id' => $wh->id,
        ])->assertCreated()
            ->assertJsonPath('data.serial_number', 'SN-001');

        $this->postJson('/api/v1/inventory/equipment/receive', [
            'product_id' => $productId,
            'serial_number' => 'SN-001',
            'branch_id' => $branch->id,
            'location_id' => $wh->id,
        ])->assertStatus(422);
    }

    public function test_goods_receipt_posts_purchase_receipt(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture();
        $this->actingAsUser($manager);

        $supplierId = $this->postJson('/api/v1/inventory/suppliers', [
            'code' => 'SUP1',
            'name' => 'Vendor A',
        ])->json('data.id');

        $this->postJson('/api/v1/inventory/goods-receipts', [
            'branch_id' => $branch->id,
            'location_id' => $wh->id,
            'supplier_id' => $supplierId,
            'items' => [['product_id' => $product->id, 'qty' => 7, 'unit_cost' => 12.5]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'posted');

        $this->assertEquals(7.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_adjustment_requires_reason_and_updates_balance(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 10);
        $this->actingAsUser($manager);

        $this->postJson('/api/v1/inventory/stock/adjust', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'location_id' => $wh->id,
            'qty' => -2,
            'reason' => 'cycle_count_variance',
        ])->assertCreated();

        $this->assertEquals(8.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_site_and_tower_crud(): void
    {
        $branch = $this->makeBranch(['code' => 'I9T']);
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $siteId = $this->postJson('/api/v1/inventory/sites', [
            'code' => 'SITE-1',
            'branch_id' => $branch->id,
            'name' => 'North Site',
            'site_type' => 'network',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/inventory/towers', [
            'code' => 'TWR-1',
            'site_id' => $siteId,
            'height_m' => 45,
            'structure_type' => 'lattice',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'TWR-1');
    }

    public function test_branch_isolation_for_stock_balances(): void
    {
        $branchA = $this->makeBranch(['code' => 'I9BA']);
        $branchB = $this->makeBranch(['code' => 'I9BB']);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        $whA = $this->makeWarehouse($branchA);

        $this->actingAsUser($managerA);
        $productId = $this->postJson('/api/v1/inventory/products', [
            'code' => 'ISO-P',
            'name_en' => 'Iso Product',
            'tracking_mode' => Product::TRACK_QUANTITY,
        ])->json('data.id');

        app(StockLedgerService::class)->postTransaction([
            'type' => StockTransaction::TYPE_RECEIPT,
            'branch_id' => $branchA->id,
            'product_id' => $productId,
            'qty' => 5,
            'to_location_id' => $whA->id,
            'idempotency_key' => 'iso-1',
        ], $managerA);

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/inventory/stock/balances')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsUser($managerA);
        $this->getJson('/api/v1/inventory/stock/balances')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_installation_reservation_and_block_complete_if_unaccounted(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 10);
        $this->actingAsUser($manager);

        $installation = Installation::query()->create([
            'installation_number' => 'INS-TEST-1',
            'branch_id' => $branch->id,
            'status' => Installation::STATUS_EQUIPMENT_WAITING,
            'prospect_name' => 'Prospect',
            'created_by' => $manager->id,
        ]);

        $rsvId = $this->postJson("/api/v1/inventory/installations/{$installation->id}/reserve", [
            'location_id' => $wh->id,
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/inventory/reservations/{$rsvId}/reserve")->assertOk();

        $queue = app(InstallationQueueService::class);
        $this->expectException(\InvalidArgumentException::class);
        $queue->ensureEquipmentReconciled($installation->fresh());
    }

    public function test_installation_complete_allowed_after_fulfill(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 10);
        $this->actingAsUser($manager);

        $installation = Installation::query()->create([
            'installation_number' => 'INS-TEST-2',
            'branch_id' => $branch->id,
            'status' => Installation::STATUS_EQUIPMENT_WAITING,
            'prospect_name' => 'Prospect 2',
            'created_by' => $manager->id,
        ]);

        $svc = app(InstallationEquipmentService::class);
        $rsv = $svc->createReservationFromInstallation($installation, $wh->id, [
            ['product_id' => $product->id, 'qty' => 1],
        ], $manager);
        $svc->fulfillForInstallation($rsv, $manager);

        $queue = app(InstallationQueueService::class);
        $queue->ensureEquipmentReconciled($installation->fresh());
        $this->assertTrue(true);
    }

    public function test_stock_count_posts_adjustment(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 10);
        $this->actingAsUser($manager);

        $created = $this->postJson('/api/v1/inventory/stock-counts', [
            'branch_id' => $branch->id,
            'location_id' => $wh->id,
        ])->assertCreated();

        $countId = $created->json('data.id');
        $lineId = $created->json('data.lines.0.id');
        $this->assertNotNull($lineId);

        $this->postJson("/api/v1/inventory/stock-counts/{$countId}/record", [
            'counted' => [$lineId => 8],
        ])->assertOk();

        $this->postJson("/api/v1/inventory/stock-counts/{$countId}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertEquals(8.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_low_stock_alert_job_emits_notification_event(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 1);
        $product->min_stock = 10;
        $product->save();

        Event::fake([\App\Events\BusinessNotificationRequested::class]);

        app(LowStockAlertService::class)->evaluate($branch->id);

        Event::assertDispatched(\App\Events\BusinessNotificationRequested::class, function ($e) {
            return $e->eventType === 'inventory.low_stock_digest';
        });

        (new EvaluateInventoryAlertsJob)->handle(app(LowStockAlertService::class));
        (new ExpireReservationsJob)->handle(app(\App\Services\Inventory\ReservationService::class));
        $this->assertTrue(true);
    }

    public function test_dashboard_and_search_endpoints(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 5);
        $this->actingAsUser($manager);

        $this->getJson('/api/v1/inventory/dashboard?branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('data.on_hand', 5);

        $this->getJson('/api/v1/inventory/search?q='.$product->code)
            ->assertOk()
            ->assertJsonFragment(['code' => $product->code]);
    }

    public function test_import_dry_run_and_apply_opening_balance(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture();
        $this->actingAsUser($manager);

        $rows = [[
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'location_id' => $wh->id,
            'qty' => 15,
            'idempotency_key' => 'import-ob-1',
        ]];

        $this->postJson('/api/v1/inventory/import/dry-run', [
            'type' => 'opening_balances',
            'rows' => $rows,
        ])->assertOk()
            ->assertJsonPath('data.valid', 1);

        $this->postJson('/api/v1/inventory/import/apply', [
            'type' => 'opening_balances',
            'rows' => $rows,
        ])->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertEquals(15.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_equipment_sale_issues_stock(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 5);
        $customer = $this->makeCustomer($branch);
        $this->actingAsUser($manager);

        $saleId = $this->postJson('/api/v1/inventory/equipment-sales', [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'location_id' => $wh->id,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_price' => 100]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/inventory/equipment-sales/{$saleId}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals(3.0, (float) StockBalance::withoutGlobalScopes()
            ->where('location_id', $wh->id)->where('product_id', $product->id)->value('on_hand'));
    }

    public function test_stock_transaction_delete_rejected(): void
    {
        [$branch, $manager, $wh, $product] = $this->setupStockFixture(qty: 1);
        $txn = StockTransaction::withoutGlobalScopes()->first();
        $this->assertNotNull($txn);
        $this->expectException(LogicException::class);
        $txn->delete();
    }

    /**
     * @return array{0:\App\Models\Branch,1:\App\Models\User,2:Location,3:Product}
     */
    private function setupStockFixture(float $qty = 0): array
    {
        $branch = $this->makeBranch(['code' => 'I9'.substr(uniqid(), -4)]);
        $manager = $this->makeManager($branch);
        $wh = $this->makeWarehouse($branch);

        $this->actingAsUser($manager);
        $productId = $this->postJson('/api/v1/inventory/products', [
            'code' => 'P-'.substr(uniqid(), -6),
            'name_en' => 'Test Product',
            'tracking_mode' => Product::TRACK_QUANTITY,
            'min_stock' => 2,
        ])->json('data.id');

        $product = Product::query()->findOrFail($productId);

        if ($qty > 0) {
            app(StockLedgerService::class)->postTransaction([
                'type' => StockTransaction::TYPE_RECEIPT,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'to_location_id' => $wh->id,
                'idempotency_key' => 'setup-'.$product->id.'-'.$qty,
            ], $manager);
        }

        return [$branch, $manager, $wh, $product];
    }

    private function makeWarehouse(\App\Models\Branch $branch): Location
    {
        return Location::withoutGlobalScopes()->create([
            'code' => 'WH-'.substr(uniqid(), -5),
            'branch_id' => $branch->id,
            'type' => Location::TYPE_WAREHOUSE,
            'name' => 'Main Warehouse',
            'allow_receiving' => true,
            'allow_dispatch' => true,
            'allow_negative' => false,
            'is_active' => true,
        ]);
    }
}
