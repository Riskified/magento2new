<?php
namespace Riskified\Decider\Model\Api\Data;

use Magento\Framework\HTTP\Header;
use Magento\Framework\Locale\ResolverInterface;
use Riskified\Decider\Api\ClientDetailsInterface;

class ClientDetails implements ClientDetailsInterface
{
    /**
     * @var ResolverInterface
     */
    private $localeResolver;
    /**
     * @var Header
     */
    private $httpHeader;

    /**
     * ClientDetails constructor.
     * @param ResolverInterface $localeResolver
     * @param Header $httpHeader
     */
    public function __construct(
        ResolverInterface $localeResolver,
        Header $httpHeader
    ) {
        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return [
            'accept_language' => $this->localeResolver->getLocale(),
            'user_agent' => $this->httpHeader->getHttpUserAgent()
        ];
    }

    /**
     * @return array
     */
    public function getCleanData()
    {
        return array_filter($this->getData(), 'strlen');
    }
}
