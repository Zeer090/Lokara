<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicSchedule;
use App\Models\Lokasi;
use App\Models\StudyProgram;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * PemindaiJadwalAkademikService
 *
 * Memindai file Excel (.xlsx) atau CSV jadwal kuliah secara otomatis:
 * - Mengenali kolom secara fleksibel (atau mengekstrak teks bebas memakai Gemini AI)
 * - Mendeteksi otomatis: dosen, mata kuliah, program studi, tanggal/hari, jam, dan ruangan
 * - Memetakan ke ID database (StudyProgram, AcademicPeriod, Lokasi) tanpa user perlu input UUID
 */
class PemindaiJadwalAkademikService
{
    public function __construct(
        protected LayananLlmService $llm
    ) {}

    /**
     * Memindai file Excel/CSV dan mengembalikan baris terstruktur siap validasi/simpan.
     */
    public function pindaiBerkas(UploadedFile $file, ?string $defaultPeriodId = null, ?string $defaultProgramId = null): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rawRows = ($ext === 'xlsx')
            ? $this->bacaXlsx($file->getRealPath())
            : $this->bacaCsv($file->getRealPath());

        if (empty($rawRows)) {
            return [
                'galat' => 'Berkas kosong atau tidak dapat dibaca.',
                'baris' => [],
            ];
        }

        // Ambil header & data
        $headerAsli = array_map(fn ($h) => trim((string) $h), $rawRows[0]);
        $dataRows   = array_slice($rawRows, 1);

        // Hapus baris kosong total
        $dataRows = array_values(array_filter($dataRows, function ($r) {
            return count(array_filter($r, fn ($cell) => trim((string) $cell) !== '')) > 0;
        }));

        if (empty($dataRows)) {
            return [
                'galat' => 'Tidak ada baris jadwal di dalam berkas.',
                'baris' => [],
            ];
        }

        // Muat master data untuk resolusi otomatis
        $prodiList   = StudyProgram::all();
        $lokasiList  = Lokasi::where('aktif', true)->get();
        $todayStr = now('Asia/Jakarta')->toDateString();
        $periodeAktif = $defaultPeriodId
            ? AcademicPeriod::find($defaultPeriodId)
            : (AcademicPeriod::where('tanggal_mulai', '<=', $todayStr)
                ->where('tanggal_selesai', '>=', $todayStr)
                ->orderByDesc('tanggal_mulai')
                ->first()
                ?? AcademicPeriod::orderByDesc('tanggal_mulai')->first());

        // Coba petakan header dengan aturan fleksibel; jika ambigu atau hanya 1-2 kolom teks bebas, gunakan AI
        $petaKolom = $this->petakanHeader($headerAsli);

        $hasilScan = [];
        $barisPerluAi = [];

        foreach ($dataRows as $idx => $row) {
            $lineNo = $idx + 2;

            // Gabungkan jika hanya 1 kolom atau kolom penting tidak terpetakan di header
            $terpetakanLengkap = isset($petaKolom['nama_mata_kuliah']) && (isset($petaKolom['hari']) || isset($petaKolom['tanggal'])) && isset($petaKolom['ruangan']);

            if ($terpetakanLengkap) {
                $item = $this->ekstrakDariBarisTerpetakan($row, $petaKolom, $lineNo);
                $hasilScan[] = $this->lengkapiResolusiMaster($item, $prodiList, $lokasiList, $periodeAktif, $defaultProgramId);
            } else {
                $barisPerluAi[] = [
                    'line' => $lineNo,
                    'teks' => $this->gabungkanBarisJadiTeks($headerAsli, $row),
                ];
            }
        }

        // Jika ada baris yang formatnya bebas / tidak standar, scan batch dengan Gemini AI
        if (!empty($barisPerluAi)) {
            $hasilAi = $this->pindaiDenganAi($barisPerluAi, $prodiList, $lokasiList);
            foreach ($hasilAi as $item) {
                $hasilScan[] = $this->lengkapiResolusiMaster($item, $prodiList, $lokasiList, $periodeAktif, $defaultProgramId);
            }
        }

        // Cek bentrok antar baris dalam file & terhadap jadwal aktif di DB
        $hasilScan = $this->tandaiBentrok($hasilScan, $periodeAktif?->id);

