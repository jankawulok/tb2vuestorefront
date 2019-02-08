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

use Manufacturer;
use ImageType;

if (!defined('_TB_VERSION_')) {
    return;
}

/**
 * Class Fetcher
 *
 * @package Tb2vuestorefrontModule
 */
class ManufacturerFetcher extends Fetcher
{

    public static $className = 'Manufacturer';
    public static $indexName = 'manufacturer';

    /*
     * Properties array
     *
     * @var array $attributes
     */
    public static $attributes = [
        'id'                => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'name'              => [
            'function'      => [__CLASS__, 'getName'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'description'       => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'short_description' => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'slug'              => [
            'function'      => [__CLASS__, 'getLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'is_active'=> [
            'function'      => [__CLASS__, 'getIsActive'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'image'=> [
            'function'      => [__CLASS__, 'getManufacturerLogo'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'product_count'     => [
            'function'      => [__CLASS__, 'getProductCount'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
            'elastic_types' => [
                META::ELASTIC_TYPE_INTEGER,
            ],
        ],
        'meta'           => [
            'function'      => [__CLASS__, 'getMeta'],
        ],
        'created_at'        => [
            'function'      => [__CLASS__, 'getCreatedAt'],
            'type'       => Meta::ELASTIC_TYPE_DATE,
        ],
        'updated_at'        => [
            'function'      => [__CLASS__, 'getUpdatedAt'],
            'type'       => Meta::ELASTIC_TYPE_DATE,
        ],

    ];


    /**
     * @param Manufacturer $manufacturer
     * @param int $idLang
     * @return string
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected static function getManufacturerLogo(Manufacturer $manufacturer, ?int $idLang)
    {
        return (!file_exists(_PS_MANU_IMG_DIR_.$manufacturer->id.'-'.ImageType::getFormatedName('medium').'.jpg')) ? \Language::getIsoById($idLang).'-default' : $manufacturer->id;

    }

    /**
     * @param Manufacturer $manufacturer
     * @param int $idLang
     * @return int
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected static function getProductCount(Manufacturer $manufacturer, ?int $idLang)
    {
        // TODO: write product count query or check if it is used by VSF
        return count($manufacturer->getProductsLite($idLang));
    }

    



}
