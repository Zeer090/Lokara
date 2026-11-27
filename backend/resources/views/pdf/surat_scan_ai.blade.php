<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2.2cm 2.2cm; }
        body {
            font-family: "DejaVu Serif", Georgia, serif;
            font-size: 11pt;
            line-height: 1.55;
            color: #111827;
        }
        .kop {
            text-align: center;
            border-bottom: 3px double #1e293b;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .kop h1 {
            font-size: 14pt;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kop p {
            font-size: 9pt;
            margin: 3px 0 0 0;
            color: #475569;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 10.5pt;
        }
        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .tujuan {
            margin-bottom: 16px;
            font-weight: bold;
        }
        .paragraf {
            text-align: justify;
            text-indent: 24px;
            margin: 0 0 11px 0;
        }
        .ttd-wrap {
            margin-top: 34px;
            width: 100%;
        }
        .ttd-box {
            width: 45%;
            margin-left: 55%;
            text-align: left;
        }
        .ttd-space {
            height: 55px;
        }
        .footer-ai {
            margin-top: 28px;
            padding-top: 8px;
            border-top: 1px dashed #cbd5e1;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8pt;
            color: #64748b;
        }
        .page-break {
            page-break-before: always;
        }
        .lampiran-title {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 10px;
            color: #334155;
        }
        .lampiran-img {
            max-width: 100%;
            max-height: 22cm;
            border: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $surat['kop_instansi'] }}</h1>
        <p>Dokumen Surat Permohonan Resmi — Dihasilkan oleh AI Scanner SMARTSPACE POLINELA</p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 85px;">Nomor</td>
            <td style="width: 12px;">:</td>
            <td>{{ $surat['nomor_surat'] }}</td>
            <td style="text-align: right;">{{ $surat['tanggal_surat'] }}</td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>:</td>
            <td colspan="2"><strong>{{ $surat['perihal'] }}</strong></td>
        </tr>
    </table>

    <div class="tujuan">
        {{ $surat['tujuan_surat'] }}<br>
        di Tempat
    </div>

    @foreach($surat['isi_paragraf'] as $p)
        <p class="paragraf">{{ $p }}</p>
    @endforeach

    <div class="ttd-wrap">
        <div class="ttd-box">
            <div>{{ $surat['jabatan'] }},</div>
            <div class="ttd-space"></div>
            <div><strong>{{ $surat['penandatangan'] }}</strong></div>
        </div>
    </div>

    <div class="footer-ai">
        Dipindai &amp; dirapikan oleh AI Document Scanner SMARTSPACE pada {{ $waktu_scan }}. Lampiran foto kertas asli tersedia di halaman berikutnya sebagai verifikasi fisik.
    </div>

    <div class="page-break"></div>
    <div class="lampiran-title">Lampiran: Foto Kertas / Surat Fisik Asli</div>
    <div style="text-align: center;">
        <img src="{{ $foto_data_uri }}" class="lampiran-img" alt="Lampiran Foto Asli">
    </div>
</body>
</html>
