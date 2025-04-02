<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ListShoppingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\FriendshipController;

Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('users', [UserController::class, 'store']);

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class)->except(['store']);
    Route::apiResource('lists', ListShoppingController::class)->except(['store']);
    Route::apiResource('users.lists', ListShoppingController::class)->only(['store', 'index'])->scoped();
    Route::post('/lists/{list}/share', [ListShoppingController::class, 'share']);
    Route::post('/lists/{list}/unshare', [ListShoppingController::class, 'unshare']);
    Route::get('/users/email/{email}', [UserController::class, 'getByEmail']);
    
    // Rotas de amizade
    Route::post('/friends/{friend}', [FriendshipController::class, 'sendRequest']);
    Route::put('/friends/{friend}/accept', [FriendshipController::class, 'acceptRequest']);
    Route::put('/friends/{friend}/reject', [FriendshipController::class, 'rejectRequest']);
    Route::delete('/friends/{friend}', [FriendshipController::class, 'removeFriend']);
    Route::get('/friends', [FriendshipController::class, 'listFriends']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');