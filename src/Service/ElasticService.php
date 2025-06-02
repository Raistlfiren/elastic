<?php

namespace Kemper\Elastic\Service;

use Bolt\Storage\Entity\Content;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\Storage\Query\Query;
use Carbon\Carbon;
use Elastic\Elasticsearch\Client;
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
    public function doesIndexExist($indexFullName)
    {
        $this->resetParams();

        $response = $this->client->indices()->exists(['index' => $indexFullName]);

        return $response->getStatusCode() === 200;
    }

    public function importData()
    {
        $this->deleteMappings();

        $this->createMappings();

        $this->loadContent();
    }

    public function doesIndecesExist()
    {
        $index = $this->config->getIndex();

        foreach ($this->config->getContentTypes() as $contentType => $contentTypeMeta) {
            $indexFullName = $index . '-' . $contentType;
            if ($this->doesIndexExist($indexFullName)) {
                return true;
            }
        }

        return false;
    }

    protected function deleteMappings()
    {
        $index = $this->config->getIndex();

        foreach ($this->config->getContentTypes() as $contentType => $contentTypeMeta) {
            $this->resetParams();

            $indexFullName = $index . '-' . $contentType;

            if ($this->doesIndexExist($indexFullName)) {
                $response = $this->client->indices()->delete(['index' => $indexFullName]);

                if ($response['acknowledged']) {
                    $this->debugging[] =
                        '<p>Successfully deleted <code>' . $this->config->getIndex() . '</code> index.</p>';
                } else {
                    $this->debugging[] =
                        '<p>Error while deleting <code>' . $this->config->getIndex() . '</code> index.</p>';
                }
            }
        }
    }

    protected function createMappings()
    {
        $index = $this->config->getIndex();

        foreach ($this->config->getContentTypes() as $contentType => $contentTypeMeta) {
            $this->resetParams();
            $this->params['body'] = [];

            $this->params['index']             = $index . '-' . $contentType;

            $properties = [];

            $fields = $this->metadata->getClassMetadata($contentType)['fields'];

            foreach ($fields as $field => $meta) {
                $userMappings = $this->config->getTypeMapping($contentType);

                $defaultMapping = [];

                if ($meta['type'] === 'datetime') {
                    $defaultMapping['type']   = 'date';
                    $defaultMapping['format'] = "yyyy-MM-dd'T'HH:mm:ssZZZZZ";
                }

                if (isset($userMappings[$field])) {
                    $properties[$field] = $userMappings[$field];
                } else {
                    $properties[$field] = $defaultMapping;
                }
            }

            $mapping = [
                '_source'    => [
                    'enabled' => true,
                ],
                'properties' => $properties,
            ];

            $this->params['body']['mappings'] = $mapping;
            $response                           = $this->client->indices()->create($this->params);

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
        $index = $this->config->getIndex();

        foreach ($this->config->getContentTypes() as $contentType => $contentTypeMeta) {
            $this->resetParams();
            $this->params['index']             = $index . '-' . $contentType;

            $this->params['type'] = $contentType;

            $records = $this->query->getContent($contentType, ['status' => 'published']);

            foreach ($records as $record) {
                $this->params['id'] = $record['id'];
                foreach ($this->metadata->getClassMetadata($contentType)['fields'] as $field => $meta) {
                    if ($meta['type'] === 'datetime') {
                        if ($record[$field] instanceof Carbon) {
                            $this->params['body'][$field] = $record[$field]->toAtomString();
                        } else {
                            $this->params['body'][$field] = null;
                        }
                    } elseif ($meta['type'] === 'json') {
                        $this->params['body'][$field] = null;
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
        $contentType = (string)$content->getContenttype();

        $this->compileRequest($contentType, $content->getId());
        $data = $this->prepareData($content);

        $index = $this->config->getIndex();
        $this->params['index']             = $index . '-' . $contentType;

        try {
            $searchResponse = $this->client->get($this->params);

            if ($searchResponse) {
                $this->params['body']['doc'] = $data;

                return $this->client->update($this->params);
            }
        } catch (\Exception $e) {
            // Couldn't find the index
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

        $fields = $this->metadata->getClassMetadata($contentType)['fields'];

        $data = [];

        foreach ($fields as $field => $meta) {
            if ($meta['type'] === 'datetime') {
                if ($content[$field] instanceof Carbon) {
                    $data[$field] = $content[$field]->toAtomString();
                } else {
                    $data[$field] = null;
                }
            } elseif ($meta['type'] === 'null') {
                $data[$field] = null;
            }  elseif ($meta['type'] === 'boolean') {
                $data[$field] = (bool) $content[$field];
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
        $contentType = (string)$content->getContenttype();
        $this->compileRequest($content->getContenttype(), $content->getId());

        $index = $this->config->getIndex();
        $this->params['index']             = $index . '-' . $contentType;

        try {
            $searchResponse = $this->client->get($this->params);

            if ($searchResponse) {
                return $this->client->delete($this->params);
            }
        } catch (\Exception $e) {
            // Couldn't find the index
            return null;
        }
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
//        $this->params['index'] = $this->config->getIndex();
    }

    /**
     * @return array
     */
    public function getDebugging()
    {
        return $this->debugging;
    }
}
