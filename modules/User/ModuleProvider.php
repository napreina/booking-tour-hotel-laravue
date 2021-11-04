<?php
namespace Modules\User;
use Modules\ModuleServiceProvider;
use Modules\User\Controllers\Vendors\PayoutController;

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

    public static function getAdminMenu()
    {
        return [
            'users'=>[
                "position"=>100,
                'url'        => 'admin/module/user',
                'title'      => __('Users'),
                'icon'       => 'icon ion-ios-contacts',
                'permission' => 'user_view',
                'children'   => [
                    'user'=>[
                        'url'   => 'admin/module/user',
                        'title' => __('All Users'),
                        'icon'  => 'fa fa-user',
                    ],
                    'role'=>[
                        'url'        => 'admin/module/user/role',
                        'title'      => __('Role Manager'),
                        'permission' => 'role_view',
                        'icon'       => 'fa fa-lock',
                    ],
                    'subscriber'=>[
                        'url'        => 'admin/module/user/subscriber',
                        'title'      => __('Subscribers'),
                        'permission' => 'newsletter_manage',
                    ],
                    'userUpgradeRequest'=>[
                        'url'        => 'admin/module/user/userUpgradeRequest',
                        'title'      => __('User Upgrade Request'),
                        'permission' => 'user_view',
                    ],
                ]
            ],
        ];
    }
}
