<?php
namespace Riskified\Decider\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Magento\Framework\Exception\State\UserLockedException;

class AccountManagementPlugin {
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    public function __construct(
        CustomerRepositoryInterface $customerRepository
    ){
        $this->customerRepository = $customerRepository;
    }

    public function aroundAuthenticate($subject, \Closure $proceed, ...$args)
    {
        try {
            $proceed(...$args);
        } catch(InvalidEmailOrPasswordException $e) {
            $customer = $this->customerRepository->get($args[0]);

            if (!$customer->getId()) {
                throw $e;
            }
            throw $e;
        } catch(EmailNotConfirmedException $e) {
            $customer = $this->customerRepository->get($args[0]);
            throw $e;
        } catch(UserLockedException $e) {
            $customer = $this->customerRepository->get($args[0]);
            throw $e;
        }
    }
}
