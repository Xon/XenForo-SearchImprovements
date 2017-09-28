<?php

// This class is used to encapsulate global state between layers without using $GLOBAL[] or
// relying on the consumer being loaded correctly by the dynamic class autoloader
class SV_SearchImprovements_Globals
{
    /** @var null|SV_SearchImprovements_XenForo_ControllerPublic_Search */
    public static $SearchController = null;
    /** @var null|SV_SearchImprovements_XenForo_ControllerPublic_Account */
    public static $PublicAccountController = null;

    private function __construct() {}
}
