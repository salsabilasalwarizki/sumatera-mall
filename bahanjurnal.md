# Bahan Jurnal — SumatraMall Semantic Explorer

Dokumen ini merangkum teknologi, struktur arsitektur, alur data, fitur, bukti verifikasi, dan batasan sistem berdasarkan kode dan pengujian yang tersedia. Gunakan bagian-bagian ini sebagai bahan penulisan naskah; jangan menyamakan hasil uji otomatis dengan uji penerimaan pengguna atau pengukuran kinerja produksi.

## 1. Identitas dan ruang lingkup sistem

**Nama aplikasi:** SumatraMall Semantic Explorer  
**Jenis sistem:** aplikasi web pencarian dan eksplorasi mall/toko berbasis Web Semantik  
**Sasaran wilayah data:** pusat perbelanjaan di Pulau Sumatera  
**Tujuan:** menyatukan informasi mall, toko, kota, provinsi, kategori, dan atribut pendukung dalam sebuah knowledge graph RDF agar informasi dapat dicari dan ditelusuri melalui hubungan semantik, lalu disajikan melalui antarmuka web.

Sistem bukan sistem transaksi jual-beli, reservasi parkir, atau pembayaran. Fitur estimasi harga juga bukan harga resmi dari tenant. Fitur rekomendasi memakai kriteria graf yang tersedia, seperti kategori dan rating, bukan model machine learning yang telah dilatih.

## 2. Ringkasan teknologi

| Lapisan | Teknologi yang ditemukan | Peran |
|---|---|---|
| Bahasa server | PHP `^8.3` | Menjalankan aplikasi Laravel, validasi, pemrosesan data, dan integrasi layanan. |
| Kerangka backend | Laravel `^13.17` | Routing, controller, view server-side, konfigurasi, validasi request, cache, session, logging, dan akses basis data. |
| Antarmuka | Blade, HTML, CSS, JavaScript | Merender halaman dan interaksi browser; bukan aplikasi SPA terpisah. |
| CSS/build frontend | Tailwind CSS `^3.4.19`, Vite `^8.0.0`, Laravel Vite Plugin `^3.1`, PostCSS `^8.5.32`, Autoprefixer `^10.6.2` | Styling utility-first dan kompilasi aset frontend. |
| Peta browser | Leaflet `^1.9.4`, tile OpenStreetMap | Peta, marker mall, dan visualisasi titik fasilitas/rute yang tersedia. |
| Grafik semantik | RDF/Turtle, namespace Schema.org dan kosakata aplikasi `http://sumateramall.org/ontology#` | Representasi entitas dan relasi mall, toko, kota, provinsi, kategori, geografi, dan atribut lain. |
| Query graf | SPARQL 1.1 | Pencarian, filter, penggabungan relasi, agregasi, serta pemeriksaan keberadaan entitas. |
| Triplestore/query server | Apache Jena Fuseki 5.1.0 (distribusi berada di folder proyek) | Menyimpan/menyajikan RDF melalui endpoint SPARQL Query dan SPARQL Update. |
| Basis data relasional | MySQL menurut `.env.example`; SQLite in-memory dipakai dalam pengujian | Menyimpan data operasional aplikasi seperti log chat dan struktur tabel aplikasi; bukan pengganti knowledge graph untuk data katalog semantik. |
| Akses HTTP PHP | Laravel HTTP Client dan Guzzle `^8.3` | Mengirim request ke Fuseki, NVIDIA, Nominatim, Overpass, dan OSRM. |
| Integrasi AI | NVIDIA API yang kompatibel dengan OpenAI Chat Completions; model terkonfigurasi `mistralai/mistral-large-4-1t-a52b` | Menyusun jawaban bahasa alami berdasarkan konteks RDF terbatas. Fitur ini opsional terhadap konfigurasi API. |
| Peta, geocoding, dan amenitas | OpenStreetMap, Nominatim, Overpass API | Tile peta, geocoding berdasarkan permintaan pengguna, dan pencarian amenitas sekitar. |
| Perutean jalan | OSRM API publik yang memakai jaringan jalan OpenStreetMap | Menyusun matriks durasi, mengurutkan hingga delapan tujuan, lalu meminta rute mengemudi. |
| Pengolahan data | Python 3, RDFLib `7.0.0`, Requests `2.31.0`, Beautiful Soup `4.12.3` | Penggabungan, pembacaan, normalisasi, dan penyiapan sumber data RDF. Versi tersebut berasal dari `scraper/requirements.txt`. |
| Ekspor dokumen | `barryvdh/laravel-dompdf` `^3.1` dan generator iCalendar di aplikasi | Ekspor rencana perjalanan ke PDF dan `.ics`. |
| Pengujian | Pest `^4.7`, Pest Laravel Plugin `^4.1`, PHPUnit melalui Laravel | Pengujian otomatis perilaku backend dan integrasi yang dimock. |
| Server lokal | Laragon/Windows dapat digunakan; `php artisan serve` untuk server Laravel lokal | Lingkungan pengembangan, bukan konfigurasi deployment produksi. |

