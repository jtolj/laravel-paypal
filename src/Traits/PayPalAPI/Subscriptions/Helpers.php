<?php

namespace Srmklive\PayPal\Traits\PayPalAPI\Subscriptions;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

trait Helpers
{
    /**
     * @var array
     */
    protected $trial_pricing = [];

    /**
     * @var int
     */
    protected $payment_failure_threshold = 3;

    /**
     * Create a monthly subscription.
     *
     * @param string $name
     * @param string $description
     * @param string $type
     * @param string $category
     * @param float  $price
     * @param string $customer_name
     * @param string $customer_email
     * @param string $start_date
     *
     * @throws Throwable
     */
    public function createMonthlySubscription(string $name, string $description, string $type, string $category, float $price, string $customer_name, string $customer_email, string $start_date = '')
    {
        $request_id = Str::random();
        $start_date = isset($start_date) ? Carbon::parse($start_date)->toIso8601String() : Carbon::now()->toIso8601String();

        $product = $this->createProduct([
            'name'          => $name,
            'description'   => $description,
            'type'          => $type,
            'category'      => $category,
        ], $request_id);

        $plan = $this->addMonthlyPlan($product['id'], $name, $description, $price);

        $subscription = $this->createSubscription([
            'plan_id'    => $plan['id'],
            'start_time' => $start_date,
            'quantity'   => 1,
            'subscriber' => [],
        ]);
    }

    /**
     * Add a subscription trial pricing tier.
     *
     * @param string    $interval_type
     * @param string    $interval_count
     * @param float|int $price
     *
     * @return \Srmklive\PayPal\Services\PayPal
     */
    public function addSubscriptionTrialPricing(string $interval_type, string $interval_count, float $price = 0): \Srmklive\PayPal\Services\PayPal
    {
        $this->trial_pricing = $this->addPlanBillingCycle($interval_type, $interval_count, $price, true);

        return $this;
    }

    /**
     * @param string $product_id
     * @param string $name
     * @param string $description
     * @param float $price
     *
     * @throws Throwable
     *
     * @return array|\Psr\Http\Message\StreamInterface|string
     */
    protected function addMonthlyPlan(string $product_id, string $name, string $description, float $price)
    {
        $plan_pricing = $this->addPlanBillingCycle('MONTH', 1, $price);
        $billing_cycles = collect([$this->trial_pricing, $plan_pricing])->filter()->toArray();

        $plan_params = [
            'product_id'     => $product_id,
            'name'           => $name,
            'description'    => $description,
            'status'         => 'ACTIVE',
            'billing_cycles' => $billing_cycles,
            'payment_preferences' => [
                'auto_bill_outstanding'     => true,
                'setup_fee_failure_action'  => 'CONTINUE',
                'payment_failure_threshold' => $this->payment_failure_threshold,
            ],
        ];

        return $this->createPlan($plan_params);
    }

    /**
     * Add Plan's Billing cycle
     *
     * @param int   $interval_unit
     * @param int   $interval_count
     * @param float $price
     * @param bool  $trial
     *
     * @return array
     */
    protected function addPlanBillingCycle(int $interval_unit, int $interval_count, float $price, bool $trial = false): array
    {
        $pricing_scheme = [
            'fixed_price' => [
                'value'         => $price,
                'currency_code' => $this->getCurrency(),
            ]
        ];

        return [
            'frequency' => [
                'interval_unit'  => $interval_unit,
                'interval_count' => $interval_count,
            ],
            'tenure_type'    => ($trial === true) ? 'TRIAL' : 'REGULAR',
            'sequence'       => ($trial === true) ? 1 : 2,
            'total_cycles'   => ($trial === true) ? 1 : 0,
            'pricing_scheme' => $pricing_scheme,
        ];
    }
}
