<?php
/**
 * Stripe payment processor
 *
 */
declare(strict_types = 1);

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * @class Stripe
 *
 * @description Handle payment data processed and returned by Stripe extension.
 */
class Stripe extends AbstractPayment
{
    private const STRIPE_CONFIG = 'StripeIntegration\Payments\Model\Config';

    private const TRANS_STATUS = [
        'authenticated' => 'Y',
        'attempt_acknowledged' => 'A',
        'failed' => 'N',
        'not_supported' => 'U',
        'processing_error' => 'U',
        'exempted' => 'I',
    ];

    // ponytail: per-process cache so gateway + payment details share one Stripe call; unbounded, fine for cron batch sizes
    private static $charges = [];

    private $objectManager;
    private $logger;

    public function __construct(ObjectManagerInterface $objectManager, LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getDetails() : array
    {
        $details = [];
        $jsonEncodedSource = $this->payment->getAdditionalInformation('source_info');
        $last4 = $this->payment->getCcLast4();
        $ccCompany = $this->payment->getCcType();

        if ($jsonEncodedSource) {
            $sourceInfo = json_decode($jsonEncodedSource, true);
            $parts = explode(' ', $sourceInfo['Card']);

            $ccCompany = $parts[0];
            $last4 = $parts[3];
        }

        $card = $this->getCard();
        if ($card) {
            $last4 = $card->last4 ?? $last4;
            $ccCompany = $card->brand ?? $ccCompany;
            $details['credit_card_bin'] = $card->iin ?? $card->country ?? null;
            if (isset($card->checks)) {
                $details += self::mapChecks($card->checks->toArray());
            }
            if (isset($card->three_d_secure)) {
                $details['authentication_result'] = self::mapThreeDSecure($card->three_d_secure->toArray());
            }
        }

        $details['credit_card_number'] = $last4;
        $details['credit_card_company'] = $ccCompany;

        return $details;
    }

    /**
     * Stripe payment method type of the charge (card, paypal, klarna, ...).
     */
    public function getPaymentMethodType() : ?string
    {
        return $this->getCharge()->payment_method_details->type
            ?? $this->getPaymentMethod()->type
            ?? null;
    }

    /**
     * Payment method the customer selected; available on the quote before any charge exists (checkout denied).
     */
    private function getPaymentMethod()
    {
        $token = (string) $this->payment->getAdditionalInformation('token');
        $paymentMethodId = preg_match('/\bpm_[A-Za-z0-9]+/', $token, $m) ? $m[0] : null;
        if (!$paymentMethodId || !class_exists(self::STRIPE_CONFIG)) {
            return null;
        }

        try {
            return $this->getStripeClient()->paymentMethods->retrieve($paymentMethodId);
        } catch (\Throwable $e) {
            $this->logger->warning('Riskified: Stripe payment method lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    private function getStripeClient()
    {
        $config = $this->objectManager->get(self::STRIPE_CONFIG);
        $config->initStripe(null, $this->order ? $this->order->getStoreId() : null);

        return $config->getStripeClient();
    }

    private function getCard()
    {
        return $this->getCharge()->payment_method_details->card ?? null;
    }

    private function getCharge()
    {
        $candidates = implode(' ', [
            $this->payment->getAdditionalInformation('payment_intent_id'),
            $this->payment->getAdditionalInformation('server_side_transaction_id'),
            $this->payment->getLastTransId(),
        ]);
        $paymentIntentId = preg_match('/\bpi_[A-Za-z0-9]+/', $candidates, $m) ? $m[0] : null;
        if (!$paymentIntentId) {
            return null;
        }
        if (isset(self::$charges[$paymentIntentId])) {
            return self::$charges[$paymentIntentId];
        }
        if (!class_exists(self::STRIPE_CONFIG)) {
            return null;
        }

        try {
            $paymentIntent = $this->getStripeClient()->paymentIntents->retrieve(
                $paymentIntentId,
                ['expand' => ['latest_charge']]
            );
            return self::$charges[$paymentIntentId] = $paymentIntent->latest_charge ?? null;
        } catch (\Throwable $e) {
            $this->logger->warning('Riskified: Stripe charge lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function mapChecks(array $checks) : array
    {
        $cvc = $checks['cvc_check'] ?? null;
        $line1 = $checks['address_line1_check'] ?? null;
        $postal = $checks['address_postal_code_check'] ?? null;

        $avs = null;
        if ($line1 === 'pass' && $postal === 'pass') {
            $avs = 'Y';
        } elseif ($line1 === 'pass') {
            $avs = 'A';
        } elseif ($postal === 'pass') {
            $avs = 'Z';
        } elseif ($line1 === 'fail' || $postal === 'fail') {
            $avs = 'N';
        } elseif ($line1 || $postal) {
            $avs = 'U';
        }

        return array_filter([
            'cvv_result_code' => ['pass' => 'M', 'fail' => 'N', 'unavailable' => 'U', 'unchecked' => 'P'][$cvc] ?? null,
            'avs_result_code' => $avs,
        ], fn ($v) => $v !== null);
    }

    public static function mapThreeDSecure(array $threeDSecure) : ?array
    {
        if (empty($threeDSecure['electronic_commerce_indicator'])) {
            return null;
        }

        $result = $threeDSecure['result'] ?? null;
        $flow = $threeDSecure['authentication_flow'] ?? null;

        return array_filter([
            'eci' => $threeDSecure['electronic_commerce_indicator'],
            'liability_shift' => in_array($result, ['authenticated', 'attempt_acknowledged'], true),
            'trans_status' => self::TRANS_STATUS[$result] ?? null,
            'three_d_challenge' => $flow ? $flow === 'challenge' : null,
            'TRA_exemption' => ($threeDSecure['exemption_indicator'] ?? null) === 'low_risk' ? true : null,
        ], fn ($v) => $v !== null);
    }
}
