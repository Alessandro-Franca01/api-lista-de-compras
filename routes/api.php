<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ListShoppingController;


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('users', UserController::class);
Route::apiResource('lists', ListShoppingController::class)->except(['store']);
Route::apiResource('users.lists', ListShoppingController::class)->only(['store', 'index'])->scoped();
Route::post('/lists/{list}/share', [ListShoppingController::class, 'share']);
Route::post('/lists/{list}/unshare', [ListShoppingController::class, 'unshare']);
