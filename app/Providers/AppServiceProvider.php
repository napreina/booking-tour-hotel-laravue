<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider; 

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Request $request)
    {

        if(env('APP_HTTPS')) {
            \URL::forceScheme('https');
        }

        Schema::defaultStringLength(191);

        if(strpos($request->path(),'install') === false  && file_exists(storage_path().'/installed')){

            $locale = $request->segment(1);
            if(in_array($locale,['admin','_debugbar'])){
                if(setting_item('site_locale')){
                    app()->setLocale(setting_item('site_locale'));
                }
                return;
            }

            // Check if the first segment matches a language code
            if(setting_item('site_enable_multi_lang')){
                app()->setLocale($request->segment(1));
            }else{
                app()->setLocale(setting_item('site_locale'));
            }
        }
    }
}
