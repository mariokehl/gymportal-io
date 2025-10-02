<?php

namespace App\Jobs;

use App\Events\MollieMandateCreated;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Services\MollieService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Exceptions\ApiException;

class CreateMollieMandate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60, 120, 300]; // 10s, 30s, 1min, 2min, 5min

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Member $member,
        public PaymentMethod $paymentMethod,
        public string $customerId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(MollieService $mollieService): void
    {
        try {
            /**
             * Attempt to create the mandate
             *
             * @var Mandate $mandate
             */
            $mandate = $mollieService->createMandate(
                $this->member->gym,
                $this->customerId,
                $this->paymentMethod,
                $this->member->fullName()
            );

            // Dispatch event to activate payment method
            MollieMandateCreated::dispatch($this->member, $this->paymentMethod, $mandate->id);

            Log::info('Mollie mandate created successfully via queue', [
                'customer_id' => $this->customerId,
                'member_id' => $this->member->id,
                'mandate_id' => $mandate->id,
                'attempt' => $this->attempts(),
            ]);

        } catch (ApiException $e) {
            // Check if it's a 404 error (customer not found)
            if ($e->getCode() === 404) {
                Log::warning('Mollie customer not found, retrying mandate creation', [
                    'customer_id' => $this->customerId,
                    'member_id' => $this->member->id,
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                ]);

                // Release the job back to the queue with exponential backoff
                throw $e;
            }

            // For other API exceptions, log and fail
            Log::error('Mollie mandate creation failed with non-retryable error', [
                'customer_id' => $this->customerId,
                'member_id' => $this->member->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            // Don't retry for non-404 errors
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Mollie mandate creation job failed permanently', [
            'customer_id' => $this->customerId,
            'member_id' => $this->member->id,
            'payment_method_id' => $this->paymentMethod->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Optionally notify administrators or trigger alternative workflow
        // You could send a notification to admins here
    }
}
