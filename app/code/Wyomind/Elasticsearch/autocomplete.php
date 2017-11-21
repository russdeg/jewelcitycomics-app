<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
define('DS', DIRECTORY_SEPARATOR);
define('BP', __DIR__);

require BP . DS . 'vendor' . DS . 'autoload.php';

use Wyomind\Elasticsearch\Autocomplete\Config;
use Wyomind\Elasticsearch\Autocomplete\Search;
use Wyomind\Elasticsearch\Autocomplete\Index\Type;
use Wyomind\Elasticsearch\Model\Client;
use Wyomind\Elasticsearch\Model\QueryBuilder;
use Elasticsearch\ClientBuilder;
use Psr\Log\NullLogger;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

$result = [];
$q = isset($_GET['q']) ? $_GET['q'] : '';
$found = false;

if ('' !== $q) {
    try {
        $store = isset($_GET['store']) ? $_GET['store'] : '';
        $configHandler = new Config\JsonHandler($store);
        $config = new Config($configHandler->load());

        if (!$config->getData()) {
            throw new \Exception('Could not find config for autocomplete');
        }

        $client = new Client(new ClientBuilder, $config, new NullLogger());
        $index = $client->getIndexAlias($store);
        $search = new Search($client);
        $limit = $config->getLimit();

        foreach ($config->getTypes() as $code => $settings) {
            try {
                if ($settings['enable'] == 1) {
                    $type = new Type($code, $settings);
                    $params = (new QueryBuilder($config))->build($q, $type);
                    $docs = $search->query($index, $type, $params);
                    $result[$code] = [
                        'count' => count($docs),
                        'docs' => array_slice($docs, 0, $limit),
                    ];
                }
            } catch (\Exception $e) {
                // Ignore results of current type if an exception is thrown when searching
                $result[$code] = [];
            }
        }

        $found = true;
    } catch (\Exception $e) {
        echo $e->getMessage();
    }
}

header('Fast-Autocomplete: ' . ($found ? 'HIT' : 'MISS'));

echo json_encode($result);
