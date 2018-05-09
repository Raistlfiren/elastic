<?php

namespace Kemper\Elastic;

use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Elasticsearch\ClientBuilder;
use Kemper\Elastic\Service\ElasticService;
use Kemper\Elastic\Subscriber\StorageSubscriber;
use Silex\Application;
use Kemper\Elastic\Controller\ElasticController;
use Kemper\Elastic\Config\Config;

/**
 * ElasticExtension extension class.
 *
 * @author KemperWebTeam <webmaster@kcpag.com>
 */
class ElasticExtension extends SimpleExtension
{
    /**
     * @param Application $app
     */
    protected function registerServices(Application $app)
    {
        $config = $this->getConfig();

        $app['elastic.config'] = $app->share(
            function ($app) use ($config) {
                return new Config($app, $config);
            }
        );

        $app['elastic.client'] = $app->share(
            function ($app) {
                return ClientBuilder::create()
                                    ->setHosts($app['elastic.config']->getHosts())
                                    ->build();
            }
        );

        $app['elastic.service'] = $app->share(
            function ($app) {
                return new ElasticService($app['elastic.client'], $app['elastic.config'], $app['query']);
            }
        );
    }

    /**
     * @return array
     */
    protected function registerTwigPaths()
    {
        return ['templates'];
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        parent::boot($app);

        $dispatcher = $this->container['dispatcher'];

        $storageSubscriber = new StorageSubscriber(
            $app['elastic.config'],
            $app['elastic.service'],
            $app['logger.system']
        );

        $dispatcher->addSubscriber($storageSubscriber);
    }

    /**
     * @return array|MenuEntry[]
     */
    protected function registerMenuEntries()
    {
        $menu = MenuEntry::create('elastic-manage', 'elastic')
                         ->setLabel('ES Status')
                         ->setIcon('fa:search')
                         ->setPermission('admin')
                         ->setRoute('elastic.manage');

        return [
            $menu,
        ];
    }

    /**
     * @return array
     */
    protected function registerBackendControllers()
    {
        $app = $this->getContainer();

        return [
            '/elastic' => new ElasticController(
                $app['elastic.service']
            ),
        ];
    }
}
