<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CabinetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('login', [AuthController::class, 'login'])->name('login');
Route::post('login',    [AuthController::class, 'doLogin'])->name('do-login');

Route::middleware('auth:web')->group(function () {
    Route::get('cabinet', [CabinetController::class, 'index'])->name('cabinet.index');
    Route::post('cabinet/upload', [CabinetController::class, 'upload'])->name('cabinet.upload');
    Route::post('cabinet/contacts', [CabinetController::class, 'store'])->name('cabinet.contacts.store');
    Route::get('cabinet/contacts/{contact}/edit', [CabinetController::class, 'edit'])->name('cabinet.contacts.edit');
    Route::put('cabinet/contacts/{contact}', [CabinetController::class, 'update'])->name('cabinet.contacts.update');
    Route::delete('cabinet/contacts/{contact}', [CabinetController::class, 'destroy'])->name('cabinet.contacts.destroy');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Админ-панель - управление доступом к книгам
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('contact-books-access', [\App\Http\Controllers\Admin\ContactBookAccessController::class, 'index'])->name('contact-books-access.index');
        Route::put('contact-books-access/{user}', [\App\Http\Controllers\Admin\ContactBookAccessController::class, 'update'])->name('contact-books-access.update');
    });
});
