<?php

use App\Http\Controllers\DocusignController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('docusign',[DocusignController::class, 'index'])->name('docusign');
Route::get('connect-docusign',[DocusignController::class, 'connectDocusign'])->name('connect.docusign');
Route::get('docusign/callback',[DocusignController::class,'callback'])->name('docusign.callback');
Route::get('sign-document',[DocusignController::class,'signDocument'])->name('docusign.sign');

Route::get('templates',[DocusignController::class,'getTemplates'])->name('templates');
Route::get('send',[DocusignController::class,'sendEnvelopeTemplate'])->name('send.envelope');
// Route::get('connect',[TestController::class,'index'])->name('sign');
// Route::get('/docusign/auth', [TestController::class,'redirectToDocusign']);
// Route::get('/docusign/callback', [TestController::class,'handleDocusignCallback']);

