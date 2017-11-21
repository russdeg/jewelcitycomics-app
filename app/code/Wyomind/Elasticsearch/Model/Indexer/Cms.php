<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model\Indexer;

use Magento\Cms\Model\ResourceModel\Page\Collection as PageCollection;

class Cms extends AbstractIndexer
{

    /**
     * @return PageCollection
     */
    protected function createPageCollection()
    {
        return $this->objectManager->create(PageCollection::class);
    }

    /**
     * @param int $storeId
     * @param array $ids
     * @return \Generator
     */
    public function export(
        $storeId,
        $ids = []
    ) {
        
        $this->eventManager->dispatch('wyomind_elasticsearch_cms_export_before', [ 'store_id' => $storeId, 'ids' => $ids]);
    
        $pages = [];

        $attributesConfig = $this->indexerHelper->getEntitySearchableAttributes('cms', $storeId);
        $collection = $this->createPageCollection()
                ->addStoreFilter($storeId)
                ->addFieldToSelect($attributesConfig);

        if ($excluded = $this->indexerHelper->getExcludedPageIds($storeId)) {
            $collection->addFieldToFilter('page_id', ['nin' => $excluded]);
        }
        $collection->addFieldToFilter('is_active' , \Magento\Cms\Model\Page::STATUS_ENABLED);

        /** @var \Magento\Cms\Model\Page $page */
        foreach ($collection as $page) {
            $page->setContent(html_entity_decode($page->getContent()));
            $pages[$page->getId()] = array_merge(
                [\Wyomind\Elasticsearch\Helper\Config::CMS_ID => $page->getId()],
                $page->toArray($attributesConfig)
            );
        }

        yield $pages;
        
        $this->eventManager->dispatch('wyomind_elasticsearch_cms_export_after', [ 'store_id' => $storeId, 'ids' => $ids]);
    }
}
