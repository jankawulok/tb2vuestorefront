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
 * @author    thirty bees <contact@thirtybee
 * s.com>
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Tb2VueStorefrontModule\Fetcher;

use AttributeGroup;
use Category;
use Configuration;
use Collection;
use Group;
use Feature;
use Context;
use Customer;
use Db;
use DbQuery;
use Image;
use ImageType;
use Link;
use Logger;
use Manufacturer;
use Page;
use phpDocumentor\Reflection\Types\Integer;
use PrestaShopException;
use Product;
use ProductSale;
use Shop;
use stdClass;
use Tools;
use StockAvailable;
use Tb2VueStorefrontModule\Meta;
use Tb2VueStorefrontModule\Fetcher;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class Fetcher
 *
 * When fetching a product for Elasticsearch indexing, it will call the functions as defined in the
 * `$attributes` array. If the value `null` is used, it will grab the property directly from the
 * thirty bees Product object.
 *
 * @package Tb2vuestorefrontModule
 */
class ProductFetcher extends Fetcher
{

    public static $className = 'Product';
    public static $indexName = 'product';

    // Cached category paths
    static $cachedCategoryPaths = [];

    // Avoid these categories (root and home)
    static $avoidCategories = null;

    /**
     * Properties array
     *
     * Defaults:
     * - function: null
     * - type: Meta::ELASTIC_TYPE_TEXT
     *
     * @var array $attributes
     */
    public static $attributes = [
        'sku'               => [
            'function'      => [__CLASS__, 'getSku'],
            'type'          => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'name'              => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'attribute_set_id'  => [
            'static'        => 0,
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'price'             => [
            'function'      => [__CLASS__, 'getPriceTaxExcl'],
            'type'          => Meta::ELASTIC_TYPE_FLOAT,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_FLOAT,
            ],
        ],
        'status'            => [
            'function'      => [__CLASS__, 'getStatus'],
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'visibility'        => [
            'function'      => [__CLASS__, 'getVisibility'],
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'type_id'           => [
            'function'      => [__CLASS__, 'getTypeId'],
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'created_at'        => [
            'function'      => [__CLASS__, 'getCreatedAt'],
            'type'          => Meta::ELASTIC_TYPE_DATE,
        ],
        'updated_at'        => [
            'function'      => [__CLASS__, 'getUpdatedAt'],
            'type'          => Meta::ELASTIC_TYPE_DATE,
        ],
        'weight'            => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_FLOAT,
        ],
        'weight'            => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_FLOAT,
        ],
        'tier_prices'       => [
            'function'      => [__CLASS__, 'getTierPrices'],
        ],
        'required_options'       => [
            'function'      => [__CLASS__, 'getRequiredOptions'],
        ],
        'has_options'       => [
            'function'      => [__CLASS__, 'getHasOptions'],
            'type'          => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'tax_class_id'      => [
            'function'      => [__CLASS__, 'getTaxClassId'],
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'category_ids'            => [
            'function'      => [__CLASS__, 'getCategoriesIds'],
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'category'          => [
            'function'      => [__CLASS__, 'getCategories'],
            'children'      => [
                'category_id'   => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'is_parent'     => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'is_virtual'    => ['type' => Meta::ELASTIC_TYPE_TEXT],
                'name'          => ['type' => Meta::ELASTIC_TYPE_TEXT],
                'position'      => ['type' => Meta::ELASTIC_TYPE_INTEGER],

            ],
        ],

        'reference'         => [
        ],
        'ean13'             => [
            'function'      => [__CLASS__, 'getEan'],
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'has_options'       => [
            'static'        => false,
            'type'          => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'required_options'  => [
            'static'        => 0,
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_salable'        => [
            'function'      => [__CLASS__, 'getAvailableForOrder'],
            'type'          => Meta::ELASTIC_TYPE_BOOLEAN,
        ],

        'is_active'         => [
            'function'      => [__CLASS__, 'getIsActive'],
            'type'          => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'request_path'      => [
            'function'      => [__CLASS__, 'getRequestPath'],
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'url_key'           => [
            'function'      => [__CLASS__, 'getRequestPath'],
            'type'          => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'url_key'           => [
            'function'      => [__CLASS__, 'getRequestPath'],
            'type'          => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'description'       => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'short_description' => [
            'function'      => [__CLASS__, 'getShortDescription'],
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'stock'             => [
            'function'      => [__CLASS__, 'getStock'],
            'children'    => [
                'item_id'     => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'product_id'  => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'stock_id'    => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'qty'         => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'is_in_stock' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'is_qty_decimal' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'show_default_notification_message' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'use_config_min_qty' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'min_qty' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'use_config_min_sale_qty' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'min_sale_qty' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'use_config_max_sale_qty' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'max_sale_qty' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'use_config_backorders' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'backorders' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'use_config_notify_stock_qty' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'notify_stock_qty' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'use_config_qty_increments' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'qty_increments' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'use_config_enable_qty_inc' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'enable_qty_increments' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'use_config_manage_stock' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'manage_stock' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'low_stock_date' => ['type' => Meta::ELASTIC_TYPE_DATE],
                'is_decimal_divided' => ['type' => Meta::ELASTIC_TYPE_BOOLEAN],
                'stock_status_changed_auto' => ['type' => Meta::ELASTIC_TYPE_INTEGER],
            ],
        ],
        'media_gallery' => [
            'function'  => [__CLASS__, 'getMediaGallery'],
            'children'  => [
                'image' => ['type' => Meta::ELASTIC_TYPE_TEXT],
                'pos'   => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'lab'   => ['type' => Meta::ELASTIC_TYPE_TEXT],
                'typ'   => ['type' => Meta::ELASTIC_TYPE_TEXT],
            ],
        ],
        'image'   => [
            'function'              => [__CLASS__, 'getImage'],
            'type'                  => Meta::ELASTIC_TYPE_TEXT,
        ],
        'categories_without_path'   => [
            'function'              => [__CLASS__, 'getCategoriesNamesWithoutPath'],
            'type'                  => Meta::ELASTIC_TYPE_TEXT,
        ],
        'condition'                 => [
            'function'              => null,
            'type'                  => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'customization_required'    => [
            'function'              => [__CLASS__, 'getCustomizationRequired'],
            'type'                  => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'date_add'                  => [
            'function'              => null,
            'type'                  => Meta::ELASTIC_TYPE_DATE,
        ],
        'date_upd'                  => [
            'function'              => null,
            'type'                  => Meta::ELASTIC_TYPE_DATE,
        ],

        'in_stock'                  => [
            'function'              => [__CLASS__, 'getInStock'],
            'type'                  => Meta::ELASTIC_TYPE_BOOLEAN,
            'visible'               => false,
        ],
        'is_virtual'              => [
            'function'      => [__CLASS__, 'getIsVirtual'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_BOOLEAN,
            ],
        ],
        'link'                    => [
            'function'      => [__CLASS__, 'generateLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
            'visible'       => false,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_KEYWORD,
            ],
        ],
        'id_tax_rules_group'      => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'visible'       => false,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_INTEGER,
            ],
        ],
        'manufacturer_name'            => [
            'function'      => [__CLASS__, 'getManufacturerName'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_KEYWORD,
                Meta::ELASTIC_TYPE_TEXT,
            ],
        ],
        'manufacturer_agg'            => [
            'function'      => [__CLASS__, 'getManufacturerName'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'minimal_quantity'        => [
            'function'      => [__CLASS__, 'getMinimalQuantity'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_INTEGER,
            ],
            'visible'       => false,
        ],
        'new'                     => [
            'function'      => [__CLASS__, 'getNew'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_BOOLEAN,
            ],
        ],
        'show_price'              => [
            'function'      => [__CLASS__, 'getShowPrice'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_BOOLEAN,
            ],
            'visible'       => false,
        ],
        'on_sale'                 => [
            'function'      => [__CLASS__, 'getOnSale'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_BOOLEAN,
            ],
        ],
        'online_only'             => [
            'function'      => [__CLASS__, 'getOnlineOnly'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_BOOLEAN,
            ],
        ],
        'ordered_qty'             => [
            'function'      => [__CLASS__, 'getOrderedQty'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'visible'       => false,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_INTEGER,
            ],
        ],
        'stock_qty'               => [
            'function'      => [__CLASS__, 'getStockQty'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'visible'       => false,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_INTEGER,
            ],
        ],
        'weight'                  => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_FLOAT,
            'visible'       => false,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_FLOAT,
            ],
        ],
        'pageviews'               => [
            'function'      => [__CLASS__, 'getPageViews'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_INTEGER,
            ],
        ],
        'sales'                   => [
            'function'      => [__CLASS__, 'getSales'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'elastic_types' => [
                Meta::ELASTIC_TYPE_INTEGER,
            ],
        ],
        'meta'           => [
            'function'      => [__CLASS__, 'getMeta'],
        ],
    ];

    /**
     * This function generates mapping for collection
     *
     * @return array
     * @throws \PrestaShopException
     */
    public static function generateMappings(int $idLang,int $idShop)
    {
        $mapping = parent::generateMappings($idLang, $idShop);
        $attributes = Feature::getFeatures($idLang);
        foreach ($attributes as &$result) {
            $attributeName = str_replace(' ','_',mb_strtolower($result['name']));
            if (!array_key_exists($mapping["properties"][$attributeName])) {
                $mapping["properties"][$attributeName] = [
                    'type' => META::ELASTIC_TYPE_KEYWORD,
                ];
            }
        }
        
        return $mapping;
    }

    public static function initObject(int $idEntity, int $idLang, int $idShop)
    {
        $elasticObject = parent::initObject($idEntity, $idLang, $idShop);

        // Features
        try {
            foreach (Product::getFrontFeaturesStatic($idLang, $idEntity) as $feature) {
                $featureName = str_replace(' ','_',mb_strtolower($feature['name']));
                if (!isset($elasticObject->{$featureName})) {
                    $elasticObject->{$featureName} = array_map('trim', explode(",", $feature['value']));
                }
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        return $elasticObject;
    }

    /**
     * Collect the amount of page views for a product
     *
     * @param Product $product
     *
     * @return false|null|string
     */
    public static function getPageViews($product)
    {
        try {
            return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('IFNULL(SUM(pv.`counter`), 0)')
                    ->from('page', 'pa')
                    ->leftJoin('page_viewed', 'pv', 'pa.`id_page` = pv.`id_page`')
                    ->where('pa.`id_object` = '.(int) $product->id)
                    ->where('pa.`id_page_type` = '.(int) Page::getPageTypeByName('product'))
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }

    }

    /**
     * Get amount of sales for this product
     *
     * @param Product $product
     *
     * @return int
     */
    public static function getSales($product)
    {
        try {
            $sales = ProductSale::getNbrSales($product->id);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }

        return $sales > 0 ? $sales : 0;
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
    public static function getCategoryPath($idCategory, $idLang)
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
     * Get stock quantity
     */
    protected static function getStockQty($product)
    {
        try {
            return Product::getQuantity($product->id);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Get the ordered quantity
     *
     * @param Product $product
     *
     * @return int
     */
    protected static function getOrderedQty($product)
    {
        if (!$product instanceof Product) {
            return 0;
        }

        try {
            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('SUM(`product_quantity`) AS `total`')
                    ->from('order_detail')
                    ->where('`product_id` = '.(int) $product->id)
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Get price tax excl for all customer groups (pre-calc group discounts)
     *
     * @param Product $product
     *
     * @return float
     *
     * @todo: optimize `getGroups` query and include default Customer IDs for higher performance
     */
    protected static function getPriceTaxExcl($product)
    {
        return (float) static::getProductBasePrice($product->id);
    }

    /**
     * @param Product $product
     * @param $idLang
     * @return string
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function generateImageLinkLarge(Product $product, $idLang)
    {
        $link = new Link();
        try {
            $cover = Image::getCover($product->id);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return '';
        }

        try {
            if ($cover['id_image']) {
                $imageLink = $link->getImageLink(
                    $product->link_rewrite[$idLang],
                    $cover['id_image'],
                    ImageType::getFormatedName('large')
                );
            } else {
                $imageLink = Tools::getHttpHost()._THEME_PROD_DIR_.'en-default-large_default.jpg';
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return '';
        }

        return $imageLink;
    }

    /**
     * Generate small image link
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return string
     */
    protected static function generateImageLinkSmall($product, $idLang)
    {
        $link = new Link();
        try {
            $cover = Image::getCover($product->id);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return '';
        }

        try {
            if ($cover['id_image']) {
                $imageLink = $link->getImageLink(
                    $product->link_rewrite[$idLang],
                    $cover['id_image'],
                    ImageType::getFormatedName('small')
                );
            } else {
                $imageLink = Tools::getHttpHost()._THEME_PROD_DIR_.'en-default-small_default.jpg';
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return '';
        }

        return $imageLink;
    }

    /**
     * Generate url slug
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return string
     */
    protected static function generateLinkRewrite($product, $idLang)
    {
        return Context::getContext()->link->getProductLink(
            $product->id,
            null,
            null,
            null,
            $idLang,
            Context::getContext()->shop->id,
            0,
            true
        );
    }

    /**
     * @param Product $product
     *
     * @return bool
     */
    protected static function getAllowOosp($product)
    {
        try {
            return (bool) Product::isAvailableWhenOutOfStock($product->out_of_stock);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }
    }

    protected static function getCategoryName($product, $idLang)
    {
        $category = new Category($product->id_category_default, $idLang);

        return $category->name;
    }

    /**
     * Get category names without path
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return array
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getCategoriesNamesWithoutPath($product, $idLang)
    {
        return array_values(array_filter(array_unique(array_map(function ($fullCategory) {
            return end(array_pad(explode(' /// ', $fullCategory), 1, ''));
        }, static::getCategoriesNames($product, $idLang)))));
    }

    /**
     * Get category ids
     *
     * @param Product $product
     *
     * @return array
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getCategoriesIds(Product $product)
    {
        return $product->getCategories();
    }

    protected static function getEan(Product $product)
    {
        return $product->ean13;
    }

    protected static function getRequestPath(Product $product)
    {
        return $product->link_rewrite;
    }

    /**
     * @param Product $product
     * @param $idLang
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getCategories(Product $product, $idLang)
    {
        $categoryIds = $product->getCategories();
        $categories=[];
        foreach ($categoryIds as $idCategory) {
            $category = new \Category($idCategory, $idLang);
            $categories[]= array(
                'category_id'  => $idCategory,
                'name'         => $category->name
            );
        }
        return $categories;
    }

    /**
     * Get category names
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return array
     * @throws PrestaShopException
     * @throws \PrestaShopDatabaseException
     */
    protected static function getCategoriesNames($product, $idLang)
    {
        if (!$idLang) {
            $idLang = Context::getContext()->language->id;
        }
        $idLang = (int) $idLang;

        if (!static::$avoidCategories) {
            static::$avoidCategories = [
                Configuration::get('PS_HOME_CATEGORY'),
                Configuration::get('PS_ROOT_CATEGORY'),
            ];
        }
        if (!array_key_exists($idLang, static::$cachedCategoryPaths)) {
            static::$cachedCategoryPaths[$idLang] = [];
        }

        $categoryPaths = [];
        $intervals = array_filter(array_map(function ($idCategory) use(&$categoryPaths, $idLang) {
            if (!empty(static::$cachedCategoryPaths[$idLang][(int) $idCategory])) {
                $categoryPaths[] = static::$cachedCategoryPaths[$idLang][(int) $idCategory];

                return null;
            }

            $interval = Category::getInterval((int) $idCategory);
            $interval['id_category'] = (int) $idCategory;

            return $interval;
        }, $product->getCategories()));

        foreach ($intervals as $interval) {
            $sql = new DbQuery();
            $sql->select('`name`');
            $sql->from('category', 'c');
            $sql->leftJoin('category_lang', 'cl', 'c.id_category = cl.id_category AND id_lang = '.(int) $idLang.Shop::addSqlRestrictionOnLang('cl'));
            $sql->where('c.`nleft` <= '.(int) $interval['nleft']);
            $sql->where('c.`nright` >= '.(int) $interval['nright']);
            $sql->where('c.`id_category` NOT IN ('.implode(',', array_map('intval', static::$avoidCategories)).')');
            $sql->orderBy('c.`level_depth`');

            $result = implode(' /// ', array_column((array) Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql), 'name'));
            static::$cachedCategoryPaths[$idLang][$interval['id_category']] = $result;
            $categoryPaths[] = $result;
        }

        return array_values(array_filter($categoryPaths));
    }

    /**
     * @param Product $product
     *
     * @return bool
     */
    protected static function getCustomizationRequired(Product $product)
    {
        return (bool) $product->customization_required;
    }

    /**
     * Get manufacturer name
     *
     * @param Product $product
     *
     * @return string
     */
    protected static function getManufacturerName(Product $product)
    {
        try {
            return Manufacturer::getNameById((int) $product->id_manufacturer);
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return '';
        }
    }

    /**
     * Get minimal quantity to order
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getMinimalQuantity(Product $product)
    {
        return (int) $product->minimal_quantity;
    }

    /**
     * Get `show_price` flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getShowPrice(Product $product)
    {
        return (bool) $product->show_price;
    }

    /**
     * @param Product $product
     * @return array
     */
    protected static function getTierPrices(Product $product)
    {
        return [];
    }

    /**
     * Get supplier name
     *
     * @param Product $product
     *
     * @return string
     */
    protected static function getSupplierName(Product $product)
    {
        return (string) $product->supplier_name;
    }

    /**
     * Get trimmed reference
     *
     * @param Product $product
     *
     * @return string
     *
     * @todo: figure out if we can also use an untrimmed reference
     */
    protected static function getTrimmedRef($product)
    {
        return (string) substr($product->reference, 3, strlen($product->reference));
    }

    /**
     * Get is_virtual flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getIsVirtual(Product $product)
    {
        return (bool) $product->is_virtual;
    }

    /**
     * Get on sale flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getOnSale(Product $product)
    {
        return (bool) $product->on_sale;
    }

    /**
     * Get online only flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getOnlineOnly(Product $product)
    {
        return (bool) $product->online_only;
    }

    /**
     * Get available_for_order flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getAvailableForOrder(Product $product)
    {
        return (bool) $product->available_for_order;
    }

    /**
     * @param Product $product
     *
     * @return bool
     */
    protected static function getNew($product)
    {
        return (bool) $product->new;
    }

    /**
     * Get `available_now` flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getAvailableNow($product)
    {
        return (bool) $product->available_now;
    }

    protected static function getTaxClassId(Product $product)
    {
        return (int) $product->id_tax_rules_group;
    }

    /**
     * Get `available_later` flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getAvailableLater(Product $product)
    {
        return (bool) $product->available_later;
    }

    /**
     * Get in stock flag
     *
     * @param Product $product
     *
     * @return bool
     */
    protected static function getInStock(Product $product)
    {
        try {
            return (bool) Product::getQuantity($product->id) > 0;
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * @param Product $product
     * @return string
     */
    protected static function getSku(Product $product)
    {
        return $product->reference;
    }


    /**
     * @param Product $product
     * @return int
     * TODO: Map prestashop visibility info to magento product status
     */
    protected static function getStatus(Product $product)
    {
        return 1;
    }

    /**
     * @param Product $product
     * @return int
     * TODO: Map prestashop visibility info to magento product visibility
     */
    protected static function getVisibility(Product $product)
    {
        return 3;
    }

    /**
     * @param Product $product
     * @return string
     * todo: impement product combinations suppport
     */
    protected static function getTypeId(Product $product)
    {
        return 'simple';
    }

    /**
     * @param Product $product
     * @return string
     */
    protected static function getShortDescription(Product $product)
    {
        return $product->description_short;
    }

    protected static function getStock(Product $product)
    {
        $stockAvailable = new StockAvailable(StockAvailable::getStockAvailableIdByProductId($product->id));
        return [
            'item_id'     => $product->id,
            'product_id'  => $product->id,
            'stock_id'    => $stockAvailable->id,
            'qty'         => $stockAvailable->quantity,
            'is_in_stock' => 1,
            'is_qty_decimal' => true,
            'show_default_notification_message' => false,
            'use_config_min_qty' => false,
            'min_qty' => 0,
            'use_config_min_sale_qty' => false,
            'min_sale_qty' => 1,
            'use_config_max_sale_qty' => false,
            'max_sale_qty' => 0,
            'use_config_backorders' => false,
            'backorders' => 0,
            'use_config_notify_stock_qty' => false,
            'notify_stock_qty' => 1,
            'use_config_qty_increments' => false,
            'qty_increments' => 1,
            'use_config_enable_qty_inc' => false,
            'enable_qty_increments' =>  false,
            'use_config_manage_stock' => false,
            'manage_stock' => false,
            'is_decimal_divided' => false,
            'stock_status_changed_auto' => 0,
        ];

    }

    /**
     * Get the base price of a product
     *
     * @param int $idProduct
     *
     * @return float
     */
    protected static function getProductBasePrice($idProduct)
    {
        try {
            return (float) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('ps.`price`')
                    ->from('product', 'p')
                    ->innerJoin(
                        'product_shop',
                        'ps',
                        'ps.`id_product` = p.`id_product` AND ps.`id_shop` = '.(int) Context::getContext()->shop->id
                    )
                    ->where('p.`id_product` = '.(int) $idProduct)
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return 0;
        }
    }

    protected static function getMediaGallery(Product $product, $idLang)
    {
        $mediaGallery=[];
        foreach ($product->getImages($idLang) as $image) {
            $imagePath= '/img/p/'.chunk_split($image['id_image'], 1, '/').$image['id_image'].'.jpg'; //todo: generate dynamic image path
            $mediaGallery[]=array(
                'image' => $imagePath,
                'lab'   => $image['legend'],
                'pos'   => $image['position'],
                'typ'   => 'image'
            );
        }
        return $mediaGallery;
    }

    protected static function getImage(Product $product, $idLang)
    {
        $id_image = $product->getCoverWs($idLang);
        return '/img/p/'.chunk_split($id_image, 1, '/').$id_image.'.jpg';
    }

}
