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

use Tb2vuestorefront;
use Configuration;
use Context;
use Elasticsearch;
use Exception;
use Language;
use Shop;
use ReflectionClass;

if (!defined('_PS_VERSION_')) {
    return;
}

/**
 * Class Indexer
 *
 * @package Tb2vuestorefrontModule
 */
class Indexer
{
    /** @var ProductFetcher $productFetcher */
    protected $productFetcher;

    /**
     * Indexer constructor.
     */
    public function __construct()
    {
        $this->productFetcher = new ProductFetcher();
    }


//    TODO: dokończyć
    public static function index($entityType, $items)
    {
        $indexPrefix = Configuration::get(Tb2vuestorefront::INDEX_PREFIX);
        $indexVersion = Configuration::get(Tb2vuestorefront::INDEX_VERSION);
        foreach ($items as &$item) {
            $params['body'][] = [
                'index' => [
                    '_index' => "{$indexPrefix}_{$item->elastic_id_shop}_{$item->elastic_id_lang}_{$indexVersion}",
                    '_type' => $entityType,
                    '_id' => $item->id,
                ],
            ];
            $params['body'][] = $item;
            try {
                $results = $client->bulk($params);
            } catch (Exception $exception) {
                return $exception;
            }


        }
    }

    /**
     * Erase Elasticsearch indices
     *
     * @param int[]|null $idLangs
     * @param int[]|null $idShops
     * @param int[]|null $indexVersions
     *
     * @throws \PrestaShopException
     */
    public static function eraseIndices($idLangs = null, $idShops = null, $indexVersions = null)
    {
        
        $indexPrefix = Configuration::get(Tb2vuestorefront::INDEX_PREFIX);

        if (!is_array($indexVersions) || empty($indexVersions)) {
            return;
        }
        if (!is_array($idLangs) || empty($idLangs)) {
            $idLangs = Language::getLanguages(true, false, true);
        }
        if (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getShops(false, null, true);
        }

        // Delete the indices first
        $client = Tb2vuestorefront::getWriteClient();
        if (!$client instanceof \Elasticsearch\Client) {
            return;
        }
        foreach ($idShops as $idShop) {
            foreach ($idLangs as $idLang) {
                foreach ($indexVersions as $indexVersion) {
                    try {
                    $client->indices()->delete(['index' => "{$indexPrefix}_{$idShop}_{$idLang}_{$indexVersion}"]);
                    } catch (Exception $e) {
                        $error = json_decode($e->getMessage());
                        if (isset($error->error->status)) {
                            if ((int) substr($error->error->status, 0, 1) !== 4) {
                                die($e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Create alias and publish index
     *
     * @param int[]|null $idLangs
     * @param int[]|null $idShops
     *
     * @throws \PrestaShopException
     */
    public static function publishIndex($idLangs = null, $idShops = null)
    {
        $indexPrefix = Configuration::get(Tb2vuestorefront::INDEX_PREFIX);
        $indexVersion = (int)Configuration::get(Tb2vuestorefront::INDEX_VERSION);
        $previousIndexVersion = $indexVersion -1;
        $client = Tb2vuestorefront::getWriteClient();
        if (!$client) {
            return;
        }
        foreach ($idShops as $idShop) {
            foreach ($idLangs as $idLang) {
                try {
                    $params = [
                        'index' => "{$indexPrefix}_{$idShop}_{$idLang}_*",
                        'name' => "{$indexPrefix}_{$idShop}_{$idLang}"
                    ];
                    $client->indices()->deleteAlias($params);
                } catch (Exception $e) {
                }
            }
        }
        foreach ($idShops as $idShop) {
            foreach ($idLangs as $idLang) {
                try {
                    $params = [
                        'index' => "{$indexPrefix}_{$idShop}_{$idLang}_{$indexVersion}",
                        'name' => "{$indexPrefix}_{$idShop}_{$idLang}"
                    ];
                    $client->indices()->putAlias($params);
                } catch (Exception $e) {
                }
            }
        }
    }


    /**
     * @param array $objects
     * @return array
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function bulkIndex(array &$objects)
    {
        $client = Tb2vuestorefront::getWriteClient();
        try {
            $indexVersion = (int)Configuration::get(Tb2vuestorefront::INDEX_VERSION);
        } catch (Exception $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            $indexVersion = 1;

        }
        try {
            $index = Configuration::get(Tb2vuestorefront::INDEX_PREFIX);
        } catch (Exception $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            return [
                'success' => false,
            ];

        }

        if (!$client) {
            return [
                'success' => false,
            ];
        }

        $params = [
            'body' => [],
        ];

        foreach ($objects as &$object) {
            $params['body'][] = [
                'index' => [
                    '_index' => "{$index}_{$object->elastic_id_shop}_{$object->elastic_id_lang}_{$indexVersion}",
                    '_type'  => "{$object->elastic_type}",
                    '_id'     => $object->id,
                ],
            ];

            $params['body'][] = $object;

        }

        // Push to Elasticsearch
        try {
            $results = $client->bulk($params);
        } catch (Exception $e) {
            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            return [
                'success' => false,
            ];

        }
        $failed = [];

        // Check error messages
        foreach ($results['items'] as $result) {
            if ((int) substr($result['index']['status'], 0, 1) !== 2) {
                preg_match(
                    '/(?P<index>[a-zA-Z]+)\_(?P<id_shop>\d+)\_(?P<id_lang>\d+)/',
                    $result['index']['_index'],
                    $details
                );
                $failed[] = [
                    'id_lang'   => (int) $details['id_lang'],
                    'id_shop'   => (int) $details['id_shop'],
                    'id_entity' => (int) $result['index']['_id'],
                    '_type'      =>  $result['index']['_type'], //TODO Do sprawdzenia
                    'error'     => isset($result['index']['error']['reason'])
                        ? $result['index']['error']['reason'].(isset($result['index']['error']['caused_by']['reason'])
                            ? ' '.$result['index']['error']['caused_by']['reason']
                            : '')
                        : 'Unknown error',
                ];
            }
        }
        // Set error messages if any.
        if (!empty($failed)) {
            foreach ($failed as $failure) {
                foreach ($objects as $index => $object) {
                    if ((int) $object->id == (int) $failure['id_entity']
                        && (int) $object->elastic_id_shop == (int) $failure['id_shop']
                        && (int) $object->elastic_id_lang == (int) $failure['id_lang']
                        && (string) $object->elastic_type == (string) $failure['_type']
                    ) {
                        try {
                            \Db::getInstance()->execute('
                              INSERT INTO `'._DB_PREFIX_."tb2vuestorefront_index_status` (`id_entity`,`index`,`id_lang`,`id_shop`, `date_upd`, `error`) 
                              VALUES ({$failure['id_entity']}, '{$failure['_type']}', {$failure['id_lang']}, {$failure['id_shop']}, '{$object->updated_at}', '{$failure['error']}') ON DUPLICATE KEY UPDATE `error` = VALUES(`error`)");
                        } catch (\PrestaShopException $e) {
                            \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
                        }

                        unset($objects[$index]);
                    }
                }
            }
        }

        // Insert index status into database
        $values = '';
        foreach ($objects as &$object) {
            $values .=  "('{$object->id}', '{$object->elastic_type}', '{$object->elastic_id_lang}', '{$object->elastic_id_shop}', '{$object->updated_at}', NULL),";
        }
        $values = rtrim($values, ',');
        if ($values) {
            try {
                \Db::getInstance()->execute('
                  INSERT INTO `'._DB_PREFIX_."tb2vuestorefront_index_status` (`id_entity`, `index`, `id_lang`,`id_shop`, `date_upd`, `error`) 
                  VALUES $values ON DUPLICATE KEY UPDATE `date_upd` = VALUES(`date_upd`), `error` = NULL");
            } catch (\PrestaShopException $e) {
                \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            }
        }

        return [
            'success'  => true,
            'nbErrors' => count($failed),
            'errors'   => $failed,
        ];
    }



    /**
     * Create and push Elasticsearch mappings
     *
     * @param int[]|null $idLangs
     * @param int[]|null $idShops
     *
     * @throws \PrestaShopException
     */
    public static function createMappings($idLangs = null, $idShops = null)
    {
        $indexPrefix = Configuration::get(Tb2vuestorefront::INDEX_PREFIX);
        $indexVersion = Configuration::get(Tb2vuestorefront::INDEX_VERSION);
        if (!is_array($idLangs) || empty($idLangs)) {
            $idLangs = Language::getLanguages(true, false, true);
        }
        if (!is_array($idShops) || empty($idShops)) {
            $idShops = Shop::getShops(false, null, true);
        }

        // Gather the properties and build the mappings
        $mappings=[];
        
        // Push the mappings to Elasticsearch
        $client = Tb2vuestorefront::getWriteClient();
        if (!$client) {
            return;
        }
        foreach ($idShops as $idShop) {
            foreach ($idLangs as $idLang) {

                foreach (EntityType::getEntityTypes(true, $idShops) as $index) {
                    /** @var Fetcher $index */
                    $index =  $index['class_name'];
                    $mappings[$index::$indexName]=$index::generateMappings($idLang, $idShop);
                }
                
                $params = [
                    'index' => "{$indexPrefix}_{$idShop}_{$idLang}_{$indexVersion}",
                    'body'  => [
                        'settings' => [
                            'number_of_shards'   => (int) Configuration::get(Tb2vuestorefront::SHARDS),
                            'number_of_replicas' => (int) Configuration::get(Tb2vuestorefront::REPLICAS),
                        ],
                        'mappings' => $mappings,
                    ],
                ];

                if ($stopWords = Configuration::get(Tb2vuestorefront::STOP_WORDS, $idLang, null, $idShop)) {
                    $analysis = [
                        'analyzer' => [
                            'tb_analyzer' => [
                                'type'      => 'standard',
                                'filter'    => ["lowercase"],
                                'stopwords' => explode(',', $stopWords),
                            ],
                            'trigram' => [
                                'type'      => 'custom',
                                'filter'    => ["lowercase", "standard", "shingle"],
                                'tokenizer' =>  'standard',
                            ],
                            'lang_pl' => [
                                'type'      => 'custom',
                                'filter'    => ["lowercase", "polish_stem", 'pl_stop'],
                                'tokenizer' =>  'whitespace',
                            ],
                            'lang_pl_ascii' => [
                                'type'      => 'custom',
                                'filter'    => ["lowercase", "polish_stem", 'pl_stop',  "asciifolding"],
                                'tokenizer' =>  'whitespace',
                            ],
                            'lang_pl_trigram' => [
                                'type'      => 'custom',
                                'filter'    => ["lowercase", "polish_stem", 'pl_stop', 'shingle'],
                                'tokenizer' =>  'whitespace',
                            ],
                            'reverse' => [
                                'type'      => 'custom',
                                'filter'    => ["standard", "reverse"],
                                'tokenizer' =>  'standard',
                            ],
                            'autocomplete' => [
                                'filter'    => ["lowercase"],
                                'tokenizer' =>  'autocomplete',
                            ],
                            'autocomplete_search' => [
                                'tokenizer' =>  'lowercase',
                                // 'filter'    =>  'polish_stem',
                            ],

                        ],
                        "tokenizer" => [
                            "autocomplete" => [
                              "type" => "edge_ngram",
                              "min_gram" => 2,
                              "max_gram" =>10,
                              "token_chars" => [
                                "letter",
                                "punctuation",
                                "digit"
                              ],
                            ],
                        ],
                        'filter' => [
                            'shingle'   => [
                                'type'  => 'shingle',
                                'min_shingle_size'  => 2,
                                'max_shingle_size' => 3,
                            ],
                            'pl_stop'   => [
                                'type'  => 'stop',
                                'stopwords' => ', a, aby, ach, acz, aczkolwiek, aj, albo, ale, alez, ależ, ani, az, aż, bardziej, bardzo, beda, bedzie, bez, deda, będą, bede, będę, będzie, bo, bowiem, by, byc, być, byl, byla, byli, bylo, byly, był, była, było, były, bynajmniej, cala, cali, caly, cała, cały, ci, cie, ciebie, cię, co, cokolwiek, cos, coś, czasami, czasem, czemu, czy, czyli, daleko, dla, dlaczego, dlatego, do, dobrze, dokad, dokąd, dosc, dość, duzo, dużo, dwa, dwaj, dwie, dwoje, dzis, dzisiaj, dziś, gdy, gdyby, gdyz, gdyż, gdzie, gdziekolwiek, gdzies, gdzieś, go, i, ich, ile, im, inna, inne, inny, innych, iz, iż, ja, jak, jakas, jakaś, jakby, jaki, jakichs, jakichś, jakie, jakis, jakiś, jakiz, jakiż, jakkolwiek, jako, jakos, jakoś, ją, je, jeden, jedna, jednak, jednakze, jednakże, jedno, jego, jej, jemu, jesli, jest, jestem, jeszcze, jeśli, jezeli, jeżeli, juz, już, kazdy, każdy, kiedy, kilka, kims, kimś, kto, ktokolwiek, ktora, ktore, ktorego, ktorej, ktory, ktorych, ktorym, ktorzy, ktos, ktoś, która, które, którego, której, który, których, którym, którzy, ku, lat, lecz, lub, ma, mają, mało, mam, mi, miedzy, między, mimo, mna, mną, mnie, moga, mogą, moi, moim, moj, moja, moje, moze, mozliwe, mozna, może, możliwe, można, mój, mu, musi, my, na, nad, nam, nami, nas, nasi, nasz, nasza, nasze, naszego, naszych, natomiast, natychmiast, nawet, nia, nią, nic, nich, nie, niech, niego, niej, niemu, nigdy, nim, nimi, niz, niż, no, o, obok, od, około, on, ona, one, oni, ono, oraz, oto, owszem, pan, pana, pani, po, pod, podczas, pomimo, ponad, poniewaz, ponieważ, powinien, powinna, powinni, powinno, poza, prawie, przeciez, przecież, przed, przede, przedtem, przez, przy, roku, rowniez, również, sam, sama, są, sie, się, skad, skąd, soba, sobą, sobie, sposob, sposób, swoje, ta, tak, taka, taki, takie, takze, także, tam, te, tego, tej, ten, teraz, też, to, toba, tobą, tobie, totez, toteż, totobą, trzeba, tu, tutaj, twoi, twoim, twoj, twoja, twoje, twój, twym, ty, tych, tylko, tym, u, w, wam, wami, was, wasz, wasza, wasze, we, według, wiele, wielu, więc, więcej, wlasnie, właśnie, wszyscy, wszystkich, wszystkie, wszystkim, wszystko, wtedy, wy, z, za, zaden, zadna, zadne, zadnych, zapewne, zawsze, ze, zeby, zeznowu, zł, znow, znowu, znów, zostal, został, żaden, żadna, żadne, żadnych, że, żeby',
                            ],
                        ],
                    ];

                    $params['body']['settings']['analysis'] = $analysis;
                }
//                die(json_encode($params));
                try {
                    // Create the index with mappings and settings

                    $client->indices()->create($params);
                } catch (Exception $e) {
                    die(json_encode([
                        'success'  => false,
                        'indexed'  => 0,
                        'total'    => 0,
                        'nbErrors' => 1,
                        'errors'   =>  $e->getMessage(),
                    ]));
                }
            }
        }
    }
}
