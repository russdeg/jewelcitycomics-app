<?php

/* *
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Ui\Component;

class Indices implements \Magento\Framework\Data\OptionSourceInterface
{

    protected $_escaper = null;
    protected $_config = null;
    protected $_dataHelper = null;

    /**
     * @var array
     */
    protected $options;

    public function __construct(
        \Magento\Framework\Escaper $escaper,
        \Wyomind\Elasticsearch\Helper\Config $config,
        \Wyomind\Elasticsearch\Helper\Data $dataHelper
    ) {
    
        $this->_escaper = $escaper;
        $this->_config = $config;
        $this->_dataHelper = $dataHelper;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $stores = $this->generateCurrentOptions();

        $this->options = array_values($stores);

        return $this->options;
    }

    /**
     * Generate current options
     *
     * @return void
     */
    protected function generateCurrentOptions()
    {

        $stores = [];
        $storeCollection = $this->_dataHelper->getAllStoreviews();

        foreach ($storeCollection as $store) {
            $configHandler = new \Wyomind\Elasticsearch\Autocomplete\Config\JsonHandler($store->getCode());
            $config = new \Wyomind\Elasticsearch\Autocomplete\Config($configHandler->load());
            $client = new \Wyomind\Elasticsearch\Model\Client(new \Elasticsearch\ClientBuilder, $config, new \Psr\Log\NullLogger());
            if ($config->getData() && $client->existsIndex($this->_config->getIndexPrefix($store).$store->getCode())) {
                $name = $this->_escaper->escapeHtml($store->getName());
                $stores[$name]['label'] = $this->_config->getIndexPrefix($store) . $store->getCode() . " [ " . $name . " ]";
                $stores[$name]['value'] = $store->getId();
            }
        }
        return $stores;
    }
}
