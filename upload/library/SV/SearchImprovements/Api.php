<?php

class SV_SearchImprovements_Api
{
    protected static $hookedApi =  null;

    public static function install(XenES_Search_SourceHandler_ElasticSearch $object)
    {
        $XenESApiLoaded = class_exists('XenES_Api', false);
        if (self::$hookedApi === null && $XenESApiLoaded)
        {
            throw new Exception("Unable to hook XenES_Api");
        }
        if (!$XenESApiLoaded)
            include('XenESApi.php');
        XenES_Api::$hookObject = $object;
    }

    public static function uninstall()
    {
        XenES_Api::$hookObject = null;
    }
}