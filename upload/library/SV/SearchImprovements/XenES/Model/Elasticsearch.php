<?php

class SV_SearchImprovements_XenES_Model_Elasticsearch extends XFCP_SV_SearchImprovements_XenES_Model_Elasticsearch
{
    public function getSingleTypeMergedOptimizableMappings()
    {
        /** @var XenForo_Model_Search $searchModel */
        $searchModel = $this->getModelFromCache('XenForo_Model_Search');
        $searchContentTypes = $searchModel->getSearchDataHandlers();
        $mappingTypes = $this->getAllSearchContentTypes();

        $expectedMapping = static::$optimizedGenericMapping;
        foreach ($mappingTypes AS $type)
        {
            $handler = isset($searchContentTypes[$type]) ? $searchContentTypes[$type] : null;
            if ($handler && is_callable([$handler, 'getCustomMapping']))
            {
                /** @noinspection PhpUndefinedMethodInspection */
                $expectedMapping = $handler->getCustomMapping($expectedMapping);
            }
        }

        return $expectedMapping;
    }

    // copied from XenES_Model_Elasticsearch, as it isn't extendable

    /**
     * @param array|null $mappingTypes
     * @param bool|null  $mappings
     * @return array
     */
    public function getOptimizableMappings(array $mappingTypes = null, $mappings = null)
    {
        if ($mappingTypes === null)
        {
            $mappingTypes = $this->getAllSearchContentTypes();
        }
        if ($mappings === null)
        {
            $mappings = $this->getMappings();
        }

        if (XenES_Api::isSingleTypeIndex())
        {
            $singleMappingName = XenES_Api::getSingleTypeName();
            $expectedMapping = $this->getSingleTypeMergedOptimizableMappings();
            if (empty($mappings->$singleMappingName) ||
                $this->_verifyMapping($mappings->$singleMappingName, $expectedMapping))
            {
                return [$singleMappingName];
            }

            return [];
        }

        $optimizable = [];
        /** @var XenForo_Model_Search $searchModel */
        $searchModel = $this->getModelFromCache('XenForo_Model_Search');
        $searchContentTypes = $searchModel->getSearchDataHandlers();

        foreach ($mappingTypes AS $type)
        {
            if (!$mappings || !isset($mappings->$type)) // no index or no mapping
            {
                $optimize = true;
            }
            else
            {
                // our change
                $expectedMapping = static::$optimizedGenericMapping;
                if (isset($searchContentTypes[$type]))
                {
                    $handler = $searchContentTypes[$type];
                    if ($handler && is_callable([$handler, 'getCustomMapping']))
                    {
                        /** @noinspection PhpUndefinedMethodInspection */
                        $expectedMapping = $handler->getCustomMapping($expectedMapping);
                    }
                }
                $optimize = $this->_verifyMapping($mappings->$type, $expectedMapping);
            }

            if ($optimize)
            {
                $optimizable[] = $type;
            }
        }

        return $optimizable;
    }

    public function optimizeMapping($type, $deleteFirst = true, array $extra = [])
    {
        if (XenES_Api::isSingleTypeIndex() && $type == XenES_Api::getSingleTypeName())
        {
            $extra = XenForo_Application::mapMerge($this->getSingleTypeMergedOptimizableMappings(), $extra);
        }

        $extra = XenForo_Application::mapMerge(static::$optimizedGenericMapping, $extra);
        /** @var XenForo_Model_Search $searchModel */
        $searchModel = $this->getModelFromCache('XenForo_Model_Search');
        $handler = $searchModel->getSearchDataHandler($type);
        if ($handler && is_callable([$handler, 'getCustomMapping']))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $extra = $handler->getCustomMapping($extra);
        }
        $extra = $this->getElasticSearchMapping($extra);
        return parent::optimizeMapping($type, $deleteFirst, $extra);
    }

    /**
     * Smooth over Elastic Search mapping version differences.
     *
     * @param array $mapping
     * @return array
     */
    public function getElasticSearchMapping($mapping)
    {
        $version = intval(XenES_Api::version());

        if ($version >= 5)
        {
            foreach ($mapping['properties'] AS &$baseValue)
            {
                if (isset($baseValue['type']) && $baseValue['type'] == 'string')
                {
                    $baseValue['type'] = 'text';
                }
            }
        }
        if ($version >= 6)
        {
            foreach ($mapping['properties'] AS &$baseValues)
            {
                foreach ($baseValues AS &$baseValue)
                {
                    if ($baseValue === 'yes')
                    {
                        $baseValue = true;
                    }
                    else if ($baseValue === 'no')
                    {
                        $baseValue = false;
                    }
                }
            }
        }

        return $mapping;
    }

    protected $hasOptimizedIndex = false;

    public function recreateIndex()
    {
        parent::recreateIndex();

        /** @var SV_ElasticEss_Model $elasticEssModel */
        $elasticEssModel = $this->getModelFromCache('SV_ElasticEss_Model');
        $elasticEssModel->updateIndexSettings();

        if (SV_ElasticEss_Globals::isSingleTypeIndex())
        {
            $this->optimizeMapping(XenES_Api::getSingleTypeName(), true);

            return;
        }

        if ($this->hasOptimizedIndex)
        {
            return;
        }
        $this->hasOptimizedIndex = true;

        /** @var XenForo_Model_Search $searchModel */
        $searchModel = $this->getModelFromCache('XenForo_Model_Search');
        $handlers = $searchModel->getSearchDataHandlers();
        foreach ($handlers as $type => $handler)
        {
            if (is_callable([$handler, 'getCustomMapping']))
            {
                if (!$this->optimizeMapping($type, true))
                {
                    XenForo_Error::debug("Updating mapping for {$type} failed");
                }
            }
        }
    }
}
