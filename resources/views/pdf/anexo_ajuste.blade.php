<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { font-size: 11px; color: #111; margin: 24px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #444; padding: 4px 6px; }
        .cab td { vertical-align: middle; }
        .titulo { text-align: center; font-weight: bold; font-size: 12px; }
        .sub { text-align: center; font-weight: bold; }
        .meta-label { font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .encabezado-rubros th { background: #d9d9d9; font-weight: bold; text-align: center; }
        .total-row td { font-weight: bold; }
        .money { text-align: right; white-space: nowrap; }
        .motivo { margin-top: 6px; }
        .motivo td { vertical-align: top; }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <table class="cab">
        <tr>
            <td class="titulo" style="width: 62%;">DIRECCIÓN FINANCIERA Y CONTABLE</td>
            <td style="width: 20%;" class="center">VERSIÓN 1</td>
        </tr>
        <tr>
            <td class="sub">ANEXO — AJUSTE DE LIQUIDACIÓN</td>
            <td class="center">GCS-DFC-02</td>
        </tr>
        <tr>
            <td></td>
            <td class="center">{{ $fecha_documento }}</td>
        </tr>
    </table>

    {{-- Datos de la comisión --}}
    <table style="margin-top: 6px;">
        <tr>
            <td class="meta-label" style="width: 32%;">NOMBRE DE EMPLEADO / CONTRATISTA:</td>
            <td class="center">{{ $empleado }}</td>
        </tr>
        <tr>
            <td class="meta-label">LUGAR DE COMISIÓN DE SERVICIO:</td>
            <td class="center">{{ $lugar }}</td>
        </tr>
        <tr>
            <td class="meta-label">RADICADO DE LA COMISIÓN:</td>
            <td class="center">{{ $radicado }}</td>
        </tr>
    </table>

    {{-- Rubros del ajuste (delta) --}}
    <table style="margin-top: 6px;">
        <thead class="encabezado-rubros">
            <tr>
                <th style="width: 40%;">DESCRIPCIÓN DE RUBRO</th>
                <th>VALOR UNITARIO</th>
                <th>CANTIDAD</th>
                <th>VALOR TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rubros as $r)
                <tr>
                    <td>{{ strtoupper($r['rubro']) }}</td>
                    <td class="money">{{ $r['valor_unitario_fmt'] }}</td>
                    <td class="center">{{ $r['dias'] }}</td>
                    <td class="money">{{ $r['subtotal_fmt'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center">Sin rubros en el ajuste.</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2" class="center">TOTAL DEL AJUSTE</td>
                <td colspan="2" class="money">{{ $total_delta_fmt }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Motivo del ajuste --}}
    <table class="motivo">
        <tr>
            <td class="meta-label" style="width: 32%;">MOTIVO DEL AJUSTE:</td>
            <td>{{ $motivo }}</td>
        </tr>
    </table>

    {{-- Firmas --}}
    <table style="margin-top: 6px;">
        <tr>
            <td class="meta-label" style="width: 25%;">Realizado por</td>
            <td class="center">{{ $realizado_por }}</td>
        </tr>
    </table>
</body>
</html>
