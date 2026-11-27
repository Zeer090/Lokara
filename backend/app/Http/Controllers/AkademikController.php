<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicSchedule;
use App\Models\AcademicScheduleUpload;
use App\Models\Department;
use App\Models\StudyProgram;
use App\Models\Lokasi;
use App\Models\Pengguna;
use App\Services\PemindaiJadwalAkademikService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\UploadedFile;

class AkademikController extends Controller
{
    /**
     * POST /api/akademik/upload
     * Upload a complete academic schedule file (CSV).
     */
    public function upload(Request $request): JsonResponse
    {
        // 1. Basic validation
        $request->validate([
            'academic_period_id' => 'required|uuid|exists:academic_periods,id',
            'department_id'      => 'required|uuid|exists:departments,id',
            'study_program_id'   => 'required|uuid|exists:study_programs,id',
            'file'               => 'required|file|mimes:csv,txt',
        ]);

        // 2. Verify program belongs to department
        $program = \App\Models\StudyProgram::findOrFail($request->input('study_program_id'));
        if ($program->department_id !== $request->input('department_id')) {
            return response()->json(['galat' => 'Study program does not belong to the specified department'], 422);
        }

        $periodId = $request->input('academic_period_id');
        /** @var UploadedFile $file */
        $file = $request->file('file');

        // 3. Store file for audit
        $path = $file->store('academic_uploads');

        // 4. Create upload record (pending)
        $upload = AcademicScheduleUpload::create([
            'academic_period_id'  => $periodId,
            'department_id'       => $request->input('department_id'),
            'study_program_id'    => $request->input('study_program_id'),
            'uploaded_by_user_id' => $request->user()->id,
            'file_path'           => $path,
            'status'              => 'pending',
        ]);

        $errors = [];
        $rows   = [];
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            $upload->update([
                'status' => 'failed',
                'validation_errors' => ['file' => ['Unable to read file']],
            ]);
            throw new HttpException(422, 'Unable to read file');
        }

        // 5. Header validation
        $header = fgetcsv($handle);
        $expected = ['nama_mata_kuliah','kode_mk','kelas','program_studi','nama_dosen','hari','jam_mulai','jam_selesai','lokasi_id'];
        $missing = array_diff($expected, $header ?? []);
        if (!empty($missing)) {
            $upload->update([
                'status' => 'failed',
                'validation_errors' => ['header' => $missing],
            ]);
            return response()->json(['galat' => 'Header missing', 'details' => $missing], 422);
        }

        $lineNumber  = 1; // header line already read
        $uniqueCheck = [];
        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $row = array_combine($header, $data);
            if ($row === false) {
                $errors[$lineNumber][] = 'Row could not be parsed';
                continue;
            }
            $row['_line'] = $lineNumber;

            // Required columns
            foreach ($expected as $col) {
                if (trim($row[$col] ?? '') === '') {
                    $errors[$lineNumber][] = "{$col} is required";
                }
            }

            // Day validation
            $validDays = ['senin','selasa','rabu','kamis','jumat','sabtu','minggu'];
            if (!in_array(strtolower($row['hari'] ?? ''), $validDays, true)) {
                $errors[$lineNumber][] = 'Invalid hari value';
            }

            // Time validation
            try {
                $start = Carbon::createFromFormat('H:i', $row['jam_mulai']);
                $end   = Carbon::createFromFormat('H:i', $row['jam_selesai']);
                if ($end->lte($start)) {
                    $errors[$lineNumber][] = 'jam_selesai must be after jam_mulai';
                }
            } catch (\Exception $e) {
                $errors[$lineNumber][] = 'Invalid time format';
            }

            // Lokasi existence
            $lokasi = Lokasi::find($row['lokasi_id'] ?? null);
            if (! $lokasi) {
                $errors[$lineNumber][] = 'lokasi_id does not exist';
            }

            // Duplicate within file
            $key = $row['lokasi_id'] . '|' . strtolower($row['hari']) . '|' . $row['jam_mulai'] . '|' . $row['jam_selesai'];
            if (isset($uniqueCheck[$key])) {
                $errors[$lineNumber][] = 'Duplicate schedule entry in file';
            } else {
                $uniqueCheck[$key] = true;
            }

