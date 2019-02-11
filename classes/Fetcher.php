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

use Collection;
use Elasticsearch\Endpoints\Index;
use Tb2VueStorefrontModule\Meta;
use Logger;
use PrestaShopException;
use stdClass;
use ObjectModel;
use Configuration;
use Db;
use DbQuery;
use Language;
use Dispatcher;
use Context;
use Shop;


if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class Fetcher
 *
 * @package Tb2vuestorefrontModule
 */
abstract class Fetcher
{

    public static $className;
    public static $indexName;
    public static $class = __CLASS__;
    public static $dispatcherPrepared = false;


    /**
     * Properties array
     *
     * Defaults:
     * - function: null
     * - default: 'text'
     * - elastic_types: all
     * - visible: true
     *
     * @var array $attributes
     */
    public static $attributes = [];



    /**
     * This function generates elasticsearch mapping for collection
     * TODO: get elastic_types if property has user selectable type
     *
     * @return array
     */
    public static function generateMappings(int $idLang,int $idShop)
    {
        $properties=[];
        $properties['id']['type'] = Meta::ELASTIC_TYPE_INTEGER;
        foreach (static::$attributes as $propName => $propItems) {
            if (isset($propItems['type'])) {
                $properties[$propName] = [
                    'type' => $propItems['type'],
                ];
            }
            if ($propItems['type'] === 'date') {
                $properties[$propName]['format'] = 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis';
            }
            if (isset($propItems['children']))
            {
                foreach ($propItems['children'] as $childrenName => $childrenItems) {
                    $properties[$propName]['properties'][$childrenName]['type'] = $childrenItems['type'];
                    if ($properties[$propName]['properties'][$childrenName]['type'] === 'date') {
                        $properties[$propName]['properties'][$childrenName]['format'] = 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis';
                    }
                }
            }
        }
        
        return [
                    '_source'    => [
                        'enabled' => true,
                    ],
                    'properties' => $properties,
                ];
    }


    /**
     * When fetching an object for Elasticsearch indexing, it will call the functions as defined in the
     * `$attributes` array. If the value `null` is used, it will grab the property directly from the object.
     * @param int $idEntity
     * @param int $idLang
     * @param int $idShop
     * @return bool|stdClass
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    public static function initObject(int $idEntity, int $idLang, int $idShop)
    {

        $className = static::$className;
        if (!class_exists($className)) {
            Logger::addLog("Elasticsearch module error: Missing class {$className}");
            return false;
        }
        $elasticObject = new stdClass();
        $elasticObject->id = (int) $idEntity;
        $elasticObject->elastic_id_lang = $idLang;
        $elasticObject->elastic_id_shop = $idShop;
        $elasticObject->elastic_type = static::$indexName;
        $object = new $className($idEntity, $idLang, $idShop);
        if (!\Validate::isLoadedObject($object)) {
            return $elasticObject;
        }

        // Default properties
        foreach (static::$attributes as $propName => $propItems) {

            // If the property is a function, call it
            if ($propItems['function'] != null) {
                $elasticObject->{$propName} = $propItems['function'][0]::{$propItems['function'][1]}($object, $idLang, $idShop);

                continue;
            }
            // If constant, get value from attributes definition
            if (isset($propItems['const'])) {
                $elasticObject->{$propName} = $propItems['const'];

                continue;
            }
            // Otherwise grab the property directly from the object.
            $elasticObject->{$propName} = $object->{$propName};

        }

        // If the object does not have the date_upd property, use the current date.
        if (!isset($elasticObject->updated_at))
        {
            $elasticObject->updated_at = date('Y-m-d H:i:s');
        }

        return $elasticObject;
    }

    /**
     * @param EntityType|null $index
     * @param null $idLang
     * @param null $idShop
     * @return int
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getIndexed( $idLang = null, $idShop = null)
    {
        try {
            if (!isset(static::$className) || !class_exists(static::$className)){
                return 0;
            }
            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('COUNT(*)')
                    ->from(bqSQL(IndexStatus::$definition['table']), 'eis')
                    ->innerJoin(bqSQL((static::$className)::$definition['table']), 'o', 'o.`'.(static::$className)::$definition['primary'].'` = eis.`id_entity`')
                    ->where($idLang ? 'eis.`id_lang` = '.(int) $idLang : '')
                    ->where('eis.`error` is NULL ') // If there is an error, assume that it is not indexed
                    ->where($idShop ? 'eis.`id_shop` = '.(int) $idShop : '')
                    ->where('eis.`index` = "'.bqSQL(static::$indexName).'"')
                    ->where(isset((static::$className)::$definition['fields']['date_upd']) ? 'eis.`date_upd` = o.`date_upd`'  : '') // If the object does not have a date_upd field, assume it has not changed
            );
        } catch (\PrestaShopException $e) {

            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param null $idLang
     * @param null $idShop
     * @return array
     * @throws PrestaShopException
     */
    public static function getObjectsToIndex($limit = 0, $offset = 0, $idLang = null, $idShop = null, $onlyActive = true)
    {
        $primary = (static::$className)::$definition['primary'];

        try {
            $isMultilang = !empty(static::$className::$definition['multilang']);
            $isMultilangShop = !empty(static::$className::$definition['multilang_shop']);
            $query = new DbQuery();
            $query->select('o.`'.$primary.'`, l.`id_lang`, s.`id_shop`')
                    ->from(bqSQL(static::$className::$definition['table']), 'o')
                    // Join with index status and get only updated objects
                    ->join(static::addSqlAssociation(static::$className::$definition['table'], 'o', $idShop))
                    ->leftJoin(
                        bqSQL(IndexStatus::$definition['table']),
                        'eis',
                        'o.`'. $primary .'` = eis.`id_entity` AND eis.`index` ="'.bqSQL(static::$indexName).'"'
                    )->where(isset((static::$className)::$definition['fields']['date_upd']) ? 'eis.`error` IS NULL AND (eis.`date_upd`  IS NULL  OR eis.`date_upd` != o.date_upd)'  : 'eis.`date_upd`  IS NULL AND eis.`error` IS NULL' )
                    ->where($onlyActive ? static::addActiveSqlQuery() : '')
                    ->limit($limit, $offset);
            // If multilang, create association to lang table
            if ($isMultilang) {
                $query->leftJoin(
                    static::$className::$definition['table'].'_lang',
                    'l',
                    'o.`'.$primary.'` = l.`'.$primary.'`'.($idLang ? ' AND l.`id_lang` = '.(int) $idLang : '').' '.($idShop && $isMultilangShop ? ' AND l.`id_shop` = '.(int) $idShop : '')
                );
                if ($idLang) {
                    $query->where('l.id_lang', '=', (int)$idLang);
                }
            } else { // if is not multilang inner join with lang table on all active languages
                $query->join('INNER JOIN `'._DB_PREFIX_.'lang` l ON l.`active` = 1'.($idLang ? ' AND l.`id_lang` = '.(int) $idLang : '')); 
            }
            $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            $results = false;
        }

        $elasticObjects = [];
        foreach ($results as &$result) {
            $elasticObjects[] = static::initObject((int)$result[$primary], (int)$result['id_lang'], (int)$result['id_shop']);
        }
        return $elasticObjects;
    }