        return [
            'periode' => $periodeAktif ? [
                'id'           => $periodeAktif->id,
                'tahun_ajaran' => $periodeAktif->tahun_ajaran,
                'semester'     => $periodeAktif->semester,
            ] : null,
            'baris' => $hasilScan,
        ];
    }

    /**
     * Membaca file .xlsx native PHP menggunakan ZipArchive + SimpleXML.
     */
    protected function bacaXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $xml = @simplexml_load_string($ssXml);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) ($r->t ?? '');
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $rowValues = [];
            $maxCol = 0;

            foreach ($rowNode->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colIndex = $this->kolomKeIndeks($ref);
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
                }

                $type = (string) ($cell['t'] ?? '');
                $val  = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's' && $val !== '') {
                    $val = $sharedStrings[(int) $val] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string) $cell->is->t;
                }

                $rowValues[$colIndex] = trim($val);
            }

            // Isi sel kosong agar array berurutan dari 0..maxCol
            $normalized = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $normalized[] = $rowValues[$i] ?? '';
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    protected function kolomKeIndeks(string $cellRef): int
    {
        if (!preg_match('/^([A-Z]+)/i', $cellRef, $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return max(0, $index - 1);
    }

    /**
     * Membaca CSV dengan deteksi delimiter otomatis (koma atau titik koma).
     */
    protected function bacaCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ($firstLine !== false && substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Hilangkan UTF-8 BOM di sel pertama jika ada
            if (empty($rows) && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
            }
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Memetakan nama header fleksibel (Indonesia/Inggris/singkatan) ke field standar.
     */
    protected function petakanHeader(array $header): array
    {
        $peta = [];
        foreach ($header as $idx => $col) {
            $norm = mb_strtolower(preg_replace('/[^a-z0-9]/i', '_', trim($col)));

            if (preg_match('/(mata_kuliah|matkul|nama_mk|mk$|subject|course)/', $norm) && !isset($peta['nama_mata_kuliah'])) {
                $peta['nama_mata_kuliah'] = $idx;
            } elseif (preg_match('/(kode_mk|kode)/', $norm) && !isset($peta['kode_mk'])) {
                $peta['kode_mk'] = $idx;
            } elseif (preg_match('/(kelas|class)/', $norm) && !isset($peta['kelas'])) {
                $peta['kelas'] = $idx;
            } elseif (preg_match('/(prodi|program_studi|jurusan|department)/', $norm) && !isset($peta['program_studi'])) {
                $peta['program_studi'] = $idx;
            } elseif (preg_match('/(dosen|pengampu|pengajar|siapa|lecturer)/', $norm) && !isset($peta['nama_dosen'])) {
                $peta['nama_dosen'] = $idx;
            } elseif (preg_match('/^(tanggal|tgl|date)$/', $norm) && !isset($peta['tanggal'])) {
                $peta['tanggal'] = $idx;
            } elseif (preg_match('/(hari|day)/', $norm) && !isset($peta['hari'])) {
                $peta['hari'] = $idx;
            } elseif (preg_match('/(jam_mulai|mulai|start)/', $norm) && !isset($peta['jam_mulai'])) {
                $peta['jam_mulai'] = $idx;
            } elseif (preg_match('/(jam_selesai|selesai|end)/', $norm) && !isset($peta['jam_selesai'])) {
                $peta['jam_selesai'] = $idx;
            } elseif (preg_match('/^(jam|waktu|pukul|time)$/', $norm) && !isset($peta['waktu_gabung'])) {
                $peta['waktu_gabung'] = $idx;
            } elseif (preg_match('/(lokasi_id|ruangan|ruang|lokasi|room)/', $norm) && !isset($peta['ruangan'])) {
                $peta['ruangan'] = $idx;
            }
        }
        return $peta;
    }

    protected function ekstrakDariBarisTerpetakan(array $row, array $peta, int $lineNo): array
    {
        $get = fn ($key) => isset($peta[$key]) ? trim((string) ($row[$peta[$key]] ?? '')) : '';

        $jamMulai   = $this->normalisasiJam($get('jam_mulai'));
        $jamSelesai = $this->normalisasiJam($get('jam_selesai'));

        if (($jamMulai === '' || $jamSelesai === '') && $get('waktu_gabung') !== '') {
            if (preg_match('/(\d{1,2}[:.]\d{2})\s*[-–sd]+\s*(\d{1,2}[:.]\d{2})/i', $get('waktu_gabung'), $m)) {
                $jamMulai   = $this->normalisasiJam($m[1]);
                $jamSelesai = $this->normalisasiJam($m[2]);
            }
        }

        $tanggalRaw = $get('tanggal');
        $hariRaw    = $get('hari');
        [$hari, $tanggalIso] = $this->normalisasiHariDanTanggal($hariRaw, $tanggalRaw);

        $mk = $get('nama_mata_kuliah');
        $kodeMk = $get('kode_mk') ?: $this->buatKodeMk($mk);

        return [
            'baris'            => $lineNo,
            'nama_mata_kuliah' => $mk,
            'kode_mk'          => $kodeMk,
            'kelas'            => $get('kelas') ?: 'A',
            'program_studi'    => $get('program_studi'),
            'nama_dosen'       => $get('nama_dosen') ?: 'Tim Pengajar',
            'hari'             => $hari,
            'tanggal'          => $tanggalIso,
            'jam_mulai'        => $jamMulai,
            'jam_selesai'      => $jamSelesai,
            'ruangan_mentah'   => $get('ruangan'),
        ];
    }

    protected function gabungkanBarisJadiTeks(array $header, array $row): string
    {
        $parts = [];
        foreach ($row as $i => $val) {
            $v = trim((string) $val);
            if ($v === '') {
                continue;
            }
            $h = trim((string) ($header[$i] ?? ''));
            $parts[] = $h !== '' ? "{$h}: {$v}" : $v;
        }
        return implode(' | ', $parts);
    }

    /**
     * Memindai baris teks bebas menggunakan Gemini AI (atau fallback regex).
     */
    protected function pindaiDenganAi(array $barisPerluAi, $prodiList, $lokasiList): array
    {
        $daftarProdi  = $prodiList->pluck('nama')->implode(', ');
        $daftarRuang  = $lokasiList->where('jenis', 'ruangan')->pluck('nama')->implode(', ');

        if ($this->llm->tersedia()) {
            $inputJson = json_encode($barisPerluAi, JSON_UNESCAPED_UNICODE);
            $systemPrompt = <<<PROMPT
Kamu adalah AI Scanner Jadwal Kuliah SMARTSPACE POLINELA.
Ekstrak setiap baris teks jadwal menjadi array JSON berisi objek dengan key persis:
- "baris": integer (nomor baris dari input)
- "nama_mata_kuliah": string
- "kode_mk": string (buat singkatan jika tidak ada, mis. IF101)
- "kelas": string (default "A" jika tidak disebut)
- "program_studi": string (pilih yang paling cocok dari: {$daftarProdi})
- "nama_dosen": string (nama dosen/pengampu, default "Tim Pengajar" jika tidak disebut)
- "hari": string huruf kecil ("senin"|"selasa"|"rabu"|"kamis"|"jumat"|"sabtu"|"minggu", konversi jika berupa tanggal!)
- "tanggal": string "YYYY-MM-DD" atau "" jika hanya nama hari
- "jam_mulai": string "HH:MM" (24 jam)
- "jam_selesai": string "HH:MM" (24 jam)
- "ruangan_mentah": string (pilih nama ruangan terdekat dari: {$daftarRuang})

Kembalikan objek JSON: {"items": [...]}
PROMPT;

            $res = $this->llm->chatJson($systemPrompt, [
                ['role' => 'user', 'content' => $inputJson],
            ], ['temperature' => 0.1, 'max_tokens' => 1500]);

            if (is_array($res) && isset($res['items']) && is_array($res['items'])) {
                return $res['items'];
            }
        }

        // Fallback lokal sederhana jika LLM tidak tersedia
        $fallback = [];
        foreach ($barisPerluAi as $b) {
            $teks = $b['teks'];
            preg_match('/(\d{1,2}[:.]\d{2})\s*[-–sd]+\s*(\d{1,2}[:.]\d{2})/i', $teks, $jm);
            $fallback[] = [
                'baris'            => $b['line'],
                'nama_mata_kuliah' => mb_substr($teks, 0, 60),
                'kode_mk'          => 'MK' . $b['line'],
                'kelas'            => 'A',
                'program_studi'    => '',
                'nama_dosen'       => 'Tim Pengajar',
                'hari'             => $this->tebakHariDariTeks($teks),
                'tanggal'          => '',
                'jam_mulai'        => isset($jm[1]) ? $this->normalisasiJam($jm[1]) : '08:00',
                'jam_selesai'      => isset($jm[2]) ? $this->normalisasiJam($jm[2]) : '10:00',
                'ruangan_mentah'   => $teks,
            ];
        }
        return $fallback;
    }

    /**
     * Mencocokkan hasil scan ke ID Prodi & ID Lokasi resmi di database.
     */
    protected function lengkapiResolusiMaster(array $item, $prodiList, $lokasiList, ?AcademicPeriod $periodeAktif, ?string $defaultProgramId): array
    {
        // 1. Resolusi Program Studi
        $prodiMentah = mb_strtolower(trim((string) ($item['program_studi'] ?? '')));
        $prodiCocok = null;

        if ($prodiMentah !== '') {
            $prodiCocok = $prodiList->first(function ($p) use ($prodiMentah) {
                $nama = mb_strtolower($p->nama);
                return $nama === $prodiMentah
                    || str_contains($nama, $prodiMentah)
                    || str_contains($prodiMentah, $nama)
                    || ($prodiMentah === 'ti' && str_contains($nama, 'informatika'))
                    || ($prodiMentah === 'si' && str_contains($nama, 'sistem informasi'));
            });
        }

        if (!$prodiCocok && $defaultProgramId) {
            $prodiCocok = $prodiList->firstWhere('id', $defaultProgramId);
        }
        if (!$prodiCocok) {
            $prodiCocok = $prodiList->first();
        }

        // 2. Resolusi Ruangan (Lokasi)
        $ruangMentah = trim((string) ($item['ruangan_mentah'] ?? ''));
        $ruangLower  = mb_strtolower($ruangMentah);
        $lokasiCocok = null;

        if ($ruangMentah !== '') {
            // Cek jika sudah UUID
            $lokasiCocok = $lokasiList->firstWhere('id', $ruangMentah);
            if (!$lokasiCocok) {
                $lokasiCocok = $lokasiList->first(function ($l) use ($ruangLower) {
                    $nama = mb_strtolower($l->nama);
                    return $nama === $ruangLower
                        || str_contains($nama, $ruangLower)
                        || str_contains($ruangLower, $nama);
                });
            }
        }

        // Normalisasi hari/tanggal sekali lagi
        [$hari, $tanggal] = $this->normalisasiHariDanTanggal(
            (string) ($item['hari'] ?? ''),
            (string) ($item['tanggal'] ?? '')
        );

        $item['academic_period_id'] = $periodeAktif?->id;
        $item['study_program_id']   = $prodiCocok?->id;
        $item['department_id']      = $prodiCocok?->department_id;
        $item['program_studi']      = $prodiCocok?->nama ?? ($item['program_studi'] ?: 'Umum');
        $item['lokasi_id']          = $lokasiCocok?->id;
        $item['lokasi_nama']        = $lokasiCocok?->nama ?? ($ruangMentah ?: 'Belum terpetakan');
        $item['hari']               = $hari;
        $item['tanggal']            = $tanggal;
        $item['jam_mulai']          = $this->normalisasiJam((string) ($item['jam_mulai'] ?? ''));
        $item['jam_selesai']        = $this->normalisasiJam((string) ($item['jam_selesai'] ?? ''));
        $item['galat']              = [];

        if (empty($item['nama_mata_kuliah'])) {
            $item['galat'][] = 'Mata kuliah tidak terdeteksi';
        }
        if (empty($item['lokasi_id'])) {
            $item['galat'][] = "Ruangan '{$ruangMentah}' tidak ditemukan di master lokasi";
        }
        if (empty($item['hari'])) {
            $item['galat'][] = 'Hari/tanggal tidak valid';
        }
        if (empty($item['jam_mulai']) || empty($item['jam_selesai']) || $item['jam_selesai'] <= $item['jam_mulai']) {
            $item['galat'][] = 'Rentang jam tidak valid';
        }

        return $item;
    }

    protected function tandaiBentrok(array $items, ?string $periodId): array
    {
        $seen = [];
        foreach ($items as &$item) {
            if (!empty($item['galat']) || empty($item['lokasi_id'])) {
                $item['status_scan'] = 'perlu_perbaikan';
                continue;
            }

            $key = $item['lokasi_id'] . '|' . $item['hari'] . '|' . $item['jam_mulai'] . '|' . $item['jam_selesai'];
            if (isset($seen[$key])) {
                $item['galat'][] = 'Duplikat dengan baris lain di berkas yang sama';
                $item['status_scan'] = 'bentrok';
                continue;
            }
            $seen[$key] = true;

            if ($periodId && !empty($item['study_program_id'])) {
                $bentrokDb = AcademicSchedule::where('academic_period_id', $periodId)
                    ->where('aktif', true)
                    ->where(function ($q) use ($item) {
                        $q->where('study_program_id', '!=', $item['study_program_id'])
                          ->orWhereNull('study_program_id');
                    })
                    ->where('lokasi_id', $item['lokasi_id'])
                    ->where('hari', $item['hari'])
                    ->where('jam_mulai', '<', $item['jam_selesai'])
                    ->where('jam_selesai', '>', $item['jam_mulai'])
                    ->exists();

                if ($bentrokDb) {
                    $item['galat'][] = 'Bentrok dengan jadwal prodi lain di ruangan ini';
                    $item['status_scan'] = 'bentrok';
                    continue;
                }
            }

            $item['status_scan'] = 'siap';
        }
        unset($item);

        return $items;
    }

    protected function normalisasiJam(string $jam): string
    {
        $jam = trim($jam);
        if ($jam === '') {
            return '';
        }

        // Tangani Excel numeric fraction of day (mis. 0.33333 -> 08:00)
        if (is_numeric($jam) && (float) $jam > 0 && (float) $jam < 1) {
            $totalMenit = (int) round((float) $jam * 24 * 60);
            return sprintf('%02d:%02d', intdiv($totalMenit, 60), $totalMenit % 60);
        }

        $jam = str_replace('.', ':', $jam);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $jam, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    protected function normalisasiHariDanTanggal(string $hariRaw, string $tanggalRaw): array
    {
        $tanggalIso = '';
        $hari = mb_strtolower(trim($hariRaw));

        $mapHari = [
            'monday' => 'senin', 'senin' => 'senin', 'mon' => 'senin',
            'tuesday' => 'selasa', 'selasa' => 'selasa', 'tue' => 'selasa',
            'wednesday' => 'rabu', 'rabu' => 'rabu', 'wed' => 'rabu',
            'thursday' => 'kamis', 'kamis' => 'kamis', 'thu' => 'kamis',
            'friday' => 'jumat', 'jumat' => 'jumat', "jum'at" => 'jumat', 'fri' => 'jumat',
            'saturday' => 'sabtu', 'sabtu' => 'sabtu', 'sat' => 'sabtu',
            'sunday' => 'minggu', 'minggu' => 'minggu', 'ahad' => 'minggu', 'sun' => 'minggu',
        ];

        $tglBersih = trim($tanggalRaw);
        if ($tglBersih !== '') {
            try {
                // Serial date Excel (angka > 30000)
                if (is_numeric($tglBersih) && (int) $tglBersih > 30000) {
                    $dt = Carbon::create(1899, 12, 30)->addDays((int) $tglBersih);
                } else {
                    $dt = Carbon::parse(str_replace('/', '-', $tglBersih));
                }
                $tanggalIso = $dt->format('Y-m-d');
                $enDay = mb_strtolower($dt->format('l'));
                $hari  = $mapHari[$enDay] ?? $hari;
            } catch (\Throwable) {
                // abaikan jika tidak bisa diparse
            }
        }

        $hari = $mapHari[$hari] ?? (in_array($hari, ['senin','selasa','rabu','kamis','jumat','sabtu','minggu'], true) ? $hari : '');

        return [$hari, $tanggalIso];
    }

    protected function tebakHariDariTeks(string $teks): string
    {
        $lower = mb_strtolower($teks);
        foreach (['senin','selasa','rabu','kamis','jumat','sabtu','minggu'] as $h) {
            if (str_contains($lower, $h)) {
                return $h;
            }
        }
        return 'senin';
    }

    protected function buatKodeMk(string $namaMk): string
    {
        $kata = preg_split('/\s+/', trim($namaMk));
        $singkat = '';
        foreach ($kata as $k) {
            if ($k !== '' && ctype_alpha($k[0])) {
                $singkat .= strtoupper($k[0]);
            }
        }
        return ($singkat !== '' ? substr($singkat, 0, 3) : 'MK') . '101';
    }
}
