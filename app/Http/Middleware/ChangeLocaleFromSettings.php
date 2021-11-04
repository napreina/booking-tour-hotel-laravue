<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class ChangeLocaleFromSettings
{

    /**
     * This function checks if language to set is an allowed lang of config.
     *
     * @param string $locale
     **/
    private function setLocale($locale)
    {

//        // Set app language
//        \App::setLocale($locale);
//
//        request()->session('website_lang',$locale);
//
//        // Set carbon language
//        if (config('language.carbon')) {
//            // Carbon uses only language code
//            if (config('language.mode.code') == 'long') {
//                $locale = explode('-', $locale)[0];
//            }
//            \Carbon\Carbon::setLocale($locale);
//        }
//        // Set date language
//        if (config('language.date')) {
//            // Date uses only language code
//            if (config('language.mode.code') == 'long') {
//                $locale = explode('-', $locale)[0];
//            }
//            \Date::setLocale($locale);
//        }
    }
    public function setDefaultLocale()
    {
        if(strpos(request()->path(),'install') === false){
            $locale = setting_item('site_locale');

            if ($locale) {
                $this->setLocale($locale);
            }
        }

    }
    public function setUserLocale()
    {
        $user = auth()->user();
        if ($user->locale) {
            $this->setLocale($user->locale);
        } else {
            $this->setDefaultLocale();
        }
    }
    public function setSystemLocale($request)
    {
        if ($request->session()->has('locale')) {
            $this->setLocale(session('locale'));
        } else {
            $this->setDefaultLocale();
        }
    }
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
//        if ($request->has('set_lang')) {
//            $this->setLocale($request->get('set_lang'));
//        } elseif (auth()->check()) {
//            $this->setUserLocale();
//        } else {
//            $this->setSystemLocale($request);
//        }
//        return $next($request);
    }
}
