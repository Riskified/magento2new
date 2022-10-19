<?php
namespace Riskified\Decider\Model\Gateway\Braintree\Response;

use Braintree\ThreeDSecureInfo;
use Braintree\Transaction;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PayPal\Braintree\Gateway\Helper\SubjectReader;

class ThreeDSecureDetailsHandler implements HandlerInterface
{
    const ECI = "eci";
    const CAVV = "cavv";
    const TRANS_STATUS = "trans_status";

    public const LIABILITY_SHIFTED = 'liabilityShifted';
    public const LIABILITY_SHIFT_POSSIBLE = 'liabilityShiftPossible';
    public const ECI_FLAG = 'eciFlag';

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader)
    {
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response) : void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        /** @var Transaction $transaction */
        $transaction = $this->subjectReader->readTransaction($response);

        if (empty($transaction->threeDSecureInfo)) {
            return;
        }

        /** @var ThreeDSecureInfo $info */
        $info = $transaction->threeDSecureInfo;

        $payment->setAdditionalInformation(self::ECI, $info->eciFlag);
        $payment->setAdditionalInformation(self::CAVV, $info->cavv);

        if (is_array($info->authentication)) {
            $payment->setAdditionalInformation(self::TRANS_STATUS, $info->authentication['transStatus'] ?? false);
        } else {
            $payment->setAdditionalInformation(self::TRANS_STATUS, $info->authentication?->transStatus);
        }
    }
}
