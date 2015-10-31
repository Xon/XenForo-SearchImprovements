<?php

class SV_SearchImprovements_XenES_Search_SourceHandler_ElasticSearch extends XFCP_SV_SearchImprovements_XenES_Search_SourceHandler_ElasticSearch
{
    public function executeSearch($searchQuery, $titleOnly, array $processedConstraints, array $orderParts,
        $groupByDiscussionType, $maxResults, XenForo_Search_DataHandler_Abstract $typeHandler = null)
    {
        SV_SearchImprovements_Api::install($this, array('typeHandler' => $typeHandler));
        try
        {
            return parent::executeSearch($searchQuery, $titleOnly, $processedConstraints, $orderParts, $groupByDiscussionType, $maxResults, $typeHandler);
        }
        finally
        {
            SV_SearchImprovements_Api::uninstall();
        }
    }

    public function searchHook($indexName, array &$dsl, $args)
    {
        // skip spesific type handler searches
        if ($args['typeHandler'] == null)
        {
            return;
        }
        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if(isset($dsl['query']['function_score']) || isset($dsl['sort'][0]['_score']))
        {
            $this->weightByContentType($dsl);
        }
    }

    function weightByContentType(array &$dsl)
    {
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

        $dsl['query'] = array('function_score' => array(
            'query' =>  $dsl['query'],
            "functions" => $functions
        ));
    }

    protected function _processConstraint(array &$dsl, $constraintName, array $constraint)
    {
        if (isset($constraint['range_query']))
        {
            return $this->_processRangeQueryConstraint($dsl, $constraintName, $constraint['range_query']);
        }
        return parent::_processConstraint($dsl, $constraintName, $constraint);
    }

    protected function _extractRangeOperatorPart(array $constraint, $index, array &$params)
    {
        if (empty($constraint[$index]))
        {
            return;
        }
        $rangePart = $constraint[$index];
        if (isset($rangePart[0]) && isset($rangePart[1]))
        {
            $params[$this->_getRangeOperator($rangePart[0])] = $rangePart[1];
        }
    }

    protected function _processRangeQueryConstraint(array &$dsl, $constraintName, array $constraint)
    {
        $params = array();

        if (empty($constraint) || empty($constraint[0]))
        {
            return false;
        }
        $field = $constraint[0];

        $this->_extractRangeOperatorPart($constraint, 1, $params);
        $this->_extractRangeOperatorPart($constraint, 2, $params);

        if (empty($params))
        {
            return false;
        }

        $dsl['query']['filtered']['filter']['and'][]['range'][$field] = $params;
        return true;
    }
}