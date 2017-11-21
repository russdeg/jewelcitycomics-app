<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model\Index;

class MappingBuilder implements MappingBuilderInterface
{
    /**
     * @var TypeInterface[]
     */
    protected $typePool = [];

    /**
     * @param TypeFactory $typeFactory
     * @param array $types
     */
    public function __construct(
        TypeFactory $typeFactory,
        array $types = []
    ) {
        foreach ($types as $code => $typeClass) {
            $this->typePool[$code] = $typeFactory->create($typeClass, $code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build($storeId)
    {
        $mapping = [];
        foreach ($this->typePool as $type) {
            $mapping[$type->getCode()] = [
                '_all' => [
                    'analyzer' => $type->getLanguageAnalyzer($storeId),
                ],
                'properties' => $type->getProperties($storeId),
            ];
        }

        return $mapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getType($code)
    {
        return isset($this->typePool[$code]) ? $this->typePool[$code] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes()
    {
        return $this->typePool;
    }
}
