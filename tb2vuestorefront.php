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
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Tb2VueStorefrontModule\IndexStatus;
use Tb2VueStorefrontModule\EntityType as VSindex;
use Tb2VueStorefrontModule\Meta;
use Tb2VueStorefrontModule\Fetcher;
use Tb2VueStorefrontModule\EntityType as ElasticIndex;
use Tb2VueStorefrontModule\ModuleAjaxTrait;
// use Monolog\Logger;
// use Monolog\Handler\StreamHandler;

if (!defined('_PS_VERSION_')) {
    return;
}

require_once __DIR__.'/vendor/autoload.php';

/**
 * Class Elasticsearch
 */
class Tb2vuestorefront extends Module
{
    // Include ajax functions
    use \Tb2VueStorefrontModule\ModuleAjaxTrait;
    // Config page
    const INDEX_CHUNK_SIZE = 'ELASTICSEARCH_ICHUNK_SIZE';
    const INDEX_PREFIX = 'ELASTICSEARCH_IPREFIX';
    const INDEX_VERSION = 'ELASTICSEARCH_IVERSION';
    const STOP_WORDS = 'ELASTICSEARCH_STOP_WORDS';
    const REPLICAS = 'ELASTICSEARCH_REPLICAS';
    const SHARDS = 'ELASTICSEARCH_SHARDS';
    const LOGGING_ENABLED = 'ELASTICSEARCH_LOGGING';

    // Connection page
    const SERVERS = 'ELASTICSEARCH_SERVERS';
    const PROXY = 'ELASTICSEARCH_PROXY';

    // Indexing page
    const BLACKLISTED_FIELDS = 'ELASTICSEARCH_BLACKLISTED_FIELDS';
    const METAS = 'ELASTICSEARCH_METAS';

    // Search page
    const QUERY_JSON = 'ELASTICSEARCH_QUERY_JSON';
    const PRODUCT_LIST = 'ELASTICSEARCH_PRODUCT_LIST';

    // Display page
    const DEFAULT_TAX_RULES_GROUP = 'ELASTICSEARCH_ID_TAX_RULES';
    const INFINITE_SCROLL = 'ELASTICSEARCH_INFINITE_SCROLL';
    const REPLACE_NATIVE_PAGES = 'ELASTICSEARCH_REPLACE_PAGES';
    const SEARCH_SUBCATEGORIES = 'ELASTICSEARCH_SEARCH_SUBCATS';
    const AUTOCOMPLETE = 'ELASTICSEARCH_AUTOCOMPLETE';
    const INSTANT_SEARCH = 'ELASTICSEARCH_INSTANT';

    // Generic
    const CONFIG_UPDATED = 'ELASTICSEARCH_CONFIG_UPDATED';


    /** @var array $stopWordLangs */
    public static $stopWordLangs = [
        'ar' => '_arabic_',
        'am' => '_armenian_',
        'eu' => '_basque_',
        'br' => '_brazilian_',
        'bg' => '_bulgarian_',
        'ca' => '_catalan_',
        'cs' => '_czech_',
        'da' => '_danish_',
        'nl' => '_dutch_',
        'en' => '_english_',
        'gb' => '_english_',
        'fi' => '_finnish_',
        'fr' => '_french_',
        'gl' => '_galician_',
        'de' => '_german_',
        'el' => '_greek_',
        'hi' => '_hindi_',
        'hu' => '_hungarian_',
        'id' => '_indonesian_',
        'ga' => '_irish_',
        'it' => '_italian_',
        'lv' => '_latvian_',
        'no' => '_norwegian_',
        'fa' => '_persian_',
        'pl' => '_polish_',
        'pt' => '_portuguese_',
        'ro' => '_romanian_',
        'ru' => '_russian_',
        'es' => '_spanish_',
        'se' => '_swedish_',
        'th' => '_thai_',
        'tr' => '_turkish_',
    ];

    /** @var \Elasticsearch\Client $readClient */
    protected static $readClient;
    /** @var \Elasticsearch\Client $writeClient */
    protected static $writeClient;
    /**
     * Hooks
     *
     * @var array
     */
    protected $hooks = [
        'actionObjectProductAddAfter',
        'actionObjectProductDeleteAfter',
        'actionObjectProductUpdateAfter',
    ];

    /**
     * Entity Types
     *
     * @var array
     */
    protected $entityTypes = [
        ['Tb2VueStorefrontModule\\AttributeFetcher', 'attribute'],
        ['Tb2VueStorefrontModule\\CategoryFetcher', 'category'],
        ['Tb2VueStorefrontModule\\CmsCategoryFetcher', 'cmscategory'],
        ['Tb2VueStorefrontModule\\CmsFetcher', 'cms'],
        ['Tb2VueStorefrontModule\\ManufacturerFetcher', 'manufacturer'],
        ['Tb2VueStorefrontModule\\ProductFetcher', 'product'],
        ['Tb2VueStorefrontModule\\TaxRuleFetcher', 'taxrule'],
    ];

