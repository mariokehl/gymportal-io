<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\WidgetController;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use App\Models\Payment;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\WidgetRegistration;
use App\Services\MollieService;
use App\Services\WidgetService;

class MollieWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $mollieService;
    protected $widgetService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mollieService = Mockery::mock(MollieService::class);
        $this->widgetService = Mockery::mock(WidgetService::class);

        $this->app->instance(MollieService::class, $this->mollieService);

        $this->controller = new WidgetController($this->widgetService); // Anpassen an deinen Controller
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_hook_ping_events()
    {
        // Arrange
        $request = new Request();
        $request->json()->replace([
            'resource' => 'event',
            'type' => 'hook.ping'
        ]);

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_returns_400_when_payment_id_is_missing()
    {
        // Arrange
        $request = new Request();
        $request->json()->replace([
            'resource' => 'payment',
            'type' => 'payment.updated'
        ]);

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Payment ID missing', $response->getContent());
    }

    /** @test */
    public function it_returns_404_when_local_payment_not_found()
    {
        // Arrange
        $paymentId = 'tr_test123';
        $request = Request::create('/webhook', 'POST', ['id' => $paymentId]);
        $request->json()->replace(['id' => $paymentId]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Mollie webhook: Payment reference not found', ['payment_id' => $paymentId]);

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Payment not found', $response->getContent());
    }

    /** @test */
    public function it_processes_successful_payment_webhook()
    {
        // Arrange
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['status' => 'pending']);
        $membershipPlan = MembershipPlan::factory()->create();
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $membershipPlan->id,
            'status' => 'pending'
        ]);

        $payment = Payment::factory()->create([
            'mollie_payment_id' => 'tr_test123',
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'status' => 'pending',
            'amount' => 29.99,
            'payment_method' => 'ideal'
        ]);

        $widgetRegistration = WidgetRegistration::factory()->create([
            'gym_id' => $gym->id,
            'payment_data' => json_encode(['mollie_payment_id' => 'tr_test123']),
            'status' => 'pending'
        ]);

        $request = Request::create('/webhook', 'POST', ['id' => 'tr_test123']);
        $request->json()->replace(['id' => 'tr_test123']);

        // Mock Mollie Payment
        $molliePayment = Mockery::mock();
        $molliePayment->shouldReceive('isPaid')->andReturn(true);
        $molliePayment->status = 'paid';
        $molliePayment->id = 'tr_test123';

        $this->mollieService->shouldReceive('getPayment')
            ->with($gym, 'tr_test123')
            ->andReturn($molliePayment);

        $this->mollieService->shouldReceive('activateMolliePaymentMethod')
            ->with($gym, $member->id, 'ideal')
            ->once();

        $this->widgetService->shouldReceive('trackEvent')
            ->with($gym, 'mollie_webhook_paid', 'payment_webhook', Mockery::type('array'))
            ->once();

        $this->widgetService->shouldReceive('sendWelcomeEmail')
            ->with($member, $gym, $membershipPlan)
            ->once();

        Log::shouldReceive('info')
            ->once()
            ->with('Mollie webhook: Payment completed', Mockery::type('array'));

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());

        // Verify database updates
        $payment->refresh();
        $member->refresh();
        $membership->refresh();
        $widgetRegistration->refresh();

        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->paid_date);
        $this->assertEquals('active', $member->status);
        $this->assertEquals('active', $membership->status);
        $this->assertEquals('completed', $widgetRegistration->status);
        $this->assertNotNull($widgetRegistration->completed_at);
    }

    /** @test */
    public function it_updates_payment_status_without_activating_when_already_paid()
    {
        // Arrange
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['status' => 'active']);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'status' => 'active'
        ]);

        $payment = Payment::factory()->create([
            'mollie_payment_id' => 'tr_test123',
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'status' => 'paid', // Already paid
            'amount' => 29.99
        ]);

        $request = Request::create('/webhook', 'POST', ['id' => 'tr_test123']);
        $request->json()->replace(['id' => 'tr_test123']);

        // Mock Mollie Payment
        $molliePayment = Mockery::mock();
        $molliePayment->shouldReceive('isPaid')->andReturn(true);
        $molliePayment->status = 'paid';

        $this->mollieService->shouldReceive('getPayment')
            ->with($gym, 'tr_test123')
            ->andReturn($molliePayment);

        // Should not call activation methods since already paid
        $this->widgetService->shouldNotReceive('trackEvent');
        $this->widgetService->shouldNotReceive('sendWelcomeEmail');

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_handles_pending_payment_status()
    {
        // Arrange
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['status' => 'pending']);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'status' => 'pending'
        ]);

        $payment = Payment::factory()->create([
            'mollie_payment_id' => 'tr_test123',
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'status' => 'pending'
        ]);

        $request = Request::create('/webhook', 'POST', ['id' => 'tr_test123']);
        $request->json()->replace(['id' => 'tr_test123']);

        // Mock Mollie Payment (still pending)
        $molliePayment = Mockery::mock();
        $molliePayment->shouldReceive('isPaid')->andReturn(false);
        $molliePayment->status = 'pending';

        $this->mollieService->shouldReceive('getPayment')
            ->with($gym, 'tr_test123')
            ->andReturn($molliePayment);

        // Should not call activation methods
        $this->widgetService->shouldNotReceive('trackEvent');
        $this->widgetService->shouldNotReceive('sendWelcomeEmail');

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        // Verify status updated but no activation
        $payment->refresh();
        $member->refresh();
        $membership->refresh();

        $this->assertEquals('pending', $payment->status);
        $this->assertNull($payment->paid_date);
        $this->assertEquals('pending', $member->status);
        $this->assertEquals('pending', $membership->status);
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        // Arrange
        $request = Request::create('/webhook', 'POST', ['id' => 'tr_test123']);
        $request->json()->replace(['id' => 'tr_test123']);

        // Force an exception by not creating the payment
        Log::shouldReceive('error')
            ->once()
            ->with('Mollie webhook processing failed', Mockery::type('array'));

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Webhook processing failed', $response->getContent());
    }

    /** @test */
    public function it_handles_gym_not_found_exception()
    {
        // Arrange
        $payment = Payment::factory()->create([
            'mollie_payment_id' => 'tr_test123',
            'gym_id' => 99999 // Non-existent gym
        ]);

        $request = Request::create('/webhook', 'POST', ['id' => 'tr_test123']);
        $request->json()->replace(['id' => 'tr_test123']);

        Log::shouldReceive('error')
            ->once()
            ->with('Mollie webhook processing failed', Mockery::type('array'));

        // Act
        $response = $this->controller->handleMollieWebhook($request);

        // Assert
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Webhook processing failed', $response->getContent());
    }
}
