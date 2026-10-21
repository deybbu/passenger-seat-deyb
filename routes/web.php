<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\RegisterController;
use App\Http\Controllers\Admin\TableQrController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerMenuController;
use App\Http\Controllers\CustomerQrOrderController;
use App\Http\Controllers\EventInquiryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\Staff\KitchenController;
use App\Http\Controllers\Staff\OrderBoardController;
use App\Http\Controllers\Staff\TableSessionController;
use App\Http\Controllers\Staff\WalkInOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/menu', [CustomerMenuController::class, 'index'])->name('menu');
Route::get('/order', [CustomerQrOrderController::class, 'show'])->name('customer.qr.show');
Route::get('/order/{tableToken}', [CustomerQrOrderController::class, 'showTable'])
    ->name('customer.order.show');
Route::post('/order/{tableToken}/submit', [CustomerQrOrderController::class, 'submit'])
    ->middleware('throttle:customer-table-order')
    ->name('customer.order.submit');
Route::post('/order/{tableToken}/bill-request', [CustomerQrOrderController::class, 'requestBillForTableToken'])
    ->middleware('throttle:customer-table-order')
    ->name('customer.order.bill-request');
Route::post('/order/{tableToken}/bill-request/cancel', [CustomerQrOrderController::class, 'cancelBillRequest'])
    ->middleware('throttle:customer-table-order')
    ->name('customer.order.bill-request.cancel');
Route::get('/order/{tableToken}/session', [CustomerQrOrderController::class, 'session'])
    ->middleware('throttle:120,1')
    ->name('customer.order.session');
Route::get('/order/{tableToken}/status', [CustomerQrOrderController::class, 'status'])
    ->middleware('throttle:120,1')
    ->name('customer.order.status');
Route::post('/order', [CustomerQrOrderController::class, 'place'])
    ->middleware('throttle:20,1')
    ->name('customer.qr.place');
Route::get('/order/table/{tableSession:public_token}/orders', [CustomerQrOrderController::class, 'orders'])
    ->name('customer.qr.orders');
Route::post('/order/table/{tableSession:public_token}/bill', [CustomerQrOrderController::class, 'requestBill'])
    ->middleware('throttle:5,1')
    ->name('customer.qr.bill');
Route::view('/story', 'landing.story')->name('story');
Route::redirect('/our-story', '/story');
Route::get('/events', [EventInquiryController::class, 'index'])->name('events');
Route::post('/events', [EventInquiryController::class, 'store'])->name('events.store');
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
            ->middleware('role:kitchen,cashier')
            ->name('orders.transition');
        Route::patch('/orders/{order}/status', [OrderBoardController::class, 'transition'])
            ->middleware('role:kitchen,cashier')
            ->name('orders.status');
        Route::patch('/orders/{order}/complete', [OrderBoardController::class, 'complete'])
            ->middleware('role:cashier')
            ->name('orders.complete');
    });

Route::middleware(['auth', 'role:kitchen'])
    ->prefix('staff/kitchen')
    ->name('staff.kitchen.')
    ->controller(KitchenController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/board', 'board')->name('board');
        Route::get('/inventory', 'inventory')->name('inventory');
    });

Route::middleware(['auth', 'role:cashier'])
    ->prefix('staff/pos')
    ->name('staff.pos.')
    ->group(function (): void {
        Route::get('/', [POSController::class, 'index'])->name('index');
        Route::get('/bill-requests', [POSController::class, 'billRequests'])->name('bill-requests');
        Route::get('/orders', [POSController::class, 'orders'])->name('orders');
        Route::get('/analytics', [POSController::class, 'analytics'])->name('analytics');
        Route::get('/transactions', [POSController::class, 'ledger'])->name('ledger');
        Route::get('/table-qr', [TableSessionController::class, 'posQr'])->name('table-qr');
        Route::get('/table-sessions/{tableSession}/bill', [POSController::class, 'tableSessionBill'])
            ->name('table-session-bill');
        Route::post('/table-sessions/{tableSession}/paid', [POSController::class, 'markTableSessionPaid'])
            ->name('table-session-paid');
        Route::post('/table-sessions/{tableSession}/reopen', [POSController::class, 'reopenTableSession'])
            ->name('table-session-reopen');
        Route::post('/tables/{restaurantTable}/paid', [POSController::class, 'markTablePaid'])
            ->name('table-paid');
        Route::get('/orders/{order}/receipt', [POSController::class, 'receipt'])->name('receipt');
        Route::post('/orders/{order}/paid', [POSController::class, 'markPaid'])->name('paid');
    });

