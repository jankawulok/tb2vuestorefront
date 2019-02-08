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
use Elasticsearch\Endpoints\Cat\Indices;
use Language;
use Product;
use Shop;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class EntityType
 *
 * @package Tb2vuestorefrontModule
 */
class EntityType extends \ObjectModel
{
    public $id;
    public $class_name;
    public $entity_name;
    public $enabled;
    public $id_shop;


    /**
     * @var array
     */
    public static $definition = [
        'primary'   => 'id_tb2vuestorefront_entity_type',
        'table'     => 'tb2vuestorefront_entity_type',
        'fields'    => [
            'class_name'                => ['type' => self::TYPE_STRING, 'required' => true],
            'entity_name'                => ['type' => self::TYPE_STRING, 'required' => true],
            'enabled'                   => ['type' => self::TYPE_BOOL,   'required' => false],
            'id_shop'                   => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
        ],
    ];

    public static function getAll($onlyActive = false, $idShop = null)
    {
        try {
            return (array) Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('*')
                    ->from(bqSQL(static::$definition['table']), 'i')
                    ->where($idShop != null? '`id_shop` = '. (int)$idShop : '')
                    ->where($onlyActive != false ? '`enabled` = 1' : '')
            );
        } catch (\PrestaShopException $e) {
            return  [];
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

    public static function addEntityType($className, $entityName, $idShop, $enabled = true)
    {
        if (static::hasEntityType($entityName)) {
            \Logger::addLog("Elasticsearch module error: Index {$entityName} already exist!");
            return false;
        }

        try {
            $entityType = new EntityType();
            $entityType->entity_name = $entityName;
            $entityType->class_name = $className;
            $entityType->id_shop = $idShop;
            $entityType->enabled = $enabled;
            $entityType->add();
            return isset($entityType->id) ? true : false;
        } catch (\Exception $e)
        {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }

    }

    /**
     * @param string $className
     * @param int $idShop
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function deleteEntityType($className, $idShop)
    {
        if ($className == '') {
            return false;
        }
        try {
            return Db::getInstance()->delete(
                bqSQL(static::$definition['table']),
                '`class_name` = '.bqSQL($className). ''.
                $idShop != null? '` and id_shop` = '. (int)$idShop : ''
            );
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }

    }


    /**
     * @param string $entityName
     * @param bool $onlyActive
     * @param null $idShop
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function hasEntityType($entityName, $onlyActive = false, $idShop = null)
    {
        try {
            $index = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
                (new DbQuery())
                    ->select(static::$definition['primary'])
                    ->from(static::$definition['table'])
                    ->where('`entity_name` = '.pSQL($entityName))
                    ->where($idShop != null? '`id_shop` = '. (int)$idShop : '')
                    ->where($onlyActive != false ? '`active` = 1' : '')
            );
            return count($index) ? true : false;
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }
    }


    /**
     * @param string $className
     * @param bool $onlyActive only active
     * @param null $idShop id shop
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function hasClass($className, $onlyActive = false, $idShop = null)
    {
        try {
            $index = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
                (new DbQuery())
                    ->select(static::$definition['primary'])
                    ->from(static::$definition['table'])
                    ->where('`class_name` = \''.pSQL($className).'\'')
                    ->where($idShop != null? '`id_shop` = '. (int)$idShop : '')
                    ->where($onlyActive != false ? '`active` = 1' : '')
            );
            return count($index) ? true : false;
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * @param bool $enabled
     * @param null $idShop id shop
     * @return array EntityType|false|null|\PDOStatement
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getEntityTypes($enabled = false, $idShop = null)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('*')
                ->from(static::$definition['table'])
                ->where($enabled ? '`enabled` = 1' : '')
                ->where($idShop ? '`id_shop` = '. (int)$idShop : '')
        );
    }

    public static function getClassByEntityName($entityName, $idShop)
    {
        try {
            return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('i.class_name')
                    ->from(bqSQL(static::$definition['table']), 'i')
                    ->where($idShop != null? '`id_shop` = '. (int)$idShop : '')
                    ->where(static::$definition['entity_name'].'='.pSQL($entityName))
                    ->limit(1)
            );
        } catch (\PrestaShopException $e) {
            return  false;
        }
    }

}
