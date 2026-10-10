<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
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
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('/pos', [DashboardController::class, 'pos'])->name('pos');
        Route::get('/menu-management', [DashboardController::class, 'menuManagement'])->name('menu-management');
        Route::post('/menu-management', [ModuleController::class, 'storeMenuItem'])->name('menu-management.store');
        Route::patch('/menu-management/{item}', [ModuleController::class, 'toggleMenuItem'])->name('menu-management.toggle');
        Route::get('/inventory', [DashboardController::class, 'inventory'])->name('inventory');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::post('/inventory/{ingredient}/logs', [InventoryController::class, 'log'])->name('inventory.log');
        Route::get('/recipes', [ModuleController::class, 'recipes'])->name('recipes');
        Route::post('/recipes', [ModuleController::class, 'storeRecipe'])->name('recipes.store');
        Route::get('/barcode', [DashboardController::class, 'barcode'])->name('barcode');
        Route::post('/barcode', [ModuleController::class, 'receiveBarcode'])->name('barcode.receive');
        Route::get('/waste', [ModuleController::class, 'waste'])->name('waste');
        Route::post('/waste', [ModuleController::class, 'storeWaste'])->name('waste.store');
        Route::get('/analytics', [ModuleController::class, 'analytics'])->name('analytics');
        Route::get('/forecasting', [ModuleController::class, 'forecasting'])->name('forecasting');
        Route::get('/reservations', [ModuleController::class, 'reservations'])->name('reservations');
        Route::post('/reservations', [ModuleController::class, 'storeReservation'])->name('reservations.store');
        Route::patch('/reservations/{reservation}', [ModuleController::class, 'updateReservation'])->name('reservations.update');
        Route::get('/chatbot', [ModuleController::class, 'chatbot'])->name('chatbot');
        Route::post('/chatbot', [ModuleController::class, 'chatbotMessage'])->name('chatbot.message');
        Route::post('/chatbot/knowledge', [ModuleController::class, 'storeKnowledge'])->name('chatbot.knowledge');
        Route::get('/users', [ModuleController::class, 'users'])->name('users');
        Route::post('/users', [ModuleController::class, 'storeUser'])->name('users.store');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/activity', [ModuleController::class, 'activity'])->name('activity');
        Route::get('/settings', [ModuleController::class, 'settings'])->name('settings');
        Route::post('/settings', [ModuleController::class, 'updateSettings'])->name('settings.update');
    });
