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
use DbQuery;
use Db;
use Logger;
use PrestaShopException;
use Category;
use Shop;
use stdClass;

if (!defined('_TB_VERSION_')) {
    return;
}

/**
 * Class Fetcher
 *
 * When fetching a product for Elasticsearch indexing, it will call the functions as defined in the
 * `$attributes` array. If the value `null` is used, it will grab the property directly from the
 * thirty bees Category object.
 *
 * @package Tb2vuestorefrontModule
 */
class CategoryFetcher extends Fetcher
{

    public static $className = 'Category';
    public static $indexName = 'category';
    // Cached category paths
    static $cachedParentCategories = [];
    // Avoid these categories (root and home)
    static $avoidCategories = null;

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
    public static $attributes = [
        'id'                => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'parent_id'         => [
            'function'      => [__CLASS__, 'getParentId'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'name'              => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'position'          => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'level'             => [
            'function'      => [__CLASS__, 'getLevelDepth'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'product_count'     => [
            'function'      => [__CLASS__, 'getProductCount'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'created_at'        => [
            'function'      => [__CLASS__, 'getCreatedAt'],
            'type'       => Meta::ELASTIC_TYPE_DATE,
        ],
        'updated_at'        => [
            'function'      => [__CLASS__, 'getUpdatedAt'],
            'type'       => Meta::ELASTIC_TYPE_DATE,
        ],
        'path'              => [
            'function'      => [__CLASS__, 'getPath'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'include_in_menu'   => [
            'function'      => null,
            'const'         => '1',
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_active'         => [
            'function'      => [__CLASS__, 'getIsActive'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'url_key'           => [
            'function'      => [__CLASS__, 'getLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'request_path'      => [
            'function'      => [__CLASS__, 'getLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'meta'           => [
            'function'      => [__CLASS__, 'getMeta'],
        ],

    ];

    public static function initObject(int $idEntity, $idLang, $idShop)
    {
        $elasticObject = parent::initObject($idEntity, $idLang, $idShop);
        static::getChildrenData($elasticObject, $idLang, $idShop);
        return $elasticObject;
    }



    /**
     * @param stdClass $elasticObject
     * @param int $idLang
     * @param int $idShop
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getChildrenData(StdClass &$elasticObject, ?int $idLang, ?int $idShop)
    {
        $childCats = Category::getChildren($elasticObject->id, $idLang, true);
        $elasticObject->children=[];
        $elasticObject->children_data=[];
        foreach ($childCats as $childCategory) {
            $elasticObject->children[] = $childCategory['id_category'];
            $elasticObject->children_data[]= static::initObject($childCategory['id_category'], $idLang, $idShop);
        }
        $elasticObject->children = implode(',', $elasticObject->children);
        $elasticObject->children_count= count($elasticObject->children_data);
    }

    /**
     * Get category path
     *
     * @param int $idCategory
     * @param int $idLang
     *
     * @return string
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    public static function getCategoryPath(int $idCategory, int $idLang)
    {
        if (!static::$avoidCategories) {
            static::$avoidCategories = [
                Configuration::get('PS_HOME_CATEGORY'),
                Configuration::get('PS_ROOT_CATEGORY'),
            ];
        }

        try {
            $interval = Category::getInterval($idCategory);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            $interval = false;
        }

        if ($interval) {
            try {
                $categories = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                    (new DbQuery())
                        ->select('c.*, cl.*')
                        ->from('category', 'c')
                        ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category`')
                        ->join(Shop::addSqlRestrictionOnLang('cl'))
                        ->join(Shop::addSqlAssociation('category', 'c'))
                        ->where('c.`nleft` <= '.(int) $interval['nleft'])
                        ->where('c.`nright` >= '.(int) $interval['nright'])
                        ->where('cl.`id_lang` = '.(int) $idLang)
                        ->where('c.`active` = 1')
                        ->where('c.`id_category` NOT IN ('.implode(',', array_map('intval', static::$avoidCategories)).')')
                        ->orderBy('c.`level_depth` ASC')
                );

                return implode(' /// ', array_column((array) $categories, 'name'));
            } catch (PrestaShopException $e) {
                Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            }

        }

        return '';
    }


    /**
     * @param Category $category
     * @return int
     */
    protected static function getParentId(Category $category)
    {
        return (int)$category->id_parent;
    }

    /**
     * @param Category $category
     * @return int
     */
    protected static function getLevelDepth(Category $category)
    {
        return (int)$category->level_depth;
    }

    /**
     * @param Category $category
     * @return false|int|null|string
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getProductCount(Category $category)
    {
        try{
            return (int)$category->getProducts(null, null, null, null, null, true);
        } catch (PrestaShopException $e)
        {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            return 0;
        }
    }


    /**
     * @param Category $category
     * @return string
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getPath(Category $category)
    {
        $parentCategories=[];
        foreach ($category->getParentsCategories() as $parentcategory) {
            $parentCategories[]=$parentcategory['id_category'];
        }
        return implode('/', array_reverse($parentCategories));
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
                    ->where('o.`id_parent` != 0')
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
            return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('COUNT(*)')
                    ->from('category', 'c')
                    ->join(Shop::addSqlAssociation('category', 'c'))
                    ->where('c.`id_parent` != 1')
                    ->groupBy('c.`id_category`')
                    ->orderBy('c.`id_category`, category_shop.`position`')
            );
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    


}
