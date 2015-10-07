<?php

class SV_SearchImprovements_XenES_Search_SourceHandler_ElasticSearch extends XFCP_SV_SearchImprovements_XenES_Search_SourceHandler_ElasticSearch
{
    public function executeSearch($searchQuery, $titleOnly, array $processedConstraints, array $orderParts,
        $groupByDiscussionType, $maxResults, XenForo_Search_DataHandler_Abstract $typeHandler = null)
    {
        SV_SearchImprovements_Api::install($this);
        try
        {
            return parent::executeSearch($searchQuery, $titleOnly, $processedConstraints, $orderParts, $groupByDiscussionType, $maxResults, $typeHandler);
        }
        finally
        {
            SV_SearchImprovements_Api::uninstall();
        }
    }

    public function searchHook($indexName, array &$dsl)
    {
        // only support ES > 1.2 & relevance weighting
        if(!isset($dsl['query']['function_score']))
        {
            return;
        }

        // pre content type weighting
        $content_type_weighting = XenForo_Application::getOptions()->content_type_weighting;
        if (empty($content_type_weighting))
        {
            return;
        }

        $functions = array();
        foreach($content_type_weighting as $content_type => $weight)
        {
            if ($weight == 1)
            {
                continue;
            }
            $functions[] =  array(
                    "filter" => array('type' => array('value' => $content_type)),
                    "weight" => $weight
            );
        }

        if (empty($functions))
        {
            return;
        }

        $function_score = $dsl['query']['function_score'];
        $dsl['query']['function_score'] = array(
            'query' => array('function_score' => $function_score),
            "functions" => $functions
        );
    }

    protected function _processConstraint(array &$dsl, $constraintName, array $constraint)
    {
        if (isset($constraint['range_query']))
        {
            return $this->_processRangeQueryConstraint($dsl, $constraintName, $constraint['range_query']);
        }
        return parent::_processConstraint($dsl, $constraintName, $constraint);
    }

    protected function _processRangeQueryConstraint(array &$dsl, $constraintName, array $constraint)
    {
        $params = array();

        if (empty($constraint[0]))
        {
            return false;
        }
        $field = $constraint[0];

        if (isset($constraint[1]) && isset($constraint[1][0]) && isset($constraint[1][1]))
        {
            $arg = $constraint[1];
            $params[$this->_getRangeOperator($arg[0])] = $arg[1];
        }

        if (isset($constraint[2]) && isset($constraint[2][0]) && isset($constraint[2][1]))
        {
            $arg = $constraint[1];
            $params[$this->_getRangeOperator($arg[0])] = $arg[1];
        }

        if (empty($params))
        {
            return false;
        }

        $dsl['query']['filtered']['filter']['and'][]['range'][$field] = $params;
        return true;
    }
}