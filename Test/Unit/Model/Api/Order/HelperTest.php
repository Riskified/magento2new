<?php
declare(strict_types = 1);

namespace Riskified\Decider\Test\Unit\Model\Api\Order;

use PHPUnit\Framework\TestCase;
use Riskified\Decider\Model\Api\Order\Helper;
use Riskified\Decider\Model\Api\Order\StripeHelper;

class HelperTest extends TestCase
{
    /**
     * @dataProvider paymentTypeProvider
     */
    public function testGetPaymentType(string $method, ?string $stripeType, string $expected)
    {
        $payment = $this->getMockBuilder(\stdClass::class)->addMethods(['getMethod'])->getMock();
        $payment->method('getMethod')->willReturn($method);
        $order = $this->getMockBuilder(\stdClass::class)->addMethods(['getPayment'])->getMock();
        $order->method('getPayment')->willReturn($payment);

        $stripeHelper = $this->createMock(StripeHelper::class);
        $stripeHelper->method('getPaymentMethodType')->willReturn($stripeType);

        $helper = (new \ReflectionClass(Helper::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(Helper::class, 'stripeHelper');
        $prop->setAccessible(true);
        $prop->setValue($helper, $stripeHelper);
        $helper->setOrder($order);

        $this->assertSame($expected, $helper->getPaymentType());
    }

    public static function paymentTypeProvider(): array
    {
        return [
            'stripe bancontact' => ['stripe_payments', 'bancontact', 'bank_transfer'],
            'stripe alma' => ['stripe_payments', 'alma', 'bnpl'],
            'stripe other type unchanged' => ['stripe_payments', 'klarna', 'klarna'],
            'free' => ['free', null, 'store_credit'],
            'stripe bank transfers' => ['stripe_payments_bank_transfers', null, 'bank_transfer'],
            'magento offline bank transfer' => ['banktransfer', null, 'bank_transfer'],
            'paypal' => ['paypal_express', null, 'paypal'],
            'default card' => ['braintree', null, 'card'],
        ];
    }
}