    /**
     * Tb2vuestorefront constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->version = '1.0.0';
        $this->name = 'tb2vuestorefront';
        $this->author = 'thirty bees';
        $this->tab = 'front_office_features';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Vuestorefront indexer');
        $this->description = $this->l('Index data to elasticsearch in Vuestorefront format');

        $this->controllers = ['cron', 'proxy', 'search'];

    }

    /**
     * Install this module
     *
     * @return bool
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function install()
    {
        if (version_compare(phpversion(), '7', '<')) {
            $this->context->controller->errors[] = sprintf($this->l('This module requires at least PHP version 7. Your current version is: %s'), phpversion());

            return false;
        }

        if (!extension_loaded('curl')) {
            $this->context->controller->errors[] = $this->l('Module requires the cURL extension to be installed and available. Ask your web host for more info on how to enable it.');
        }

        try {
            if (!parent::install()) {
                return false;
            }
        } catch (PrestaShopException $e) {
            return false;
        }

        $this->installDB();


        foreach ($this->entityTypes as $entityType) {
            try {
                foreach (Shop::getShops() as $shop) {
                    static::registerIndex($entityType[0], $entityType[1], $shop['id_shop']);
                }
            } catch (PrestaShopException $e) {
                \Tools::displayError($e->getMessage());
            }
        }

        foreach ($this->hooks as $hook) {
            try {
                $this->registerHook($hook);
            } catch (PrestaShopException $e) {
                \Tools::displayError($e->getMessage());
            }
        }

        Configuration::updateGlobalValue(static::INDEX_CHUNK_SIZE, 100);
        Configuration::updateGlobalValue(static::INDEX_PREFIX, 'thirtybees');
        Configuration::updateGlobalValue(static::INDEX_VERSION, 1);
        Configuration::updateGlobalValue(static::PROXY, true);
        Configuration::updateGlobalValue(static::SHARDS, 3);
        Configuration::updateGlobalValue(static::SERVERS, json_encode([['url' => 'http://localhost:9200', 'read' => true, 'write' => true]]));
        Configuration::updateGlobalValue(static::REPLICAS, 2);
        Configuration::updateGlobalValue(static::QUERY_JSON, file_get_contents(__DIR__.'/data/defaultquery.json'));
        Configuration::updateGlobalValue(static::BLACKLISTED_FIELDS, 'pageviews,sales');
        Configuration::updateGlobalValue(static::REPLACE_NATIVE_PAGES, true);
        Configuration::updateGlobalValue(static::SEARCH_SUBCATEGORIES, true);
        Configuration::updateGlobalValue(static::AUTOCOMPLETE, true);
        Configuration::updateGlobalValue(static::INSTANT_SEARCH, true);


        $defaultTaxGroup = 0;
        try {
            $taxes = TaxRulesGroup::getTaxRulesGroups(true);
        } catch (PrestaShopException $e) {
            $taxes = [];
        }
        if (!empty($taxes)) {
            $defaultTaxGroup = $taxes[0][TaxRulesGroup::$definition['primary']];
        }

        Configuration::updateGlobalValue(static::DEFAULT_TAX_RULES_GROUP, $defaultTaxGroup);

        foreach (Shop::getShops(false) as $shop) {
            $stopWords = [];

            try {
                foreach (Language::getLanguages(true) as $language) {
                    $stopWords[(int) $language['id_lang']] = static::getStopWordLang(strtolower($language['iso_code']));
                }
            } catch (PrestaShopException $e) {
            }

            try {
                Configuration::updateValue(
                    static::STOP_WORDS,
                    $stopWords,
                    false,
                    (int) $shop['id_shop_group'],
                    (int) $shop['id_shop']
                );
            } catch (PrestaShopException $e) {
                $this->context->controller->errors[] = $this->l('Unable to add stop words during installation, you might have to change these manually.');
            }
        }

        return true;
    }

    /**
     * Uninstall this module
     *
     * @return bool
     */
    public function uninstall()
    {
        foreach ([
            static::SERVERS,
            static::PROXY,
            static::LOGGING_ENABLED,
            static::INDEX_CHUNK_SIZE,
            static::INDEX_PREFIX,
            static::INDEX_VERSION,
            static::REPLICAS,
            static::SHARDS,
            static::BLACKLISTED_FIELDS,
            static::DEFAULT_TAX_RULES_GROUP,
            static::REPLACE_NATIVE_PAGES,
            static::SEARCH_SUBCATEGORIES,
            static::AUTOCOMPLETE,
            static::INSTANT_SEARCH,
            ] as $key) {
            try {
                Configuration::deleteByName($key);
            } catch (PrestaShopException $e) {
                Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
            }
        }

        try {
            Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'tb2vuestorefront_index_status`');
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }
        try {
            Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'tb2vuestorefront_entity_type`');
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }
        try {
            Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'tb2vuestorefront_meta`');
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }
        try {
            Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'tb2vuestorefront_meta_lang`');
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        try {
            return parent::uninstall();
        } catch (PrestaShopException $e) {
            Context::getContext()->controller->errors[] = $e->getMessage();

            return false;
        }
    }

    /**
     * @return string
     */
    public function getContent()
    {
        // jQuery + sortable plugin
        $this->context->controller->addJquery();
        $this->context->controller->addJqueryUI('ui.sortable');

        // Module CSS
        $this->context->controller->addCSS($this->_path.'views/css/style.css', 'all');
        $this->context->controller->addCSS($this->_path.'views/css/admin.css', 'all');

        // Bootstrap select
        $this->context->controller->addCSS($this->_path.'views/css/bootstrap-select-1.12.4.min.css', 'screen');
        $this->context->controller->addJS($this->_path.'views/js/bootstrap-select-1.12.4.min.js');

        // SweetAlert 2
        $this->context->controller->addJS($this->_path.'views/js/sweetalert-2.1.0.min.js');

        // Lodash
        $this->context->controller->addJS($this->_path.'views/js/lodash-4.17.4.min.js');

        // Ace editor
        $this->context->controller->addJS(_PS_JS_DIR_.'ace/ace.js');
        $this->context->controller->addCSS(_PS_JS_DIR_.'ace/aceinput.css');

        // Vue.js
        $this->context->controller->addJS($this->_path.'views/js/vue-2.5.11.min.js');

        // Vuex
        $this->context->controller->addJS($this->_path.'views/js/vuex-2.5.0.min.js');

        try {
            $elasticAjaxUrl = $this->context->link->getAdminLink('AdminModules', true)."&configure={$this->name}&tab_module={$this->tab}&module_name={$this->name}";
            $configFormValues = $this->getConfigFormValues();
            $configUpdated = (bool) Configuration::get(static::CONFIG_UPDATED);
            $languages = Language::getLanguages(true, false, false);
        } catch (PrestaShopException $e) {
            $this->context->controller->errors[] = $e->getMessage();

            return '';
        }

        Media::addJsDef(['elasticAjaxUrl' => $elasticAjaxUrl]);
        $this->context->smarty->assign([
            'config'         => $configFormValues,
            'configUpdated'  => $configUpdated,
            'initialTab'     => 'config',
            'status'         => [
                'indexed' => IndexStatus::getIndexed(null, $this->context->shop->id),
                'total'   => (int) \Tb2VueStorefrontModule\IndexStatus::countObjects(null, $this->context->shop->id),
            ],
            'totalProducts'  => \Tb2VueStorefrontModule\IndexStatus::countObjects($this->context->language->id, $this->context->shop->id),
            'languages'      => $languages,
            'tabGroups' => [
                [
                    [
                        'name' => 'Configuration',
                        'key'  => 'config',
                        'icon' => 'cogs',
                    ],
                    [
                        'name' => 'Connection',
                        'key'  => 'connection',
                        'icon' => 'plug',
                    ],
                ],
                [
                    [
                        'name' => 'Indices',
                        'key'  => 'index',
                        'icon' => 'table',
                    ],
                    [
                        'name' => 'Attribute mapping',
                        'key'  => 'indexing',
                        'icon' => 'sort',
                    ],
                    [
                        'name' => 'Default Filter',
                        'key'  => 'filter',
                        'icon' => 'filter',
                    ],
                ],
            ],
            'elastic_types' => Meta::getElasticTypes(),
        ]);

        try {
            return $this->display(__FILE__, 'views/templates/admin/config/main.tpl');
        } catch (Exception $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return '';
        }
    }



    /**
     * Get read hosts
     *
     * @return array
     */
    public static function getReadHosts()
    {
        $readHosts = [];
        try {
            foreach ((array) json_decode(Configuration::get(static::SERVERS), true) as $host) {
                if ($host['read']) {
                    $parsed = self::splitUrl($host['url']);
                    if (empty($parsed['host'])) {
                        continue;
                    }
                    if (empty($parsed['scheme'])) {
                        $parsed['scheme'] = 'http';
                    }

                    if (empty($parsed['port'])) {
                        $parsed['port'] = ($parsed['scheme'] === 'https') ? 443 : 80;
                    }

                    $readHosts[] = self::joinUrl($parsed);
                }
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        return $readHosts;
    }

    /**
     * Get Tb2vuestorefront Client with read access
     *
     * @return \Elasticsearch\Client|null
     *
     * @throws \Exception
     */
    public static function getReadClient()
    {
        if (!isset(static::$readClient)) {
            try {
                $client = ClientBuilder::create()
                    ->setHosts(static::getReadHosts())
                    ->build();

                // Check connection, throws an exception if something's wrong
                $client->cluster()->stats();

                static::$readClient = $client;
            } catch (Exception $e) {
                return null;
            }
        }

        return static::$readClient;
    }

    /**
     * Get write hosts
     *
     * @return array
     */
    public static function getWriteHosts()
    {
        $writeHosts = [];
        try {
            foreach ((array) json_decode(Configuration::get(static::SERVERS), true) as $host) {
                if ($host['write'] == 1) {
                    $writeHosts[] = $host['url'];
                }
            }

        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

            return $writeHosts;
        }
        return $writeHosts;
    }

    /**
     * Get Tb2vuestorefront Client with write access
     *
     * @return \Elasticsearch\Client|null
     */
    public static function getWriteClient()
    {
        if (!isset(static::$writeClient)) {
            try {
                $client = ClientBuilder::create()
                    ->setHosts(static::getWriteHosts())
                    ->build();

                // Check connection, throws an exception if something's wrong
                $client->cluster()->stats();

                static::$writeClient = $client;
            } catch (Exception $e) {
                $context = Context::getContext();
                if (isset($context->employee->id) && $context->employee->id) {
                    $context->controller->errors[] = $e->getMessage();
                }

                return null;
            }

        }

        return static::$writeClient;
    }

    /**
     * Get frontend hosts
     *
     * @return array
     */
    public static function getFrontendHosts()
    {
        try {
            if (Configuration::get(static::PROXY)) {
                return [Context::getContext()->link->getModuleLink('elasticsearch', 'proxy', [], Tools::usingSecureMode())];
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Elasticsearch module error: {$e->getMessage()}");
        }

        return static::getReadHosts();
    }

    /**
     * Return the location of a template file
     * Search order is as follows for front office and hook templates:
     * - theme-specific templates in current theme dir
     * - theme-specific templates in this module's dir
     * - generic templates in this module's dir
     *
     * Search order for back office templates:
     * - generic templates in this module's dir
     *
     * NOTE: relative path should always be *NIX style, preferably without a leading slash
     *
     * @param string $relativePath
     *
     * @return string
     */
    public static function tpl($relativePath)
    {
        $themeBaseDir = _PS_THEME_DIR_.'modules/elasticsearch/';
        $modThemeBaseDir = __DIR__.'/views/templates/themes/'.Context::getContext()->shop->theme_directory.'/';
        $modDir = __DIR__.'/views/templates/';

        // Search for a theme-specific file
        if (in_array(substr($relativePath, 0, 5), ['hook/', 'front'])) {
            foreach ([$themeBaseDir, $modThemeBaseDir, $modDir] as $basePath) {
                if (file_exists($basePath.$relativePath)) {
                    return $basePath.$relativePath;
                }
            }
        } else {
            if (file_exists($modDir.$relativePath)) {
                return $modDir.$relativePath;
            }
        }

        Logger::addLog("Elasticsearch module error: Unable to find Elasticsearch template file `$relativePath`");

        return '';
    }

    /**
     * Get stop word lang array for the given iso code
     *
     * @param string $isoCode
     *
     * @return mixed
     */
    public static function getStopWordLang($isoCode)
    {
        if (isset(static::$stopWordLangs[$isoCode])) {
            return static::$stopWordLangs[$isoCode];
        }

        return static::$stopWordLangs['en'];
    }

    /**
     * @param string $query
     *
     * @return string
     */
    public static function jsonEncodeQuery($query)
    {
        return str_replace("\n", '', $query);
    }

    /**
     * Index remaining products
     *
     * @param int $chunks
     * @param int $idShop
     */
    public function cronProcessRemainingProducts($chunks, $idShop)
    {
        /** @var Client $client */
        $client = static::getWriteClient();
        if (!$client) {
            die(json_encode([
                'success' => false,
            ]));
        }
        $amount = (int) (Configuration::get(static::INDEX_CHUNK_SIZE) ?: 100);
        if (!$amount) {
            $amount = 100;
        }
        $index = Configuration::get(static::INDEX_PREFIX);
        $idLang = Context::getContext()->language->id;
        $metas = Meta::getAllMetas([$idLang]);
        if (isset($metas[$idLang])) {
            $metas = $metas[$idLang];
        }
        $priceTaxExclAlias = static::getAlias('price_tax_excl');

        while ($chunks > 0) {
            // Check which products are available for indexing
            $products = IndexStatus::getProductsToIndex($amount, 0, null, $idShop);

            if (empty($products)) {
                // Nothing to index -- cron job done
                exit(0);
            }

            $params = [
                'body' => [],
            ];
            foreach ($products as &$product) {
                $params['body'][] = [
                    'index' => [
                        '_index' => "{$index}_{$idShop}_{$product->elastic_id_lang}",
                        '_type'  => 'product',
                        '_id'     => $product->id,
                    ],
                ];

                // Process prices for customer groups
                foreach ($product->{$priceTaxExclAlias} as $group => $value) {
                    $product->{"{$priceTaxExclAlias}_{$group}"} = $value;
                }
                unset($product->{$priceTaxExclAlias});

                // Make aggregatable copies of the properties
                // These need to be `link_rewrite`d to make sure they can fit a the friendly URL
                foreach (get_object_vars($product) as $name => $var) {
                    // Do not create an aggregatable copy for color codes
                    // Color codes are meta data for aggregations
                    if (substr($name, -11) === '_color_code') {
                        continue;
                    }

                    if (isset($metas[$name]) && in_array($metas[$name]['elastic_type'], ['string', 'text'])) {
                        if (is_array($var)) {
                            foreach ($var as &$item) {
                                try {
                                    $item = Tools::link_rewrite($item);
                                } catch (\PrestaShopException $e) {
                                    \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

                                    continue;
                                }
                            }
                        } else {
                            try {
                                $var = Tools::link_rewrite($var);
                            } catch (\PrestaShopException $e) {
                                \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

                                continue;
                            }
                        }
                    }
                }

                $params['body'][] = $product;
            }

            // Push to Elasticsearch
            try {
                $results = $client->bulk($params);
            } catch (Exception $exception) {
                exit(1);
            }
            $failed = [];
            foreach ($results['items'] as $result) {
                if ((int) substr($result['index']['status'], 0, 1) !== 2) {
                    preg_match('/(?P<index>[a-zA-Z]+)\_(?P<id_shop>\d+)\_(?P<id_lang>\d+)/', $result['index']['_index'], $details);
                    $failed[] = [
                        'id_lang'    => (int) $details['id_lang'],
                        'id_shop'    => (int) $details['id_shop'],
                        'id_product' => (int) $result['index']['_id'],
                        'error'      => isset($result['index']['error']['reason']) ? $result['index']['error']['reason'].(isset($result['index']['error']['caused_by']['reason']) ? ' '.$result['index']['error']['caused_by']['reason'] : '') : 'Unknown error',
                    ];
                }
            }
            if (!empty($failed)) {
                foreach ($failed as $failure) {
                    foreach ($products as $index => $product) {
                        if ((int) $product->id === (int) $failure['id_product']
                            && (int) $product->elastic_id_shop === (int) $failure['id_shop']
                            && (int) $product->elastic_id_lang === (int) $failure['id_lang']
                        ) {
                            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_."tb2vuestorefront_index_status` (`id_product`,`id_lang`,`id_shop`, `error`) VALUES ('{$failed['id_product']}', '{$failed['id_lang']}', '{$failed['id_shop']}', '{$failed['error']}') ON DUPLICATE KEY UPDATE `error` = VALUES(`error`)");

                            unset($products[$index]);
                        }
                    }
                }
            }

            // Insert index status into database
            $dateUpdAlias = static::getAlias('date_upd');
            $values = '';
            foreach ($products as &$product) {
                $values .= "('{$product->id}', '{$product->elastic_id_lang}', '{$this->context->shop->id}', '{$product->{$dateUpdAlias}}', ''),";
            }
            $values = rtrim($values, ',');
            if ($values) {
                Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_."tb2vuestorefront_index_status` (`id_product`,`id_lang`,`id_shop`, `date_upd`, `error`) VALUES $values ON DUPLICATE KEY UPDATE `date_upd` = VALUES(`date_upd`), `error` = ''");
            }

            $chunks--;
        }

        exit(0);
    }


    /**
     * Index remaining products
     *
     * @param int $chunks
     * @param int $idShop
     */
    public function cronProcessRemaining($chunks, $idShop)
    {
        /** @var Client $client */
        $client = static::getWriteClient();
        if (!$client) {
            die(json_encode([
                'success' => false,
            ]));
        }
        $amount = (int) (Configuration::get(static::INDEX_CHUNK_SIZE) ?: 100);
        if (!$amount) {
            $amount = 100;
        }
        $index = Configuration::get(static::INDEX_PREFIX);

        while ($chunks > 0) {
            // Check which products are available for indexing
            $products = IndexStatus::getProductsToIndex($amount, 0, null, $idShop);

            if (empty($products)) {
                // Nothing to index -- cron job done
                exit(0);
            }

            $params = [
                'body' => [],
            ];
            foreach ($products as &$product) {
                $params['body'][] = [
                    'index' => [
                        '_index' => "{$index}_{$idShop}_{$product->elastic_id_lang}",
                        '_type'  => 'product',
                        '_id'     => $product->id,
                    ],
                ];

                // Process prices for customer groups
                foreach ($product->{$priceTaxExclAlias} as $group => $value) {
                    $product->{"{$priceTaxExclAlias}_{$group}"} = $value;
                }
                unset($product->{$priceTaxExclAlias});

                // Make aggregatable copies of the properties
                // These need to be `link_rewrite`d to make sure they can fit a the friendly URL
                foreach (get_object_vars($product) as $name => $var) {
                    // Do not create an aggregatable copy for color codes
                    // Color codes are meta data for aggregations
                    if (substr($name, -11) === '_color_code') {
                        continue;
                    }

                    if (isset($metas[$name]) && in_array($metas[$name]['elastic_type'], ['string', 'text'])) {
                        if (is_array($var)) {
                            foreach ($var as &$item) {
                                try {
                                    $item = Tools::link_rewrite($item);
                                } catch (\PrestaShopException $e) {
                                    \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

                                    continue;
                                }
                            }
                        } else {
                            try {
                                $var = Tools::link_rewrite($var);
                            } catch (\PrestaShopException $e) {
                                \Logger::addLog("Elasticsearch module error: {$e->getMessage()}");

                                continue;
                            }
                        }
                    }
                }

                $params['body'][] = $product;
            }

            // Push to Elasticsearch
            try {
                $results = $client->bulk($params);
            } catch (Exception $exception) {
                exit(1);
            }
            $failed = [];
            foreach ($results['items'] as $result) {
                if ((int) substr($result['index']['status'], 0, 1) !== 2) {
                    preg_match('/(?P<index>[a-zA-Z]+)\_(?P<id_shop>\d+)\_(?P<id_lang>\d+)/', $result['index']['_index'], $details);
                    $failed[] = [
                        'id_lang'    => (int) $details['id_lang'],
                        'id_shop'    => (int) $details['id_shop'],
                        'id_product' => (int) $result['index']['_id'],
                        'error'      => isset($result['index']['error']['reason']) ? $result['index']['error']['reason'].(isset($result['index']['error']['caused_by']['reason']) ? ' '.$result['index']['error']['caused_by']['reason'] : '') : 'Unknown error',
                    ];
                }
            }
            if (!empty($failed)) {
                foreach ($failed as $failure) {
                    foreach ($products as $index => $product) {
                        if ((int) $product->id === (int) $failure['id_product']
                            && (int) $product->elastic_id_shop === (int) $failure['id_shop']
                            && (int) $product->elastic_id_lang === (int) $failure['id_lang']
                        ) {
                            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_."tb2vuestorefront_index_status` (`id_product`,`id_lang`,`id_shop`, `error`) VALUES ('{$failed['id_product']}', '{$failed['id_lang']}', '{$failed['id_shop']}', '{$failed['error']}') ON DUPLICATE KEY UPDATE `error` = VALUES(`error`)");

                            unset($products[$index]);
                        }
                    }
                }
            }

            // Insert index status into database
            $dateUpdAlias = static::getAlias('date_upd');
            $values = '';
            foreach ($products as &$product) {
                $values .= "('{$product->id}', '{$product->elastic_id_lang}', '{$this->context->shop->id}', '{$product->{$dateUpdAlias}}', ''),";
            }
            $values = rtrim($values, ',');
            if ($values) {
                Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_."tb2vuestorefront_index_status` (`id_product`,`id_lang`,`id_shop`, `date_upd`, `error`) VALUES $values ON DUPLICATE KEY UPDATE `date_upd` = VALUES(`date_upd`), `error` = ''");
            }

            $chunks--;
        }

        exit(0);
    }


    /**
     * Install the database tables for this module
     *
     * @return bool
     */
    protected function installDB()
    {
        if (!file_exists(__DIR__.'/sql/install.sql')) {
            return false;
        } elseif (!$sql = file_get_contents(__DIR__.'/sql/install.sql')) {
            return false;
        }
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $sql = preg_split("/;\s*[\r\n]+/", trim($sql));

        foreach ($sql as $query) {
            try {
                if (!Db::getInstance()->execute(trim($query))) {
                    return false;
                }
            } catch (PrestaShopException $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    protected function getConfigFormValues()
    {
        $stopWords = [];
        foreach (Language::getLanguages(true) as $language) {
            $idLang = (int) $language['id_lang'];
            $stopWords[$idLang] = (string) Configuration::get(static::STOP_WORDS, $idLang);
        }


        return [
            static::LOGGING_ENABLED         => (int) Configuration::get(static::LOGGING_ENABLED),
            static::PROXY                   => (int) Configuration::get(static::PROXY),
            static::SERVERS                 => (array) json_decode(Configuration::get(static::SERVERS), true),
            static::SHARDS                  => (int) Configuration::get(static::SHARDS),
            static::REPLICAS                => (int) Configuration::get(static::REPLICAS),
            static::METAS                   => Meta::getAllProperties((int) Configuration::get('PS_LANG_DEFAULT')),
            static::INDEX_PREFIX            => Configuration::get(static::INDEX_PREFIX),
            static::QUERY_JSON              => Configuration::get(static::QUERY_JSON),
            static::PRODUCT_LIST            => Configuration::get(static::PRODUCT_LIST),
            static::DEFAULT_TAX_RULES_GROUP => Configuration::get(static::DEFAULT_TAX_RULES_GROUP),
            static::STOP_WORDS              => $stopWords,
            static::BLACKLISTED_FIELDS      => Configuration::get(static::BLACKLISTED_FIELDS),
            static::REPLACE_NATIVE_PAGES    => (int) Configuration::get(static::REPLACE_NATIVE_PAGES),
            static::SEARCH_SUBCATEGORIES    => (int) Configuration::get(static::SEARCH_SUBCATEGORIES),
            static::INSTANT_SEARCH          => (int) Configuration::get(static::INSTANT_SEARCH),
            static::AUTOCOMPLETE            => (int) Configuration::get(static::AUTOCOMPLETE),
        ];
    }

    /**
     * Get Tb2vuestorefront version
     *
     * @return string
     */
    protected function getElasticVersion()
    {
        try {
            $client = static::getWriteClient();
        } catch (Exception $e) {
            $context = Context::getContext();
            if (isset($context->employee->id) && $context->employee->id) {
                $context->controller->errors[] = sprintf(
                    $this->l('Unable to initialize Elasticsearch: %s'),
                    strip_tags($e->getMessage())
                );
            }
        }

        if (isset($client)) {
            try {
                $stats = $client->cluster()->stats();

                if (isset($stats['nodes']['versions'])) {
                    $clusterStats = $client->cluster()->stats();

                    return (string) min($clusterStats['nodes']['versions']);
                }
            } catch (Exception $e) {
                $context = Context::getContext();
                if (isset($context->employee->id) && $context->employee->id) {
                    $context->controller->errors[] = sprintf(
                        $this->l('Unable to initialize Elasticsearch: %s'),
                        strip_tags($e->getMessage())
                    );
                }
            }
        }

        return $this->l('Unknown');
    }

    /**
     * @return null|array
     * @throws PrestaShopException
     */
    protected function getFixedFilter()
    {
        if (!Configuration::get(static::REPLACE_NATIVE_PAGES)) {
            return null;
        }

        $idLang = (int) Context::getContext()->language->id;
        $controller = Context::getContext()->controller;
        if ($controller instanceof CategoryControllerCore) {
            $category = $controller->getCategory();

            if (Validate::isLoadedObject($category)) {
                if (!Configuration::get(static::SEARCH_SUBCATEGORIES)) {
                    return [
                        'aggregationCode' => static::getAlias('category'),
                        'aggregationName' => Meta::getName(static::getAlias('category'), $idLang),
                        'filterCode'      => Tools::link_rewrite($category->name),
                        'filterName'      => $category->name,
                    ];
                }

                $categoryPath = \Tb2VueStorefrontModule\ProductFetcher::getCategoryPath($category->id, $idLang);

                return [
                    'aggregationCode' => static::getAlias('categories'),
                    'aggregationName' => Meta::getName(static::getAlias('category'), $idLang),
                    'filterCode'      => Tools::link_rewrite($categoryPath),
                    'filterName'      => $category->name,
                ];
            }
        } elseif ($controller instanceof ManufacturerControllerCore) {
            $manufacturer = $controller->getManufacturer();

            if (Validate::isLoadedObject($manufacturer)) {
                return [
                    'aggregationCode' => static::getAlias('manufacturer'),
                    'aggregationName' => Meta::getName(static::getAlias('manufacturer'), $idLang),
                    'filterCode'      => Tools::link_rewrite($manufacturer->name),
                    'filterName'      => $manufacturer->name,
                ];
            }
        } elseif ($controller instanceof SupplierControllerCore) {
            $supplier = $controller->getSupplier();

            if (Validate::isLoadedObject($supplier)) {
                return [
                    'aggregationCode' => static::getAlias('supplier'),
                    'aggregationName' => Meta::getName(static::getAlias('supplier'), Context::getContext()->language->id),
                    'filterCode'      => Tools::link_rewrite($supplier->name),
                    'filterName'      => $supplier->name,
                ];
            }
        }

        return null;
    }

    /**
     * @param string $url
     * @param bool   $decode
     *
     * @return mixed
     *
     * @source http://nadeausoftware.com/articles/2008/05/php_tip_how_parse_and_build_urls
     */
    protected static function splitUrl($url, $decode = true)
    {
        $xunressub = 'a-zA-Z\d\-._~\!$&\'()*+,;=';
        $xpchar = $xunressub.':@%';

        $xscheme = '([a-zA-Z][a-zA-Z\d+-.]*)';

        $xuserinfo = '((['.$xunressub.'%]*)'.'(:(['.$xunressub.':%]*))?)';

        $xipv4 = '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';

        $xipv6 = '(\[([a-fA-F\d.:]+)\])';

        $xhostName = '([a-zA-Z\d-.%]+)';

        $xhost = '('.$xhostName.'|'.$xipv4.'|'.$xipv6.')';
        $xport = '(\d*)';
        $xauthority = '(('.$xuserinfo.'@)?'.$xhost.'?(:'.$xport.')?)';

        $xslashSeg = '(/['.$xpchar.']*)';
        $xpathAuthabs = '((//'.$xauthority.')((/['.$xpchar.']*)*))';
        $xpathRel = '(['.$xpchar.']+'.$xslashSeg.'*)';
        $xpathAbs = '(/('.$xpathRel.')?)';
        $xapath = '('.$xpathAuthabs.'|'.$xpathAbs.'|'.$xpathRel.')';

        $xqueryfrag = '(['.$xpchar.'/?'.']*)';

        $xurl = '^('.$xscheme.':)?'.$xapath.'?'.'(\?'.$xqueryfrag.')?(#'.$xqueryfrag.')?$';


        // Split the URL into components.
        if (!preg_match('!'.$xurl.'!', $url, $m)) {
            return false;
        }

        if (!empty($m[2])) {
            $parts['scheme'] = strtolower($m[2]);
        }

        if (!empty($m[7])) {
            if (isset($m[9])) {
                $parts['user'] = $m[9];
            } else {
                $parts['user'] = '';
            }
        }
        if (!empty($m[10])) {
            $parts['pass'] = $m[11];
        }

        if (!empty($m[13])) {
            $h = $parts['host'] = $m[13];
        } elseif (!empty($m[14])) {
            $parts['host'] = $m[14];
        } elseif (!empty($m[16])) {
            $parts['host'] = $m[16];
        } elseif (!empty($m[5])) {
            $parts['host'] = '';
        }
        if (!empty($m[17])) {
            $parts['port'] = $m[18];
        }

        if (!empty($m[19])) {
            $parts['path'] = $m[19];
        } elseif (!empty($m[21])) {
            $parts['path'] = $m[21];
        } elseif (!empty($m[25])) {
            $parts['path'] = $m[25];
        }

        if (!empty($m[27])) {
            $parts['query'] = $m[28];
        }
        if (!empty($m[29])) {
            $parts['fragment'] = $m[30];
        }

        if (!$decode) {
            return $parts;
        }
        if (!empty($parts['user'])) {
            $parts['user'] = rawurldecode($parts['user']);
        }
        if (!empty($parts['pass'])) {
            $parts['pass'] = rawurldecode($parts['pass']);
        }
        if (!empty($parts['path'])) {
            $parts['path'] = rawurldecode($parts['path']);
        }
        if (isset($h)) {
            $parts['host'] = rawurldecode($parts['host']);
        }
        if (!empty($parts['query'])) {
            $parts['query'] = rawurldecode($parts['query']);
        }
        if (!empty($parts['fragment'])) {
            $parts['fragment'] = rawurldecode($parts['fragment']);
        }

        return $parts;
    }

    /**
     * @param array $parts
     * @param bool  $encode
     *
     * @return string
     *
     * @source http://nadeausoftware.com/articles/2008/05/php_tip_how_parse_and_build_urls
     */
    protected static function joinUrl($parts, $encode = true)
    {
        if ($encode) {
            if (isset($parts['user'])) {
                $parts['user'] = rawurlencode($parts['user']);
            }
            if (isset($parts['pass'])) {
                $parts['pass'] = rawurlencode($parts['pass']);
            }
            if (isset($parts['host']) &&
                !preg_match('!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts['host'])) {
                $parts['host'] = rawurlencode($parts['host']);
            }
            if (!empty($parts['path'])) {
                $parts['path'] = preg_replace('!%2F!ui', '/', rawurlencode($parts['path']));
            }
            if (isset($parts['query'])) {
                $parts['query'] = rawurlencode($parts['query']);
            }
            if (isset($parts['fragment'])) {
                $parts['fragment'] = rawurlencode($parts['fragment']);
            }
        }

        $url = '';
        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'].':';
        }
        if (isset($parts['host'])) {
            $url .= '//';
            if (isset($parts['user'])) {
                $url .= $parts['user'];
                if (isset($parts['pass'])) {
                    $url .= ':'.$parts['pass'];
                }
                $url .= '@';
            }
            if (preg_match('!^[\da-f]*:[\da-f.:]+$!ui', $parts['host'])) {
                $url .= '['.$parts['host'].']';
            } // IPv6
            else {
                $url .= $parts['host'];
            }             // IPv4 or name
            if (isset($parts['port'])) {
                $url .= ':'.$parts['port'];
            }
            if (!empty($parts['path']) && $parts['path'][0] != '/') {
                $url .= '/';
            }
        }
        if (!empty($parts['path'])) {
            $url .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }
        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }

    /**
     * @param string $code
     * @param string $type
     *
     * @return string
     * @throws PrestaShopException
     */
    public static function getAlias($code, $type = 'property')
    {
        return (string) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new \DbQuery())
                ->select('`alias`')
                ->from(bqSQL(Meta::$definition['table']))
                ->where('`code` = \''.$code.'\'')
                ->where('`meta_type` = \''.pSQL($type).'\'')
        );
    }

    /**
     * @param string[] $codes
     * @param string   $type
     *
     * @return string[]
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getAliases($codes, $type = 'property')
    {
        if (!is_array($codes) || !count($codes)) {
            return [];
        }

        $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new \DbQuery())
                ->select('`code`, `alias`')
                ->from(bqSQL(Meta::$definition['table']))
                ->where('`code` IN (\''.implode('\',\'', array_map('pSQL', $codes)).'\')')
                ->where('`meta_type` = \''.pSQL($type).'\'')
        );

        if (!is_array($results)) {
            return $results;
        }

        return array_combine(array_column($results, 'code'), array_column($results, 'alias'));
    }


    /**
     * @param string $className
     * @param int $idShop
     * @return bool
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function addIndex($className, $idShop)
    {
        $index = new \Tb2VueStorefrontModule\EntityType();
        $index->class_name = $className;
        $index->id_shop = $idShop;
        return $index->add();
    }

    public static function deleteIndexByName($className)
    {

    }

    /**
     * @param string $className
     * @param string $indexName
     * @param int idShop
     * @return bool
     */
    public static function registerIndex($className, $indexName, $idShop)
    {
        return ElasticIndex::addEntityType($className, $indexName, $idShop);
    }


    /**
     * @param $className
     * @param $idShop
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function unregisterIndex($className, $idShop)
    {
        return ElasticIndex::deleteEntityType($className, $idShop);
    }

    public static function indexObject($className, $entityId)
    {

    }
}
