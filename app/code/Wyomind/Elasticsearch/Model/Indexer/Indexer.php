<?php

/* *
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model\Indexer;

$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
$config = $objectManager->get("\Wyomind\Elasticsearch\Helper\Config");



if ($config->getEngine() == "elasticsearch") {


    class Indexer extends \Wyomind\Elasticsearch\Model\Indexer\Product
    {
        
    }

} else {

    class Indexer extends \Magento\CatalogSearch\Model\Indexer\Fulltext
    {
        
    }

}
