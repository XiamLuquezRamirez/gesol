<?php
use App\Http\Controllers\{OficinaController, ProfileController, SolicitudController, ViaticosController};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('solicitudes.index'))->middleware('auth');

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/inicio', fn() => Inertia::render('Inicio/Index'))->name('inicio');

    // Solicitudes
    Route::get('/solicitudes',                          [SolicitudController::class, 'index'])->name('solicitudes.index');
    Route::get('/solicitudes/{solicitud}',              [SolicitudController::class, 'show'])->name('solicitudes.show');
    Route::post('/solicitudes/{solicitud}/transicion',  [SolicitudController::class, 'transicion'])->name('solicitudes.transicion');

    // Oficina
    Route::get('/oficina/crear',              [OficinaController::class, 'create'])->name('oficina.crear');
    Route::post('/oficina',                   [OficinaController::class, 'store'])->name('oficina.store');
    Route::get('/oficina/{solicitud}/editar', [OficinaController::class, 'edit'])->name('oficina.editar');
    Route::put('/oficina/{solicitud}',        [OficinaController::class, 'update'])->name('oficina.update');

    // Viáticos
    Route::get('/viaticos/crear',                    [ViaticosController::class, 'create'])->name('viaticos.crear');
    Route::post('/viaticos',                         [ViaticosController::class, 'store'])->name('viaticos.store');
    Route::get('/viaticos/{solicitud}/liquidar',     [ViaticosController::class, 'liquidacion'])->name('viaticos.liquidacion');
    Route::put('/viaticos/{solicitud}/asignaciones', [ViaticosController::class, 'updateAllocations'])->name('viaticos.asignaciones');

    // Perfil
    Route::get('/perfil',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
