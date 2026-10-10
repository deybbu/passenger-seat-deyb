<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::view('/our-story', 'landing.story')->name('story');
Route::view('/contact', 'landing.contact')->name('contact');
