<?php

// This class is used to encapsulate global state between layers without using $GLOBAL[] or
// relying on the consumer being loaded correctly by the dynamic class autoloader
class SV_SearchImprovements_Globals
{
    public static $SearchController = null;
    public static $PublicAccountController = null;

    private function __construct() {}
}
