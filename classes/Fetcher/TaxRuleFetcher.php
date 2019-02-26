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

use TaxRule;
use TaxRulesGroup;
use Country;
use DbQuery;
use Db;
use Tb2VueStorefrontModule\Meta;
use Tb2VueStorefrontModule\Fetcher;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class TaxruleFetcher
 *
 * @package Tb2vuestorefrontModule
 */
class TaxruleFetcher extends Fetcher
{
    // @var TaxRulesGroup $className
    public static $className = 'TaxRulesGroup';
    public static $indexName = 'taxrule';

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
        'code'         => [
            'function'      => [__CLASS__, 'getName'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'priority'          => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'position'          => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'calculate_subtotal'=> [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'rates'=> [
            'function'      => [__CLASS__, 'getRates'],
            'children'      => [
                'id' => [
                    'type' => Meta::ELASTIC_TYPE_INTEGER,
                ],
                'tax_country_id' => [
                    'type' => Meta::ELASTIC_TYPE_KEYWORD,
                ],
                'tax_region_id' => [
                    'type' => Meta::ELASTIC_TYPE_INTEGER,
                ],
                'rate' => [
                    'type' => Meta::ELASTIC_TYPE_FLOAT,
                ],
                'tax_postcode' => [
                    'type' => Meta::ELASTIC_TYPE_TEXT,
                ],
                'zip_is_range' => [
                    'type' => Meta::ELASTIC_TYPE_INTEGER,
                ],
                'zip_from' => [
                    'type' => Meta::ELASTIC_TYPE_TEXT,
                ],
                'zip_to' => [
                    'type' => Meta::ELASTIC_TYPE_TEXT,
                ],

            ],
  
        ],
        'tax_rates_ids'=> [
            'function'      => [__CLASS__, 'getRateIds'],
        ],
        'id'                => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'created_at'        => [
            'function'      => [__CLASS__, 'getCreatedAt'],
            'type'       => Meta::ELASTIC_TYPE_DATE,
        ],
        'product_tax_class_ids'        => [
            'function'      => [__CLASS__, 'getProductTaxClassIds'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],

    ];

    protected static function getProductTaxClassIds(TaxRulesGroup $taxRulesGroup)
    {
        return [$taxRulesGroup->id];

    }

    protected static function getRates(TaxRulesGroup $taxRulesGroup, $idLang)
    {
        $taxRuleRates = TaxRule::getTaxRulesByGroupId($idLang, $taxRulesGroup->id);
        $rates=[];
        foreach ($taxRuleRates as $taxRateId) {
            $rates[]=array(
                'tax_country_id' => Country::getIsoById($taxRateId['id_country']),
                // 'tax_region_id'  => $taxRateId['id_country'],
                'tax_region_id'  => 0,
                'rate'           => $taxRateId['rate'],
                'tax_postcode'   => '*',
                'zip_is_range'   => null,
                'zip_from'       => null,
                'zip_to'         => null,
                'id'             => $taxRateId['id_tax_rule'],
            );
        }
        return $rates;

    }

    protected static function getRateIds(TaxRulesGroup $taxRulesGroup)
    {
        $tax_rates_ids=[];
        foreach (TaxRule::getTaxRulesByGroupId($idLang, $taxRulesGroup->id) as $taxRateId) {
            $tax_rates_ids[]=(int)$taxRateId['id_tax_rule'];
        }
        return $tax_rates_ids;

    }
}
