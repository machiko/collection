<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
Route::resource('/', 'HomeController');

Route::get('api/curl', ['uses' => 'ApiController@getCurl']);
// Route::get('/', function () {
//     return view('home.index');
// });
