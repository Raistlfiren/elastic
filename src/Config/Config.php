<?php

namespace Kemper\Elastic\Config;

use Bolt\Application;

/**
 * Class Config
 * @package Kemper\Elastic\Config
 * @author KemperWebTeam <webmaster@kcpag.com>
 */
class Config
{
    protected $hosts;

    protected $index;

    protected $indexSettings;

    protected $mappings;

    protected $contentTypes;

    public function __construct(Application $app, array $config)
    {
        $this->setContentTypes($app['config']->get('contenttypes'));
        $this->setHosts($config['hosts']);
        $this->setIndex($config['index']);
        $this->setIndexSettings($config['indexSettings']);
        $this->setMappings($config['mappings']);
    }

    /**
     * @return array
     */
    public function getHosts()
    {
        return $this->hosts;
    }

    /**
     * @param array $hosts
     *
     * @return Config
     */
    public function setHosts($hosts)
    {
        $this->hosts = $hosts;

        return $this;
    }

    /**
     * @return string
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param string $index
     *
     * @return Config
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return array
     */
    public function getIndexSettings()
    {
        return $this->indexSettings;
    }

    /**
     * @param array $indexSettings
     *
     * @return Config
     */
    public function setIndexSettings($indexSettings)
    {
        $this->indexSettings = $indexSettings;

        return $this;
    }

    /**
     * @return array
     */
    public function getMappings()
    {
        return $this->mappings;
    }

    /**
     * @param $contentType
     *
     * @return array
     */
    public function getTypeMapping($contentType)
    {
        if (isset($this->mappings[$contentType])) {
            return $this->mappings[$contentType];
        }

        return [];
    }

    /**
     * @param array $mappings
     *
     * @return Config
     */
    public function setMappings($mappings)
    {
        $this->mappings = $mappings;

        return $this;
    }

    /**
     * @return array
     */
    public function getContentTypes()
    {
        $searchableContent = [];

        foreach ($this->contentTypes as $contentType => $contentTypeMeta) {
            if (isset($contentTypeMeta['searchable']) && $contentTypeMeta['searchable'] === true) {
                $searchableContent[$contentType] = $contentTypeMeta;
            }
        }

        return $searchableContent;
    }

    /**
     * @param $contentType
     *
     * @return bool
     */
    public function isSearchable($contentType)
    {
        return array_key_exists($contentType, $this->getContentTypes());
    }

    /**
     * @param array $contentTypes
     *
     * @return Config
     */
    public function setContentTypes($contentTypes)
    {
        $this->contentTypes = $contentTypes;

        return $this;
    }
}
