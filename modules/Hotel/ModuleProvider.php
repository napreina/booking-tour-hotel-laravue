<?php
namespace Modules\Hotel;
use Modules\ModuleServiceProvider;
use Modules\Hotel\Models\Hotel;

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
            'hotel'=>[
                "position"=>32,
                'url'        => 'admin/module/hotel',
                'title'      => __('Hotel'),
                'icon'       => 'fa fa-building-o',
                'permission' => 'hotel_view',
                'children'   => [
                    'add'=>[
                        'url'        => 'admin/module/hotel',
                        'title'      => __('All Hotels'),
                        'permission' => 'hotel_view',
                    ],
                    'create'=>[
                        'url'        => 'admin/module/hotel/create',
                        'title'      => __('Add new Hotel'),
                        'permission' => 'hotel_create',
                    ],
                    'attribute'=>[
                        'url'        => 'admin/module/hotel/attribute',
                        'title'      => __('Attributes'),
                        'permission' => 'hotel_manage_attributes',
                    ],
                    'room_attribute'=>[
                        'url'        => 'admin/module/hotel/room/attribute',
                        'title'      => __('Room Attributes'),
                        'permission' => 'hotel_manage_attributes',
                    ],
                ]
            ]
        ];
    }

    public static function getBookableServices()
    {
        return [
            'hotel'=>Hotel::class
        ];
    }

    public static function getMenuBuilderTypes()
    {
        return [
            'hotel'=>[
                'class' => Hotel::class,
                'name'  => __("Hotel"),
                'items' => Hotel::searchForMenu(),
                'position'=>41
            ]
        ];
    }


    public static function getUserMenu()
    {
        return [
            'hotel' => [
                'url'   => route('hotel.vendor.index'),
                'title'      => __("Manage Hotel"),
                'icon'       => Hotel::getServiceIconFeatured(),
                'position'   => 30,
                'permission' => 'hotel_view',
                'children' => [
                    [
                        'url'   => route('hotel.vendor.index'),
                        'title'  => __("All Hotels"),
                    ],
                    [
                        'url'   => route('hotel.vendor.create'),
                        'title'      => __("Add Hotel"),
                        'permission' => 'hotel_create',
                    ],
                    [
                        'url'   => route('hotel.vendor.booking_report'),
                        'title'      => __("Booking Report"),
                        'permission' => 'hotel_view',
                    ],
                ]
            ],
        ];
    }

    public static function getTemplateBlocks(){
        return [
            'form_search_hotel'=>"\\Modules\\Hotel\\Blocks\\FormSearchHotel",
            'list_hotel'=>"\\Modules\\Hotel\\Blocks\\ListHotel",
        ];
    }
}