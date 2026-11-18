<?php

namespace App\Http\Controllers;

use App\Models\ChatLog;
use App\Services\GeminiService;
use App\Services\SparqlService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly SparqlService $sparql,
        private readonly GeminiService $gemini,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['nullable', 'string', 'max:80', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'locale' => ['nullable', 'in:id,en'],
        ]);
        $message = trim($validated['message']);
        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Enter a question.']);
        }

        $sessionId = $validated['session_id'] ?? Str::random(24);
        $intent = $this->parseIntent($message);
        $sparql = $this->generateSparql($intent);

        try {
            $results = $this->sparql->query($sparql);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('SumateraMall chatbot query failed.', [
                'exception' => $exception->getMessage(),
                'intent' => $intent['type'],
            ]);

            return response()->json([
                'message' => 'Mall data is temporarily unavailable. Please try again later.',
            ], 503);
        }

        $locale = $validated['locale'] ?? 'id';
        $aiEnabled = $this->gemini->isConfigured();
        $notice = null;
        if ($aiEnabled) {
            try {
                $reply = $this->gemini->reply($message, $results, $locale);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                Log::error('SumateraMall Gemini response failed.', [
                    'exception' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'The AI assistant is temporarily unavailable. Please try again later.',
                ], 503);
            }
        } else {
            $reply = $this->formatReply($intent, $results, $locale);
            $notice = $locale === 'en'
                ? 'AI is not configured yet; this is a rule-based reply.'
                : 'AI belum dikonfigurasi; jawaban ini menggunakan aturan pencarian.';
        }

        ChatLog::create([
            'session_id' => $sessionId,
            'user_message' => $message,
            'bot_reply' => $reply,
            'intent_detected' => $intent['type'],
            'sparql_query_used' => $sparql,
        ]);

        return response()->json([
            'reply' => $reply,
            'ai_enabled' => $aiEnabled,
            'notice' => $notice,
            'intent' => $intent,
            'results_count' => count($results),
            'session_id' => $sessionId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function parseIntent(string $message): array
    {
        $text = mb_strtolower($message);
        $intent = ['type' => 'unknown'];

        if (preg_match('/(buka sekarang|masih buka|jam buka|open now|still open|opening hours)/u', $text)) {
            $intent['type'] = 'open_now';
        } elseif (preg_match('/(budget|biaya|estimasi|cost|estimate)/u', $text)) {
            $intent['type'] = 'budget';
        } elseif (preg_match('/(top|terbaik|rating|populer|best|highest rated|popular)/u', $text)) {
            $intent['type'] = 'top_rated';
        } elseif (preg_match('/(info|tentang|profil|about|profile)/u', $text)) {
            $intent['type'] = 'mall_info';
        } elseif (preg_match('/(kota|daftar mall|provinsi|mall di|city|list malls|province|malls in)/u', $text)) {
            $intent['type'] = 'list_by_region';
        } elseif (preg_match('/(cari|dimana|ada|toko|rekomendasi|find|search|where|store|recommend)/u', $text)) {
            $intent['type'] = 'find_store';
        }

        $provinces = [
            'sumatera utara' => 'Sumatera Utara',
            'sumatera barat' => 'Sumatera Barat',
            'sumatera selatan' => 'Sumatera Selatan',
            'kepulauan riau' => 'Kepulauan Riau',
            'bangka belitung' => 'Bangka Belitung',
            'aceh' => 'Aceh',
            'sumut' => 'Sumatera Utara',
            'sumbar' => 'Sumatera Barat',
            'pekanbaru' => 'Riau',
            'batam' => 'Kepulauan Riau',
            'kepri' => 'Kepulauan Riau',
            'jambi' => 'Jambi',
            'sumsel' => 'Sumatera Selatan',
            'babel' => 'Bangka Belitung',
            'bengkulu' => 'Bengkulu',
            'lampung' => 'Lampung',
            'north sumatra' => 'Sumatera Utara',
            'west sumatra' => 'Sumatera Barat',
            'south sumatra' => 'Sumatera Selatan',
            'riau islands' => 'Kepulauan Riau',
            'riau' => 'Riau',
        ];
        foreach ($provinces as $keyword => $province) {
            if (str_contains($text, $keyword)) {
                $intent['province'] = $province;
                break;
            }
        }

        $cities = [
            'banda aceh' => 'Banda Aceh',
            'medan' => 'Medan',
            'padang' => 'Padang',
            'pekanbaru' => 'Pekanbaru',
            'tanjung pinang' => 'Tanjung Pinang',
            'batam' => 'Batam',
            'jambi' => 'Jambi',
            'palembang' => 'Palembang',
            'pangkal pinang' => 'Pangkalpinang',
            'bengkulu' => 'Bengkulu',
            'bandar lampung' => 'Bandar Lampung',
        ];
        foreach ($cities as $keyword => $city) {
            if (str_contains($text, $keyword)) {
                $intent['city'] = $city;
                break;
            }
        }

        $categories = [
            'makan' => 'Food & Beverage',
            'restoran' => 'Food & Beverage',
            'cafe' => 'Food & Beverage',
            'coffee' => 'Food & Beverage',
            'baju' => 'Fashion',
            'fashion' => 'Fashion',
            'sepatu' => 'Fashion',
            'elektronik' => 'Electronics',
            'hp' => 'Electronics',
            'bioskop' => 'Entertainment',
            'nonton' => 'Entertainment',
            'supermarket' => 'Supermarket',
            'restaurant' => 'Food & Beverage',
            'food' => 'Food & Beverage',
            'dining' => 'Food & Beverage',
            'clothes' => 'Fashion',
            'shoes' => 'Fashion',
            'electronics' => 'Electronics',
            'phone' => 'Electronics',
            'cinema' => 'Entertainment',
            'movie' => 'Entertainment',
        ];
        foreach ($categories as $keyword => $category) {
            if (str_contains($text, $keyword)) {
                $intent['category'] = $category;
                break;
            }
        }

        $malls = [
            'sun plaza' => 'sun_plaza',
            'centre point' => 'centre_point_mall',
            'deli park' => 'deli_park_mall',
            'medan mall' => 'medan_mall',
            'nagoya hill' => 'nagoya_hill_mall',
            'palembang icon' => 'palembang_icon',
            'grand batam' => 'grand_batam_mall',
        ];
        foreach ($malls as $keyword => $mallId) {
            if (str_contains($text, $keyword)) {
                $intent['mall'] = $mallId;
                break;
            }
        }

        return $intent;
    }

    /**
     * @param  array<string, string>  $intent
     */
    protected function generateSparql(array $intent): string
    {
        $prefix = "PREFIX mm: <http://sumateramall.org/ontology#>\n"
            ."PREFIX schema: <http://schema.org/>\n"
            ."PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\n";
        $provinceFilter = isset($intent['province'])
            ? '?provinceResource schema:name '.$this->sparql->literal($intent['province'])." .\n"
            : '';
        $cityFilter = isset($intent['city'])
            ? 'FILTER(LCASE(STR(?city)) = LCASE('.$this->sparql->literal($intent['city']).'))'
            : '';
        $categoryFilter = isset($intent['category'])
            ? 'FILTER(CONTAINS(LCASE(STR(?category)), LCASE('.$this->sparql->literal($intent['category'])."))) \n"
            : '';
        $mallFilter = isset($intent['mall'])
            ? 'FILTER(?mall = <http://sumateramall.org/resource/Mall/'.$intent['mall'].'>)'
            : '';

        if ($intent['type'] === 'list_by_region') {
            return $prefix.<<<SPARQL
SELECT ?mallName ?city ?province WHERE {
    ?mall a mm:Mall ; schema:name ?mallName ; mm:inCity ?cityResource ; mm:inProvince ?provinceResource .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    {$provinceFilter}
    {$cityFilter}
} ORDER BY ?province ?city ?mallName LIMIT 30
SPARQL;
        }

        if ($intent['type'] === 'mall_info' && isset($intent['mall'])) {
            return $prefix.<<<SPARQL
SELECT ?mallName ?city ?province ?address ?description WHERE {
    BIND(<http://sumateramall.org/resource/Mall/{$intent['mall']}> AS ?mall)
    ?mall schema:name ?mallName ; mm:inCity/mm:inProvince ?provinceResource ; mm:inCity ?cityResource .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    OPTIONAL { ?mall schema:address ?address }
    OPTIONAL { ?mall schema:description ?description }
}
SPARQL;
        }

        if ($intent['type'] === 'budget') {
            return $prefix.<<<SPARQL
SELECT ?mallName (AVG(xsd:integer(?priceLevel)) AS ?avgPriceLevel) (COUNT(?priceLevel) AS ?pricedStores) WHERE {
    ?store a mm:Store ; mm:locatedIn ?mall ; mm:priceLevel ?priceLevel .
    ?mall schema:name ?mallName .
    FILTER(xsd:integer(?priceLevel) >= 1 && xsd:integer(?priceLevel) <= 4)
    {$mallFilter}
}
GROUP BY ?mallName
ORDER BY DESC(?avgPriceLevel)
LIMIT 10
SPARQL;
        }

        $timeFilter = '';
        if ($intent['type'] === 'open_now') {
            $now = now('Asia/Jakarta');
            $day = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$now->dayOfWeek];
            $time = $this->sparql->literal($now->format('H:i:s'));
            $timeFilter = <<<SPARQL
    ?store schema:openingHoursSpecification ?spec .
    ?spec schema:dayOfWeek <http://schema.org/{$day}> ;
          schema:opens ?opens ; schema:closes ?closes .
    FILTER({$time}^^xsd:time >= ?opens && {$time}^^xsd:time <= ?closes)
SPARQL;
        }

        $rating = in_array($intent['type'], ['top_rated'], true) ? '?store mm:rating ?rating .' : 'OPTIONAL { ?store mm:rating ?rating }';
        $ordering = $intent['type'] === 'top_rated' ? 'ORDER BY DESC(?rating)' : 'ORDER BY ?storeName';
        if (! in_array($intent['type'], ['find_store', 'open_now', 'top_rated'], true)) {
            return $prefix.'SELECT ?storeName WHERE { FILTER(false) }';
        }

        return $prefix.<<<SPARQL
SELECT DISTINCT ?storeName ?mallName ?city ?category ?rating WHERE {
    ?store a mm:Store ; schema:name ?storeName ; mm:locatedIn ?mall ; mm:hasCategory ?categoryResource .
    ?mall schema:name ?mallName ; mm:inCity ?cityResource ; mm:inProvince ?provinceResource .
    ?cityResource schema:name ?city .
    ?provinceResource schema:name ?province .
    ?categoryResource schema:name ?category .
    {$rating}
    {$provinceFilter}
    {$cityFilter}
    {$categoryFilter}
    {$mallFilter}
    {$timeFilter}
}
{$ordering}
LIMIT 10
SPARQL;
    }

    /**
     * @param  array<string, string>  $intent
     * @param  array<int, array<string, string>>  $results
     */
    protected function formatReply(array $intent, array $results, string $locale = 'id'): string
    {
        $english = $locale === 'en';
        if ($results === []) {
            return $english
                ? 'Sorry, I could not find matching data. Try different keywords.'
                : 'Maaf, saya tidak menemukan data yang sesuai. Coba kata kunci lain.';
        }

        return match ($intent['type']) {
            'find_store' => ($english ? 'I found ' : 'Saya menemukan ').count($results)
                .($english ? (count($results) === 1 ? " store:\n\n" : " stores:\n\n") : " toko:\n\n").implode("\n", array_map(
                    fn (array $row): string => '- '.$row['storeName'].($english ? ' at ' : ' di ').$row['mallName'].' ('.$row['city'].')'
                        .(isset($row['rating']) ? ' ['.($english ? 'Rating: ' : 'Rating: ').$row['rating'].']' : ''),
                    array_slice($results, 0, 5),
                )),
            'open_now' => ($english ? 'There are ' : 'Saat ini ada ').count($results)
                .($english ? ' stores recorded as open. For example: ' : ' toko yang tercatat buka. Contoh: ')
                .implode(', ', array_column(array_slice($results, 0, 3), 'storeName')),
            'top_rated' => ($english ? "Top-rated stores:\n\n" : "Toko dengan rating tertinggi:\n\n").implode("\n", array_map(
                fn (array $row): string => '- '.$row['storeName'].' ('.$row['mallName'].', '.$row['city'].') — '.$row['rating'],
                array_slice($results, 0, 5),
            )),
            'list_by_region' => ($english ? "Mall directory:\n\n" : "Daftar mall:\n\n").implode("\n", array_map(
                fn (array $row): string => '- '.$row['mallName'].' ('.$row['city'].', '.$row['province'].')',
                array_slice($results, 0, 20),
            )),
            'mall_info' => $english
                ? $results[0]['mallName'].' is in '.$results[0]['city'].', '.$results[0]['province'].(isset($results[0]['address']) ? ' — '.$results[0]['address'] : '')
                : $results[0]['mallName'].' berada di '.$results[0]['city'].', '.$results[0]['province'].(isset($results[0]['address']) ? ' — '.$results[0]['address'] : ''),
            'budget' => ($english ? "Estimated price levels from store data:\n\n" : "Perkiraan price level berdasarkan data toko:\n\n").implode("\n", array_map(
                fn (array $row): string => '- '.$row['mallName'].': level '.($row['avgPriceLevel'] ?? ($english ? 'unavailable' : 'belum tersedia'))
                    .' ('.$row['pricedStores'].($english ? ' stores recorded)' : ' toko tercatat)'),
                array_slice($results, 0, 10),
            )),
            default => $english
                ? 'I found '.count($results).' results. Try asking a more specific question.'
                : 'Saya menemukan '.count($results).' hasil. Coba pertanyaannya lebih spesifik.',
        };
    }
}
