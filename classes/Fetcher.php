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


if (!defined('_TB_VERSION_')) {
    return;
}

/**
 * Class Fetcher
 *
 * When fetching an object for Elasticsearch indexing, it will call the functions as defined in the
 * `$attributes` array. If the value `null` is used, it will grab the property directly from the object.
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
     * This function generates mapping for collection
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
            if ($propItems['function'] != null){

            }
            if ($propItems['function'] != null) {



                $elasticObject->{$propName} = $propItems['function'][0]::{$propItems['function'][1]}($object, $idLang, $idShop);

                continue;
            }
            if (isset($propItems['const'])) {
                $elasticObject->{$propName} = $propItems['const'];

                continue;
            }
            if (isset($className::$definition['fields'][$propName]['lang']) == true) {
                $elasticObject->{$propName} = $object->{$propName};
            } else {
                $elasticObject->{$propName} = $object->{$propName};
            }

        }

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
                    ->where('eis.`error` is NULL ')
                    ->where($idShop ? 'eis.`id_shop` = '.(int) $idShop : '')
                    ->where('eis.`index` = "'.bqSQL(static::$indexName).'"')
                    ->where(isset((static::$className)::$definition['fields']['date_upd']) ? 'eis.`date_upd` = o.`date_upd`'  : '')
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
    public static function getObjectsToIndex($limit = 0, $offset = 0, $idLang = null, $idShop = null)
    {
        // We have to prepare the back office dispatcher, otherwise it will not generate friendly URLs for languages
        // other than the current language
        static::prepareDispatcher();

        $primary = (static::$className)::$definition['primary'];

        try {
            $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('o.`'.$primary.'`')
                    ->from(bqSQL(static::$className::$definition['table']), 'o')
                    ->leftJoin(
                        bqSQL(IndexStatus::$definition['table']),
                        'eis',
                        'o.`'. $primary .'` = eis.`id_entity` AND eis.`index` ="'.bqSQL(static::$indexName).'"'
                    )
                    ->where(isset((static::$className)::$definition['fields']['date_upd']) ? 'eis.`error` IS NULL AND (eis.`date_upd`  IS NULL  OR eis.`date_upd` != o.date_upd)'  : 'eis.`date_upd`  IS NULL AND eis.`error` IS NULL' )
                    ->limit($limit, $offset)
            );

        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            $results = false;
        }
        $elasticObjects = [];
        foreach ($results as &$result) {
            $elasticObjects[] = static::initObject($result[$primary], $idLang, $idShop);
        }
        return $elasticObjects;
    }

    /**
     * Get amount of products for the given shop and lang
     *
     * @param int|null $idLang
     * @param int|null $idShop
     *
     * @return int
     */
    public static function countObjects($idLang = null, $idShop = null)
    {
        if (!$idShop) {
            $idShop = \Shop::getContextShopID();
        }

        try {
            return (new Collection(static::$className, $idLang))
                ->count();
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
    protected static function getLinkRewrite(ObjectModel $object)
    {
        return $object->link_rewrite;
    }

    /**
     * @param  $feature
     * @return string
     */
    public static function getName($feature)
    {

        return $feature->name;
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

    /**
     * By default the dispatcher does not load the default routes for languages that have been deactivated.
     * This is a problem, because we also want to index languages that are not currently active.
     * By inserting the routes from inactive languages we can still generate friendly URLs for inactive languages.
     *
     * @return void
     */
    protected static function prepareDispatcher()
    {
        if (static::$dispatcherPrepared) {
            return;
        }

        // Set new routes
        $prodroutes = 'PS_ROUTE_product_rule';
        $catroutes = 'PS_ROUTE_category_rule';
        $supproutes = 'PS_ROUTE_supplier_rule';
        $manuroutes = 'PS_ROUTE_manufacturer_rule';
        $layeredroutes = 'PS_ROUTE_layered_rule';
        $cmsroutes = 'PS_ROUTE_cms_rule';
        $cmscatroutes = 'PS_ROUTE_cms_category_rule';
        $moduleroutes = 'PS_ROUTE_module';
        try {
            foreach (Language::getLanguages(true) as $lang) {
                foreach (Dispatcher::getInstance()->default_routes as $id => $route) {
                    switch ($id) {
                        case 'product_rule':
                            $rule = Configuration::get($prodroutes, (int) $lang['id_lang']);
                            break;
                        case 'category_rule':
                            $rule = Configuration::get($catroutes, (int) $lang['id_lang']);
                            break;
                        case 'supplier_rule':
                            $rule = Configuration::get($supproutes, (int) $lang['id_lang']);
                            break;
                        case 'manufacturer_rule':
                            $rule = Configuration::get($manuroutes, (int) $lang['id_lang']);
                            break;
                        case 'layered_rule':
                            $rule = Configuration::get($layeredroutes, (int) $lang['id_lang']);
                            break;
                        case 'cms_rule':
                            $rule = Configuration::get($cmsroutes, (int) $lang['id_lang']);
                            break;
                        case 'cms_category_rule':
                            $rule = Configuration::get($cmscatroutes, (int) $lang['id_lang']);
                            break;
                        case 'module':
                            $rule = Configuration::get($moduleroutes, (int) $lang['id_lang']);
                            break;
                        default:
                            $rule = $route['rule'];
                            break;
                    }

                    Dispatcher::getInstance()->addRoute(
                        $id,
                        $rule,
                        $route['controller'],
                        $lang['id_lang'],
                        $route['keywords'],
                        isset($route['params']) ? $route['params'] : [],
                        Context::getContext()->shop->id
                    );
                }
            }
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        static::$dispatcherPrepared = true;
    }


}
