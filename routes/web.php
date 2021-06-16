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
Route::get('/', 'SavedReportController@home')->name('index')->middleware(['auth']);
Route::get('/home', 'SavedReportController@home')->name('home')->middleware(['auth']);
// Auth::routes();
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::get('logout', 'Auth\LoginController@logout');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
//
Route::get('/forgot-password','Auth\ForgotPasswordController@showForgotForm')->name('password.forgot.get');
Route::post('/forgot-password','Auth\ForgotPasswordController@submitForgotForm')->name('password.forgot.post');
Route::get('/reset-password/{consortium}/{token}','Auth\ForgotPasswordController@showResetForm')
     ->name('password.reset.get');
Route::post('/reset-password','Auth\ForgotPasswordController@submitResetForm')->name('password.reset.post');
//
Route::resource('/consortia','ConsortiumController')->middleware('can:update,consortium');
Route::resource('/roles', 'RoleController')->middleware(['auth']);
Route::resource('/users', 'UserController')->middleware(['auth','cache.headers:no_store']);
Route::resource('/institutions', 'InstitutionController')->middleware(['auth','cache.headers:no_store']);
Route::resource('/institutiontypes', 'InstitutionTypeController')->middleware(['auth','cache.headers:no_store']);
Route::resource('/institutiongroups', 'InstitutionGroupController')->middleware(['auth','cache.headers:no_store']);
Route::resource('/providers', 'ProviderController')->middleware(['auth','cache.headers:no_store']);
Route::resource('/harvestlogs', 'HarvestLogController')->middleware(['auth','cache.headers:no_store']);
Route::resource('/sushisettings', 'SushiSettingController')
     ->middleware(['auth','role:Admin,Manager','cache.headers:no_store']);
Route::resource('/alertsettings', 'AlertSettingController')
     ->middleware(['auth','role:Admin,Manager','cache.headers:no_store']);
Route::resource('/savedreports', 'SavedReportController')->middleware(['auth']);
Route::resource('/systemalerts', 'SystemAlertController')->middleware(['auth']);
//
// Route::get('/globaladmin', 'GlobalAdminController@index')->middleware('auth','role:GlobalAdmin');
// Route::get('/', 'ReportController@index')->name('reports')->middleware('auth');
Route::get('/admin', 'AdminController@index')->name('admin')->middleware(['auth','role:Admin,Manager']);
Route::get('/alerts', 'AlertController@index')->name('alerts')->middleware('auth');
Route::get('/reports', 'ReportController@index')->name('reports.index')->middleware('auth');
Route::get('/reports/create', 'ReportController@create')->name('reports.create')->middleware('auth');
Route::get('/reports/preview', 'ReportController@preview')->name('reports.preview')->middleware('auth');
Route::get('/reports/{id}', 'ReportController@show')->name('reports.show')->middleware('auth');
Route::get('/reports-available', 'ReportController@getAvailable')->middleware(['auth']);
Route::get('/usage-report-data', 'ReportController@getReportData')->middleware(['auth']);
Route::post('/export-report-data', 'ReportController@exportReportData')->middleware(['auth']);
Route::post('/update-report-columns', 'ReportController@updateReportColumns')->middleware(['auth']);
Route::post('/save-report-config', 'SavedReportController@saveReportConfig')->middleware(['auth']);
//
Route::post('/update-alert-status', 'AlertController@updateStatus')->middleware(['auth','role:Admin,Manager']);
Route::post('/update-system-alert', 'AlertController@updateSysAlert')->middleware(['auth','role:Admin,Manager']);
Route::post('/alert-dash-refresh', 'AlertController@dashRefresh')->middleware('auth');
Route::post('/alertsettings-fields-refresh', 'AlertSettingController@fieldsRefresh')
     ->middleware(['auth','role:Admin,Manager']);
Route::post('/sushisettings-update', 'SushiSettingController@update')->middleware(['auth','role:Admin,Manager']);
Route::get('/sushisettings-refresh', 'SushiSettingController@refresh')->middleware(['auth']);
Route::get('/sushisettings-test', 'SushiSettingController@test')->middleware(['auth','role:Admin,Manager']);
Route::get('/harvestlogs/{id}/raw', 'HarvestLogController@downloadRaw')->middleware(['auth','role:Admin,Manager']);
Route::get('/available-providers', 'HarvestLogController@availableProviders')->middleware(['auth']);
Route::post('/update-harvest-status', 'HarvestLogController@updateStatus')->middleware(['auth','role:Admin,Manager']);
//
Route::get('/users/export/{type}', 'UserController@export');
Route::get('/providers/export/{type}', 'ProviderController@export');
Route::get('/institutions/export/{type}', 'InstitutionController@export');
Route::get('/institutiontypes/export/{type}', 'InstitutionTypeController@export');
Route::get('/institutiongroups/export/{type}', 'InstitutionGroupController@export');
Route::get('/sushisettings/export/{type}/{inst?}/{prov?}', 'SushiSettingController@export');
//
Route::post('/users/import', 'UserController@import');
Route::post('/providers/import', 'ProviderController@import');
Route::post('/institutions/import', 'InstitutionController@import');
Route::post('/institutiontypes/import', 'InstitutionTypeController@import');
Route::post('/institutiongroups/import', 'InstitutionGroupController@import');
Route::post('/sushisettings/import', 'SushiSettingController@import');
