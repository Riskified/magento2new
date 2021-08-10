<?php

namespace Riskified\Decider\Model;

class Queue extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init('Riskified\Decider\Model\Resource\Queue');
    }

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getEntryId()
    {
        return $this->getData('entry_id');
    }

    /**
     * Get URL Key
     *
     * @return string|null
     */
    public function getAction()
    {
        return $this->getData('action');
    }

    /**
     * Get URL Key
     *
     * @return int|null
     */
    public function getOrderId()
    {
        return $this->getData('order_id');
    }

    /**
     * Get URL Key
     *
     * @return int|null
     */
    public function getLastError()
    {
        return $this->getData('last_error');
    }

    /**
     * Get URL Key
     *
     * @return int|null
     */
    public function getAttempts()
    {
        return $this->getData('attempts');
    }
    /**
     * Get URL Key
     *
     * @return string|null
     */
    public function getUpdatedAt()
    {
        return $this->getData('updated_at');
    }

    /**
     * Set ID
     *
     * @param int $entry_id
     *
     * @return $this
     */
    public function setEntryId($entry_id)
    {
        return $this->setData('entry_id', $entry_id);
    }

    /**
     * Set Action
     *
     * @param string $action
     *
     * @return $this
     */
    public function setAction($action)
    {
        return $this->setData('action', $action);
    }

    /**
     * Set Order ID
     *
     * @param int $order_id
     *
     * @return $this
     */
    public function setOrderId($order_id)
    {
        return $this->setData('order_id', $order_id);
    }
    /**
     * Set Attemps
     *
     * @param int $attempts
     *
     * @return $this
     */
    public function setAttempts($attempts)
    {
        return $this->setData('attempts', $attempts);
    }
    /**
     * Set ID
     *
     * @param int $lastAttempt
     *
     * @return $this
     */
    public function setLastAttempt($lastAttempt)
    {
        return $this->setData('last_attempt', $lastAttempt);
    }
    /**
     * Set ID
     *
     * @param int $nextAttempt
     *
     * @return $this
     */
    public function setNextAttempt($nextAttempt)
    {
        return $this->setData('next_attempt', $nextAttempt);
    }
    /**
     * Set ID
     *
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        return $this->setData('status', $status);
    }
}