### Konfigurasi layanan yang penting

Nama variabel di `.env.example` mengindikasikan komponen yang dikonfigurasi:

- `FUSEKI_URL` dan `FUSEKI_UPDATE_URL`: endpoint query dan update Fuseki.
- `NVIDIA_API_KEY`, `NVIDIA_API_MODEL`, `NVIDIA_API_BASE_URL`, `NVIDIA_API_TIMEOUT`: konfigurasi AI; kunci API wajib dijaga di server dan tidak dimasukkan ke browser atau naskah.
- `OSM_GEOCODING_URL`, `OSM_OVERPASS_URL`, `OSRM_URL`, `OSM_USER_AGENT`: endpoint geografis dan identitas aplikasi.
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, dan variabel sejenis: konfigurasi koneksi basis data relasional.
- `BUDGET_PRICE_LEVEL_1` sampai `BUDGET_PRICE_LEVEL_4`: nilai perkiraan yang dapat diatur untuk estimasi budget.

Nama konfigurasi bukan bukti bahwa layanan eksternal selalu tersedia. Layanan publik dapat memiliki pembatasan kuota, jeda respons, atau perubahan kebijakan.

## 3. Arsitektur sistem

### 3.1 Pandangan komponen

```text
┌────────────────────────── Browser / Pengguna ───────────────────────────┐
│ Blade HTML · Tailwind CSS · JavaScript · Leaflet · Web Speech (opsional) │
│ Halaman: beranda, pencarian, mall, peta, rekomendasi, budget, planner, chat│
└───────────────────────────────┬─────────────────────────────────────────┘
                                │ HTTP / form / fetch
┌───────────────────────────────▼─────────────────────────────────────────┐
│ Laravel 13 / PHP 8.3                                                    │
│ routes/web.php → Controller → validasi, aturan fitur, view / JSON       │
│                                                                         │
│ Layanan aplikasi:                                                       │
│ SparqlService · OsmService · OpenAICompatibleChatService                │
│ Cache · Session · Logging · Eloquent · PDF/ICS                          │
└───────────┬──────────────────────────┬─────────────────────┬────────────┘
            │ SPARQL HTTP              │ HTTP                 │ SQL/Eloquent
┌───────────▼────────┐      ┌──────────▼─────────────┐  ┌────▼────────────┐
│ Apache Jena Fuseki │      │ Layanan geografis      │  │ MySQL           │
│ RDF/Turtle graph  │      │ Nominatim / Overpass   │  │ chat_logs dan   │
│ SPARQL query/update│      │ OSRM / tile OSM       │  │ tabel aplikasi  │
└────────────────────┘      └────────────────────────┘  └─────────────────┘
            ▲
            │ RDF/Turtle hasil penggabungan dan normalisasi
┌───────────┴─────────────────────────────────────────────────────────────┐
│ Sumber Turtle → scraper/merge_dataset.py → graph kanonis → impor Fuseki │
└────────────────────────────────────────────────────────────────────────┘

Layanan jawaban AI:
Laravel → ambil hasil SPARQL terpilih → NVIDIA Chat Completions → jawaban teks
```

