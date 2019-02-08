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

use Configuration;
use Context;
use Db;
use DbQuery;
use Dispatcher;
use Language;
use Product;
use Shop;
use Tb2VueStorefrontModule\EntityType as EntityType;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class Indexer
 *
 * @package Tb2vuestorefrontModule
 */
class IndexStatus extends \ObjectModel
{
    /**
     * Indicates whether the dispatcher has been prepared to handle
     * inactive languages
     *
     * @var bool
     */
    protected static $dispatcherPrepared = false;

    /**
     * @var array
     */
    public static $definition = [
        'primary'   => 'id_tb2vuestorefront_index_status',
        'table'     => 'tb2vuestorefront_index_status',
        'fields'    => [
            'id_entity'      => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'index'          => ['type' => self::TYPE_STRING, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_lang'        => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'id_shop'        => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'date_upd'       => ['type' => self::TYPE_DATE,   'validate' => 'isDate',        'required' => true],
        ],
    ];



    /**
     * Get amount of languages for the given shop
     *
     * @param int|null $idShop
     *
     * @return int
     */
    public static function countLanguages($idShop = null)
    {
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }

        try {
            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('COUNT(ls.*)')
                    ->from(bqSQL(Language::$definition['table']).'_shop', 'ls')
                    ->innerJoin(bqSQL(Language::$definition['table']), 'l', 'ls.`id_lang` = l.`id_lang` AND l.`active` = 1')
                    ->where('ls.`id_shop` = '.(int) $idShop)
            );
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Reset index
     *
     * @param int|null $idShop
     *
     * @return bool
     */
    public static function erase($idShop = null)
    {
        try {
            return Db::getInstance()->delete(
                bqSQL(static::$definition['table']),
                $idShop ? '`id_shop` = '.(int) $idShop : ''
            );
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }
    }

    public static function getIndexed( $idLang = null, $idShop = null)
    {
        try {
            $count = 0;
            $entities = EntityType::getAll(true, $idShop);
            foreach ($entities as $entity) {
                $count += $entity['class_name']::getIndexed($idLang, $idShop);

            }
            return $count;
        } catch (\PrestaShopException $e) {

            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * @param string $indexName
     * @param string $idLang
     * @param string $idShop
     * @return int
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getNbErrors($indexName = null, $idLang = null, $idShop = null)
    {
        try {
            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('COUNT(*)')
                    ->from(bqSQL(static::$definition['table']), 'eis')
                    ->where($idLang ? 'eis.`id_lang` = '.(int) $idLang : '')
                    ->where('eis.`error` IS NOT NULL ')
                    ->where($idShop ? 'eis.`id_shop` = '.(int) $idShop : '')
                    ->where($indexName ? 'eis.`index` = '. bqSQL($indesName) : '')
            );
        } catch (\PrestaShopException $e) {

            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    public static function countObjects($idLang = null, $idShop = null)
    {
        try {
            $count = 0;
            $entities = EntityType::getAll(true, $idShop);
            foreach ($entities as $entity) {

                $count += $entity['class_name']::countObjects($idLang, $idShop);

            }
            return $count;
        } catch (\PrestaShopException $e) {

            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }






}
