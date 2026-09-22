<?php
use App\Http\Controllers\{AbonoObraController, AbonoOficinaController, ArchivoViajeroController, ComisionesRrhhController, InicioController, LiquidacionPdfController, NotificacionController, ObraController, OficinaController, ParametrosController, ProfileController, ReporteController, SolicitudController, UsuarioController, ViaticosController};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('inicio'))->middleware('auth');

// Pantalla propia de "sesion expirada" (error 419). Es publica a proposito: cuando
// la sesion caduca, el usuario ya no esta autenticado y debe poder ver este aviso.
Route::get('/sesion-expirada', fn () => Inertia::render('Errores/SesionExpirada'))
    ->name('sesion.expirada');

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
    // Cotizaciones y comentario para el contador (RR. HH. / solicitante)
    Route::post('/oficina/{solicitud}/cotizacion', [OficinaController::class, 'anexarCotizacion'])->name('oficina.cotizacion.anexar');
    Route::get('/oficina/{solicitud}/cotizacion/{cotizacion}',    [OficinaController::class, 'descargarCotizacion'])->name('oficina.cotizacion.descargar');
    Route::delete('/oficina/{solicitud}/cotizacion/{cotizacion}', [OficinaController::class, 'eliminarCotizacion'])->name('oficina.cotizacion.eliminar');
    Route::post('/oficina/{solicitud}/cotizacion/{cotizacion}/actualizar', [OficinaController::class, 'actualizarCotizacion'])->name('oficina.cotizacion.actualizar');
    // Abonos (pagos parciales)
    Route::post('/oficina/{solicitud}/abono',                 [AbonoOficinaController::class, 'store'])->name('oficina.abono.store');
    Route::delete('/oficina/{solicitud}/abono/{abono}',       [AbonoOficinaController::class, 'destroy'])->name('oficina.abono.eliminar');
    Route::get('/oficina/{solicitud}/abono/{abono}/soporte',  [AbonoOficinaController::class, 'descargarSoporte'])->name('oficina.abono.soporte');

    // Formato de liquidación por viajero (comisión cerrada)
    Route::get('/solicitudes/{solicitud}/viajeros/{viajero}/liquidacion.pdf', [LiquidacionPdfController::class, 'descargar'])->name('liquidacion.pdf');
    Route::post('/solicitudes/{solicitud}/viajeros/{viajero}/liquidacion/correo', [LiquidacionPdfController::class, 'enviarCorreo'])->name('liquidacion.correo');

    // Obra
    Route::get('/obra/crear',  [ObraController::class, 'create'])->name('obra.crear');
    Route::post('/obra',       [ObraController::class, 'store'])->name('obra.store');
    Route::put('/obra/{solicitud}/cotizar',   [ObraController::class, 'cotizar'])->name('obra.cotizar');
    Route::put('/obra/{solicitud}/contrato',  [ObraController::class, 'relacionarContrato'])->name('obra.contrato');
    Route::post('/obra/{solicitud}/documento',[ObraController::class, 'anexarDocumento'])->name('obra.documento');
    Route::post('/obra/{solicitud}/abonos',                  [AbonoObraController::class, 'store'])->name('obra.abono.store');
    Route::put('/obra/{solicitud}/abonos/{abono}/retencion', [AbonoObraController::class, 'aplicarRetencion'])->name('obra.abono.retencion');
    Route::get('/obra/{solicitud}/abonos/{abono}/soporte',   [AbonoObraController::class, 'descargarSoporte'])->name('obra.abono.soporte');

    // Viáticos
    Route::get('/viaticos/crear',                    [ViaticosController::class, 'create'])->name('viaticos.crear');
    Route::post('/viaticos',                         [ViaticosController::class, 'store'])->name('viaticos.store');
    Route::get('/viaticos/{solicitud}/editar',       [ViaticosController::class, 'edit'])->name('viaticos.editar');
    Route::put('/viaticos/{solicitud}',              [ViaticosController::class, 'update'])->name('viaticos.update');
    Route::get('/viaticos/{solicitud}/liquidar',     [ViaticosController::class, 'liquidacion'])->name('viaticos.liquidacion');
    Route::put('/viaticos/{solicitud}/asignaciones', [ViaticosController::class, 'updateAllocations'])->name('viaticos.asignaciones');
    Route::post('/viaticos/{solicitud}/cancelar',    [ViaticosController::class, 'cancelar'])->name('viaticos.cancelar');
    Route::post('/viaticos/{solicitud}/reactivar',   [ViaticosController::class, 'reactivar'])->name('viaticos.reactivar');
    Route::put('/viaticos/{solicitud}/ajustar',      [ViaticosController::class, 'ajustar'])->name('viaticos.ajustar');
    Route::post('/viaticos/{solicitud}/reajustar-rubro', [ViaticosController::class, 'reajustarRubro'])->name('viaticos.reajustar-rubro');
    Route::get('/viaticos/{solicitud}/ajustes/{ajuste}/liquidar',     [ViaticosController::class, 'liquidacionAjuste'])->name('viaticos.ajuste.liquidar');
    Route::put('/viaticos/{solicitud}/ajustes/{ajuste}/asignaciones', [ViaticosController::class, 'updateAjuste'])->name('viaticos.ajuste.asignaciones');
    Route::post('/viaticos/{solicitud}/ajustes/{ajuste}/aprobar',  [ViaticosController::class, 'aprobarAjuste'])->name('viaticos.ajuste.aprobar');
    Route::post('/viaticos/{solicitud}/ajustes/{ajuste}/devolver', [ViaticosController::class, 'devolverAjuste'])->name('viaticos.ajuste.devolver');
    Route::get('/viaticos/{solicitud}/ajustes/{ajuste}/anexo.pdf',     [LiquidacionPdfController::class, 'descargarAnexo'])->name('viaticos.ajuste.pdf');
    Route::post('/viaticos/{solicitud}/ajustes/{ajuste}/anexo/correo', [LiquidacionPdfController::class, 'enviarCorreoAnexo'])->name('viaticos.ajuste.correo');
    Route::post('/viaticos/{solicitud}/viajeros/{viajero}/archivos',            [ArchivoViajeroController::class, 'store'])->name('viaticos.archivos.store');
    Route::get('/viaticos/{solicitud}/viajeros/{viajero}/archivos/{archivo}',   [ArchivoViajeroController::class, 'descargar'])->name('viaticos.archivos.descargar');
    Route::delete('/viaticos/{solicitud}/viajeros/{viajero}/archivos/{archivo}', [ArchivoViajeroController::class, 'destroy'])->name('viaticos.archivos.destroy');
    Route::patch('/viaticos/{solicitud}/viajeros/{viajero}/salida', [ComisionesRrhhController::class, 'confirmarSalida'])->name('viaticos.salida.confirmar');

    // Parámetros
    Route::get('/parametros',                          [ParametrosController::class, 'index'])->name('parametros.index');
    Route::post('/parametros/tarifas',                 [ParametrosController::class, 'storeTarifa'])->name('parametros.tarifas.store');
    Route::put('/parametros/tarifas/{tarifa}',         [ParametrosController::class, 'updateTarifa'])->name('parametros.tarifas.update');
    Route::delete('/parametros/tarifas/{tarifa}',      [ParametrosController::class, 'destroyTarifa'])->name('parametros.tarifas.destroy');
    Route::post('/parametros/empleados',               [ParametrosController::class, 'storeEmpleado'])->name('parametros.empleados.store');
    Route::put('/parametros/empleados/{empleado}',     [ParametrosController::class, 'updateEmpleado'])->name('parametros.empleados.update');
    Route::delete('/parametros/empleados/{empleado}',  [ParametrosController::class, 'destroyEmpleado'])->name('parametros.empleados.destroy');
    Route::post('/parametros/contratos',                [ParametrosController::class, 'storeContrato'])->name('parametros.contratos.store');
    Route::put('/parametros/contratos/{contrato}',      [ParametrosController::class, 'updateContrato'])->name('parametros.contratos.update');
    Route::delete('/parametros/contratos/{contrato}',   [ParametrosController::class, 'destroyContrato'])->name('parametros.contratos.destroy');
    Route::post('/parametros/conceptos',              [ParametrosController::class, 'storeConcepto'])->name('parametros.conceptos.store');
    Route::put('/parametros/conceptos/{concepto}',    [ParametrosController::class, 'updateConcepto'])->name('parametros.conceptos.update');
    Route::delete('/parametros/conceptos/{concepto}', [ParametrosController::class, 'destroyConcepto'])->name('parametros.conceptos.destroy');

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

    // Reportes consolidados (admin y contabilidad). Panel de tarjetas + una vista por informe.
    Route::middleware('role:admin|contador|contabilidad_lider')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/',            [ReporteController::class, 'index'])->name('index');
        Route::get('/viaticos',    [ReporteController::class, 'viaticos'])->name('viaticos');
        Route::get('/oficina',     [ReporteController::class, 'oficina'])->name('oficina');
        Route::get('/personal',    [ReporteController::class, 'personal'])->name('personal');
        Route::get('/comprobantes-pendientes', [ReporteController::class, 'comprobantesPendientes'])->name('comprobantes');
        Route::get('/reajustes',   [ReporteController::class, 'reajustes'])->name('reajustes');
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
