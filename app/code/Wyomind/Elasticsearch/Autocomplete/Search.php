<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Autocomplete;

use Wyomind\Elasticsearch\Model\ClientInterface;
use Wyomind\Elasticsearch\Model\Index\TypeInterface;
use Magento\Framework\DataObject;

class Search
{

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $index
     * @param TypeInterface $type
     * @param array $params
     * @return array
     */
    public function query(
        $index,
        TypeInterface $type,
        array $params = []
    ) {
    



        $response = $this->client->query($index, $type->getCode(), $params);
        $docs = [];
        foreach ($response['hits']['hits'] as $doc) {
            $data = $doc['_source'];
            $ids[] = $doc['_id'];
            $docs[$doc['_id']] = $doc['_score'];

            if (isset($data[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_PARENT_IDS])) {
                foreach ($data[\Wyomind\Elasticsearch\Helper\Config::PRODUCT_PARENT_IDS] as $parentId) {
                    if (isset($docs[$parentId])) {
                        $doc['_score'] = max($doc['_score'], $docs[$parentId]);
                    }
                    $docs[$parentId] = $doc['_score'];
                }
            }
        }

        $result = [];

        if (!empty($docs)) {
            // Sort results by relevance
            if (!$this->client->isProductWeightEnabled()) {
                arsort($docs);
            }

            $response = $this->client->getByIds($index, $type->getCode(), array_keys($docs));

            foreach ($response['docs'] as $data) {
                if (isset($data['_source']) && $type->validateResult($data['_source'])) {
                    $result[] = $data['_source'];
                }
            }
        }

        return $result;
    }
}