    public static function addSqlAssociation($table, $alias, $idShop = null)
    {
        $tableAlias = 's';
        if (strpos($table, '.') !== false) {
            list($tableAlias, $table) = explode('.', $table);
        }

        $assoTable = Shop::getAssoTable($table);
        if ($assoTable === false || $assoTable['type'] != 'shop') {
            return '';
        }
        $sql = 'INNER JOIN '._DB_PREFIX_.$table.'_shop '.$tableAlias.'
        ON ('.$tableAlias.'.id_'.$table.' = '.$alias.'.id_'.$table;

        if($idShop) {
            $sql .= ' AND '.$tableAlias.'.id_shop = '.(int)$idShop;
        }

        $sql .= ')';

        return $sql;
    }

    public static function addActiveSqlQuery()
    {
        if (isset(static::$className::$definition['fields']['active'])) {
            if (isset(static::$className::$definition['fields']['active']['shop'])) {
                return 's.`active` = 1';
            } else {
                return 'o.`active` = 1';
            }
        }
        return '';
    }

    /**
     * Get amount of products for the given shop and lang
     *
     * @param int|null $idLang
     * @param int|null $idShop
     *
     * @return int
     */
    public static function countObjects($idLang = null, $idShop = null, $onlyActive = true)
    {
        try {
            if (!isset(static::$className) || !class_exists(static::$className)){
                return 0;
            }
            $table = (static::$className)::$definition['table'];
            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('COUNT(*)')
                    ->from($table, 'o')
                    ->join(static::addSqlAssociation($table, 'o', $idShop))
                    ->where($onlyActive ? static::addActiveSqlQuery() : '')
            );

        } catch (\PrestaShopException $e) {

            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    protected static function getCreatedAt(ObjectModel $object)
    {
        return $object->date_add;
    }

    /**
     * @param ObjectModel $object
     * @return mixed
     */
    protected static function getUpdatedAt(ObjectModel $object)
    {
        return $object->date_upd;
    }

    /**
     * @param ObjectModel $object
     * @return bool
     */
    protected static function getIsActive(ObjectModel $object)
    {
        return (bool)$object->active;
    }

    /**
     * @param ObjectModel $object
     * @return mixed
     */
    protected static function getLinkRewrite(ObjectModel $object, $idlang)
    {
        return $object->link_rewrite;
    }

    /**
     * @param  $feature
     * @return string
     */
    public static function getName($feature)
    {
        return $elasticObject->{$propName} = $object->{$propName};
    }

    /**
     * @param ObjectModel $object
     * @return array
     */
    protected static function getMeta(ObjectModel $object)
    {
        return [
            'title'        => $object->meta_title,
            'description'  => $object->meta_description,
            'keywords'     => $object->meta_keywords,
        ];
    }


}
