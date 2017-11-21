<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model;

use Wyomind\Elasticsearch\Model\Index\TypeInterface;
use Magento\Framework\Search\Adapter\Mysql\DocumentFactory;
use Magento\Framework\Search\Document;

class DocumentsBuilder implements DocumentsBuilderInterface
{

    /**
     * Document Factory
     *
     * @var DocumentFactory
     */
    protected $documentFactory;
    protected $magentoVersion = 0;

    /**
     * @param DocumentFactory $documentFactory
     */
    public function __construct(
        DocumentFactory $documentFactory,
        \Wyomind\Core\Helper\Data $coreHelper
    ) {
        $this->documentFactory = $documentFactory;
        $this->magentoVersion = $coreHelper->getMagentoVersion();
    }

    /**
     * {@inheritdoc}
     */
    public function build(
        array $response,
        TypeInterface $type
    ) {
        $documents = [];
        foreach ($response['hits']['hits'] as $doc) {
            $data = $doc['_source'];
            if ($type->validateResult($data)) {
                $documents[$doc['_id']] = $this->createDocument($doc['_id'], $doc['_score']);
            }

            if (isset($data[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_PARENT_IDS])) {
                foreach ($data[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_PARENT_IDS] as $parentId) {
                    if (isset($documents[$parentId])) {
                        if (version_compare($this->magentoVersion, "2.1.0") == -1) { // Mage version < 2.1.0
                            $score = $documents[$parentId]->getField('score')->getValue();
                        } else {
                            $score = $documents[$parentId]->getCustomAttribute('score')->getValue();
                        }
                        
                        $doc['_score'] = max($doc['_score'], $score);
                    }
                    $documents[$parentId] = $this->createDocument($parentId, $doc['_score']);
                }
            }
        }

        return $documents;
    }

    /**
     * @param int $id
     * @param float $score
     * @return Document
     */
    protected function createDocument(
        $id,
        $score
    ) {
        if (version_compare($this->magentoVersion, "2.1.0") == -1) { // Mage version < 2.1.0
            return $this->documentFactory->create([
                ['name' => 'entity_id', 'value' => (int) $id],
                ['name' => 'score', 'value' => $score],
            ]);
        } else {
            return $this->documentFactory->create([
                'entity_id' => (int) $id,
                'score' => $score,
            ]);
        }
    }
}
