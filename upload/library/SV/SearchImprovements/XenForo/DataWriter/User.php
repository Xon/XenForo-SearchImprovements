<?php

class SV_SearchImprovements_XenForo_DataWriter_User extends XFCP_SV_SearchImprovements_XenForo_DataWriter_User
{
    protected function _getFields()
    {
        $fields = parent::_getFields();
        $fields['xf_user_option']['sv_default_search_order'] = array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => '');
        return $fields;
    }

    protected function _preSave()
    {
        if (!empty(SV_SearchImprovements_Globals::$PublicAccountController))
        {
            $input = SV_SearchImprovements_Globals::$PublicAccountController->getInput();
            $sv_default_search_order = $input->filterSingle('sv_default_search_order', XenForo_Input::STRING);
            $this->set('sv_default_search_order', $sv_default_search_order);
        }
        parent::_preSave();
    }
}
