<?php
declare(strict_types = 1);

namespace Riskified\Decider\Test\Unit\Model\Api\Order\PaymentProcessor;

use PHPUnit\Framework\TestCase;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Stripe;

class StripeTest extends TestCase
{
    public function testMapThreeDSecure()
    {
        $this->assertSame([
            'eci' => '05',
            'liability_shift' => true,
            'trans_status' => 'Y',
            'three_d_challenge' => true,
        ], Stripe::mapThreeDSecure([
            'electronic_commerce_indicator' => '05',
            'result' => 'authenticated',
            'authentication_flow' => 'challenge',
            'transaction_id' => 'ds-trans-id',
        ]));

        $this->assertSame([
            'eci' => '07',
            'liability_shift' => false,
            'trans_status' => 'N',
            'three_d_challenge' => false,
            'TRA_exemption' => true,
        ], Stripe::mapThreeDSecure([
            'electronic_commerce_indicator' => '07',
            'result' => 'failed',
            'authentication_flow' => 'frictionless',
            'exemption_indicator' => 'low_risk',
        ]));

        $this->assertNull(Stripe::mapThreeDSecure(['result' => 'authenticated']));
    }

    public function testMapChecks()
    {
        $this->assertSame(['cvv_result_code' => 'M', 'avs_result_code' => 'Y'], Stripe::mapChecks([
            'cvc_check' => 'pass', 'address_line1_check' => 'pass', 'address_postal_code_check' => 'pass',
        ]));
        $this->assertSame(['cvv_result_code' => 'N', 'avs_result_code' => 'Z'], Stripe::mapChecks([
            'cvc_check' => 'fail', 'address_line1_check' => 'fail', 'address_postal_code_check' => 'pass',
        ]));
        $this->assertSame(['avs_result_code' => 'N'], Stripe::mapChecks([
            'address_line1_check' => 'fail', 'address_postal_code_check' => 'unavailable',
        ]));
        $this->assertSame([], Stripe::mapChecks(['cvc_check' => null]));
    }
}
