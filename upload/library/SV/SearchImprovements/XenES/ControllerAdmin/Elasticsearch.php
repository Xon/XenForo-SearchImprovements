<?php

class SV_SearchImprovements_XenES_ControllerAdmin_Elasticsearch extends XFCP_SV_SearchImprovements_XenES_ControllerAdmin_Elasticsearch
{
    protected function _preDispatchFirst($action)
    {
        SV_SearchImprovements_Api::install(null, null);
    }
}