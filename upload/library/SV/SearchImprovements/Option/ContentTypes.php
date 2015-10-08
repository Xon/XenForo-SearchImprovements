<?php

class SV_SearchImprovements_Option_ContentTypes
{
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $searchModel = XenForo_Model::create('XenForo_Model_Search');

        $options = array();
        $handlerPhrases = array();
        foreach ($searchModel->getSearchDataHandlers() AS $contentType => $handler)
        {
            $phrase = $handler->getSearchContentTypePhrase();
            $handlerPhrases[$contentType] = $phrase;

            if (!empty($preparedOption['option_value']) && 
                is_array($preparedOption['option_value']) && 
                isset($preparedOption['option_value'][$contentType]))
            {
                continue;
            }

            $options[] = array(
                'value' => $contentType,
                'label' => $phrase,
                'selected' => false,
                'depth' => 0,
            );
        }

        $preparedOption['formatParams'] = $options;

        return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
            'sv_search_weighting', $view, $fieldPrefix, $preparedOption, $canEdit,
            array('handlers' => $handlerPhrases)
        );
    }

    public static function verifyOption(array &$values, XenForo_DataWriter $dw, $fieldName)
    {
        $searchModel = XenForo_Model::create('XenForo_Model_Search');
        $handlers = $searchModel->getSearchDataHandlers();

        // pull out new items and re-insert in the correct format.
        foreach($values as $key => $value)
        {
            if (is_numeric($key) && isset($values[$key]) && isset($values[$key + 1]))
            {
                $values[$values[$key]] = $values[$key + 1];
                unset($values[$key]);
                unset($values[$key + 1]);
            }
        }

        foreach($values as $key => $value)
        {
            if (!isset($handlers[$key]) || $value == 1)
            {
                unset($values[$key]);
            }
        }

        return true;
    }
}