Route::middleware(['auth', 'role:cashier'])
    ->prefix('staff/tables')
    ->name('staff.tables.')
    ->group(function (): void {
        Route::get('/', [TableSessionController::class, 'index'])->name('index');
        Route::post('/open', [TableSessionController::class, 'open'])->name('open');
        Route::patch('/{tableSession}/bill', [TableSessionController::class, 'requestBill'])->name('bill');
        Route::patch('/{tableSession}/close', [TableSessionController::class, 'close'])->name('close');
    });

Route::middleware(['auth', 'role:inventory'])
    ->prefix('staff/inventory')
    ->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/logs', [InventoryController::class, 'logs'])->name('inventory.logs');
        Route::post('/', [InventoryController::class, 'store'])->name('staff.inventory.store');
        Route::post('/barcode', [InventoryController::class, 'assignBarcode'])->name('staff.inventory.barcode');
        Route::post('/preparation-recipes', [InventoryController::class, 'storePreparationRecipe'])->name('staff.inventory.preparation-recipes.store');
        Route::post('/prepare', [InventoryController::class, 'recordPreparation'])->name('staff.inventory.prepare');
        Route::post('/waste', [InventoryController::class, 'recordWaste'])->name('staff.inventory.waste');
        Route::patch('/ingredients/{ingredient}/recipe-settings', [InventoryController::class, 'updateRecipeSettings'])->name('staff.inventory.recipe-settings');
        Route::post('/receive/fresh', [InventoryController::class, 'receiveFresh'])->name('staff.inventory.receive.fresh');
        Route::post('/receive/lookup', [InventoryController::class, 'lookupBarcode'])->name('staff.inventory.receive.lookup');
        Route::post('/receive/preview', [InventoryController::class, 'previewReceipt'])->name('staff.inventory.receive.preview');
        Route::post('/receive/confirm', [InventoryController::class, 'confirmReceipt'])->name('staff.inventory.receive.confirm');
        Route::post('/packages', [InventoryController::class, 'registerPackage'])->name('staff.inventory.packages.store');
        Route::patch('/{ingredient}', [InventoryController::class, 'update'])->name('staff.inventory.update');
        Route::post('/{ingredient}/logs', [InventoryController::class, 'log'])->name('staff.inventory.log');
        Route::delete('/{ingredient}', [InventoryController::class, 'destroy'])->name('staff.inventory.destroy');
    });

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/reference', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('reference');
        Route::get('/api/reference/dashboard', [DashboardController::class, 'referenceData'])->name('api.reference.dashboard');
        Route::post('/api/reference/menu', [ModuleController::class, 'referenceMenuStore'])->name('api.reference.menu.store');
        Route::post('/api/reference/recipe-target', [ModuleController::class, 'referenceRecipeTarget'])->name('api.reference.recipe.target');
        Route::post('/api/reference/purchase-orders/{ingredient}', [AdminInventoryController::class, 'referencePurchaseOrder'])->name('api.reference.purchase-orders.store');
        Route::patch('/api/reference/purchase-order-items/{purchaseOrderItem}/close-short', [AdminInventoryController::class, 'referenceCloseShort'])->name('api.reference.purchase-orders.close-short');
        Route::post('/api/reference/waste', [AdminInventoryController::class, 'referenceWaste'])->name('api.reference.waste.store');
        Route::post('/api/reference/barcode/lookup', [AdminInventoryController::class, 'lookupBarcode'])->name('api.reference.barcode.lookup');
        Route::post('/api/reference/barcode/receive', [AdminInventoryController::class, 'receivePackage'])->name('api.reference.barcode.receive');
        Route::post('/api/reference/register/place', [RegisterController::class, 'referencePlace'])->name('api.reference.register.place');
        Route::patch('/api/reference/table-qr/{restaurantTable}/toggle', [TableQrController::class, 'referenceToggle'])->name('api.reference.table-qr.toggle');
        Route::post('/api/reference/table-qr', [TableQrController::class, 'referenceStore'])->name('api.reference.table-qr.store');
        Route::patch('/api/reference/table-qr/{restaurantTable}', [TableQrController::class, 'referenceUpdate'])->name('api.reference.table-qr.update');
        Route::post('/api/reference/table-qr/{restaurantTable}/regenerate', [TableQrController::class, 'referenceRegenerate'])->name('api.reference.table-qr.regenerate');
        Route::get('/api/reference/table-qr/{restaurantTable}/image', [TableQrController::class, 'image'])->name('api.reference.table-qr.image');
        Route::delete('/api/reference/table-qr/{restaurantTable}', [TableQrController::class, 'referenceDelete'])->name('api.reference.table-qr.delete');
        Route::patch('/api/reference/reservations/{reservation}', [ModuleController::class, 'referenceReservation'])->name('api.reference.reservations.update');
        Route::post('/api/reference/reservations/{reservation}/notes', [ModuleController::class, 'referenceReservationNote'])->name('api.reference.reservations.notes');
        Route::post('/api/reference/chatbot/faq', [ModuleController::class, 'referenceFaq'])->name('api.reference.chatbot.faq');
        Route::post('/api/reference/chatbot/unanswered/{question}', [ModuleController::class, 'referenceAnswer'])->name('api.reference.chatbot.answer');
        Route::post('/api/reference/users', [ModuleController::class, 'referenceUser'])->name('api.reference.users.store');
        Route::patch('/api/reference/users/{user}/toggle', [ModuleController::class, 'referenceUserToggle'])->name('api.reference.users.toggle');
        Route::patch('/api/reference/roles/{role}/permission', [ModuleController::class, 'referencePermission'])->name('api.reference.roles.permission');
        Route::patch('/api/reference/alert-rules/{rule}', [ModuleController::class, 'referenceRule'])->name('api.reference.alert-rules.update');
        Route::post('/api/reference/settings', [ModuleController::class, 'referenceSettings'])->name('api.reference.settings.update');
        Route::post('/api/reference/activity', [ModuleController::class, 'referenceActivity'])->name('api.reference.activity.store');
        Route::patch('/api/reference/orders/{order}/status', [AdminOrderController::class, 'referenceStatus'])->name('api.reference.orders.status');
        Route::patch('/api/reference/menu/{item}/archive', [ModuleController::class, 'referenceMenuArchive'])->name('api.reference.menu.archive');
        Route::patch('/api/reference/menu/{item}/restore', [ModuleController::class, 'referenceMenuRestore'])->name('api.reference.menu.restore');
        Route::delete('/api/reference/menu/{item}', [ModuleController::class, 'referenceMenuDelete'])->name('api.reference.menu.delete');
        Route::post('/api/reference/menu/{item}/recipe', [ModuleController::class, 'referenceRecipe'])->name('api.reference.recipe.store');
        Route::delete('/api/reference/menu/{item}/recipe/{ingredient}', [ModuleController::class, 'referenceRecipeDelete'])->name('api.reference.recipe.delete');
        Route::get('/', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('dashboard');
        Route::get('/dashboard', fn () => view('admin.app', ['adminUser' => request()->user()]));
        Route::get('/orders', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('orders');
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('/pos', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('pos');
        Route::post('/pos', [RegisterController::class, 'store'])->name('pos.store');
        Route::post('/pos/held', [RegisterController::class, 'hold'])->name('pos.hold');
        Route::post('/pos/held/{order}/resume', [RegisterController::class, 'resume'])->name('pos.held.resume');
        Route::delete('/pos/held/{order}', [RegisterController::class, 'discard'])->name('pos.held.discard');
        Route::get('/billing', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('billing');
        Route::get('/table-qr', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('table-qr');
        Route::get('/table-qr/print', [TableQrController::class, 'printSheet'])->name('table-qr.print');
        Route::patch('/table-qr/{restaurantTable}/toggle', [TableQrController::class, 'toggle'])->name('table-qr.toggle');
        Route::get('/menu-management', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('menu-management');
        Route::post('/menu-management', [ModuleController::class, 'storeMenuItem'])->name('menu-management.store');
        Route::patch('/menu-management/{item}', [ModuleController::class, 'toggleMenuItem'])->name('menu-management.toggle');
        Route::patch('/menu-management/{item}/archive', [ModuleController::class, 'archiveMenuItem'])->name('menu-management.archive');
        Route::patch('/menu-management/{item}/restore', [ModuleController::class, 'restoreMenuItem'])->name('menu-management.restore');
        Route::delete('/menu-management/{item}', [ModuleController::class, 'destroyMenuItem'])->name('menu-management.destroy');
        Route::get('/inventory', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('inventory');
        Route::post('/inventory', [AdminInventoryController::class, 'storeIngredient'])->name('inventory.store');
        Route::post('/inventory/{ingredient}/logs', [InventoryController::class, 'log'])->name('inventory.log');
        Route::post('/inventory/{ingredient}/purchase-orders', [AdminInventoryController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
        Route::get('/purchase-orders', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('purchase-orders');
        Route::patch('/purchase-order-items/{purchaseOrderItem}/close-short', [AdminInventoryController::class, 'closeShort'])
            ->name('purchase-orders.close-short');
        Route::get('/recipes', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('recipes');
        Route::post('/recipes', [ModuleController::class, 'storeRecipe'])->name('recipes.store');
        Route::post('/recipes/target', [ModuleController::class, 'updateRecipeTarget'])->name('recipes.target');
        Route::delete('/recipes/{item}/ingredients/{ingredient}', [ModuleController::class, 'destroyRecipeLine'])->name('recipes.line.destroy');
        Route::get('/barcode', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('barcode');
        Route::post('/barcode/lookup', [AdminInventoryController::class, 'lookupBarcode'])->name('barcode.lookup');
        Route::post('/barcode/receive', [AdminInventoryController::class, 'receivePackage'])->name('barcode.receive');
        Route::get('/lots', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('lots');
        Route::get('/waste', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('waste');
        Route::post('/waste', [AdminInventoryController::class, 'storeWaste'])->name('waste.store');
        Route::get('/analytics', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('analytics');
        Route::get('/archived', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('archived');
        Route::get('/slow-moving', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('slow-moving');
        Route::get('/forecasting', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('forecasting');
        Route::get('/reservations', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('reservations');
        Route::post('/reservations', [ModuleController::class, 'storeReservation'])->name('reservations.store');
        Route::patch('/reservations/{reservation}', [ModuleController::class, 'updateReservation'])->name('reservations.update');
        Route::post('/reservations/{reservation}/notes', [ModuleController::class, 'addInquiryNote'])->name('reservations.notes.store');
        Route::post('/reservations/blackout', [ModuleController::class, 'toggleBlackout'])->name('reservations.blackout');
        Route::get('/chatbot', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('chatbot');
        Route::post('/chatbot', [ModuleController::class, 'chatbotMessage'])->name('chatbot.message');
        Route::post('/chatbot/knowledge', [ModuleController::class, 'storeKnowledge'])->name('chatbot.knowledge');
        Route::post('/chatbot/faq', [ModuleController::class, 'storeFaq'])->name('chatbot.faq.store');
        Route::post('/chatbot/unanswered/{question}', [ModuleController::class, 'answerQuestion'])->name('chatbot.unanswered.answer');
        Route::post('/chatbot/settings', [ModuleController::class, 'updateChatbotSettings'])->name('chatbot.settings');
        Route::get('/users', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('users');
        Route::post('/users', [ModuleController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{user}/toggle', [ModuleController::class, 'toggleUser'])->name('users.toggle');
        Route::patch('/roles/{role}/permission', [ModuleController::class, 'updatePermission'])->name('roles.permission');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::patch('/notifications/rules/{rule}/toggle', [NotificationController::class, 'toggleRule'])->name('notifications.rules.toggle');
        Route::get('/activity', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('activity');
        Route::get('/settings', fn () => view('admin.app', ['adminUser' => request()->user()]))->name('settings');
        Route::post('/settings', [ModuleController::class, 'updateSettings'])->name('settings.update');
    });
