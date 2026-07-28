<?php
use App\Http\Controllers\{ComisionesRrhhController, InicioController, LiquidacionPdfController, NotificacionController, OficinaController, ParametrosController, ProfileController, SolicitudController, UsuarioController, ViaticosController};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('inicio'))->middleware('auth');

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/inicio', [InicioController::class, 'index'])->name('inicio');

    // Solicitudes
    Route::get('/solicitudes',                          [SolicitudController::class, 'index'])->name('solicitudes.index');
    Route::get('/solicitudes/{solicitud}',              [SolicitudController::class, 'show'])->name('solicitudes.show');
    Route::post('/solicitudes/{solicitud}/transicion',  [SolicitudController::class, 'transicion'])->name('solicitudes.transicion');

    // Oficina
    Route::get('/oficina/crear',              [OficinaController::class, 'create'])->name('oficina.crear');
    Route::post('/oficina',                   [OficinaController::class, 'store'])->name('oficina.store');
    Route::get('/oficina/{solicitud}/editar', [OficinaController::class, 'edit'])->name('oficina.editar');
    Route::put('/oficina/{solicitud}',        [OficinaController::class, 'update'])->name('oficina.update');

    // Formato de liquidación por viajero (comisión cerrada)
    Route::get('/solicitudes/{solicitud}/viajeros/{viajero}/liquidacion.pdf', [LiquidacionPdfController::class, 'descargar'])->name('liquidacion.pdf');
    Route::post('/solicitudes/{solicitud}/viajeros/{viajero}/liquidacion/correo', [LiquidacionPdfController::class, 'enviarCorreo'])->name('liquidacion.correo');

    // Viáticos
    Route::get('/viaticos/crear',                    [ViaticosController::class, 'create'])->name('viaticos.crear');
    Route::post('/viaticos',                         [ViaticosController::class, 'store'])->name('viaticos.store');
    Route::get('/viaticos/{solicitud}/editar',       [ViaticosController::class, 'edit'])->name('viaticos.editar');
    Route::put('/viaticos/{solicitud}',              [ViaticosController::class, 'update'])->name('viaticos.update');
    Route::get('/viaticos/{solicitud}/liquidar',     [ViaticosController::class, 'liquidacion'])->name('viaticos.liquidacion');
    Route::put('/viaticos/{solicitud}/asignaciones', [ViaticosController::class, 'updateAllocations'])->name('viaticos.asignaciones');

    // Parámetros
    Route::get('/parametros',                          [ParametrosController::class, 'index'])->name('parametros.index');
    Route::post('/parametros/tarifas',                 [ParametrosController::class, 'storeTarifa'])->name('parametros.tarifas.store');
    Route::put('/parametros/tarifas/{tarifa}',         [ParametrosController::class, 'updateTarifa'])->name('parametros.tarifas.update');
    Route::delete('/parametros/tarifas/{tarifa}',      [ParametrosController::class, 'destroyTarifa'])->name('parametros.tarifas.destroy');
    Route::post('/parametros/empleados',               [ParametrosController::class, 'storeEmpleado'])->name('parametros.empleados.store');
    Route::put('/parametros/empleados/{empleado}',     [ParametrosController::class, 'updateEmpleado'])->name('parametros.empleados.update');
    Route::delete('/parametros/empleados/{empleado}',  [ParametrosController::class, 'destroyEmpleado'])->name('parametros.empleados.destroy');

    // Usuarios (solo admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/usuarios',           [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios',          [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    });

    // Panel de comisiones (solo RR. HH.)
    Route::middleware('role:rrhh')->group(function () {
        Route::get('/rrhh/comisiones', [ComisionesRrhhController::class, 'index'])->name('rrhh.comisiones');
    });

    // Notificaciones
    Route::get('/notificaciones',                    [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/{id}/leer',          [NotificacionController::class, 'marcarLeida'])->name('notificaciones.leer');
    Route::post('/notificaciones/leer-todas',         [NotificacionController::class, 'marcarTodasLeidas'])->name('notificaciones.leer-todas');

    // Perfil
    Route::get('/perfil',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
