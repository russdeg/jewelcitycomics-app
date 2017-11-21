<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model\Index\Type;

use Wyomind\Elasticsearch\Helper\Attribute as AttributeHelper;
use Wyomind\Elasticsearch\Helper\Interfaces\IndexerInterface as IndexerHelperInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogEavAttribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class Product extends AbstractType
{

    /**
     * @var ProductAttributeCollectionFactory
     */
    protected $productAttributeCollectionFactory;

    /**
     * Searchable attributes cache
     *
     * @var CatalogEavAttribute[]
     */
    protected $searchableAttributes;

    /**
     * @param ProductAttributeCollectionFactory $productAttributeCollectionFactory
     * @param EavConfig $eavConfig
     * @param EventManagerInterface $eventManager
     * @param AttributeHelper $attributeHelper
     * @param IndexerHelperInterface $indexerHelper
     * @param string $code
     */
    public function __construct(
        ProductAttributeCollectionFactory $productAttributeCollectionFactory,
        EavConfig $eavConfig,
        EventManagerInterface $eventManager,
        AttributeHelper $attributeHelper,
        IndexerHelperInterface $indexerHelper,
        $code
    ) {
    
        parent::__construct($eventManager, $attributeHelper, $indexerHelper, $code);

        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties(
        $store = null,
        $withBoost = false
    ) {
    
        \Magento\Framework\Profiler::start(__METHOD__);

        $properties = [];

        $attributes = $this->getSearchableAttributes(['varchar', 'int']);
        foreach ($attributes as $attribute) {
            /** @var CatalogEavAttribute $attribute */
            if ($this->indexerHelper->isAttributeIndexable($attribute)) {
                $key = $attribute->getAttributeCode();
                $properties[$key] = $this->getAttributeProperties($attribute, $store, $withBoost);
            }
        }

        $attributes = $this->getSearchableAttributes('text');
        foreach ($attributes as $attribute) {
            /** @var CatalogEavAttribute $attribute */
            $key = $attribute->getAttributeCode();
            $properties[$key] = $this->getAttributeProperties($attribute, $store, $withBoost);
        }

        $attributes = $this->getSearchableAttributes(['static', 'varchar', 'decimal', 'datetime']);
        foreach ($attributes as $attribute) {
            /** @var CatalogEavAttribute $attribute */
            $key = $attribute->getAttributeCode();
            if ($this->indexerHelper->isAttributeIndexable($attribute) && !isset($properties[$key])) {
                $type = $this->getAttributeType($attribute);
                if ($type === 'option') {
                    continue;
                }

                $properties[$key] = [
                    'type' => $type,
                    'include_in_all' => (bool) $attribute->getIsSearchable(),
                ];

                if ($withBoost) {
                    $boost = (int) $attribute->getData('search_weight');
                    if ($boost > 1) {
                        $properties[$key]['boost'] = $boost;
                    }
                }

                if ($key == 'sku') {
                    $properties[$key]['include_in_all'] = true;
                    $properties[$key]['type'] = "string";
                    $properties[$key]['index'] = "not_analyzed";
//                    $properties[$key]['fields'] = [
//                        'keyword' => [
//                            'type' => 'string',
//                            'analyzer' => 'keyword',
//                        ],
//                        'prefix' => [
//                            'type' => 'string',
//                            'analyzer' => 'keyword_prefix',
//                            'search_analyzer' => 'keyword',
//                        ],
//                    ];
                }

                if ($attribute->getBackendType() == 'datetime') {
                    $properties[$key]['format'] = 'date';
                    $properties[$key]['ignore_malformed'] = true;
                }
            }
        }

        // Add categories field
        $properties[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_CATEGORIES] = [
            'type' => 'string',
            'include_in_all' => true,
            'analyzer' => $this->getLanguageAnalyzer($store),
        ];

        // Add parent_ids field
        $properties[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_PARENT_IDS] = [
            'type' => 'integer',
            'store' => true,
            'index' => 'no',
        ];

        // Add URL field
        $properties[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_URL] = [
            'type' => 'string',
            'store' => true,
            'index' => 'no',
        ];

        $properties = new DataObject($properties);

        $this->eventManager->dispatch('wyomind_elasticsearch_product_index_properties', [
            'indexer' => $this,
            'store' => $store,
            'properties' => $properties,
        ]);

        \Magento\Framework\Profiler::stop(__METHOD__);

        return $properties->getData();
    }

    /**
     * @param mixed $backendType
     * @return CatalogEavAttribute[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getSearchableAttributes($backendType = null)
    {
        if (null === $this->searchableAttributes) {
            $this->searchableAttributes = [];

            $productAttributes = $this->productAttributeCollectionFactory->create();
            $productAttributes->addToIndexFilter(true);

            /** @var CatalogEavAttribute[] $attributes */
            $attributes = $productAttributes->getItems();

            /** @var \Magento\Catalog\Model\ResourceModel\Product $entity */
            $entity = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY)->getEntity();

            foreach ($attributes as $attribute) {
                /** @var CatalogEavAttribute $attribute */
                $attribute->setEntity($entity);
            }

            $this->searchableAttributes = $attributes;
        }

        if ($backendType !== null) {
            $backendType = (array) $backendType;
            $attributes = [];
            foreach ($this->searchableAttributes as $attributeId => $attribute) {
                if (in_array($attribute->getBackendType(), $backendType)) {
                    $attributes[$attributeId] = $attribute;
                }
            }

            return $attributes;
        }

        return $this->searchableAttributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchFields(
        $q,
        $store = null,
        $withBoost = false
    ) {
    
        $excludedFields = [];
        if (!is_numeric($q)) {
            // Ignore numeric fields if text query is not numeric (avoid potential Elasticsearch exceptions)
            $properties = $this->getProperties($store);
            foreach ($properties as $field => $property) {
                if ($property['type'] !== 'string') {
                    $excludedFields[] = $field;
                }
            }
        } else {
            /**
             * Remove all numeric types if text query is numeric due to a bug in Elasticsearch
             *
             * @see https://github.com/elastic/elasticsearch/issues/15860
             * @todo: Remove this block when bug is fixed
             */
            $properties = $this->getProperties($store);
            foreach ($properties as $field => $property) {
                if ($property['type'] === 'integer') {
                    $excludedFields[] = $field;
                }
            }
        }

        $fields = parent::getSearchFields($q, $store, $withBoost);

        return array_values(array_diff($fields, $excludedFields));
    }

    /**
     * {@inheritdoc}
     */
    public function validateResult(array $data)
    {
        /**
         * @see \Magento\Catalog\Model\Product\Visibility
         */
        return isset($data[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_PRICES]) && isset($data['visibility']) && $data['visibility'] >= 3; // visible in catalog search
    }
}
