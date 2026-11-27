<?php

namespace App\Http\Controllers;

use App\Http\Requests\DaftarRequest;
use App\Http\Requests\MasukRequest;
use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/daftar — Daftar akun pengguna baru.
     */
    public function daftar(DaftarRequest $request): JsonResponse
    {
        $data = $request->validated();

        $pengguna = Pengguna::create([
            'nama'          => $data['nama'],
            'email'         => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'peran'         => 'pengguna', // pendaftaran mandiri selalu "pengguna"
            'organisasi'    => $data['organisasi'] ?? null,
            'no_hp'         => $data['no_hp'] ?? null,
            'aktif'         => true,
        ]);

        $token = $pengguna->createToken('auth', ['*'], now()->addMinutes(
            (int) config('sanctum.expiration', 480)
        ))->plainTextToken;

        return response()->json([
            'token'    => $token,
            'pengguna' => $this->profilData($pengguna),
        ], 201);
    }

    /**
     * POST /api/auth/masuk — Login, mengembalikan token Bearer.
     */
    public function masuk(MasukRequest $request): JsonResponse
    {
        $data = $request->validated();

        $pengguna = Pengguna::where('email', $data['email'])->first();

        if (! $pengguna || ! Hash::check($data['password'], $pengguna->password_hash)) {
            return response()->json(['galat' => 'Email atau password salah.'], 401);
        }

        if (! $pengguna->aktif) {
            return response()->json(['galat' => 'Akun Anda tidak aktif. Hubungi admin.'], 403);
        }

        $token = $pengguna->createToken('auth', ['*'], now()->addMinutes(
            (int) config('sanctum.expiration', 480)
        ))->plainTextToken;

        return response()->json([
            'token'    => $token,
            'pengguna' => $this->profilData($pengguna),
        ]);
    }

    /**
     * GET /api/auth/saya — Profil pengguna yang sedang login.
     */
    public function saya(Request $request): JsonResponse
    {
        return response()->json([
            'pengguna' => $this->profilData($request->user()),
        ]);
    }

    /**
     * POST /api/auth/keluar — Hapus token saat ini.
     */
    public function keluar(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        auth()->forgetGuards();

        return response()->json(['pesan' => 'Berhasil keluar.']);
    }

    /**
     * Format data profil yang dikembalikan ke klien.
     */
    private function profilData(Pengguna $p): array
    {
        return [
            'id'             => $p->id,
            'nama'           => $p->nama,
            'email'          => $p->email,
            'peran'          => $p->peran,
            'organisasi'     => $p->organisasi,
            'no_hp'          => $p->no_hp,
            'aktif'          => $p->aktif,
            'perlu_pemetaan' => in_array($p->peran, ['admin', 'pimpinan'], true),
        ];
    }
}