Komunikasi antara browser dan Laravel menggunakan halaman server-rendered, formulir, serta beberapa request `fetch` untuk operasi seperti chat, geocoding, dan amenitas. Browser tidak mengakses kredensial NVIDIA, database MySQL, ataupun endpoint update Fuseki secara langsung. Integrasi peta berjalan melalui Leaflet dan tile OpenStreetMap; pengambilan koordinat yang belum tersedia dilakukan lewat endpoint Laravel yang menghubungi Nominatim.

### 3.2 Lapisan presentasi

Layout bersama berada di `resources/views/layouts/app.blade.php`. Halaman Blade menyajikan navigasi, pilihan bahasa, tema terang/gelap, konten per fitur, dock filter, dan komponen chat. CSS utama berada di `resources/css/app.css`; JavaScript interaksi dan kamus label berada di `resources/js/app.js`.

Leaflet dipakai untuk membuat peta dan marker di browser. Pencarian suara menggunakan dukungan Web Speech API browser bila tersedia; karena bergantung pada browser, fitur itu tidak dapat dijamin seragam di semua perangkat. Bahasa utama aplikasi adalah Bahasa Indonesia dan Inggris. Pilihan bahasa lain tersedia pada kontrol antarmuka, tetapi cakupan terjemahannya tidak boleh diasumsikan lengkap tanpa pemeriksaan terpisah.

### 3.3 Lapisan aplikasi dan routing

`routes/web.php` menghubungkan URL ke controller. Endpoint utama mencakup beranda, tim, daftar dan detail mall, pencarian dan pengecekan nama toko, peta, geocoding, amenitas sekitar, planner dan ekspor, rekomendasi, budget, serta chatbot. Beberapa endpoint pencarian, geocoding, amenitas, dan chat memakai pembatasan laju (rate limit).

Controller memvalidasi format masukan sebelum membentuk query atau memanggil layanan. `SparqlService` membatasi IRI internal ke awalan resource yang dikenal dan melakukan serialisasi string literal untuk query SPARQL. Hasil Fuseki diperiksa bentuk JSON-nya sebelum digunakan. Cache dipakai untuk hasil query dan sejumlah operasi geografi agar request berulang tidak selalu dikirim ke layanan upstream.

### 3.4 Lapisan data semantik

Informasi domain disimpan sebagai graph RDF dan ditanyakan dengan SPARQL. Resource kanonis aplikasi menggunakan pola URI `http://sumateramall.org/resource/<Jenis>/<slug>` dan kelas/properti khusus memakai namespace `http://sumateramall.org/ontology#`. Properti umum seperti nama, gambar, dan alamat memakai Schema.org jika sesuai.

Model konsep utamanya:

- **Mall**: nama, alamat, kota, provinsi, situs, jam buka, gambar, koordinat, dan atribut aksesibilitas apabila tersedia.
- **Store**: nama toko, mall tempat toko berada, kategori, lantai, gambar/logo, rating, status operasional, dan price level apabila tersedia.
- **City** dan **Province**: mengelompokkan mall secara geografis.
- **Category**: mengklasifikasikan tenant; kategori kanonis pada merger mencakup Food & Beverage, Fashion, Electronics, Entertainment, Supermarket, Beauty & Health, Books, Accessories, dan Other.
- **Geo**: nilai latitude/longitude yang dapat dipakai untuk marker atau perutean.

Relasi seperti `mm:locatedIn`, `mm:inCity`, `mm:inProvince`, dan `mm:hasCategory` membuat query lintas entitas dapat ditulis sebagai graph pattern. Ini memungkinkan filter gabungan berdasarkan lokasi, kategori, rating, harga, status, dan teks tanpa menjadikan RDF sekadar file unduhan.

### 3.5 Lapisan relasional

Migrasi Laravel membuat tabel inti dan tabel fitur aplikasi seperti `chat_logs`, `favorites`, dan `itineraries`; migrasi lanjutan memperluas kolom aplikasi. Log chat menyimpan session ID, pesan pengguna, jawaban bot, intent, query yang dipakai, dan waktu pencatatan. Rencana perjalanan saat ini juga disimpan di session web untuk mempertahankan konteks ekspor PDF/iCalendar.

