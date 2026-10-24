<?php

use App\Http\Controllers\AdmisionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaniaController;
use App\Http\Controllers\CampaniaEnlaceController;
use App\Http\Controllers\CampaniaUsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaboratorioAdmisionController;
use App\Http\Controllers\LaboratorioAsignacionController;
use App\Http\Controllers\LaboratorioStockController;
use App\Http\Controllers\LaboratorioTomaMuestraController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\ServicioUpsController;
use App\Http\Controllers\TriajeController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\EnsureOfertaFlexibleAdministrator;
use App\Http\Middleware\EnsureOfertaFlexibleAdmision;
use App\Http\Middleware\EnsureOfertaFlexibleAdmisionLaboratorio;
use App\Http\Middleware\EnsureOfertaFlexibleLaboratorio;
use App\Http\Middleware\EnsureOfertaFlexibleTriaje;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');

    Route::post('/login', [AuthController::class, 'store'])->name('login.store')->middleware('throttle:login');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email')->middleware('throttle:forgot-password');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.update')->middleware('throttle:reset-password');
});

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/welcome', [DashboardController::class, 'welcome'])->name('welcome');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('oferta-flexible/laboratorio')
        ->name('oferta-flexible.laboratorio.')
        ->middleware([EnsureOfertaFlexibleLaboratorio::class, 'menu.access'])
        ->group(function (): void {
            Route::get('asignar', [LaboratorioAsignacionController::class, 'index'])->name('asignar.index');
            Route::post('asignar', [LaboratorioAsignacionController::class, 'store'])->name('asignar.store');
            Route::get('toma-muestra', [LaboratorioTomaMuestraController::class, 'index'])
                ->name('toma-muestra.index');
            Route::post('toma-muestra', [LaboratorioTomaMuestraController::class, 'store'])
                ->name('toma-muestra.store');
        });
    Route::prefix('oferta-flexible/laboratorio')
        ->name('oferta-flexible.laboratorio.')
        ->middleware([EnsureOfertaFlexibleAdmisionLaboratorio::class, 'menu.access'])
        ->group(function (): void {
            Route::get('admision', [LaboratorioAdmisionController::class, 'index'])->name('admision.index');
            Route::delete('admision', [LaboratorioAdmisionController::class, 'destroy'])
                ->name('admision.destroy');
            Route::post('admision', [LaboratorioAdmisionController::class, 'store'])
                ->middleware('throttle:oferta-flexible-dni')
                ->name('admision.store');
        });

    Route::middleware('menu.access')->group(function () {
        Route::prefix('oferta-flexible')
            ->name('oferta-flexible.')
            ->middleware(EnsureOfertaFlexibleAdmision::class)
            ->group(function (): void {
                Route::get('admision', [AdmisionController::class, 'index'])->name('admision.index');
                Route::post('admision', [AdmisionController::class, 'imprimir'])->name('admision.imprimir');
                Route::get('admision/hoja-de-ruta/{token}.pdf', [AdmisionController::class, 'pdf'])
                    ->where('token', '[A-Za-z0-9]{64}')
                    ->name('admision.pdf');
                Route::delete('admision', [AdmisionController::class, 'destroy'])->name('admision.destroy');
            });
        Route::prefix('oferta-flexible')
            ->name('oferta-flexible.')
            ->middleware(EnsureOfertaFlexibleTriaje::class)
            ->group(function (): void {
                Route::get('triaje', [TriajeController::class, 'index'])->name('triaje.index');
                Route::post('triaje', [TriajeController::class, 'store'])
                    ->middleware('throttle:oferta-flexible-dni')
                    ->name('triaje.store');
            });
        Route::prefix('oferta-flexible')
            ->name('oferta-flexible.')
            ->middleware(EnsureOfertaFlexibleAdministrator::class)
            ->group(function (): void {
                Route::resource('servicios', ServicioUpsController::class)->except('show');
                Route::resource('campanias', CampaniaController::class)->parameters([
                    'campanias' => 'campania',
                ]);
                Route::post('campanias/{campania}/cerrar', [CampaniaController::class, 'cerrar'])->name(
                    'campanias.cerrar',
                );
                Route::post('campanias/{campania}/reabrir', [CampaniaController::class, 'reabrir'])->name(
                    'campanias.reabrir',
                );
                Route::get('campanias/{campania}/laboratorio/stock', LaboratorioStockController::class)
                    ->name('laboratorio.stock');
                Route::get('usuarios-campanias', [CampaniaUsuarioController::class, 'index'])->name(
                    'usuarios.index',
                );
                Route::post('campanias/{campania}/usuarios', [
                    CampaniaUsuarioController::class,
                    'store',
                ])->name('usuarios.store');
                Route::delete('campanias/{campania}/usuarios/{asignacion}', [
                    CampaniaUsuarioController::class,
                    'destroy',
                ])->name('usuarios.destroy');
                Route::post('campanias/{campania}/enlaces', [CampaniaEnlaceController::class, 'store'])->name(
                    'enlaces.store',
                );
                Route::delete('campanias/{campania}/enlaces/{enlace}', [
                    CampaniaEnlaceController::class,
                    'destroy',
                ])->name('enlaces.destroy');
            });
        // MÓDULO ADMIN
        Route::resource('usuarios', UsuarioController::class)->parameters(['usuarios' => 'usuario'])->except(['show']);
        Route::resource('roles', RolController::class)->parameters(['roles' => 'rol'])->except(['show']);
        Route::resource('menus', MenuController::class)->except(['show']);
    });

});
