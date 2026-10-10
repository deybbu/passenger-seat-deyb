<?php

use App\Http\Controllers\Admin\MenuAvailabilityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\Staff\OrderBoardController;
use App\Http\Controllers\Staff\WalkInOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::view('/our-story', 'landing.story')->name('story');
Route::view('/contact', 'landing.contact')->name('contact');

Route::get('/staff/login', [LoginController::class, 'show'])->name('staff.login');
Route::post('/staff/login', [LoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('staff.login.attempt');
Route::post('/staff/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('staff.logout');

Route::middleware(['auth', 'role:kitchen,cashier'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function (): void {
        Route::get('/orders', [OrderBoardController::class, 'index'])->name('orders.index');
        Route::get('/orders/new', [WalkInOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders/new', [WalkInOrderController::class, 'store'])->name('orders.store');
        Route::patch('/orders/{order}/transition', [OrderBoardController::class, 'transition'])
            ->middleware('role:kitchen')
            ->name('orders.transition');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->middleware('role:kitchen')
            ->name('orders.status');
    });

Route::middleware(['auth', 'role:cashier'])
    ->prefix('staff/pos')
    ->name('staff.pos.')
    ->group(function (): void {
        Route::get('/', [POSController::class, 'index'])->name('index');
        Route::get('/transactions', [POSController::class, 'ledger'])->name('ledger');
        Route::get('/orders/{order}/receipt', [POSController::class, 'receipt'])->name('receipt');
        Route::post('/orders/{order}/paid', [POSController::class, 'markPaid'])->name('paid');
    });

Route::middleware(['auth', 'role:inventory'])
    ->prefix('staff/inventory')
    ->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/logs', [InventoryController::class, 'logs'])->name('inventory.logs');
        Route::post('/', [InventoryController::class, 'store'])->name('staff.inventory.store');
        Route::patch('/{ingredient}', [InventoryController::class, 'update'])->name('staff.inventory.update');
        Route::post('/{ingredient}/logs', [InventoryController::class, 'log'])->name('staff.inventory.log');
        Route::delete('/{ingredient}', [InventoryController::class, 'destroy'])->name('staff.inventory.destroy');
    });

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/menu', [MenuAvailabilityController::class, 'index'])->name('menu.index');
        Route::patch('/menu/{item}/availability', [MenuAvailabilityController::class, 'toggle'])->name('menu.toggle');
    });