Berkas `database/sql/sumateramall-academic-components.sql` menyediakan lima contoh komponen DBMS MySQL berbasis `chat_logs`: trigger validasi insert, fungsi hitung pesan per sesi, stored procedure riwayat chat, view aktivitas harian menurut intent, dan indeks sesi/waktu. Skrip ini merupakan langkah SQL tersendiri, bukan bagian yang otomatis dijalankan oleh migrasi Laravel. Status penerapan ke database lokal/produksi dan uji kompatibilitas pada MySQL server harus dikonfirmasi sebelum komponen tersebut ditulis sebagai fitur yang telah aktif.

Pemisahan ini disengaja: graph menyimpan hubungan semantik katalog, sedangkan database relasional menangani state dan transaksi operasional aplikasi.

### 3.6 Lapisan integrasi eksternal

1. **Fuseki** menerima query SPARQL melalui endpoint query yang dikonfigurasi. Update RDF ditujukan ke endpoint update dan tidak boleh dibuka ke browser tanpa pengamanan.
2. **Nominatim** mencari koordinat berdasarkan nama/alamat ketika peta membutuhkan lokasi tetapi data RDF tidak menyediakannya. Endpoint diakses melalui Laravel, memakai `User-Agent` konfigurabel, dibatasi laju, dan hasilnya di-cache.
3. **Overpass** menerima query amenitas sekitar koordinat pengguna/mall untuk masjid, ATM, dan toilet. Radius permintaan dibatasi aplikasi.
4. **OSRM** menghitung tabel waktu perjalanan antartitik dan rute jalan. Untuk jumlah tujuan kecil, aplikasi menyusun urutan berdasarkan matriks durasi dengan pemrograman dinamis; jika sumber rute tidak tersedia atau respons tidak dapat digunakan, layanan memakai perkiraan jarak garis lurus dan memberi label bahwa hasilnya estimasi.
5. **NVIDIA Chat Completions** hanya dipanggil dari server bila kunci tersedia. Konteks graf dipangkas ke maksimal 20 record dan histori percakapan dibatasi hingga enam giliran terakhir. Jawaban tetap dibatasi pada konteks SumatraMall; model bukan sumber data primer.

Peta dan rute memerlukan jaringan serta kebijakan penggunaan layanan publik. Rute yang akurat bergantung pada koordinat mall yang tersedia di graph. Geocoding peta tidak otomatis berarti semua koordinat telah disimpan di RDF.

## 4. Alur pemrosesan data

1. Berkas sumber `.ttl` diletakkan di `data-sumateramall/datamall`.
2. `scraper/merge_dataset.py` membaca berkas sumber sebagai Turtle atau RDF/XML sesuai isi, menggabungkan triple, menormalisasi resource lama `http://www.example.org/mall#` ke URI/ontologi aplikasi, dan menyelaraskan nama kategori.
3. Perbaikan sintaks tertentu dilakukan di memori saat proses, tanpa mengubah salinan sumber. Jika dataset lengkap tersedia, berkas duplikat yang diketahui malformed dilewati dengan catatan agar proses dapat diteruskan.
4. Hasil diserialisasi sebagai Turtle ke `storage/app/sumateramall-merged.ttl`.
5. Dataset diimpor secara eksplisit ke graph Fuseki. Impor `POST` menambahkan data; sebelum mengganti graph yang sudah ada, operator perlu membuat cadangan dan memastikan dataset target benar.
6. Controller membentuk query SPARQL untuk setiap fitur, Fuseki mengembalikan SPARQL Results JSON, lalu hasil diformat dan diteruskan ke Blade/JSON.

**Snapshot verifikasi data yang direproduksi saat penyusunan bahan ini:** 59 berkas berakhiran `.ttl` ditemukan; satu file `alldta-sumateramall.ttl` dilewati karena sintaks malformed dan keberadaan dataset lengkap; satu kutip pada nama toko diperbaiki hanya dalam memori. Graph sementara hasil merge berisi 40.416 triple. Skrip melaporkan 106 mall sumber dinormalisasi dan 2.872 toko. Parser graph melaporkan 107 resource bertipe `mm:Mall`, 10 provinsi, dan 9 kategori. Perbedaan 106 vs. 107 berasal dari satu resource mall legacy yang telah ada di graph sumber, bukan dari tambahan hasil normalisasi; angka itu perlu diperlakukan konsisten dan dijelaskan apabila dilaporkan.

