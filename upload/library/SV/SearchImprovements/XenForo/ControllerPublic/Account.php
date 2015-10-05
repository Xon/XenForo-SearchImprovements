<?php

class SV_SearchImprovements_XenForo_ControllerPublic_Account extends XFCP_SV_SearchImprovements_XenForo_ControllerPublic_Account
{
    public function actionPreferencesSave()
    {
        SV_SearchImprovements_Globals::$PublicAccountController = $this;

        return parent::actionPreferencesSave();
    }
}