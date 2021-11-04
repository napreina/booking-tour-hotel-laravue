<?php
namespace Modules\Tour;

use Illuminate\Support\ServiceProvider;
use Modules\ModuleServiceProvider;
use Modules\Tour\Models\Tour;

class ModuleProvider extends ModuleServiceProvider
{
    public function boot()
    {
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

    public static function getBookableServices()
    {
        return [
            'tour' => Tour::class,
        ];
    }

    public static function getAdminMenu()
    {
        return [
            'tour'=>[
                "position"=>40,
                'url'        => 'admin/module/tour',
                'title'      => __("Tour"),
                'icon'       => 'icon ion-md-umbrella',
                'permission' => 'tour_view',
                'children'   => [
                    'tour_view'=>[
                        'url'        => 'admin/module/tour',
                        'title'      => __('All Tours'),
                        'permission' => 'tour_view',
                    ],
                    'tour_create'=>[
                        'url'        => 'admin/module/tour/create',
                        'title'      => __("Add Tour"),
                        'permission' => 'tour_create',
                    ],
                    'tour_category'=>[
                        'url'        => 'admin/module/tour/category',
                        'title'      => __('Categories'),
                        'permission' => 'tour_manage_others',
                    ],
                    'tour_attribute'=>[
                        'url'        => 'admin/module/tour/attribute',
                        'title'      => __('Attributes'),
                        'permission' => 'tour_manage_attributes',
                    ],
                    'tour_availability'=>[
                        'url'        => 'admin/module/tour/availability',
                        'title'      => __('Availability'),
                        'permission' => 'tour_create',
                    ],
                    'tour_booking'=>[
                        'url'        => 'admin/module/tour/booking',
                        'title'      => __('Booking Calendar'),
                        'permission' => 'tour_create',
                    ],
                ]
            ],
        ];
    }


    public static function getUserMenu()
    {
        return [
            'tour' => [
                'url'   => route('tour.vendor.index'),
                'title'      => __("Manage Tour"),
                'icon'       => Tour::getServiceIconFeatured(),
                'permission' => 'tour_view',
                'position'   => 30,
                'children'   => [
                    [
                        'url'   => route('tour.vendor.index'),
                        'title' => "All Tours",
                    ],
                    [
                        'url'        => route('tour.vendor.create'),
                        'title'      => "Add Tour",
                        'permission' => 'tour_create',
                    ],
                    [
                        'url'        => route('tour.vendor.availability.index'),
                        'title'      => __("Availability"),
                        'permission' => 'tour_create',
                    ],
                    [
                        'url'        => route('tour.vendor.booking_report'),
                        'title'      => "Booking Report",
                        'permission' => 'tour_view',
                    ],
                ]
            ],
        ];
    }

    public static function getTemplateBlocks(){
        return [
            'list_tours'=>"\\Modules\\Tour\\Blocks\\ListTours",
            'form_search_tour'=>"\\Modules\\Tour\\Blocks\\FormSearchTour",
        ];
    }
}
