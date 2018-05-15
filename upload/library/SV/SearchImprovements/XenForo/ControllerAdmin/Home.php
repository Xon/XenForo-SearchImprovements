<?php

class SV_SearchImprovements_XenForo_ControllerAdmin_Home extends XFCP_SV_SearchImprovements_XenForo_ControllerAdmin_Home
{
    protected function _preDispatchFirst($action)
    {
        SV_SearchImprovements_Api::install(null, null);
    }

    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof XenForo_ControllerResponse_View && !class_exists(
                'DigitalPointSearch_ControllerAdmin_Elasticsearch', false
            ))
        {
            $esModel = $this->_getEsModel();
            if ($esModel)
            {
                $esApi = XenES_Api::getInstance();
                $esVersion = $esApi->version();
                if ($esVersion)
                {
                    $response->params['esVersion'] = $esVersion;
                    $response->params['esStats'] = $esModel->getStats();
                }
            }
        }

        return $response;
    }

    /**
     * @return null|XenForo_Model|XenES_Model_Elasticsearch
     */
    protected function _getEsModel()
    {
        if (!XenForo_Application::get('options')->enableElasticsearch)
        {
            return null;
        }

        return $this->getModelFromCache('XenES_Model_Elasticsearch');
    }
}
