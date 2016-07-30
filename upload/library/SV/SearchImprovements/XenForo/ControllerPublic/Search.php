<?php

class SV_SearchImprovements_XenForo_ControllerPublic_Search extends XFCP_SV_SearchImprovements_XenForo_ControllerPublic_Search
{
    protected function _preDispatchFirst($action)
    {
        SV_SearchImprovements_Api::install(null, null);
    }

    public function actionIndex()
    {
        SV_SearchImprovements_Globals::$SearchController = $this;
        $response = parent::actionIndex();

        if ($response instanceof XenForo_ControllerResponse_View
            && !empty($response->params['search'])
            && empty($response->params['search']['existing'])
        )
        {
            $visitor = XenForo_Visitor::getInstance();
            if (!empty($visitor['sv_default_search_order']))
            {
                XenForo_Application::getOptions()->set('esDefaultSearchOrder', $visitor['sv_default_search_order']);
                $response->params['search']['order'] = $visitor['sv_default_search_order'];
            }
        }

        return $response;
    }

    public function actionSearch()
    {
        SV_SearchImprovements_Globals::$SearchController = $this;

        if (!$this->_request->getParam('order'))
        {
            $visitor = XenForo_Visitor::getInstance();
            if (!empty($visitor['sv_default_search_order']))
            {

                XenForo_Application::getOptions()->set('esDefaultSearchOrder', $visitor['sv_default_search_order']);
                $this->_request->setParam('order', $visitor['sv_default_search_order']);
            }
        }

        return parent::actionSearch();
    }
}
