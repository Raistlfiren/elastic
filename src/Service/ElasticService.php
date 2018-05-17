<?php

namespace Kemper\Elastic\Service;

use Bolt\Storage\Entity\Content;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\Storage\Query\Query;
use Carbon\Carbon;
use Elasticsearch\Client;
use Kemper\Elastic\Config\Config;

/**
 * Class ElasticService
 * @package Kemper\Elastic\Service
 * @author Kemper Web Team <webmaster@kcpag.com>
 */
class ElasticService
{
    /** @var Client $client */
    protected $client;

    /** @var Config $config */
    protected $config;

    /** @var array $params */
    protected $params = [];

    /** @var Query $query */
    protected $query;

    /** @var MetadataDriver $metadata */
    protected $metadata;

    /** @var array $debugging */
    protected $debugging = [];

    /**
     * ElasticService constructor.
     *
     * @param Client $client
     * @param Config $config
     * @param Query $query
     * @param MetadataDriver $metadata
     */
    public function __construct(Client $client, Config $config, Query $query, MetadataDriver $metadata)
    {
        $this->client = $client;
        $this->config = $config;
        $this->query  = $query;
        $this->metadata  = $metadata;
    }

    /**
     * @return bool
     */
    public function isAvailable()
    {
        return $this->client->ping();
    }

    /**
     * @return array
     */
    public function getMappings()
    {
        $this->resetParams();

        return $this->client->indices()->getMapping($this->params);
    }

    /**
     * @return bool
     */
    public function doesIndexExist()
    {
        $this->resetParams();

        return $this->client->indices()->exists($this->params);
    }

    public function createIndex()
    {
        $this->resetParams();

        $this->params['body'] = $this->config->getIndexSettings();
        $response             = $this->client->indices()->create($this->params);

        if ($response['acknowledged']) {
            $this->debugging[] = '<p>Successfully created <code>' . $this->config->getIndex() . '</code> index.</p>';
        } else {
            $this->debugging[] = '<p>Error while creating <code>' . $this->config->getIndex() . '</code> index.</p>';
        }
    }

    public function recreateIndex()
    {
        $this->resetParams();

        if ($this->doesIndexExist()) {
            $response = $this->client->indices()->delete($this->params);

            if ($response['acknowledged']) {
                $this->debugging[] =
                    '<p>Successfully deleted <code>' . $this->config->getIndex() . '</code> index.</p>';
            } else {
                $this->debugging[] =
                    '<p>Error while deleting <code>' . $this->config->getIndex() . '</code> index.</p>';
            }
        }

        $this->createIndex();
    }

    public function importData()
    {
        $this->recreateIndex();

        $this->createMappings();

        $this->loadContent();
    }

    protected function createMappings()
    {
        foreach ($this->config->getContentTypes() as $contentType => $contentTypeMeta) {
            $this->resetParams();
            $this->params['body'] = [];

            $this->params['type']             = $contentType;

            $properties = [];

            foreach ($this->metadata->getClassMetadata($contentType)['fields'] as $field => $meta) {
                $userMappings = $this->config->getTypeMapping($contentType);

                $defaultMapping = [];

                if ($meta['type'] === 'datetime') {
                    $defaultMapping['type']   = 'date';
                    $defaultMapping['format'] = 'YYYY-MM-dd HH:mm:ss';
                }

                if (isset($userMappings[$field])) {
                    $properties[$field] = $userMappings[$field];
                } else {
                    $properties[$field] = $defaultMapping;
                }
            }

            $mapping = [
                '_source'    => [
                    'enabled' => true
                ],
                'properties' => $properties
            ];

            $this->params['body'][$contentType] = $mapping;
            $response                           = $this->client->indices()->putMapping($this->params);

            if ($response['acknowledged']) {
                $this->debugging[] =
                    '<p>Successfully added mapping for <code>' . $contentType . '</code>.</p>';
            } else {
                $this->debugging[] =
                    '<p>Error while adding mapping for <code>' . $contentType . '</code>.</p>';
            }
        }
    }

    protected function loadContent()
    {
        foreach ($this->config->getContentTypes() as $contentType => $contentTypeMeta) {
            $this->resetParams();

            $this->params['type'] = $contentType;

            $records = $this->query->getContent($contentType);

            foreach ($records as $record) {
                $this->params['id'] = $record['id'];
                foreach ($this->metadata->getClassMetadata($contentType)['fields'] as $field => $meta) {
                    if ($meta['type'] === 'datetime') {
                        if ($record[$field] instanceof Carbon) {
                            $this->params['body'][$field] = $record[$field]->format('Y-m-d H:i:s');
                        } else {
                            $this->params['body'][$field] = null;
                        }
                    } elseif ($meta['type'] === 'null') {
                        $this->params['body'][$field] = null;
                    } else {
                        $this->params['body'][$field] = $record[$field];
                    }
                }

                $this->client->index($this->params);
            }

            $this->debugging[] =
                '<p>Imported ' . count($records) . ' for <code>' . $contentType . '</code>.</p>';
        }
    }

    /**
     * @param Content $content
     *
     * @return array
     */
    public function saveIndex(Content $content)
    {
        $this->compileRequest($content->getContenttype(), $content->getId());

        $data = $this->prepareData($content);

        if ($this->client->exists($this->params)) {
            $this->params['body']['doc'] = $data;

            return $this->client->update($this->params);
        }

        $this->params['body'] = $data;

        return $this->client->index($this->params);
    }

    /**
     * @param Content $content
     *
     * @return array
     */
    protected function prepareData(Content $content)
    {
        $contentType = (string)$content->getContenttype();

        $fields = $this->config->getContentTypes()[$contentType]['fields'];

        $data = [];

        foreach ($fields as $field => $meta) {
            if ($meta['type'] === 'datetime') {
                if ($content[$field] instanceof Carbon) {
                    $data[$field] = $content[$field]->format('Y-m-d H:i:s');
                } else {
                    $data[$field] = null;
                }
            } elseif ($meta['type'] === 'null') {
                $data[$field] = null;
            } else {
                $data[$field] = $content[$field];
            }
        }

        return $data;
    }

    /**
     * @param Content $content
     *
     * @return array|null
     */
    public function deleteIndex(Content $content)
    {
        $this->compileRequest($content->getContenttype(), $content->getId());

        if ($this->client->exists($this->params)) {
            return $this->client->delete($this->params);
        }

        return null;
    }

    /**
     * @param $contentType
     * @param $id
     */
    protected function compileRequest($contentType, $id)
    {
        $this->resetParams();

        $this->params['type'] = $contentType;
        $this->params['id']   = $id;
    }

    private function resetParams()
    {
        $this->params          = [];
        $this->params['index'] = $this->config->getIndex();
    }

    /**
     * @return array
     */
    public function getDebugging()
    {
        return $this->debugging;
    }
}
