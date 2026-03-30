<?php

namespace Riskified\Decider\Model\Config\Source;

use Magento\Catalog\Model\Category;
use \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class Categories implements \Magento\Framework\Data\OptionSourceInterface
{
    private CollectionFactory $categoryCollectionFactory;

    public function __construct(
        CollectionFactory $categoryCollectionFactory,
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addIsActiveFilter();

        $data = [];

        /** @var Category $category */
        foreach ($collection as $category) {
            $prefix = '';

            for($i = 1; $i < $category->getLevel(); $i++) {
                $prefix .= '---';
            }

            $data[] = [
                'value' => $category->getId(),
                'label' =>  $prefix .' '. __($category->getName())
            ];
        }

        return $data;
    }
}
