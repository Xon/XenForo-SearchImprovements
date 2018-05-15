<?php

class SV_SearchImprovements_Installer
{
    public static function install($installedAddon, array $addonData, SimpleXMLElement $xml)
    {
        $version = isset($installedAddon['version_id']) ? $installedAddon['version_id'] : 0;
        $required = '5.4.0';
        $phpversion = phpversion();
        if (version_compare($phpversion, $required, '<'))
        {
            throw new XenForo_Exception("PHP {$required} or newer is required. {$phpversion} does not meet this requirement. Please ask your host to upgrade PHP", true);
        }
        if (XenForo_Application::$versionId < 1030070)
        {
            throw new XenForo_Exception('XenForo 1.3.0+ is Required!', true); // Make this show nicely.
        }
        if (!(XenForo_Application::get('options')->enableElasticsearch) || !($XenEs = XenForo_Model::create('XenES_Model_Elasticsearch')))
        {
            throw new Exception("Require Enhanced Search to be installed and enabled");
        }

        SV_Utils_Install::addColumn('xf_user_option', 'sv_default_search_order', "varchar(50) NOT NULL default ''");

        if ($version < 1010300)
        {
            $db = XenForo_Application::getDb();
            // make sure the model is loaded before accessing the static properties
            XenForo_Model::create("XenForo_Model_User");
            $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) values
                (?, 0, 'general', 'sv_searchOptions', 'allow', '0')
            ", array(XenForo_Model_User::$defaultRegisteredGroupId));
        }

        if (SV_Utils_AddOn::addOnIsActive('dpSearch') && $xml)
        {
            // disable AdminCP home extension which conflicts with Digitalpoint search
            $xmlListeners = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->code_event_listeners->listener);
            foreach ($xmlListeners AS $event)
            {
                if ((string)$event['hint'] == 'XenForo_ControllerAdmin_Home')
                {
                    $event['active'] = 0;
                }
            }
            $xmlAdminModifications = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->admin_template_modifications->modification);
            foreach ($xmlAdminModifications AS $adminMod)
            {
                if ((string)$adminMod['modification_key'] == 'SV_SearchImprovements_home1')
                {
                    $event['enabled'] = 0;
                }
            }
        }

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_Option', XenForo_DataWriter::ERROR_SILENT);
        if ($dw->setExistingData('elasticSearchServer'))
        {
            $subOptionsRaw = $dw->get('sub_options');
            $subOptions = preg_split('/(\r\n|\n|\r)+/', trim($subOptionsRaw), -1, PREG_SPLIT_NO_EMPTY);
            if (!in_array('*', $subOptions))
            {
                if (!in_array('username', $subOptions))
                {
                    $subOptionsRaw .= "\nusername";
                }
                if (!in_array('password', $subOptions))
                {
                    $subOptionsRaw .= "\npassword";
                }
                if (!in_array('https', $subOptions))
                {
                    $subOptionsRaw .= "\nhttps";
                }
            }

            $dw->set('sub_options', $subOptionsRaw);
            if ($dw->hasChanges())
            {
                $dw->save();
            }
        }

        SV_Utils_Install::removeOldAddons(array('SV_ElasticSearchInfo' => array()));
    }

    public static function uninstall()
    {
        $db = XenForo_Application::getDb();

        $db->delete('xf_permission_entry', "permission_id = 'sv_searchOptions'");
        SV_Utils_Install::dropColumn('xf_user_option', 'sv_default_search_order');
    }
}