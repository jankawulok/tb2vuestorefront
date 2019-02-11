<?php
/**
 * Copyright (C) 2017-2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Tb2VueStorefrontModule;

use Tb2vuestorefront;
use Configuration;
use Context;
use Db;
use Elasticsearch;
use Elasticsearch\Client;
use Exception;
use ReflectionClass;
use Tools;
use Fetcher;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Trait ModuleAjaxTrait
 *
 * @package Tb2vuestorefrontModule
 */
trait ModuleAjaxTrait
{
    // BACK OFFICE
    /**
     * Ajax process save module settings
     */
    public function ajaxProcessSaveSettings()
    {
        header('Content-Type: application/json; charset=utf-8');
        $settings = json_decode(file_get_contents('php://input'), true);

        // Figure out which setting keys are available (constants from the main class)
        /** @var ReflectionClass $reflect */
        $reflect = new ReflectionClass($this);
        $consts = $reflect->getConstants();
        foreach ($settings as $setting => $value) {
            if (in_array($setting, $consts)) {
                if ($setting === static::METAS) {
                    Meta::saveMetas($value);
                    continue;
                } elseif ($setting == static::STOP_WORDS) {
                    try {
                        Configuration::updateValue($setting, $value);
                    } catch (\PrestaShopException $e) {
                        \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
                    }

                    continue;
                } elseif ($setting == static::SERVERS) {
                    if ($settings[static::PROXY]) {
                        foreach ($value as &$server) {
                            $server['read'] = 1;
                            $server['write'] = 1;
                        }
                    }
                    $value = json_encode($value);
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }

                try {
                    Configuration::updateValue($setting, $value);
                } catch (\PrestaShopException $e) {
                    \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
                }
            }
        }

        try {
            Configuration::updateValue(Tb2vuestorefront::CONFIG_UPDATED, true);
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        // Response status
        die(json_encode([
            'success' => true,
            'indexed' => 0,
            'total'   => (int) IndexStatus::countObjects(null, $this->context->shop->id),
        ]));
    }

    /**
     * Index remaining objects
     *
     * @throws \PrestaShopException
     */
    public function ajaxProcessIndexRemaining()
    {
        header('Content-Type: application/json; charset=utf-8');
        /** @var Client $client */
        $client = static::getWriteClient();
        if (!$client) {
            die(json_encode([
                'success' => false,
            ]));
        }
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $amount = (int) (isset($input['amount'])
                ? (int) $input['amount']
                : Configuration::get(static::INDEX_CHUNK_SIZE));
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            $amount = false;
        }
        if (!$amount) {
            $amount = 100;
        }
        try {
            $index = Configuration::get(Tb2vuestorefront::INDEX_PREFIX);
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return;
        }
        $idShop = Context::getContext()->shop->id;
        $indexVersion = (int)Configuration::get(Tb2vuestorefront::INDEX_VERSION);
        $dateUpdAlias = Tb2vuestorefront::getAlias('date_upd');


        // Check which products are available for indexing
        foreach (EntityType::getEntityTypes(true, $idShop) as $entityType)
        {
            $objects = $entityType['class_name']::getObjectsToIndex($amount, 0, null, $idShop);
            if (empty($objects)) {
                continue;
            } else {
                break;
            }
        }
        if (empty($objects)) {
            // Nothing to index
            die(json_encode([
                'success'  => true,
                'indexed'  => IndexStatus::getIndexed(null, $this->context->shop->id),
                'total'    => (int) IndexStatus::countObjects(null, $this->context->shop->id),
                'nbErrors' => IndexStatus::getNbErrors(null, null, $this->context->shop->id),
                'errors'   => [],
            ]));
        }
        $response = Indexer::bulkIndex($objects);
        die(json_encode([
            'success'  => $response['success'],
            'indexed'  => IndexStatus::getIndexed(null, $this->context->shop->id),
            'total'    => (int) IndexStatus::countObjects(null, $this->context->shop->id),
            'nbErrors' => IndexStatus::getNbErrors(null, null, $this->context->shop->id),
            'errors'   => $response['errors'],
        ]));

    }

    /**
     * Ajax process delete unused indices
     */
    public function ajaxProcessEraseIndex()
    {

    }

    /**
     * Ajax process create index
     */
    public function ajaxProcessCreateIndex()
    {
        header('Content-Type: application/json; charset=utf-8');
        // TODO: check if context is multishop
        $idShop = Context::getContext()->shop->id;
        $oldIndexVersion = (int)Configuration::get(Tb2vuestorefront::INDEX_VERSION);
        Configuration::updateValue(Tb2vuestorefront::INDEX_VERSION, ++$oldIndexVersion);
        try {
            // Reset the mappings
            Indexer::createMappings(null, [$idShop]);

            // Erase the index status for the current store
            IndexStatus::erase($idShop);
        } catch (Exception $e) {
        }

        try {
            Configuration::updateValue(Tb2vuestorefront::CONFIG_UPDATED, false);
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        // Response status
        die(json_encode([
            'success' => true,
            'indexed' => IndexStatus::getIndexed(null, $idShop),
            'total'   => (int) IndexStatus::countObjects(null, $idShop),
        ]));
    }

    /**
     * Ajax process publish index
     */
    public function ajaxProcessPublishIndex()
    {
        header('Content-Type: application/json; charset=utf-8');
        $idShop = Context::getContext()->shop->id;
        $idLang = Context::getContext()->language->id;
        try {
            // create index alias
            Indexer::publishIndex([$idLang], [$idShop]);
        } catch (Exception $e) {
        }

        try {
            Configuration::updateValue(Tb2vuestorefront::CONFIG_UPDATED, false);
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        // Response status
        die(json_encode([
            'success' => true,
            'indexed' => IndexStatus::getIndexed(null, $idShop),
            'total'   => (int) IndexStatus::countObjects(null, $idShop),
        ]));
    }


    /**
     * @return void
     */
    public function ajaxProcessGetElasticsearchVersion()
    {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode([
            'version' => $this->getElasticVersion(),
            'errors'  => $this->context->controller->errors,
        ]));
    }
}
