<?php

namespace Kemper\Elastic\Subscriber;

use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Storage\Entity\Content;
use Bolt\Storage\Mapping\MetadataDriver;
use Carbon\Carbon;
use Elasticsearch\Client;
use Kemper\Elastic\Config\Config;
use Kemper\Elastic\Service\ElasticService;
use Monolog\Logger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class StorageSubscriber
 * @package Kemper\Elastic\Subscriber
 * @author Kemper Web Team <webmaster@kcpag.com>
 */
class StorageSubscriber implements EventSubscriberInterface
{
    /** @var Config $config */
    private $config;

    /** @var ElasticService $elasticService */
    private $elasticService;

    /** @var Logger $logger */
    private $logger;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            StorageEvents::POST_DELETE => [
                ['postDelete', 0],
            ],
            StorageEvents::POST_SAVE   => [
                ['postSave', 0],
            ]
        ];
    }

    /**
     * StorageSubscriber constructor.
     *
     * @param Config $config
     * @param ElasticService $elasticService
     * @param Logger $logger
     */
    public function __construct(Config $config, ElasticService $elasticService, Logger $logger)
    {
        $this->config         = $config;
        $this->elasticService = $elasticService;
        $this->logger         = $logger;
    }

    /**
     * @param StorageEvent $event
     */
    public function postDelete(StorageEvent $event)
    {
        $content     = $event->getContent();
        $contentType = $event->getContentType();
        $id          = $event->getContent()->getId();

        if ($this->config->isSearchable($contentType)) {
            $response = $this->elasticService->deleteIndex($content);

            if ($response !== null && $response['result'] === 'deleted') {
                $this->log(Logger::INFO, 'Deleted ' . $contentType . ': ' . $id);
            } else {
                $this->log(Logger::CRITICAL, 'Failed to delete ' . $contentType . ' with ID: ' . $id);
            }
        }
    }

    /**
     * @param StorageEvent $event
     */
    public function postSave(StorageEvent $event)
    {
        $contentType = $event->getContentType();
        $id          = $event->getContent()->getId();

        if ($this->config->isSearchable($contentType)) {
            $response = $this->elasticService->saveIndex($event->getContent());

            if ($response['result'] === 'updated') {
                $this->log(Logger::INFO, 'Saved ' . $contentType . ': ' . $id);
            } elseif ($response['result'] === 'created') {
                $this->log(Logger::INFO, 'Created ' . $contentType . ': ' . $id);
            } else {
                $this->log(Logger::CRITICAL, 'Failed to save ' . $contentType . ': ' . $id);
            }
        }
    }

    /**
     * @param $level
     * @param $message
     */
    protected function log($level, $message)
    {
        $this->logger->log($level, $message, ['event' => 'elasticsearch']);
    }
}
