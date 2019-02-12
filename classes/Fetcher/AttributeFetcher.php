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

namespace Tb2VueStorefrontModule\Fetcher;

use Db;
use DbQuery;
use Feature;
use FeatureValue;
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
 * thirty bees Category object.
 *
 * @package Tb2vuestorefrontModule
 */
class AttributeFetcher extends Fetcher
{

    public static $className = 'Feature';
    public static $indexName = 'attribute';

    // Cached category paths
    static $cachedParentCategories = [];

    // Avoid these categories (root and home)
    static $avoidCategories = null;

    /**
     * Properties array
     *
     * @var array $attributes
     */
    public static $attributes = [
        'id'                => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'attribute_id'      => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'attribute_code'    => [
            'function'      => [__CLASS__, 'getCode'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'frontend_input'    => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'frontend_label'    => [
            'function'      => [__CLASS__, 'getName'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'position'          => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_searchable'     => [
            'const'      => 1,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_filterable'     => [
            'const'      => 1,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_comparable'     => [
            'const'      => 1,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_visible_on_front'=> [
            'const'      => 1,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'is_configurable'   => [
            'function'      => [__CLASS__, 'getIsConfigurable'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'frontend_input_renderer' => [
            'function'      => [__CLASS__, 'getName'],
            'type'          => Meta::ELASTIC_TYPE_TEXT,
        ],
        'options'           => [
            'function'      => [__CLASS__, 'getOptions'],
            'children'      => [
                'value'     => ['type' => Meta::ELASTIC_TYPE_INTEGER],
                'label'     => ['type' => Meta::ELASTIC_TYPE_TEXT],
            ],
        ],

    ];

    public static function getIsConfigurable(Feature $feature)
    {
        return 0;
    }

    protected static function getCode(Feature $feature, $idLang)
    {
        return str_replace(' ','_',mb_strtolower($feature->name))
    }

    /**
     * @param Feature $feature
     * @param int $idLang
     * @return array
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected static function getOptions(Feature $feature, $idLang)
    {
        try {
            $options = FeatureValue::getFeatureValuesWithLang($idLang, $feature->id);
        } catch (\PrestaShopException $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            return [];
        }
        $res=[];
        foreach ($options as $option) {
            $res[] = [
                'value' => (int)$option['id_feature_value'],
                'label' => (string)$option['value'],
            ];
        }
        return $res;

    }
}
