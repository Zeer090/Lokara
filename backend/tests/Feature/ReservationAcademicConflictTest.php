<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicSchedule;
use App\Models\Lokasi;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationAcademicConflictTest extends TestCase
{
    use DatabaseTransactions;

    protected Pengguna $user;
    protected Lokasi $ruangan;
    protected AcademicPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = Pengguna::create([
            'nama'          => 'Mahasiswa Tester',
            'email'         => 'tester_' . uniqid() . '@kampus.ac.id',
            'password_hash' => Hash::make('Password123!'),
            'peran'         => 'pengguna',
            'aktif'         => true,
        ]);

        $this->ruangan = Lokasi::create([
            'nama'      => 'Ruang Kuliah ' . uniqid(),
            'jenis'     => 'ruangan',
            'kapasitas' => 50,
            'aktif'     => true,
        ]);

        $this->period = AcademicPeriod::create([
            'tahun_ajaran'    => '2026/2027',
            'semester'        => 'ganjil',
            'tanggal_mulai'   => '2026-09-01',
            'tanggal_selesai' => '2026-12-31',
        ]);
    }

    protected function payload(Carbon $mulai, Carbon $selesai): array
    {
        return [
            'lokasi_id' => $this->ruangan->id,
            'keperluan' => 'Uji Reservasi Akademik',
            'mulai'     => $mulai->toIso8601String(),
            'selesai'   => $selesai->toIso8601String(),
        ];
    }

    /**
     * Test A: Friday conflict
     * Reservation: Friday, November 13, 2026, 16:00 – Saturday, November 14, 2026, 12:00.
     * Schedule on Friday: 17:00–18:00.
     * Expected: HTTP 409.
     */
    public function test_friday_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Kalkulus Lanjut',
            'hari'               => 'jumat',
            'jam_mulai'          => '17:00:00',
            'jam_selesai'        => '18:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-14 12:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }

    /**
     * Test B: Saturday conflict
     * Reservation: Friday, November 13, 2026, 16:00 – Saturday, November 14, 2026, 12:00.
     * Schedule on Saturday: 08:00–10:00.
     * Expected: HTTP 409.
     */
    public function test_saturday_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Algoritma',
            'hari'               => 'sabtu',
            'jam_mulai'          => '08:00:00',
            'jam_selesai'        => '10:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-14 12:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }

    /**
     * Test C: No conflict
     * Reservation: Friday, November 13, 2026, 16:00 – Saturday, November 14, 2026, 12:00.
     * Schedule on Saturday: 13:00–14:00 (after reservation ends at 12:00).
     * Expected: HTTP 201.
     */
    public function test_no_conflict_when_saturday_class_is_after_reservation(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Praktikum Robotika',
            'hari'               => 'sabtu',
            'jam_mulai'          => '13:00:00',
            'jam_selesai'        => '14:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-14 12:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(201);
    }

    /**
     * Midnight Test 1:
     * Reservation Friday 16:00–Saturday 00:00 with Friday class 17:00–18:00.
     * Expected: HTTP 409.
     */
    public function test_midnight_boundary_friday_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Fisika Dasar',
            'hari'               => 'jumat',
            'jam_mulai'          => '17:00:00',
            'jam_selesai'        => '18:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-14 00:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }

    /**
     * Midnight Test 2:
     * Reservation Friday 16:00–Saturday 00:00 with Saturday class 08:00–10:00.
     * Expected: HTTP 201 (Saturday is NOT inspected).
     */
    public function test_midnight_boundary_saturday_no_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Sistem Basis Data',
            'hari'               => 'sabtu',
            'jam_mulai'          => '08:00:00',
            'jam_selesai'        => '10:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-14 00:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(201);
    }

    /**
     * Midnight Test 3:
     * Reservation Friday 00:00–Friday 08:00 with Friday class 07:00–09:00.
     * Expected: HTTP 409.
     */
    public function test_midnight_start_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Kimia Teknik',
            'hari'               => 'jumat',
            'jam_mulai'          => '07:00:00',
            'jam_selesai'        => '09:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 00:00:00');
        $end   = Carbon::parse('2026-11-13 08:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }

    /**
     * Adjacent Test 4:
     * Reservation Friday 08:00–Friday 10:00 with class starting exactly at 10:00 (10:00–12:00).
     * Expected: HTTP 201 (no conflict, half-open [start, end)).
     */
    public function test_adjacent_right_no_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Jaringan Komputer',
            'hari'               => 'jumat',
            'jam_mulai'          => '10:00:00',
            'jam_selesai'        => '12:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 08:00:00');
        $end   = Carbon::parse('2026-11-13 10:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(201);
    }

    /**
     * Adjacent Test 5:
     * Reservation Friday 08:00–Friday 10:00 with class ending exactly at 08:00 (06:00–08:00).
     * Expected: HTTP 201 (no conflict, half-open [start, end)).
     */
    public function test_adjacent_left_no_conflict(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Studi Mandiri Pagi',
            'hari'               => 'jumat',
            'jam_mulai'          => '06:00:00',
            'jam_selesai'        => '08:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 08:00:00');
        $end   = Carbon::parse('2026-11-13 10:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(201);
    }

    /**
     * Multi-day Test 6:
     * Reservation spanning multiple days with conflicts on both the first and last dates.
     * Friday 16:00 to Sunday 12:00.
     * Expected: HTTP 409.
     */
    public function test_multi_day_conflict_first_and_last_dates(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Kuliah Jumat',
            'hari'               => 'jumat',
            'jam_mulai'          => '17:00:00',
            'jam_selesai'        => '18:00:00',
            'aktif'              => true,
        ]);

        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Kuliah Minggu',
            'hari'               => 'minggu',
            'jam_mulai'          => '10:00:00',
            'jam_selesai'        => '11:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-15 12:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }

    /**
     * Targeted Edge-Case Test:
     * Reservation Friday 16:00 through Saturday 00:00.
     * Academic schedule: Friday 23:59:59 – 24:00:00.
     * Expected: HTTP 409.
     */
    public function test_targeted_edge_case_235959_to_240000(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Midnight Workshop',
            'hari'               => 'jumat',
            'jam_mulai'          => '23:59:59',
            'jam_selesai'        => '24:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-14 00:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }

    /**
     * Period Boundaries Test:
     * Period A ends 2026-11-13.
     * Period B starts 2026-11-14.
     * Schedules in Period A do NOT apply in Period B, and vice versa.
     */
    public function test_period_boundaries_isolated(): void
    {
        $this->period->delete();

        $periodA = AcademicPeriod::create([
            'tahun_ajaran'    => '2026/2027-A',
            'semester'        => 'pendek',
            'tanggal_mulai'   => '2026-11-01',
            'tanggal_selesai' => '2026-11-13',
        ]);

        $periodB = AcademicPeriod::create([
            'tahun_ajaran'    => '2026/2027-B',
            'semester'        => 'genap',
            'tanggal_mulai'   => '2026-11-14',
            'tanggal_selesai' => '2026-11-30',
        ]);

        // Schedule only in Period B on Saturday 08:00-10:00
        AcademicSchedule::create([
            'academic_period_id' => $periodB->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Period B Saturday Class',
            'hari'               => 'sabtu',
            'jam_mulai'          => '08:00:00',
            'jam_selesai'        => '10:00:00',
            'aktif'              => true,
        ]);

        // Reservation on Friday Nov 13, 08:00-10:00 (covered by Period A).
        // Since Period A has no schedules, it must succeed even though Period B has a class on the same day-of-week/time.
        $startFriday = Carbon::parse('2026-11-13 08:00:00');
        $endFriday   = Carbon::parse('2026-11-13 10:00:00');

        $response1 = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($startFriday, $endFriday));
        $response1->assertStatus(201);

        // Reservation on Saturday Nov 14, 08:00-10:00 (covered by Period B).
        // It must conflict with Period B's schedule.
        $startSaturday = Carbon::parse('2026-11-14 08:00:00');
        $endSaturday   = Carbon::parse('2026-11-14 10:00:00');

        $response2 = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($startSaturday, $endSaturday));
        $response2->assertStatus(409);
    }

    /**
     * Superseded schedules (aktif = false) must never block reservations.
     */
    public function test_superseded_version_does_not_block(): void
    {
        AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => 'Old Syllabus',
            'hari'               => 'jumat',
            'jam_mulai'          => '16:00:00',
            'jam_selesai'        => '18:00:00',
            'aktif'              => false, // Inactive / superseded
        ]);

        $start = Carbon::parse('2026-11-13 16:00:00');
        $end   = Carbon::parse('2026-11-13 18:00:00');

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(201);
    }

    public static function allWeekdaysProvider(): array
    {
        return [
            ['Monday',    'senin',  '2026-11-16'],
            ['Tuesday',   'selasa', '2026-11-17'],
            ['Wednesday', 'rabu',   '2026-11-18'],
            ['Thursday',  'kamis',  '2026-11-19'],
            ['Friday',    'jumat',  '2026-11-20'],
            ['Saturday',  'sabtu',  '2026-11-21'],
            ['Sunday',    'minggu', '2026-11-22'],
        ];
    }

    #[DataProvider('allWeekdaysProvider')]
    public function test_all_seven_weekdays_matched_correctly(string $englishDay, string $indonesianDay, string $date): void
    {
        $sched = AcademicSchedule::create([
            'academic_period_id' => $this->period->id,
            'lokasi_id'          => $this->ruangan->id,
            'nama_mata_kuliah'   => "Kuliah {$indonesianDay}",
            'hari'               => $indonesianDay,
            'jam_mulai'          => '10:00:00',
            'jam_selesai'        => '12:00:00',
            'aktif'              => true,
        ]);

        $start = Carbon::parse("{$date} 09:00:00");
        $end   = Carbon::parse("{$date} 11:00:00");

        $this->assertEquals($englishDay, $start->englishDayOfWeek);

        $response = $this->actingAs($this->user)->postJson('/api/reservasi', $this->payload($start, $end));

        $response->assertStatus(409)
            ->assertJsonPath('galat', 'Jadwal yang dipilih bentrok dengan jadwal akademik.');
    }
}
