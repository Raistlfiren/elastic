<?php

namespace Kemper\Elastic\Controller;

use Bolt\Controller\Base;
use Bolt\Logger\FlashLogger;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\Storage\Query\Query;
use Carbon\Carbon;
use Elasticsearch\Client;
use Kemper\Elastic\Config\Config;
use Kemper\Elastic\Service\ElasticService;
use Monolog\Logger;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ElasticController
 * @package Kemper\Elastic\Controller
 * @author KemperWebTeam <webmaster@kcpag.com>
 */
class ElasticController extends Base
{
    /** @var ElasticService $elasticService */
    private $elasticService;

    /**
     * ElasticController constructor.
     *
     * @param ElasticService $elasticService
     */
    public function __construct(ElasticService $elasticService)
    {
        $this->elasticService = $elasticService;
    }

    /**
     * @param ControllerCollection $controller
     *
     * @return ControllerCollection
     */
    public function addRoutes(ControllerCollection $controller)
    {
        $controller->match('/manage', [$this, 'manage'])
                   ->bind('elastic.manage');

        return $controller;
    }

    /**
     * @param Request $request
     *
     * @return \Bolt\Response\TemplateResponse|\Bolt\Response\TemplateView
     */
    public function manage(Request $request)
    {
        $isESAvailable = $this->elasticService->isAvailable();

        $data = [
            'isESAvailable'    => $isESAvailable,
            'isIndexAvailable' => false,
            'mappings'         => []
        ];

        if ($isESAvailable) {
            $data['isIndexAvailable'] = $this->elasticService->doesIndexExist();

            if ($data['isIndexAvailable']) {
                $data['mappings'] = $this->elasticService->getMappings();
            }
        }

        if ($request->isMethod('POST')) {
            $this->elasticService->importData();

            $data['mappings'] = $this->elasticService->getMappings();

            $data['debugging'] = $this->elasticService->getDebugging();

            return $this->render('manage.html.twig', $data);
        }

        return $this->render('manage.html.twig', $data);
    }
}
