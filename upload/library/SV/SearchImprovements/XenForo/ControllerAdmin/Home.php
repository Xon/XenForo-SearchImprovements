<?php

class SV_SearchImprovements_XenForo_ControllerAdmin_Home extends XFCP_SV_SearchImprovements_XenForo_ControllerAdmin_Home
{
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof XenForo_ControllerResponse_View && !class_exists('DigitalPointSearch_ControllerAdmin_Elasticsearch', false))
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

    var $_elasticsearch = null;

    protected function _getEsModel()
    {
        if ($this->_elasticsearch === null)
        {
            $this->_elasticsearch = false;
            if (XenForo_Application::get('options')->enableElasticsearch && $XenEs = XenForo_Model::create('XenES_Model_Elasticsearch'))
            {
                $this->_elasticsearch = $XenEs;
            }
        }
        return $this->_elasticsearch;
    }
}
