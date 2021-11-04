<?php
namespace Modules;
class ModuleServiceProvider extends \Illuminate\Support\ServiceProvider
{

    public static function getAdminMenu(){
        return [];
    }

    public static function getAdminSubmenu(){
        return [];
    }
    public static function getBookableServices(){
        return [];
    }

    public static function getMenuBuilderTypes(){
        return [];
    }

    public static function adminUserMenu(){
        return [];
    }

    public static function adminUserSubMenu(){
        return [];
    }

    public static function getUserMenu(){
        return [];
    }

    public static function getUserSubMenu(){
        return [];
    }

    public static function getTemplateBlocks(){
        return [];
    }
}