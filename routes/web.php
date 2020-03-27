<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', function () {
    return view('reports.usage');
})->middleware('auth');

Route::resource('/consortia','ConsortiumController')->middleware('can:update,consortium');
Route::resource('/roles', 'RoleController');
Route::resource('/users', 'UserController');
Route::resource('/institutions', 'InstitutionController');
Route::resource('/institutiontypes', 'InstitutionTypeController');
Route::resource('/institutiongroups', 'InstitutionGroupController');
Route::resource('/providers', 'ProviderController');
Route::resource('/harvestlogs', 'HarvestLogController');
Route::resource('/failedharvests', 'FailedHarvestController');
Route::resource('/alertsettings', 'AlertSettingController')->middleware(['auth','role:Admin,Manager']);

Auth::routes();
Route::get('logout', 'Auth\LoginController@logout');
// Route::get('/globaladmin', 'GlobalAdminController@index')->middleware('auth','role:GlobalAdmin');
Route::get('/', 'ReportController@index')->name('reports')->middleware('auth');
Route::get('/admin', 'AdminController@index')->name('admin')->middleware(['auth','role:Admin,Manager']);
Route::get('/alerts', 'AlertController@index')->name('alerts')->middleware('auth');
Route::get('/reports', 'ReportController@index')->name('reports.usage')->middleware('auth');
Route::get('/reports/view', 'ReportController@view')->name('reports.view')->middleware('auth');
Route::get('/reports/{id}', 'ReportController@show')->name('reports.show')->middleware('auth');
Route::post('/update-report-filters', 'ReportController@updateFilters')->middleware(['auth']);
Route::post('/usage-report-data', 'ReportController@getReportData')->middleware(['auth']);
Route::post('/update-alert-status', 'AlertController@updateStatus')->middleware(['auth','role:Admin,Manager']);
Route::post('/alert-dash-refresh', 'AlertController@dashRefresh')->middleware('auth');
Route::post('/alertsettings-fields-refresh', 'AlertSettingController@fieldsRefresh')
     ->middleware(['auth','role:Admin,Manager']);
Route::get('/sushisettings-refresh', 'SushiSettingController@show')->middleware(['auth']);
Route::post('/sushisettings-update', 'SushiSettingController@update')->middleware(['auth','role:Admin,Manager']);
Route::get('/sushisettings-test', 'SushiSettingController@test')->middleware(['auth','role:Admin,Manager']);
