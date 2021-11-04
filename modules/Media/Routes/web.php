<?php
use Illuminate\Support\Facades\Route;

// Media
Route::group(['prefix'=>'media'],function(){
    Route::get('/preview/{id}/{size?}','\Modules\Media\Controllers\MediaController@preview');//
});
Route::group(['middleware' => ['auth']],function(){
    Route::match(['get','post'],'/admin/module/media/store','\Modules\Media\Admin\MediaController@store');
    Route::match(['get','post'],'/admin/module/media/getLists','\Modules\Media\Admin\MediaController@getLists');
    Route::match(['get','post'],'/admin/module/media/removeFiles','\Modules\Media\Admin\MediaController@removeFiles');
});