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

if (!defined('_PS_VERSION_')) {
    if (php_sapi_name() !== 'cli') {
        exit;
    } else {
        $first = true;
        foreach ($argv as $arg) {
            if ($first) {
                $first = false;
                continue;
            }

            $arg = substr($arg, 2); // --
            $e = explode('=', $arg);
            if (count($e) == 2) {
                $_GET[$e[0]] = $e[1];
            } else {
                $_GET[$e[0]] = true;
            }
        }
        $_GET['module'] = 'cronjobs';
        $_GET['fc'] = 'module';
        $_GET['controller'] = 'cron';

        require_once __DIR__.'/../../../../config/config.inc.php';
        require_once __DIR__.'/../../tb2vuestorefront.php';
    }
}

/**
 * Class ElasticsearchcronModuleFrontController
 */
class ElasticsearchcronModuleFrontController extends ModuleFrontController
{
    /**
     * Run the cron job
     *
     * ElasticsearchcronModuleFrontController constructor.
     */
    public function __construct()
    {
        // Use admin user for indexing
        Context::getContext()->employee = new Employee(Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new DbQuery())
                ->select('`'.bqSQL(Employee::$definition['primary']).'`')
                ->from(bqSQL(Employee::$definition['table']))
                ->where('`id_profile` = 1')
        ));

        if (isset($_GET['id_shop'])) {
            $idShop = (int) $_GET['id_shop'];
        } else {
            $idShop = Context::getContext()->shop->id;
        }

        if (isset($_GET['clear'])) {
            try {
                // Delete the indices first
                Tb2vuestorefrontModule\Indexer::eraseIndices(null, [$idShop]);

                // Reset the mappings
                Tb2vuestorefrontModule\Indexer::createMappings(null, [$idShop]);

                // Erase the index status for the current store
                Tb2vuestorefrontModule\IndexStatus::erase($idShop);
            } catch (Exception $e) {
            }
        }

        $chunks = INF;
        if (isset($_GET['chunks'])) {
            $chunks = (int) $_GET['chunks'];
        }

        /** @var Elasticsearch $module */
        $module = Module::getInstanceByName('tb2vuestorefront');
        $module->cronProcessRemainingProducts($chunks, $idShop);
//        \Tb2VueStorefrontModule\Indexer::createMappings(null, [1]);





        $indices = [
            ['Tb2VueStorefrontModule\\AttributeFetcher', 'attribute'],
            ['Tb2VueStorefrontModule\\CategoryFetcher', 'category'],
            ['Tb2VueStorefrontModule\\CmsCategoryFetcher', 'cmscategory'],
            ['Tb2VueStorefrontModule\\CmsFetcher', 'cms'],
            ['Tb2VueStorefrontModule\\ManufacturerFetcher', 'manufacturer'],
            ['Tb2VueStorefrontModule\\ProductFetcher', 'product'],
            ['Tb2VueStorefrontModule\\TaxRuleFetcher', 'taxrule'],
        ];
        foreach ($indices as $i) {
            var_dump($i);
            foreach (Shop::getShops() as $shop) {
                    var_dump(new Tb2VueStorefrontModule\EntityType());
                $index = new Tb2VueStorefrontModule\EntityType();
                $index->entity_name = $i[1];
                $index->class_name = $i[0];
                $index->id_shop = $shop['id_shop'];
                $index->enabled = true;
                var_dump($index);
                echo "test";
                $index->add();
            }
        }






    }
}

if (php_sapi_name() === 'cli') {
    new ElasticsearchcronModuleFrontController();
}
