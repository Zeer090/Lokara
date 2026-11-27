<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSchedule;
use App\Models\AcademicScheduleUpload;
use App\Models\Lokasi;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AkademikUploadTest extends TestCase
{
    use DatabaseTransactions;

    protected Pengguna $akademikUser;
    protected Pengguna $penggunaBiasa;
    protected AcademicPeriod $period;
    protected Lokasi $ruangan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->akademikUser = Pengguna::create([
            'nama'          => 'Staf Akademik',
            'email'         => 'akademik_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'akademik',
            'aktif'         => true,
        ]);

        $this->penggunaBiasa = Pengguna::create([
            'nama'          => 'Pengguna Biasa',
            'email'         => 'biasa_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'pengguna',
            'aktif'         => true,
        ]);

        $this->period = AcademicPeriod::create([
            'tahun_ajaran'    => '2026/2027',
            'semester'        => 'ganjil',
            'tanggal_mulai'   => '2026-09-01',
            'tanggal_selesai' => '2027-01-31',
        ]);

        $this->ruangan = Lokasi::create([
            'nama'      => 'Lab Komputer ' . uniqid(),
            'jenis'     => 'ruangan',
            'kapasitas' => 40,
            'aktif'     => true,
        ]);
    }

    // =========================================================================
    // Authentication & Role Authorization (Endpoint Gating)
    // =========================================================================

    public function test_unauthenticated_cannot_access_akademik_endpoints(): void
    {
        $this->getJson('/api/akademik/periods')->assertStatus(401);
        $this->getJson('/api/akademik/upload')->assertStatus(401);
        $this->getJson("/api/akademik/schedule/{$this->period->id}")->assertStatus(401);
        $this->getJson('/api/akademik/upload/' . Str::uuid() . '/errors')->assertStatus(401);
        $this->postJson('/api/akademik/upload', [])->assertStatus(401);
    }

    public function test_non_akademik_role_rejected_403(): void
    {
        $this->actingAs($this->penggunaBiasa)->getJson('/api/akademik/periods')->assertStatus(403);
        $this->actingAs($this->penggunaBiasa)->getJson('/api/akademik/upload')->assertStatus(403);
        $this->actingAs($this->penggunaBiasa)->getJson("/api/akademik/schedule/{$this->period->id}")->assertStatus(403);
        $this->actingAs($this->penggunaBiasa)->getJson('/api/akademik/upload/' . Str::uuid() . '/errors')->assertStatus(403);
        $this->actingAs($this->penggunaBiasa)->postJson('/api/akademik/upload', [])->assertStatus(403);
    }

    // =========================================================================
    // Endpoint: GET /api/akademik/periods
    // =========================================================================

    public function test_akademik_can_list_periods(): void
    {
        $response = $this->actingAs($this->akademikUser)
            ->getJson('/api/akademik/periods');

        $response->assertStatus(200)
            ->assertJsonFragment(['tahun_ajaran' => '2026/2027']);
    }

    // =========================================================================
    // Endpoint: GET /api/akademik/upload (History)
    // =========================================================================

    public function test_list_uploads_unauthenticated_401(): void
    {
        $this->getJson('/api/akademik/upload')->assertStatus(401);
    }

    public function test_list_uploads_unauthorized_role_403(): void
    {
        $this->actingAs($this->penggunaBiasa)
            ->getJson('/api/akademik/upload')
            ->assertStatus(403);
    }

    public function test_list_uploads_authorized_akademik_returns_descending_history(): void
    {
        $upload1 = AcademicScheduleUpload::create([
            'academic_period_id'  => $this->period->id,
            'uploaded_by_user_id' => $this->akademikUser->id,
            'file_path'           => 'academic_uploads/file1.csv',
            'status'              => 'active',
        ]);
        $upload1->timestamps = false;
        $upload1->created_at = now()->subHours(2);
        $upload1->save();

        $upload2 = AcademicScheduleUpload::create([
            'academic_period_id'  => $this->period->id,
            'uploaded_by_user_id' => $this->akademikUser->id,
            'file_path'           => 'academic_uploads/file2.csv',
            'status'              => 'failed',
        ]);
        $upload2->timestamps = false;
        $upload2->created_at = now()->subHour();
        $upload2->save();

        $response = $this->actingAs($this->akademikUser)
            ->getJson('/api/akademik/upload');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        // Confirm descending order by created_at (upload2 is newer, so it appears before upload1)
        $ids = array_column($data, 'id');
        $pos1 = array_search($upload1->id, $ids);
        $pos2 = array_search($upload2->id, $ids);
        $this->assertNotFalse($pos1);
        $this->assertNotFalse($pos2);
        $this->assertTrue($pos2 < $pos1, 'Newer upload2 should appear before older upload1 in descending list');
    }

    // =========================================================================
    // Endpoint: GET /api/akademik/schedule/{periodId}
    // =========================================================================

    public function test_active_schedule_unauthenticated_401(): void
    {
        $this->getJson("/api/akademik/schedule/{$this->period->id}")->assertStatus(401);
    }

    public function test_active_schedule_unauthorized_role_403(): void
    {
        $this->actingAs($this->penggunaBiasa)
            ->getJson("/api/akademik/schedule/{$this->period->id}")
            ->assertStatus(403);
    }

    public function test_active_schedule_returns_only_active_schedules_for_requested_period(): void
    {
        // Active schedule in target period
        $activeClass = AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Active Class In Period',
            'hari'               => 'senin',
            'jam_mulai'          => '08:00:00',
            'jam_selesai'        => '10:00:00',
            'aktif'              => true,
        ]);

        // Inactive / historical schedule in target period
        $inactiveClass = AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Superseded Class In Period',
            'hari'               => 'selasa',
            'jam_mulai'          => '10:00:00',
            'jam_selesai'        => '12:00:00',
            'aktif'              => false,
        ]);

        // Active schedule in another period
        $otherPeriod = AcademicPeriod::create([
            'tahun_ajaran'    => '2027/2028',
            'semester'        => 'genap',
            'tanggal_mulai'   => '2027-02-01',
            'tanggal_selesai' => '2027-06-30',
        ]);
        $otherClass = AcademicSchedule::create([
            'academic_period_id' => $otherPeriod->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Active Class In Other Period',
            'hari'               => 'rabu',
            'jam_mulai'          => '13:00:00',
            'jam_selesai'        => '15:00:00',
            'aktif'              => true,
        ]);

        $response = $this->actingAs($this->akademikUser)
            ->getJson("/api/akademik/schedule/{$this->period->id}");

        $response->assertStatus(200);
        $names = array_column($response->json(), 'nama_mata_kuliah');

        $this->assertContains('Active Class In Period', $names);
        $this->assertNotContains('Superseded Class In Period', $names);
        $this->assertNotContains('Active Class In Other Period', $names);
    }

    public function test_active_schedule_with_invalid_uuid_returns_404(): void
    {
        $response = $this->actingAs($this->akademikUser)
            ->getJson('/api/akademik/schedule/not-a-valid-uuid');

        $response->assertStatus(404)
            ->assertJsonPath('galat', 'Format ID periode akademik tidak valid.');
    }

    public function test_active_schedule_with_nonexistent_period_returns_empty_array(): void
    {
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAs($this->akademikUser)
            ->getJson("/api/akademik/schedule/{$randomUuid}");

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    // =========================================================================
    // Endpoint: GET /api/akademik/upload/{uploadId}/errors
    // =========================================================================

    public function test_upload_errors_unauthenticated_401(): void
    {
        $this->getJson('/api/akademik/upload/' . Str::uuid() . '/errors')->assertStatus(401);
    }

    public function test_upload_errors_unauthorized_role_403(): void
    {
        $this->actingAs($this->penggunaBiasa)
            ->getJson('/api/akademik/upload/' . Str::uuid() . '/errors')
            ->assertStatus(403);
    }

    public function test_upload_errors_returns_status_and_errors(): void
    {
        $upload = AcademicScheduleUpload::create([
            'academic_period_id'  => $this->period->id,
            'uploaded_by_user_id' => $this->akademikUser->id,
            'file_path'           => 'academic_uploads/bad.csv',
            'status'              => 'failed',
            'validation_errors'   => [
                '2' => ['Invalid time format', 'Invalid hari value'],
            ],
        ]);

        $response = $this->actingAs($this->akademikUser)
            ->getJson("/api/akademik/upload/{$upload->id}/errors");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'failed',
                'errors' => [
                    '2' => ['Invalid time format', 'Invalid hari value'],
                ],
            ]);
    }

    public function test_upload_errors_nonexistent_upload_returns_404(): void
    {
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAs($this->akademikUser)
            ->getJson("/api/akademik/upload/{$randomUuid}/errors");

        $response->assertStatus(404)
            ->assertJsonPath('galat', 'Data tidak ditemukan.');
    }

    public function test_upload_errors_invalid_uuid_returns_404(): void
    {
        $response = $this->actingAs($this->akademikUser)
            ->getJson('/api/akademik/upload/invalid-uuid-string/errors');

        $response->assertStatus(404)
            ->assertJsonPath('galat', 'Data tidak ditemukan.');
    }

    // =========================================================================
    // Endpoint: POST /api/akademik/upload & Replacement Regression Verification
    // =========================================================================

    public function test_non_csv_upload_is_rejected_422(): void
    {
        $file = UploadedFile::fake()->create('jadwal.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file,
            ]);

        $response->assertStatus(400); // ValidationException rendered as 400
    }

    public function test_valid_csv_upload_activates_schedules_atomically(): void
    {
        $csvContent = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Pemrograman Web,TI101,A,Informatika,Pak Dosen,senin,08:00,10:00,{$this->ruangan->id}",
            "Struktur Data,TI102,B,Informatika,Bu Dosen,selasa,10:00,12:00,{$this->ruangan->id}",
        ]);

        $file = UploadedFile::fake()->createWithContent('jadwal.csv', $csvContent);

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Schedule uploaded and activated']);

        $this->assertDatabaseHas('academic_schedules', [
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Pemrograman Web',
            'hari'               => 'senin',
            'jam_mulai'          => '08:00:00',
            'jam_selesai'        => '10:00:00',
            'aktif'              => true,
        ]);

        $this->assertDatabaseHas('academic_schedule_uploads', [
            'academic_period_id' => $this->period->id,
            'status'             => 'active',
        ]);
    }

    public function test_invalid_csv_does_not_activate_and_records_errors(): void
    {
        $csvContent = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Invalid Row,,A,Informatika,Pak Dosen,hari_palsu,10:00,08:00,{$this->ruangan->id}", // invalid day, invalid time end < start
        ]);

        $file = UploadedFile::fake()->createWithContent('jadwal_rusak.csv', $csvContent);

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['galat' => 'Validation errors']);

        $this->assertDatabaseHas('academic_schedule_uploads', [
            'academic_period_id' => $this->period->id,
            'status'             => 'failed',
        ]);

        $this->assertDatabaseMissing('academic_schedules', [
            'academic_period_id' => $this->period->id,
            'nama_mata_kuliah'   => 'Invalid Row',
        ]);
    }

    public function test_atomic_replacement_deactivates_previous_active_schedules(): void
    {
        // 1. First upload
        $csv1 = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Version 1 Class,TI101,A,Informatika,Pak Dosen,senin,08:00,10:00,{$this->ruangan->id}",
        ]);
        $file1 = UploadedFile::fake()->createWithContent('jadwal_v1.csv', $csv1);

        $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file1,
            ])->assertStatus(201);

        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Version 1 Class',
            'aktif'            => true,
        ]);

        // 2. Second upload for same period replaces and deactivates old schedules
        $csv2 = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Version 2 Class,TI101,A,Informatika,Pak Dosen,senin,13:00,15:00,{$this->ruangan->id}",
        ]);
        $file2 = UploadedFile::fake()->createWithContent('jadwal_v2.csv', $csv2);

        $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file2,
            ])->assertStatus(201);

        // Version 1 is now inactive
        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Version 1 Class',
            'aktif'            => false,
        ]);

        // Version 2 is now active
        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Version 2 Class',
            'aktif'            => true,
        ]);
    }

    public function test_replacement_succeeds_even_when_old_and_new_schedule_times_overlap(): void
    {
        // 1. First upload: Monday 08:00 - 10:00
        $csv1 = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Initial Monday Class,TI101,A,Informatika,Dosen Lama,senin,08:00,10:00,{$this->ruangan->id}",
        ]);
        $file1 = UploadedFile::fake()->createWithContent('upload_initial.csv', $csv1);

        $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file1,
            ])->assertStatus(201);

        // 2. Replacement upload: exact same room, day, and time slot (Monday 08:00 - 10:00) with new instructor/course
        $csv2 = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Replacement Monday Class,TI101,A,Informatika,Dosen Baru,senin,08:00,10:00,{$this->ruangan->id}",
        ]);
        $file2 = UploadedFile::fake()->createWithContent('upload_replacement.csv', $csv2);

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file2,
            ]);

        $response->assertStatus(201);

        // Verify initial class is superseded/inactive
        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Initial Monday Class',
            'aktif'            => false,
        ]);

        // Verify replacement class is active
        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Replacement Monday Class',
            'aktif'            => true,
        ]);
    }

    public function test_inactive_historical_schedules_do_not_block_uploads(): void
    {
        // Pre-create historical inactive record with identical slot
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Historical Inactive Class',
            'hari'               => 'rabu',
            'jam_mulai'          => '09:00:00',
            'jam_selesai'        => '11:00:00',
            'aktif'              => false,
        ]);

        $csv = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "New Active Wednesday Class,TI103,A,Informatika,Dosen Baru,rabu,09:00,11:00,{$this->ruangan->id}",
        ]);
        $file = UploadedFile::fake()->createWithContent('wednesday_new.csv', $csv);

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'New Active Wednesday Class',
            'aktif'            => true,
        ]);
    }

    public function test_conflicts_inside_the_new_csv_are_rejected(): void
    {
        // CSV contains two overlapping classes for the SAME room on the SAME day:
        // Line 2: 08:00 - 10:00
        // Line 3: 09:00 - 11:00 (overlaps by 1 hour)
        $csv = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Class A,TI101,A,Informatika,Dosen 1,kamis,08:00,10:00,{$this->ruangan->id}",
            "Class B,TI102,B,Informatika,Dosen 2,kamis,09:00,11:00,{$this->ruangan->id}",
        ]);
        $file = UploadedFile::fake()->createWithContent('internal_conflict.csv', $csv);

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('galat', 'Conflict with existing schedule');

        $this->assertDatabaseMissing('academic_schedules', [
            'nama_mata_kuliah' => 'Class A',
        ]);
        $this->assertDatabaseMissing('academic_schedules', [
            'nama_mata_kuliah' => 'Class B',
        ]);
    }

    public function test_failed_replacement_does_not_deactivate_previous_active_schedule(): void
    {
        // 1. Initial successful upload
        $csv1 = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Stable Active Class,TI101,A,Informatika,Dosen Tetap,jumat,08:00,10:00,{$this->ruangan->id}",
        ]);
        $file1 = UploadedFile::fake()->createWithContent('stable_v1.csv', $csv1);

        $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file1,
            ])->assertStatus(201);

        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Stable Active Class',
            'aktif'            => true,
        ]);

        // 2. Second upload containing an error (invalid day name)
        $csv2 = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Faulty Class,TI102,B,Informatika,Dosen Gagal,hari_salah,10:00,12:00,{$this->ruangan->id}",
        ]);
        $file2 = UploadedFile::fake()->createWithContent('faulty_v2.csv', $csv2);

        $response = $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file2,
            ]);

        $response->assertStatus(422);

        // Previous active schedule must remain ACTIVE
        $this->assertDatabaseHas('academic_schedules', [
            'nama_mata_kuliah' => 'Stable Active Class',
            'aktif'            => true,
        ]);
    }

    public function test_other_academic_periods_remain_unchanged(): void
    {
        // Create Period 2 with an active schedule
        $period2 = AcademicPeriod::create([
            'tahun_ajaran'    => '2027/2028',
            'semester'        => 'genap',
            'tanggal_mulai'   => '2027-02-01',
            'tanggal_selesai' => '2027-06-30',
        ]);
        $period2Class = AcademicSchedule::create([
            'academic_period_id' => $period2->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Untouched Period 2 Class',
            'hari'               => 'senin',
            'jam_mulai'          => '08:00:00',
            'jam_selesai'        => '10:00:00',
            'aktif'              => true,
        ]);

        // Perform replacement on Period 1
        $csv = implode("\n", [
            'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
            "Period 1 New Class,TI101,A,Informatika,Dosen P1,selasa,08:00,10:00,{$this->ruangan->id}",
        ]);
        $file = UploadedFile::fake()->createWithContent('p1_upload.csv', $csv);

        $this->actingAs($this->akademikUser)
            ->postJson('/api/akademik/upload', [
                'academic_period_id' => $this->period->id,
                'file'               => $file,
            ])->assertStatus(201);

        // Period 2 schedule must remain active and untouched
        $this->assertDatabaseHas('academic_schedules', [
            'id'                 => $period2Class->id,
            'academic_period_id' => $period2->id,
            'nama_mata_kuliah'   => 'Untouched Period 2 Class',
            'aktif'              => true,
        ]);
    }
}
