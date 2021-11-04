<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 7/11/2019
 * Time: 4:54 PM
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Modules\Language\Models\Language;

class RedirectForMultiLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string|null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {

        if(strpos($request->path(),'install') === false  && file_exists(storage_path().'/installed') && strtolower($request->method()) === 'get'){

            $locale = $request->segment(1);
            if(in_array($locale,['admin','_debugbar'])){
                return $next($request);
            }

            // Check if the first segment matches a language code
            if(setting_item('site_enable_multi_lang') && setting_item('site_locale')){
                $lang = Language::findByLocale($locale);

                if (empty($lang)) {

                    // Store segments in array
                    $segments = $request->segments();

                    // Set the default language code as the first segment
                    $segments = Arr::prepend($segments, setting_item('site_locale',config('app.fallback_locale')));

                    $url = implode('/', $segments);
                    if(!empty($request->query())){
                        $url.='?'.http_build_query($request->query());
                    }

                    // Redirect to the correct url
                    return redirect()->to( $url );
                }
            }
        }
        return $next($request);
    }
}