Folder `foto-mall` berisi 99 foto; 99 berkas gambar mall juga tersedia di `public/images/malls`. Sebagian gambar toko berasal dari nilai `schema:image` pada RDF dan dapat mengarah ke host eksternal. Pastikan hak penggunaan, keterjangkauan URL, dan konsistensi domain `APP_URL` sebelum menerbitkan atau menyalin image IRI.

## 5. Penjelasan fungsi sistem untuk naskah

### 5.1 Pencarian semantik

Form pencarian mengirim kata kunci beserta filter ke `SearchController`. Filter dapat memilih kota, mall, kategori, rating minimum, price level maksimum, status operasional, dan toko yang sedang buka. SPARQL menggabungkan toko dengan mall, kota, serta kategori; data opsional seperti gambar, lantai, harga, koordinat, dan status diambil bila tersedia.

Pencarian teks memecah masukan menjadi token, menormalisasi huruf/karakter tertentu, menyusun kandidat melalui `CONTAINS`, `STRSTARTS`, dan `REGEX` tanpa membedakan kapitalisasi, kemudian melakukan pemeringkatan fuzzy di PHP terhadap nama toko, mall, kota, kategori, alamat, dan lantai. Sistem dapat memberi saran istilah ketika kandidat menunjukkan kemungkinan salah ketik. Pengecekan apakah nama toko tepat ada menggunakan query `ASK`, bukan query daftar hasil.

Peringkat fuzzy adalah heuristik aplikasi; jangan menyebutnya model pembelajaran mesin atau mengklaim akurasi pencarian tanpa uji relevansi dengan kumpulan query berlabel.

### 5.2 Penemuan mall, peta, dan lokasi

Beranda dan direktori menampilkan mall dari Fuseki berikut kota, provinsi, informasi jam/gambar yang tersedia, serta tautan ke detail. Peta memakai marker berdasarkan koordinat RDF. Jika titik tidak ada, pengguna dapat meminta geocoding alamat melalui endpoint Laravel. Geolokasi browser bersifat pilihan dan memerlukan izin pengguna.

Detail mall dapat menampilkan toko beserta kategori, lantai, logo, rating, status, level harga, dan informasi aksesibilitas yang benar-benar tercatat. Ketiadaan atribut aksesibilitas tidak ditafsirkan sebagai fasilitas itu tidak tersedia.

### 5.3 Rekomendasi dan estimasi budget

Rekomendasi menggabungkan kategori dengan rating minimal, mengurutkan kandidat menurut rating dan price level, lalu memungkinkan penyaringan hasil di browser menurut kota/harga. Ini merupakan rekomendasi berbasis query graf dan aturan yang eksplisit.

Budget menggunakan rata-rata `mm:priceLevel` toko dalam mall jika data itu ada. Jika data belum cukup, aplikasi memakai level fallback yang dipilih atau konfigurasi nilai level 1–4. Hasil dikalikan jumlah orang dan selalu merupakan estimasi, bukan harga resmi atau penawaran vendor.

### 5.4 Perencanaan perjalanan

Pengguna memilih dua sampai delapan mall, opsional memberi koordinat awal, lalu controller memvalidasi ID dan mengambil ulang nama, lokasi administratif, serta koordinat mall dari graph. Mall tanpa koordinat tidak dapat digunakan untuk perhitungan jalan pada alur ini. `OsmService` meminta matrix waktu OSRM dan mengurutkan tujuan dengan pemrograman dinamis; kemudian meminta geometri rute. Bila request rute gagal, nearest-neighbour dan jarak Haversine menghasilkan perkiraan yang ditandai sebagai estimasi, bukan rute jalan terverifikasi.

Itinerary disimpan di session untuk halaman hasil dan diekspor sebagai PDF atau iCalendar `.ics`. Ekspor kalender menghasilkan slot dua jam untuk setiap kunjungan; itu adalah aturan sederhana, bukan durasi aktual kunjungan.

### 5.5 Amenitas sekitar

Atas permintaan pengguna, endpoint peta mengirim koordinat dan radius tervalidasi ke Overpass. Hasil masjid, ATM, dan toilet dinormalisasi menjadi nama, jenis, dan koordinat untuk ditampilkan sebagai daftar/marker. Ketersediaan dan kelengkapan data bergantung pada kontribusi OpenStreetMap dan respons Overpass.

