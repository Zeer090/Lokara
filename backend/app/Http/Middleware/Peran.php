<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pembatas peran.
 *
 * Pemakaian di rute: ->middleware('peran:admin') atau peran:admin,pimpinan
 */
class Peran
{
    public function handle(Request $request, Closure $next, string ...$peranDiizinkan): Response
    {
        $pengguna = $request->user();

        if (! $pengguna) {
            return response()->json(['galat' => 'Anda belum masuk.'], 401);
        }

        if (! $pengguna->aktif) {
            return response()->json(['galat' => 'Akun Anda dinonaktifkan. Silakan hubungi pengelola sistem.'], 403);
        }

        if (in_array($pengguna->peran, ['admin', 'pimpinan'], true)) {
            return response()->json(['galat' => 'Akun ini belum dipetakan ke role baru. Hubungi pemilik proyek.'], 403);
        }

        if (! in_array($pengguna->peran, $peranDiizinkan, true)) {
            return response()->json(['galat' => 'Anda tidak memiliki akses untuk tindakan ini.'], 403);
        }

        return $next($request);
    }
}
