<?php
namespace Modules\Core;
use Modules\ModuleServiceProvider;

class ModuleProvider extends ModuleServiceProvider
{

    public function boot(){

        $this->loadMigrationsFrom(__DIR__ . '/Migrations');

    }
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouterServiceProvider::class);
    }


    public static function getAdminSubmenu()
    {
        return [
            [
                'id'=>'updater',
                'parent'=>'tools',
                'title'=>__("Updater"),
                'url'=>'admin/module/core/updater',
                'icon'=>'icon ion-md-download',
                'permission'=>'system_log_view'
            ]
        ];
    }
}