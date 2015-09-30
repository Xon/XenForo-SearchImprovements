<?php

class SV_SearchImprovements_XenForo_ControllerPublic_Search extends XFCP_SV_SearchImprovements_XenForo_ControllerPublic_Search
{
    public function actionIndex()
    {
        SV_SearchImprovements_Globals::$SearchController = $this;
        return parent::actionIndex();
    }

    public function actionSearch()
    {
        SV_SearchImprovements_Globals::$SearchController = $this;
        return parent::actionSearch();
    }
}