### 5.6 Chatbot

Controller melakukan klasifikasi intent berbasis aturan dan menolak pertanyaan yang berada di luar lingkup. Untuk intent yang dikenali, aplikasi membentuk query SPARQL yang sudah ditetapkan, mengambil konteks dari Fuseki, lalu jika API telah dikonfigurasi mengirim sebagian kecil konteks dan histori terbatas ke model NVIDIA. Tanpa API, jawaban rule-based tetap tersedia. Respons dan log chat disimpan untuk kesinambungan sesi.

Dalam naskah, jelaskan bahwa model bahasa berfungsi sebagai pembentuk jawaban berbasis konteks, sedangkan data faktual mall berasal dari RDF. Ini mengurangi ruang jawaban yang tidak berdasar tetapi tidak menjamin bebas halusinasi; kualitas jawaban AI membutuhkan evaluasi khusus.

## 6. Arsitektur implementasi dan lokasi kode

| Area | Lokasi kode utama | Tanggung jawab |
|---|---|---|
| URL dan middleware/rate limit | `routes/web.php` | Memetakan request ke controller dan membatasi request sensitif/mahal. |
| Akses graph | `app/Services/SparqlService.php` | Query, ASK, update, query eksternal, serialisasi literal, validasi resource, dan format hasil. |
| Pencarian | `app/Http/Controllers/SearchController.php` | Validasi filter, SPARQL kandidat, pemeringkatan fuzzy, saran istilah, dan ASK nama toko. |
| Beranda dan mall | `app/Http/Controllers/HomeController.php`, `MallController.php` | Data beranda, direktori, detail, relasi tenant, gambar dan metadata yang ada. |
| Peta dan eksternal geografis | `app/Http/Controllers/MapController.php`, `app/Services/OsmService.php` | Geocoding, amenitas, optimasi urutan rute, OSRM, dan fallback estimasi. |
| Rekomendasi dan budget | `RecommendationController.php`, `BudgetController.php` | Query rekomendasi dan perhitungan estimasi berdasarkan level harga. |
| Chat | `ChatbotController.php`, `OpenAICompatibleChatService.php` | Intent, query graf, konteks AI, integrasi NVIDIA, histori, dan respons. |
| Tampilan | `resources/views/` | Halaman Blade untuk fitur, layout, dan komponen bersama. |
| Aset | `resources/css/app.css`, `resources/js/app.js`, `public/images/malls/` | Styling, interaksi, terjemahan, gambar publik. |
| Skema SQL | `database/migrations/`, `database/sql/sumateramall-academic-components.sql` | Tabel aplikasi dan contoh komponen akademik MySQL. |
| Data dan konversi | `scraper/`, `data-sumateramall/datamall/`, `ontology/` | Sumber RDF, utilitas merger/normalisasi, serta berkas ontology. |
| Konfigurasi | `config/services.php`, `.env.example` | Endpoint pihak ketiga dan pengaturan fitur tanpa hard-code kredensial. |
| Pengujian | `tests/Feature/ExplorerFeatureTest.php` dan suite Pest | Pemeriksaan perilaku aplikasi dengan dependency eksternal yang sesuai dapat dimock. |

## 7. Aspek kualitas dan keamanan yang dapat dilaporkan

- Validasi Laravel membatasi tipe, rentang, panjang, dan format parameter pencarian/lokasi.
- Resource IRI internal divalidasi terhadap awalan dan slug yang diperbolehkan.
- String literal untuk SPARQL di-encode sebagai literal JSON agar tanda kutip dan karakter khusus tidak langsung disisipkan sebagai sintaks.
- Request AI dibatasi dari sisi panjang masukan, jumlah record konteks, jumlah histori, timeout, dan frekuensi route.
- Kunci NVIDIA berada pada konfigurasi server `.env`; `.env` tidak boleh dimasukkan dalam lampiran jurnal, log, tangkapan layar, atau repository publik.
- Respons eksternal diperiksa sebelum dipakai; kegagalan geocoding/amenitas/rute dicatat atau ditampilkan sebagai kegagalan/estimasi, bukan sebagai klaim sukses yang pasti.
- Data gambar eksternal perlu disanitasi/ditinjau URL-nya; gunakan HTTPS bila memungkinkan dan pertimbangkan hak cipta gambar sebelum publikasi.
- Fuseki endpoint Update, database, serta file `.env` harus dilindungi di deployment; jangan mempublikasikan layanan admin ke internet terbuka.

