<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model\Index\Type;

use Wyomind\Elasticsearch\Helper\Attribute as AttributeHelper;
use Wyomind\Elasticsearch\Helper\Interfaces\IndexerInterface as IndexerHelperInterface;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory as CategoryAttributeCollection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute as EntityAttribute;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class Category extends AbstractType
{

    /**
     * @var CategoryAttributeCollection
     */
    protected $categoryAttributeCollectionFactory;

    /**
     * Searchable attributes cache
     *
     * @var EntityAttribute[]
     */
    protected $searchableAttributes = [];

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param CategoryAttributeCollection $categoryAttributeCollectionFactory
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param EventManagerInterface $eventManager
     * @param AttributeHelper $attributeHelper
     * @param IndexerHelperInterface $indexerHelper
     * @param string $code
     */
    public function __construct(
        CategoryAttributeCollection $categoryAttributeCollectionFactory,
        EavConfig $eavConfig,
        StoreManagerInterface $storeManager,
        EventManagerInterface $eventManager,
        AttributeHelper $attributeHelper,
        IndexerHelperInterface $indexerHelper,
        $code
    ) {
    
        parent::__construct($eventManager, $attributeHelper, $indexerHelper, $code);

        $this->categoryAttributeCollectionFactory = $categoryAttributeCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties(
        $store = null,
        $withBoost = false
    ) {
    
        $properties = [];

        $attributes = $this->getSearchableAttributes($store);
        foreach ($attributes as $attribute) {
            /** @var EntityAttribute $attribute */
            $key = $attribute->getAttributeCode();
            $attribute->setData('is_searchable', true);
            $properties[$key] = $this->getAttributeProperties($attribute, $store, $withBoost);
        }

        // Add URL field
        $properties[\Wyomind\Elasticsearch\Helper\Config::CATEGORIES_URL] = [
            'type' => 'string',
            'store' => true,
            'index' => 'no',
        ];

        // Add category path field
        $properties[\Wyomind\Elasticsearch\Helper\Config::CATEGORIES_PATH] = [
            'type' => 'string',
            'store' => true,
            'index' => 'no',
        ];

        $properties = new DataObject($properties);

        $this->eventManager->dispatch('wyomind_elasticsearch_category_index_properties', [
            'indexer' => $this,
            'store' => $store,
            'properties' => $properties,
        ]);

        return $properties->getData();
    }

    /**
     * @param mixed $store
     * @return EntityAttribute[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getSearchableAttributes($store)
    {
        $storeId = $this->storeManager->getStore($store)->getId();

        if (!isset($this->searchableAttributes[$storeId])) {
            $this->searchableAttributes[$storeId] = [];

            $categoryAttributes = $this->categoryAttributeCollectionFactory->create();
            $categoryAttributes->addFieldToFilter('attribute_code', [
                'in' => $this->getEntitySearchableAttributes($storeId)
            ]);

            /** @var \Magento\Catalog\Model\ResourceModel\Category $entity */
            $entity = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Category::ENTITY)->getEntity();

            foreach ($categoryAttributes->getItems() as $attribute) {
                /** @var EntityAttribute $attribute */
                $attribute->setEntity($entity);
                $this->searchableAttributes[$storeId][$attribute->getAttributeCode()] = $attribute;
            }
        }

        return $this->searchableAttributes[$storeId];
    }
}
