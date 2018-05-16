<?php

class SV_SearchImprovements_XenES_Search_SourceHandler_ElasticSearch extends XFCP_SV_SearchImprovements_XenES_Search_SourceHandler_ElasticSearch
{
    public function __construct()
    {
        SV_SearchImprovements_Api::install();
        parent::__construct();
    }

    public function executeSearch($searchQuery, $titleOnly, array $processedConstraints, array $orderParts,
                                  $groupByDiscussionType, $maxResults, XenForo_Search_DataHandler_Abstract $typeHandler = null)
    {
        SV_SearchImprovements_Api::install($this, ['typeHandler' => $typeHandler]);
        $result = parent::executeSearch(
            $searchQuery, $titleOnly, $processedConstraints, $orderParts, $groupByDiscussionType, $maxResults,
            $typeHandler
        );
        SV_SearchImprovements_Api::uninstall();

        return $result;
    }

    public function parseQuery($query)
    {
        $options = XenForo_Application::getOptions();
        if ($options->searchImpov_simpleQuerySyntax)
        {
            // rewrite unmatched / into \/ because this causes simple_query_string to throw
            // https://www.elastic.co/guide/en/elasticsearch/reference/6.x/query-dsl-simple-query-string-query.html
            // Lies on it not throwing!
            return str_replace('/','\/', $query);
        }

        return parent::parseQuery($query);
    }

    public function searchHook($indexName, array &$dsl, $args)
    {
        $options = XenForo_Application::getOptions();
        if ($options->searchImpov_simpleQuerySyntax)
        {
            // use simple_query_string instead of an escaped query_string
            $this->rewriteSimpleQueryString($dsl);
        }
        // skip spesific type handler searches
        if (!empty($args['typeHandler']))
        {
            return;
        }
        // only support ES > 1.2 & relevance weighting or plain sorting by relevance score
        if (isset($dsl['query']['function_score']) || isset($dsl['sort'][0]['_score']))
        {
            $this->weightByContentType($dsl);
        }
    }

    function rewriteSimpleQueryString(array &$dsl)
    {
        if (isset($dsl['query']['query_string']))
        {
            $dsl['query']['simple_query_string'] = $dsl['query']['query_string'];
            unset($dsl['query']['query_string']);
        }
        else if (isset($dsl['query']['function_score']['query']['query_string']))
        {
            $dsl['query']['function_score']['query']['simple_query_string'] = $dsl['query']['function_score']['query']['query_string'];
            unset($dsl['query']['function_score']['query']['query_string']);
        }
        else if (isset($dsl['query']['filtered']['query']['query_string']))
        {
            $dsl['query']['filtered']['query']['simple_query_string'] = $dsl['query']['filtered']['query']['query_string'];
            unset($dsl['query']['filtered']['query']['query_string']);
        }
        else if (isset($dsl['query']['bool']['must']['query_string']))
        {
            $dsl['query']['bool']['must']['simple_query_string'] = $dsl['query']['bool']['must']['query_string'];
            unset($dsl['query']['bool']['must']['query_string']);
        }
        else if (isset($dsl['query']['custom_score']['query']['query_string']))
        {
            $dsl['query']['custom_score']['query']['simple_query_string'] = $dsl['query']['custom_score']['query']['query_string'];
            unset($dsl['query']['custom_score']['query']['query_string']);
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

        $functions = [];
        $isSingleTypeIndex = XenES_Api::isSingleTypeIndex();
        foreach ($content_type_weighting as $content_type => $weight)
        {
            if ($weight == 1)
            {
                continue;
            }
            $functions[] = [
                "filter" => $isSingleTypeIndex ? ['term' => ['type' => $content_type]] : ['type' => ['value' => $content_type]],
                "weight" => $weight
            ];
        }

        if (empty($functions))
        {
            return;
        }

        $dsl['query'] = [
            'function_score' => [
                'query' => $dsl['query'],
                "functions" => $functions
            ]
        ];
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
        $params = [];

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
