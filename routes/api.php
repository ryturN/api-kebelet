<?php

use App\Http\Controllers\CheckinController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ToiletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Auth
Route::get('v1/login/google/redirect', [LoginController::class, 'redirectToGoogle'])
    ->middleware(['guest'])
    ->name('redirect');;
Route::get('v1/login/google/callback', [LoginController::class, 'handleGoogleCallback'])
    ->middleware(['guest'])
    ->name('callback');;
Route::post('v1/logout', [LoginController::class, 'logout']);
Route::post('v1/register',[LoginController::class, 'register'])->middleware(['guest']);

// Profile
Route::get('v1/profile', [ProfileController::class, 'index'])->middleware('auth:api');
Route::post('v1/profile/{id}', [ProfileController::class, 'update'])->middleware('auth:api');

// Location
Route::get('v1/locations', [LocationController::class,'index']);
Route::post('v1/locations',  [LocationController::class,'store'])->middleware('auth:api');
Route::get('v1/locations/{id}', [LocationController::class,'show']);
Route::put('v1/locations/{id}',  [LocationController::class,'update'])->middleware('auth:api');
Route::delete('v1/locations/{id}',  [LocationController::class,'destroy'])->middleware('auth:api');

// Toilet
Route::get('v1/toilets', [ToiletController::class,'index']);
Route::post('v1/toilets',  [ToiletController::class,'store'])->middleware('auth:api');
Route::get('v1/toilets/{id}', [ToiletController::class,'show']);
Route::put('v1/toilets/{id}', [ToiletController::class,'update'])->middleware('auth:api');
Route::delete('v1/toilets/{id}', [ToiletController::class,'destroy'])->middleware('auth:api');

Route::get('v1/toilets-favorite', [ToiletController::class,'indexFavorite'])->middleware('auth:api');
Route::post('v1/toilets-favorite/{id}', [ToiletController::class,'favorite'])->middleware('auth:api');
Route::delete('v1/toilets-favorite/{id}', [ToiletController::class,'unfavorite'])->middleware('auth:api');

// Checkin
Route::get('v1/checkins',  [CheckinController::class,'index'])->middleware('auth:api');
Route::post('v1/checkins',  [CheckinController::class,'store']);
Route::get('v1/checkins/{id}',  [CheckinController::class,'show'])->middleware('auth:api');
Route::delete('v1/checkins/{id}',  [CheckinController::class,'destroy'])->middleware('auth:api');

// Review
Route::get('v1/reviews',  [ReviewController::class,'index'])->middleware('auth:api');
Route::post('v1/reviews',  [ReviewController::class,'store'])->middleware('auth:api');
Route::post('v1/reviews/upload',  [ReviewController::class,'storeImage'])->middleware('auth:api');
Route::get('v1/reviews/{id}',  [ReviewController::class,'show'])->middleware('auth:api');
Route::delete('v1/reviews/{id}',  [ReviewController::class,'destroy'])->middleware('auth:api');

// Toilet Image
Route::post('v1/toilets/images', [ToiletController::class, 'storeImage'])->middleware('auth:api');
Route::post('v1/toilets/update-images/{id}',  [ToiletController::class, 'updateImage'])->middleware('auth:api');
Route::delete('v1/toilets/images/{id}',  [ToiletController::class, 'deleteimage'])->middleware('auth:api');

// point
Route::post('v1/point',[PointController::class,'claimPoint'])->middleware('auth:api');
Route::get('v1/point',[PointController::class,'index'])->middleware('auth:api');
Route::get('v1/point-histories',[PointController::class,'pointHistory'])->middleware('auth:api');
Route::delete('v1/point/{id}',[PointController::class,'deletePoint'])->middleware('auth:api');
