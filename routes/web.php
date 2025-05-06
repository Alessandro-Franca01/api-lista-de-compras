<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

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
    return view('welcome');
});

Route::get('/politica-de-privacidade', function () {
    return view('privacy_policy');
});

Route::get('/whatsapp', [WhatsAppController::class, 'showForm'])->name('show.form.whatsapp');
Route::post('/whatsapp/send', [WhatsAppController::class, 'sendFromForm'])->name('send.whatsapp');
