<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Memo Persetujuan Gedung</title>
    <style>
        @page {
            size: 80mm 45mm;
            margin: 1.5mm;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 77mm;
            height: 42mm;
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            font-size: 5pt;
            line-height: 1.15;
            background: #ffffff;
        }
        .card {
            border: 1pt solid #1e3a8a;
            border-radius: 2mm;
            width: 100%;
            height: 42mm;
            background: #ffffff;
            display: table;
            table-layout: fixed;
            position: relative;
        }
        .col-left {
            display: table-cell;
            width: 68%;
            vertical-align: top;
            padding: 1.8mm 2.2mm;
            border-right: 0.6pt dashed #cbd5e1;
        }
        .col-right {
            display: table-cell;
            width: 32%;
            vertical-align: top;
            padding: 1.8mm 1.5mm;
            background: #f8fafc;
            text-align: center;
            border-top-right-radius: 1.8mm;
            border-bottom-right-radius: 1.8mm;
        }
        .header {
            border-bottom: 0.6pt solid #e2e8f0;
            padding-bottom: 0.6mm;
            margin-bottom: 0.8mm;
        }
        .instansi {
            font-size: 3.8pt;
            font-weight: bold;
            color: #475569;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .judul {
            font-size: 5.4pt;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            margin-top: 0.2mm;
        }
        table.info {
            width: 100%;
            border-collapse: collapse;
        }
        table.info td {
            padding: 0.3mm 0;
            vertical-align: top;
        }
        td.label {
            width: 25%;
            color: #64748b;
            font-size: 4.4pt;
        }
        td.colon {
            width: 4%;
            text-align: center;
            color: #64748b;
        }
        td.val {
            width: 71%;
            font-size: 4.6pt;
            color: #0f172a;
        }
        .badge-status {
            background: #dcfce7;
            border: 0.6pt solid #86efac;
            color: #15803d;
            font-weight: 900;
            font-size: 5pt;
            padding: 1mm 1.5mm;
            border-radius: 1mm;
            display: inline-block;
            margin-top: 1mm;
            margin-bottom: 2mm;
            text-transform: uppercase;
        }
        .ttd-box {
            margin-top: 1.2mm;
            border-top: 0.4pt solid #cbd5e1;
            padding-top: 1mm;
            font-size: 3.8pt;
            color: #475569;
        }
        .ttd-nama {
            font-size: 4.5pt;
            font-weight: bold;
            color: #1e293b;
            margin-top: 2.2mm;
            text-decoration: underline;
        }
        .stamp-cabut {
            position: absolute;
            top: 10mm;
            left: 3mm;
            right: 3mm;
            background: rgba(220, 38, 38, 0.95);
            color: #ffffff;
            font-weight: 900;
            font-size: 6.5pt;
            text-align: center;
            padding: 1.8mm 0;
            border-radius: 1mm;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            z-index: 100;
        }
    </style>
</head>
<body>

<div class="card">
    @if ($dicabut)
        <div class="stamp-cabut">
            TIDAK BERLAKU / DICABUT
            <div style="font-size: 3.8pt; font-weight: normal; margin-top: 0.3mm;">
                {{ $dicabutPada ?? 'Jadwal dialihkan kepentingan dinas' }}
            </div>
        </div>
    @endif

    <div class="col-left">
        <div class="header">
            <div class="instansi">POLINELA • SMARTSPACE AI</div>
            <div class="judul">MEMO PERSETUJUAN GEDUNG</div>
        </div>
        <table class="info">
            <tr>
                <td class="label">No. Memo</td>
                <td class="colon">:</td>
                <td class="val" style="font-family: monospace; font-weight: bold;">{{ $nomorMemo }}</td>
            </tr>
            <tr>
                <td class="label">Kode</td>
                <td class="colon">:</td>
                <td class="val" style="font-family: monospace;">{{ $reservasi->kode }}</td>
            </tr>
            <tr>
                <td class="label">Gedung</td>
                <td class="colon">:</td>
                <td class="val" style="color: #1e40af; font-weight: bold;">
                    {{ $lokasi->nama }} ({{ $lokasi->kapasitas }} org)
                </td>
            </tr>
            <tr>
                <td class="label">Pemohon</td>
                <td class="colon">:</td>
                <td class="val">
                    {{ \Illuminate\Support\Str::limit($pengguna->nama, 24) }} ({{ $pengguna->organisasi ?? 'Civitas' }})
                </td>
            </tr>
            <tr>
                <td class="label">Waktu</td>
                <td class="colon">:</td>
                <td class="val" style="font-weight: bold;">
                    {{ \Carbon\Carbon::parse($reservasi->mulai)->translatedFormat('l, d M Y') }} • 
                    {{ \Carbon\Carbon::parse($reservasi->mulai)->format('h:i A') }} - {{ \Carbon\Carbon::parse($reservasi->selesai)->format('h:i A') }}
                </td>
            </tr>
            <tr>
                <td class="label">Acara</td>
                <td class="colon">:</td>
                <td class="val">
                    {{ \Illuminate\Support\Str::limit($reservasi->keperluan, 30) }}
                    @if($reservasi->jumlah_peserta)
                        ({{ $reservasi->jumlah_peserta }} org)
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="col-right">
        <div style="font-size: 3.8pt; color: #64748b; text-transform: uppercase;">VERIFIKASI IZIN</div>
        <div class="badge-status">DISETUJUI ✓</div>
        <div class="ttd-box">
            <div>Otorisasi Pengelola:</div>
            <div class="ttd-nama">Bu Eci</div>
            <div style="font-size: 3.5pt; color: #64748b;">Pengelola Gedung POLINELA</div>
        </div>
        <div style="font-size: 3.2pt; color: #94a3b8; margin-top: 1.8mm; font-family: monospace;">
            AUTH-VERIFIED-PDF
        </div>
    </div>
</div>

</body>
</html>
