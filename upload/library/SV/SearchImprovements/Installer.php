<?php

class SV_SearchImprovements_Installer
{
    public static function install($installedAddon, array $addonData, SimpleXMLElement $xml)
    {
        $version = isset($installedAddon['version_id']) ? $installedAddon['version_id'] : 0;

        if (!(XenForo_Application::get('options')->enableElasticsearch) || !($XenEs = XenForo_Model::create('XenES_Model_Elasticsearch')))
        {
            throw new Exception("Require Enhanced Search to be installed and enabled");
        }

        SV_Utils_Install::addColumn('xf_user_option', 'sv_default_search_order', "varchar(50) NOT NULL default ''");

        $db = XenForo_Application::getDb();
        // make sure the model is loaded before accessing the static properties
        XenForo_Model::create("XenForo_Model_User");
        $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) values
            (?, 0, 'general', 'sv_searchOptions', 'allow', '0')
        ", array(XenForo_Model_User::$defaultRegisteredGroupId));

        SV_Utils_Install::removeOldAddons(array('SV_ElasticSearchInfo' => array()));
    }

    public static function uninstall()
    {
        $db = XenForo_Application::getDb();

        $db->delete('xf_permission_entry', "permission_id = 'sv_searchOptions'");
        SV_Utils_Install::dropColumn('xf_user_option', 'sv_default_search_order');
    }
}