## 8. Bukti pengujian dan batas interpretasi

Verifikasi yang dapat dicantumkan:

1. **Penggabungan data:** proses merger selesai pada output sementara; 40.416 triple; 106 record mall sumber dinormalisasi; 2.872 record toko; graph memuat 10 provinsi, 9 kategori, dan 107 resource bertipe mall.
2. **Pengujian aplikasi:** `php artisan test` lulus dengan **46 tes dan 187 assertion** pada eksekusi terakhir yang tercatat untuk snapshot ini.
3. **Konsistensi file:** `git diff --check` selesai tanpa kesalahan whitespace; ada peringatan normalisasi CRLF/LF pada file Windows.
4. **Aset foto:** ada 99 berkas lokal di folder foto dan 99 berkas terbit di `public/images/malls`.

**Batas klaim:** angka lulus tes menunjukkan keberhasilan pemeriksaan otomatis yang dijalankan, bukan bukti performa, usability, aksesibilitas menyeluruh, kepuasan pengunjung, atau validasi lapangan. Pengujian berbasis fake tidak membuktikan setiap endpoint upstream selalu tersedia. Tidak ada survei responden, eksperimen A/B, uji beban, pengukuran latency produksi, atau perbandingan precision/recall pencarian yang menjadi dasar dokumen ini.

## 9. Keterbatasan dan pekerjaan lanjutan

1. Verifikasi setiap nama, alamat, koordinat, jam, kategori, status, harga, rating, dan image IRI terhadap sumber primer; RDF agregat dapat mengandung data tidak lengkap atau usang.
2. Selesaikan rekonsiliasi 106 sumber mall yang dinormalisasi versus 107 resource mall bertipe pada graph dan tetapkan aturan hitung konsisten.
3. Ukur precision/recall atau nDCG pencarian dengan query dan penilaian relevansi oleh pengguna/pakar.
4. Lakukan uji usability dan aksesibilitas dengan peserta dan protokol yang disetujui; laporkan populasi, sampel, instrumen, serta hasil aktual.
5. Uji beban dan timeout Fuseki, MySQL, NVIDIA, Nominatim, Overpass, serta OSRM; sediakan layanan produksi berkuota/SLA bila diperlukan.
6. Audit koordinat mall yang hilang/salah sebelum menyebut rute optimal. Estimasi fallback tidak menggantikan road routing.
7. Evaluasi factuality chatbot terhadap pertanyaan uji, termasuk jawaban tanpa data, salah konteks, dan prompt injection; ukuran maksimal konteks tidak sama dengan verifikasi kebenaran AI.
8. Jalankan SQL akademik pada MySQL target yang sesuai, verifikasi privilege serta efek indeks, dan laporkan komponen mana yang benar-benar diaktifkan.
9. Tinjau lisensi, atribusi, masa berlaku, domain, dan kebijakan penggunaan seluruh foto/logo sebelum publikasi dan deployment.

## 10. Berkas pendukung yang perlu dikutip/dilampirkan dalam naskah

- Kode utama: `app/`, `routes/web.php`, `resources/views/`.
- Skema: `database/migrations/` dan `database/sql/sumateramall-academic-components.sql`.
- Transformasi dataset: `scraper/merge_dataset.py`.
- Data masukan: `data-sumateramall/datamall/` (catat asal dan lisensi dataset dalam naskah).
- Konfigurasi aman: `.env.example` (bukan `.env`).
- Uji: `tests/Feature/ExplorerFeatureTest.php` dan hasil `php artisan test`.
- Versi komponen: `composer.json`, `package.json`, dan `scraper/requirements.txt`.

Sebelum submission, ganti semua placeholder identitas/afiliasi, pastikan lisensi data dan foto, masukkan tanggal pengujian yang sebenarnya, periksa kembali hasil aktual di lingkungan target, dan selesaikan checklist penulis serta reviewer pada `isian jurnal.md`.
