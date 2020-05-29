<?php
namespace Riskified\Decider\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Magento\Framework\Exception\State\UserLockedException;

use Magento\Setup\Exception;
use Riskified\Decider\Api\ClientDetailsInterface;
use Riskified\Decider\Api\SessionDetailsInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\DateFormatter;

class AccountManagementPlugin
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ClientDetailsInterface
     */
    private $clientDetails;

    /**
     * @var SessionDetailsInterface
     */
    private $sessionDetails;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var
     */
    private $payload;

    /**
     * @var [string] Input data needed to load  customer object and pass additional information to next steps
     */
    private $inputData;

    use DateFormatter;

    /**
     * AccountManagementPlugin constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param ClientDetailsInterface $clientDetails
     * @param SessionDetailsInterface $sessionDetails
     * @param Api $api
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ClientDetailsInterface $clientDetails,
        SessionDetailsInterface $sessionDetails,
        Api $api
    ) {
        $this->customerRepository = $customerRepository;
        $this->clientDetails = $clientDetails;
        $this->sessionDetails = $sessionDetails;
        $this->api = $api;
    }

    /**
     * @param $subject
     * @param \Closure $proceed
     * @param mixed ...$args
     * @throws EmailNotConfirmedException
     * @throws InvalidEmailOrPasswordException
     * @throws UserLockedException
     */
    public function aroundAuthenticate($subject, \Closure $proceed, ...$args)
    {
        try {
            $this->inputData = $args;

            $proceed(...$args);
        } catch (InvalidEmailOrPasswordException $e) {
            $this
                ->prepareFailedLoginCustomerObject($e)
                ->callApi();

            throw $e;
        } catch (EmailNotConfirmedException $e) {
            $this
                ->prepareFailedLoginCustomerObject($e)
                ->callApi();

            throw $e;
        } catch (UserLockedException $e) {
            $this
                ->prepareFailedLoginCustomerObject($e)
                ->callApi();

            throw $e;
        }

        $this
            ->prepareSuccessfulLoginCustomerObject()
            ->callApi();
    }

    /**
     * @return $this
     */
    private function prepareSuccessfulLoginCustomerObject()
    {
        return $this->prepareCustomerObject(true);
    }

    /**
     * @param \Exception $exception
     * @return $this
     */
    private function prepareFailedLoginCustomerObject(\Exception $exception)
    {
        $type = false;
        if ($exception instanceof InvalidEmailOrPasswordException) {
            $type = 'wrong password';
        }
        if ($exception instanceof EmailNotConfirmedException) {
            $type = 'disabled account';
        }
        if ($exception instanceof UserLockedException) {
            $type = 'disabled account';
        }

        if ($type === false) {
            $type = 'other';
        }
        return $this->prepareCustomerObject(false, $type);
    }

    /**
     * @param bool $isSuccessful
     * @param null $reason
     * @return $this
     */
    private function prepareCustomerObject(bool $isSuccessful, $reason = null)
    {
        try {
            $this->payload = null;

            if (!isset($this->inputData[0])) {
                throw new \Exception("Input data is missing.");
            }

            $customer = $this->customerRepository->get($this->inputData[0]);

            if (!$customer->getId()) {
                throw new \Exception("No customer.");
            }

            $clientDetails = new \Riskified\OrderWebhook\Model\ClientDetails(
                $this->clientDetails->getCleanData()
            );

            $sessionDetails = new \Riskified\OrderWebhook\Model\SessionDetails(
                $this->sessionDetails->getCleanData()
            );

            $loginStatus = new \Riskified\OrderWebhook\Model\LoginStatus(
                array_filter([
                    'login_status_type' => $isSuccessful ? 'success' : 'failure',
                    'failure_reason' => !$isSuccessful ? $reason : null,
                ], 'strlen')
            );

            $customerPayload = [
                'customer_id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'customer_created_at' => $this->formatDateAsIso8601($customer->getCreatedAt()),
                'login_status' => $loginStatus,
                'client_details' => $clientDetails,
                'session_details' => $sessionDetails
            ];

            $riskifiedCustomerObject = new \Riskified\OrderWebhook\Model\Login($customerPayload);

            $this->payload = $riskifiedCustomerObject;
        } catch (\Exception $e) {
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function callApi()
    {
        if ($this->payload === null) {
            return $this;
        }

        try {
            $this->api->initSdk();
            $transport = $this->api->getTransport();
            $transport->login($this->payload);
        } catch (Exception $e) {
        }

        return $this;
    }
}
