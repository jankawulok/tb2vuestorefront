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

use CmsCategory;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class ReviewFetcher
 *
 * @package Tb2vuestorefrontModule
 */
class CmsCategoryFetcher extends Fetcher
{

    public static $className = 'CMSCategory';

    public static $indexName = 'cmscategory';

    /**
     * Properties array
     *
     * @var array $attributes
     */
    public static $attributes = [
        'id'                => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_active'         => [
            'function'      => [__CLASS__, 'getIsActive'],
            'type'          => Meta::ELASTIC_TYPE_BOOLEAN,
        ],
        'parent_id'         => [
            'function'      => [__CLASS__, 'getParentId'],
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'position'          => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'level_depth'       => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'name'              => [
            'function'      => null,
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'description'       => [
            'function'      => null,
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
        'slug'              => [
            'function'      => [__CLASS__, 'getLinkRewrite'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'meta'              => [
            'function'      => [__CLASS__, 'getMeta'],
        ],

    ];

    /**
     * @param CmsCategory $category
     * @return int
     */
    protected static function getParentId(CmsCategory $category)
    {
        return $category->id_parent;
    }

}
