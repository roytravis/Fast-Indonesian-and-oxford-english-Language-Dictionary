<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Kamus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; // <-- Tambahkan ini untuk memanggil API
use Illuminate\Support\Facades\Log;

class KamusController extends Controller
{
    /**
     * Fungsi untuk mencari kata di database (Bahasa Indonesia).
     */
    public function search(Request $request)
    {
        // Membersihkan input dari spasi yang tidak disengaja
        $query = trim($request->input('q'));

        if (!$query) {
            return response()->json([]);
        }

        // Query pencarian yang andal dan tidak sensitif huruf
        $searchTerm = strtolower($query) . '%';
        
        $results = Kamus::whereRaw('LOWER(kata) LIKE ?', [$searchTerm])
                        ->limit(10) // Batasi hasil untuk performa
                        ->get();

        return response()->json($results);
    }

    /**
     * Fungsi baru untuk mencari kata menggunakan API eksternal (Bahasa Inggris).
     */
    public function searchEnglish(Request $request)
    {
        $query = trim($request->input('q'));

        if (!$query) {
            return response()->json([]);
        }

        // Panggil API kamus gratis
        $response = Http::get("https://api.dictionaryapi.dev/api/v2/entries/en/{$query}");

        // Jika kata tidak ditemukan atau terjadi error, kembalikan array kosong
        if ($response->failed()) {
            return response()->json([]);
        }

        $data = $response->json();
        
        // Format ulang data dari API agar sesuai dengan format frontend kita
        $formattedResults = [];
        if (is_array($data) && !empty($data)) {
            $entry = $data[0];
            $kata = $entry['word'] ?? 'N/A';
            $phonetic = $entry['phonetic'] ?? '';
            
            $definisi = 'Definisi tidak ditemukan.';
            if (isset($entry['meanings'][0]['definitions'][0]['definition'])) {
                $definisi = $entry['meanings'][0]['definitions'][0]['definition'];
            }

            $formattedResults[] = [
                'id' => 1,
                'kata' => $kata,
                'arti' => "{$phonetic}<br><strong>Definisi:</strong> {$definisi}"
            ];
        }

        return response()->json($formattedResults);
    }
}
