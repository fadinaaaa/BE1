<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\AhsWithItemsController;
use App\Http\Controllers\VendorController;

//Auth
Route::get('/vendors/template/download', [VendorController::class, 'downloadTemplate']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/validate-token', [AuthController::class, 'validateToken']);
});
Route::get('/vendors/export', [VendorController::class, 'export'])->name('vendors.export');
Route::get('/items/export', [ItemController::class, 'export']);
Route::get('/ahs', [AhsWithItemsController::class, 'get_data_ahs']);
Route::get('/ahs/option-item', [AhsWithItemsController::class, 'getOptionItem']);
Route::put('/ahs/{ahs_id}', [AhsWithItemsController::class, 'update']);

Route::middleware('auth:sanctum')->group(function () {


    //AHS
    Route::post('/ahs', [AhsWithItemsController::class, 'addDataAhs']);
    Route::delete('/ahs/{ahs_id}', [AhsWithItemsController::class, 'destroy']);
    Route::get('ahs/export', [AhsWithItemsController::class, 'export'])->name('ahs.export');
    Route::get('ahs/import/template', [AhsWithItemsController::class, 'downloadImportTemplate'])->name('ahs.import.template');
    Route::post('ahs/import', [AhsWithItemsController::class, 'import'])->name('ahs.import.store');
});

    // Item
    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::get('/items/next-id', [ItemController::class, 'getNextId']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);
    Route::get('/items/template/download', [ItemController::class, 'downloadTemplate']);
    Route::post('items/import', [ItemController::class, 'import'])->name('items.import');
    Route::apiResource('items', ItemController::class);
    Route::get('/dokumen/preview/{id}', [ItemController::class, 'previewDokumen']);


    // Vendor
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::post('/vendors', [VendorController::class, 'store']);
    Route::get('/vendors/{id}', [VendorController::class, 'show']);
    Route::put('/vendors/{id}', [VendorController::class, 'update']);
    Route::delete('/vendors/{id}', [VendorController::class, 'destroy']);
    Route::post('vendors/import', [VendorController::class, 'import'])->name('vendors.import');
