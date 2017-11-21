<?php

/* *
 * Copyright Â© 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\Elasticsearch\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wyomind\Elasticsearch\Helper\Interfaces\AutocompleteInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * $ bin/magento help wyomind:elasticsearch:updateconfig
 * Usage:
 * wyomind:elasticsearch:updateconfig
 *
 * Options:
 * --help (-h)           Display this help message
 * --quiet (-q)          Do not output any message
 * --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 * --version (-V)        Display this application version
 * --ansi                Force ANSI output
 * --no-ansi             Disable ANSI output
 * --no-interaction (-n) Do not ask any interactive question
 */
class UpdateConfig extends Command
{

    protected $_state = null;
    protected $_storeManager = null;
    protected $_autocomplete = null;

    public function __construct(
    StoreManagerInterface $storeManager,
            AutocompleteInterface $autocomplete,
            \Magento\Framework\App\State $state
    )
    {
        $this->_storeManager = $storeManager;
        $this->_autocomplete = $autocomplete;
        $this->_state = $state;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('wyomind:elasticsearch:updateconfig')
                ->setDescription(__('Update the autocomplete config file'))
                ->setDefinition([]);
        parent::configure();
    }

    protected function execute(
    InputInterface $input,
            OutputInterface $output
    )
    {

        $returnValue = \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        try {
            $this->_state->setAreaCode('adminhtml');
            $output->writeln("");
            foreach ($this->_storeManager->getStores() as $store) {
                $this->_autocomplete->saveConfig($store);
                $output->writeln(sprintf(__("Configuration file updated for store '%s' (%s)"),$store['name'], $store['code']));
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln($e->getMessage());
            $returnValue = \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }


        return $returnValue;
    }

}
