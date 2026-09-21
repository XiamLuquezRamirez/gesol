<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { font-size: 10px; color: #111; margin: 24px; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .rango { color: #555; font-size: 10px; margin: 0 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        thead th { background: #e5e7eb; font-weight: bold; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .marca { text-align: right; color: #94a3b8; font-size: 9px; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p class="rango">Rango: {{ $desde }} a {{ $hasta }} · Generado: {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($encabezados as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($encabezados) }}" style="text-align:center; color:#777;">
                        Sin datos en este rango.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="marca">GeSol — Gestión de solicitudes</p>
</body>
</html>
