<?php

use Illuminate\Support\Facades\Route;
use Modules\Academic\Infrastructure\Http\Controllers\CatalogosController;
use Modules\Academic\Infrastructure\Http\Controllers\GradoController;
use Modules\Academic\Infrastructure\Http\Controllers\MatriculaController;
use Modules\Academic\Infrastructure\Http\Controllers\NivelAcademicoController;
use Modules\Academic\Infrastructure\Http\Controllers\OfertaAcademicaController;
use Modules\Academic\Infrastructure\Http\Controllers\PeriodoAcademicoController;
use Modules\Academic\Infrastructure\Http\Controllers\SeccionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth', 'role:staff/admin', 'module:academic'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('ofertas/create', [OfertaAcademicaController::class, 'create'])->name('ofertas.create');
    Route::post('ofertas', [OfertaAcademicaController::class, 'store'])->name('ofertas.store');
    Route::get('matriculas/create', [MatriculaController::class, 'create'])->name('matriculas.create');
    Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');

    Route::get('catalogos', [CatalogosController::class, 'index'])->name('catalogos');

    Route::post('niveles', [NivelAcademicoController::class, 'store'])->name('niveles.store');
    Route::put('niveles/{nivel}', [NivelAcademicoController::class, 'update'])->name('niveles.update');
    Route::delete('niveles/{nivel}', [NivelAcademicoController::class, 'destroy'])->name('niveles.destroy');

    Route::post('grados', [GradoController::class, 'store'])->name('grados.store');
    Route::put('grados/{grado}', [GradoController::class, 'update'])->name('grados.update');
    Route::delete('grados/{grado}', [GradoController::class, 'destroy'])->name('grados.destroy');

    Route::post('secciones', [SeccionController::class, 'store'])->name('secciones.store');
    Route::put('secciones/{seccion}', [SeccionController::class, 'update'])->name('secciones.update');
    Route::delete('secciones/{seccion}', [SeccionController::class, 'destroy'])->name('secciones.destroy');

    Route::post('periodos', [PeriodoAcademicoController::class, 'store'])->name('periodos.store');
    Route::put('periodos/{periodo}', [PeriodoAcademicoController::class, 'update'])->name('periodos.update');
    Route::delete('periodos/{periodo}', [PeriodoAcademicoController::class, 'destroy'])->name('periodos.destroy');
    Route::post('periodos/{periodo}/activate', [PeriodoAcademicoController::class, 'activate'])->name('periodos.activate');
});
