@php
    $today = \Illuminate\Support\Carbon::today();
    $totalDebt = 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturas por vencer</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, Helvetica, sans-serif; color:#222;">
    <div style="max-width:640px; margin:0 auto; padding:24px;">
        <div style="background:#fff; border:1px solid #e5e5e5; border-radius:8px; padding:24px;">
            <h1 style="font-size:18px; margin:0 0 4px;">Facturas por vencer</h1>
            <p style="margin:0 0 16px; color:#666; font-size:14px;">
                Tenés {{ $purchases->count() }} factura(s) de proveedores próximas a vencer.
            </p>

            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e5e5; color:#666;">
                        <th style="padding:8px 4px;">Proveedor</th>
                        <th style="padding:8px 4px;">N° Factura</th>
                        <th style="padding:8px 4px;">Vence</th>
                        <th style="padding:8px 4px; text-align:center;">Faltan</th>
                        <th style="padding:8px 4px; text-align:right;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $p)
                        @php
                            $balance = (float) $p->balance;
                            $totalDebt += $balance;
                            $daysLeft = $today->diffInDays($p->due_date, false);
                        @endphp
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:8px 4px;"><strong>{{ $p->supplier->name ?? '-' }}</strong></td>
                            <td style="padding:8px 4px;">{{ $p->invoice_number ?? '-' }}</td>
                            <td style="padding:8px 4px;">{{ $p->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td style="padding:8px 4px; text-align:center;">
                                {{ $daysLeft <= 0 ? 'hoy' : $daysLeft . ' día' . ($daysLeft === 1 ? '' : 's') }}
                            </td>
                            <td style="padding:8px 4px; text-align:right; white-space:nowrap;">
                                ${{ number_format($balance, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin:18px 0 0; font-size:16px; text-align:right;">
                <strong>Total adeudado: ${{ number_format($totalDebt, 2, ',', '.') }}</strong>
            </p>

            <div style="margin:24px 0 0; padding:10px 14px; background:#fff7e6; border:1px solid #ffe0a3; border-radius:6px; font-size:13px; color:#A16207; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <span>Revisá y registrá los pagos en el panel.</span>
                <a href="https://admin.concoypunto.com/proveedores/compras"
                   style="display:inline-block; padding:7px 14px; background:#A16207; color:#fff; text-decoration:none; border-radius:5px; font-size:13px; font-weight:bold; white-space:nowrap;">
                    Cuentas por pagar →
                </a>
            </div>
        </div>
    </div>
</body>
</html>
