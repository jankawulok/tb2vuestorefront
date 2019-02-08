<?php
/**
 * Copyright (C) 2017-2018 thirty bees
 * Copyright (C) 2018 Jan Kawulok
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
 * @author    thirty bees <contact@thirtybees.com>, Jan Kawulok <jan@kawulok.com.pl>
 * @copyright 2018 thirty bees, 2018 Jan Kawulok
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Tb2VueStorefrontModule;

use \ProductComment;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class ReviewFetcher
 *
 * @package Tb2vuestorefrontModule
 */
class ReviewFetcher extends Fetcher
{

    public static $className = 'ProductComment';

    public static $indexName = 'review';

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
        'product_id'        => [
            'function'      => [__CLASS__, 'getProductId'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'review_entity'     => [
            'function'      => [__STATIC__, 'product'],
            'type'       => Meta::ELASTIC_TYPE_KEYWORD,
        ],
        'review_status'     => [
            'function'      => [__CLASS__, 'getReviewStatus'],
            'type'       => Meta::ELASTIC_TYPE_INTEGER,
        ],
        'created_at'        => [
            'function'      => [__CLASS__, 'getCreatedAt'],
            'type'       => Meta::ELASTIC_TYPE_DATE,
        ],
        'detail'            => [
            'function'      => [__CLASS__, 'getDetail'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'nickname'          => [
            'function'      => [__CLASS__, 'getNickName'],
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'title'             => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_TEXT,
        ],
        'grade'             => [
            'function'      => null,
            'type'       => Meta::ELASTIC_TYPE_FLOAT,
        ],

    ];

    /**
     * @param $comment
     * @return int
     */
    protected static function getReviewStatus($comment)
    {
        return (int)( (bool)$comment->validate AND !(bool)$comment->delete );
    }

    protected static function getProductId($comment)
    {
        return $comment->id_product;
    }

    protected static function getNickName($comment)
    {
        return $comment->customer_name;
    }

    protected static function getDetail($comment)
    {
        return $comment->content;
    }
}