            $rows[] = $row;
        }
        fclose($handle);

        // 6. Verify program_studi matches selected program for all rows
        $programName = $program->nama;
        foreach ($rows as $row) {
            if (trim($row['program_studi'] ?? '') !== $programName) {
                $errors[$row['_line']][] = 'program_studi does not match the selected study program';
            }
        }

        // 7. Return validation errors if any (no DB changes)
        if (!empty($errors)) {
            $upload->update([
                'status' => 'failed',
                'validation_errors' => $errors,
            ]);
            return response()->json(['galat' => 'Validation errors', 'details' => $errors], 422);
        }

        // 8. Conflict detection across overlapping periods
        $conflictRows = [];
        $targetPeriod = AcademicPeriod::find($periodId);
        if ($targetPeriod) {
            $overlappingIds = AcademicPeriod::where('id', '!=', $periodId)
                ->where('tanggal_mulai', '<=', $targetPeriod->tanggal_selesai)
                ->where('tanggal_selesai', '>=', $targetPeriod->tanggal_mulai)
                ->pluck('id');
            if ($overlappingIds->isNotEmpty()) {
                foreach ($rows as $row) {
                    $conflict = AcademicSchedule::whereIn('academic_period_id', $overlappingIds)
                        ->where('lokasi_id', $row['lokasi_id'])
                        ->where('hari', $row['hari'])
                        ->where('aktif', true)
                        ->where(function ($q) use ($row) {
                            $q->where('jam_mulai', '<', $row['jam_selesai'])
                               ->where('jam_selesai', '>', $row['jam_mulai']);
                        })
                        ->exists();
                    if ($conflict) {
                        $conflictRows[$row['_line']][] = "Overlaps with active schedule from another overlapping academic period";
                    }
                }
            }
        }

        // 9. Conflict detection within same period but different program (including NULL)
        $programId = $request->input('study_program_id');
        foreach ($rows as $row) {
            $conflict = AcademicSchedule::where('academic_period_id', $periodId)
                ->where('aktif', true)
                ->where(function ($q) use ($programId) {
                    $q->where('study_program_id', '!=', $programId)
                      ->orWhereNull('study_program_id');
                })
                ->where('lokasi_id', $row['lokasi_id'])
                ->where('hari', $row['hari'])
                ->where(function ($q) use ($row) {
                    $q->where('jam_mulai', '<', $row['jam_selesai'])
                       ->where('jam_selesai', '>', $row['jam_mulai']);
                })
                ->exists();
            if ($conflict) {
                $conflictRows[$row['_line']][] = 'Overlaps with active schedule from another program in the same academic period';
            }
        }

        if (!empty($conflictRows)) {
            $upload->update([
                'status' => 'failed',
                'validation_errors' => $conflictRows,
            ]);
            return response()->json(['galat' => 'Conflict with existing schedule', 'details' => $conflictRows], 422);
        }

        // 10. Prepare rows for insertion (remove _line and add foreign keys)
        $insertRows = array_map(function ($r) use ($periodId, $programId) {
            unset($r['_line']);
            $r['academic_period_id'] = $periodId;
            $r['study_program_id']   = $programId;
            $r['aktif']              = true;
            return $r;
        }, $rows);

        // 11. Atomic replacement transaction
        DB::transaction(function () use ($periodId, $programId, $insertRows, $upload) {
            // Deactivate previous active schedules for same period & program
            AcademicSchedule::where('academic_period_id', $periodId)
                ->where('study_program_id', $programId)
                ->where('aktif', true)
                ->update(['aktif' => false]);

            // Bulk insert new schedules
            AcademicSchedule::insert($insertRows);

            // Mark upload as active
            $upload->update(['status' => 'active']);
        });

        return response()->json([
            'message'   => 'Schedule uploaded and activated',
            'upload_id' => $upload->id,
        ], 201);
    }

    /**
     * POST /api/akademik/scan
     * Memindai file Excel (.xlsx) atau CSV jadwal kuliah dengan AI Agent / Sistem
     * tanpa mengharuskan pengguna mengisi periode, prodi, atau UUID ruangan secara manual.
     */
    public function scan(Request $request, PemindaiJadwalAkademikService $pemindai): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:10240',
        ]);

        $hasil = $pemindai->pindaiBerkas($request->file('file'));
        if (!empty($hasil['galat'])) {
            return response()->json(['galat' => $hasil['galat']], 422);
        }

        return response()->json($hasil);
    }

    /**
     * POST /api/akademik/aktivasi-scan
     * Mengaktifkan hasil pemindaian jadwal kuliah dan langsung mengintegrasikannya ke jadwal ruangan.
     */
    public function aktivasiScan(Request $request): JsonResponse
    {
        $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.academic_period_id'  => 'required|uuid|exists:academic_periods,id',
            'items.*.study_program_id'    => 'required|uuid|exists:study_programs,id',
            'items.*.department_id'       => 'nullable|uuid|exists:departments,id',
            'items.*.lokasi_id'           => 'required|uuid|exists:lokasi,id',
            'items.*.nama_mata_kuliah'    => 'required|string|max:200',
            'items.*.kode_mk'             => 'required|string|max:50',
            'items.*.kelas'               => 'required|string|max:20',
            'items.*.program_studi'       => 'required|string|max:120',
            'items.*.nama_dosen'          => 'required|string|max:120',
            'items.*.hari'                => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'items.*.jam_mulai'           => 'required|date_format:H:i',
            'items.*.jam_selesai'         => 'required|date_format:H:i',
        ]);

        $items = $request->input('items');
        $user  = $request->user();

        $jumlahTersimpan = 0;

        DB::transaction(function () use ($items, $user, &$jumlahTersimpan) {
            // Kelompokkan berdasarkan kombinasi periode + prodi yang terdeteksi dari hasil scan
            $grup = collect($items)->groupBy(fn ($it) => $it['academic_period_id'] . '|' . $it['study_program_id']);

            foreach ($grup as $key => $barisProdi) {
                [$periodId, $programId] = explode('|', $key);
                $deptId = $barisProdi->first()['department_id']
                    ?? StudyProgram::find($programId)?->department_id;

                $upload = AcademicScheduleUpload::create([
                    'academic_period_id'  => $periodId,
                    'department_id'       => $deptId,
                    'study_program_id'    => $programId,
                    'uploaded_by_user_id' => $user->id,
                    'file_path'           => 'ai_scan_' . now()->format('YmdHis'),
                    'status'              => 'active',
                ]);

                // Nonaktifkan jadwal aktif sebelumnya untuk periode + prodi yang sama
                AcademicSchedule::where('academic_period_id', $periodId)
                    ->where('study_program_id', $programId)
                    ->where('aktif', true)
                    ->update(['aktif' => false]);

                $insertRows = [];
                foreach ($barisProdi as $row) {
                    $insertRows[] = [
                        'id'                 => (string) Str::uuid(),
                        'academic_period_id' => $periodId,
                        'study_program_id'   => $programId,
                        'nama_mata_kuliah'   => $row['nama_mata_kuliah'],
                        'kode_mk'            => $row['kode_mk'],
                        'kelas'              => $row['kelas'],
                        'program_studi'      => $row['program_studi'],
                        'nama_dosen'         => $row['nama_dosen'],
                        'hari'               => $row['hari'],
                        'jam_mulai'          => $row['jam_mulai'],
                        'jam_selesai'        => $row['jam_selesai'],
                        'lokasi_id'          => $row['lokasi_id'],
                        'aktif'              => true,
                    ];
                }

                AcademicSchedule::insert($insertRows);
                $jumlahTersimpan += count($insertRows);
            }
        });

        return response()->json([
            'message'         => 'Jadwal kuliah hasil pemindaian AI berhasil diaktifkan dan terintegrasi ke jadwal ruangan.',
            'jumlah_tersimpan' => $jumlahTersimpan,
        ], 201);
    }

    /**
     * GET /api/akademik/upload
     * List upload history (newest first).
     */
    public function listUploads(): JsonResponse
    {
        $uploads = AcademicScheduleUpload::orderByDesc('created_at')->get();
        return response()->json($uploads);
    }

    /**
     * GET /api/akademik/periods
     * List academic periods ordered by start date descending.
     */
    public function listPeriods(): JsonResponse
    {
        $periods = AcademicPeriod::orderByDesc('tanggal_mulai')->get();
        return response()->json($periods);
    }

    /**
     * GET /api/akademik/schedule/{periodId}
     * Return active schedules for a given academic period.
     */
    public function activeSchedule(string $periodId): JsonResponse
    {
        if (!Str::isUuid($periodId)) {
            return response()->json(['galat' => 'Format ID periode akademik tidak valid.'], 404);
        }
        $schedules = AcademicSchedule::where('academic_period_id', $periodId)
            ->where('aktif', true)
            ->get();
        return response()->json($schedules);
    }

    /**
     * GET /api/akademik/upload/{uploadId}/errors
     * Return stored validation errors for a specific upload.
     */
    public function validationErrors(string $uploadId): JsonResponse
    {
        if (!Str::isUuid($uploadId)) {
            return response()->json(['galat' => 'Data tidak ditemukan.'], 404);
        }
        $upload = AcademicScheduleUpload::find($uploadId);
        if (! $upload) {
            return response()->json(['galat' => 'Data tidak ditemukan.'], 404);
        }
        return response()->json([
            'status' => $upload->status,
            'errors' => $upload->validation_errors,
        ]);
    }
}
