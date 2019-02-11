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

use CMS;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class CmsFetcher
 *
 * @package Tb2vuestorefrontModule
 */
class CmsFetcher extends Fetcher
{

    public static $className = 'CMS';

    public static $indexName = 'cms_page';

    /**
     * Properties array
     *
     * @var array $attributes
     */
    public static $attributes = [
        'is_active'         => [
            'function'      => [__CLASS__, 'getIsActive'],
            'type'       => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'cms_category_id'         => [
            'function'      => [__CLASS__, 'getCmsCategoryId'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'position'          => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'level_depth'       => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'title'              => [
            'function'      => [__CLASS__, 'getTitle'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'content'           => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'url_key'              => [
            'function'      => [__CLASS__, 'getLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'identifier'              => [
            'function'      => [__CLASS__, 'getLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'meta'              => [
            'function'      => [__CLASS__, 'getMeta'],
        ],

    ];

    /**
     * @param CMS $cms
     * @return mixed
     */
    protected static function getCmsCategoryId(CMS $cms)
    {
        return $cms->id_cms_category;
    }

    protected static function getTitle(CMS $cms)
    {
        return $cms->name;
    }


}
