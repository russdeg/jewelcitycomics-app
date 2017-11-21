<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Model;

use Wyomind\Elasticsearch\Helper\Interfaces\QueryInterface as QueryHelperInterface;
use Wyomind\Elasticsearch\Model\Index\TypeInterface;

class QueryBuilder implements QueryBuilderInterface
{

    /**
     * @var QueryHelperInterface
     */
    protected $queryHelper;
    protected $coreHelper;

    /**
     * @param QueryHelperInterface $queryHelper
     */
    public function __construct(
    QueryHelperInterface $queryHelper,
            \Wyomind\Core\Helper\Data $coreHelper = null
    )
    {

        $this->queryHelper = $queryHelper;
        $this->coreHelper = $coreHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function build(
    $q,
            TypeInterface $type,
            $store = null,
            $boolQuery = null
    )
    {

        $queries = [];

        $params = [
            'from' => 0,
            'size' => 10000,
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => &$queries
                    ],
                ],
            ],
        ];

        $queries[]['multi_match'] = [
            'query' => $q,
            'type' => 'cross_fields',
            'fields' => $type->getSearchFields($q, $store, true),
            'lenient' => true, // ignore bad format exception
            'operator' => $this->queryHelper->getQueryOperator($store),
        ];

        if ($this->queryHelper->isFuzzyQueryEnabled($store)) {
            $queries[]['match']['_all'] = [
                'query' => $q,
                'operator' => 'AND',
                'fuzziness' => $this->queryHelper->getFuzzyQueryMode($store),
            ];
        }
        return $params;
    }

    public function buildQuick(
    $q,
            TypeInterface $type,
            $boolQuery,
            $store = null
    )
    {

        $queries = [];
        $filters = [];
        $params = [
            'from' => 0,
            'size' => 10000,
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => &$queries,
                        'must' => &$filters
                    ],
                ],
            ],
        ];

        $queries[]['multi_match'] = [
            'query' => $q,
            'type' => 'cross_fields',
            'fields' => $type->getSearchFields($q, $store, true),
            'lenient' => true, // ignore bad format exception
            'operator' => $this->queryHelper->getQueryOperator($store),
        ];

        $should = $boolQuery->getShould();
        foreach ($should as $key => $info) {
            if ($info instanceof \Magento\Framework\Search\Request\Query\Match) {
                
            } elseif ($info instanceof \Magento\Framework\Search\Request\Query\Filter) {
                $reference = $info->getReference();
                if ($reference instanceof \Magento\Framework\Search\Request\Filter\Term) {
                    $filters [] = ['term' =>
                        [
                            $reference->getField() . "_ids" => $reference->getValue()
                        ]
                    ];
                }
            }
        }

        if ($this->queryHelper->isFuzzyQueryEnabled($store)) {
            $queries[]['match']['_all'] = [
                'query' => $q,
                'operator' => 'AND',
                'fuzziness' => 'AUTO',
            ];
        }
        return $params;
    }

    /**
     * Advanced Search request
     * @param type $boolQuery
     * @param TypeInterface $type
     * @param type $store
     * @return array
     */
    public function buildAdv(
    $boolQuery,
            TypeInterface $type,
            $store = null
    )
    {

        $should = $boolQuery->getShould();

        $queries = [];

        $params = [
            'from' => 0,
            'size' => 10000,
            'body' => [
                'query' => []
            ]
        ];

        foreach ($should as $key => $info) {
            if ($info instanceof \Magento\Framework\Search\Request\Query\Match) {
                $matches = $info->getMatches();
                $match = $matches[0];
                $value = $info->getValue();
                $cond = "match";
                if ($info->getName() == "sku") {
                    $value = strtolower($value);
                    $cond = "must";
                }
                $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                    $cond => [
                        str_replace("_query", "", $info->getName()) => $value
                    ]
                ];
            } elseif ($info instanceof \Magento\Framework\Search\Request\Query\Filter) {
                $reference = $info->getReference();
                if ($reference instanceof \Magento\Framework\Search\Request\Filter\Wildcard || $reference instanceof \Magento\Framework\Search\Request\Filter\Term) {
                    if (is_array($reference->getValue())) {
                        foreach ($reference->getValue() as $value) {
                            $params['body']['query']['constant_score']['filter']['bool']['should'][] = [
                                'term' => [
                                    str_replace("_query", "", $reference->getField() . "_ids") => $value
                                ]
                            ];
                        }
                    } else {
                        $value = $reference->getValue();
                        if ($reference->getField() == "sku") {
                            $value = strtolower($value);
                        }
                        $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                            'term' => [
                                str_replace("_query", "", $reference->getField()) => $value
                            ]
                        ];
                    }
                } else {
                    $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                        'range' => [
                            $reference->getField() => [
                                "from" => $reference->getFrom(),
                                "to" => $reference->getTo()
                            ]
                        ]
                    ];
                }
            }
        }
        return $params;
    }

    /**
     * Category page request
     * @param type $boolQuery
     * @param TypeInterface $type
     * @param type $store
     * @return array
     */
    public function buildCat(
    $boolQuery,
            TypeInterface $type,
            $store = null
    )
    {

        $must = $boolQuery->getMust();
        $should = $boolQuery->getShould();

        $q = $must['category']->getReference()->getValue();

        $queries = [];

        $params = [
            'from' => 0,
            'size' => 10000,
            'body' => [
                'query' => []
            ]
        ];

        $params['body']['query']['constant_score']['filter']['bool']['should'][] = [
            'term' => [
                \Wyomind\Elasticsearch\Helper\Config::PRODUCT_CATEGORIES_ID => $q
            ]
        ];
        $params['body']['query']['constant_score']['filter']['bool']['should'][] = [
            'term' => [
                \Wyomind\Elasticsearch\Helper\Config::PRODUCT_CATEGORIES_PARENT_ID => $q
            ]
        ];


        foreach ($should as $key => $info) {
            $reference = $info->getReference();
            $field = $reference->getField();
            if ($reference instanceof \Magento\Framework\Search\Request\Filter\Range) {
                $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                    'range' => [
                        $field => [
                            "from" => $reference->getFrom(),
                            "to" => $reference->getTo()
                        ]
                    ]
                ];
            } else {
                if ($this->coreHelper != null && $this->coreHelper->moduleIsEnabled("Amasty_Shopby")) { // fix for Amasty_Shopby !
                    $tmp = $reference->getValue();
                    $value = $tmp[0];
                } else {
                    $value = $reference->getValue();
                }
                if (is_array($value)) {
                    $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                        'terms' => [
                            $field . "_ids" => $value
                        ]
                    ];
                } else {
                    $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                        'term' => [
                            $field . "_ids" => $value
                        ]
                    ];
                }
            }
        }

        foreach ($must as $key => $info) {
            if ($info->getReference()->getField() != "category_ids") {
                if (method_exists($info->getReference(), "getFrom")) {
                    $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                        'range' => [
                            $info->getReference()->getField() => [
                                "from" => $info->getReference()->getFrom(),
                                "to" => $info->getReference()->getTo()
                            ]
                        ]
                    ];
                } else {

                    $reference = $info->getReference();
                    $field = $reference->getField();
                    if ($this->coreHelper != null && $this->coreHelper->moduleIsEnabled("Amasty_Shopby")) { // fix for Amasty_Shopby !
                        $tmp = $reference->getValue();
                        $value = $tmp[0];
                    } else {
                        $value = $reference->getValue();
                    }
                    if (is_array($value)) {
                        $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                            'terms' => [
                                $field . ($field != "visibility" ? "_ids" : "") => $value
                            ]
                        ];
                    } else {
                        $params['body']['query']['constant_score']['filter']['bool']['must'][] = [
                            'term' => [
                                $field . ($field != "visibility" ? "_ids" : "") => $value
                            ]
                        ];
                    }
                }
            }
        }

        return $params;
    }

}
