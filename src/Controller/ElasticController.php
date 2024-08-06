<?php

namespace Kemper\Elastic\Controller;

use Bolt\Controller\Base;
use Kemper\Elastic\Config\Config;
use Kemper\Elastic\Service\ElasticService;
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

    /** @var Config $config */
    private $config;

    /**
     * ElasticController constructor.
     *
     * @param ElasticService $elasticService
     * @param Config $config
     */
    public function __construct(ElasticService $elasticService, Config $config)
    {
        $this->elasticService = $elasticService;
        $this->config = $config;
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
            'mappings'         => [],
            'version'          => $this->config->getVersion()
        ];

        if ($isESAvailable) {
            $data['isIndexAvailable'] = $this->elasticService->doesIndecesExist();

//            if ($data['isIndexAvailable']) {
//                $data['mappings'] = $this->elasticService->getMappings();
//            }
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
