# Langkah agar AI, chat, dan peta SumatraMall berfungsi

Ikuti urutan berikut dari folder proyek `C:\laragon\www\sumateramall-app`.

## 1. Pastikan struktur database sudah diperbarui

Kolom `session_id` pada error chat berasal dari migration database yang belum dijalankan. Migration ini sudah dijalankan pada workspace saat perbaikan. Untuk komputer atau database lain, jalankan:

```powershell
cd C:\laragon\www\sumateramall-app
php artisan migrate --force
```

Pastikan migration `2026_11_14_000000_expand_explorer_tables` berstatus `Ran`:

```powershell
php artisan migrate:status
```

Jangan menghapus database atau menjalankan `migrate:fresh`; perintah tersebut dapat menghapus data.

## 2. Aktifkan AI untuk chatbot

Chatbot sudah disiapkan untuk NVIDIA Build dengan model Mistral Large 4. Pada `.env`, pastikan nama variabel dan endpoint berikut tersedia. Jangan menyalin placeholder key; pertahankan NVIDIA API key milik Anda sendiri:

```dotenv
NVIDIA_API_KEY=isi_dengan_api_key_NVIDIA_milik_anda
NVIDIA_API_MODEL=mistralai/mistral-large-4-1t-a52b
NVIDIA_API_BASE_URL=https://integrate.api.nvidia.com/v1/chat/completions
NVIDIA_API_TIMEOUT=60
```

`NVIDIA_API_KEY` harus merupakan API key dari NVIDIA Build. Endpoint menerima Bearer authentication dan menggunakan format `/v1/chat/completions`. Aplikasi menerima URL dengan atau tanpa akhiran `/chat/completions`. API key hanya disimpan di `.env` lokal; jangan kirim key ke chat, jangan taruh di JavaScript, dan jangan commit file `.env`.

Sesudah mengubah `.env`, jalankan:

```powershell
php artisan optimize:clear
```

Setelah mengubah `.env`, hentikan lalu jalankan ulang `php artisan serve` agar proses lokal membaca konfigurasi terbaru. Muat ulang halaman. Label di header chat akan menunjukkan **AI + data RDF terverifikasi** jika key terbaca. Tanpa key, chat masih bisa memakai jawaban berbasis aturan dan data Fuseki, tetapi belum menggunakan AI generatif.

## 3. Aktifkan pencarian alamat OpenStreetMap

Nominatim menolak request yang tidak mengidentifikasi aplikasi. Pada `.env`, ganti `OSM_USER_AGENT` dengan nama aplikasi dan alamat email kontak yang benar-benar dapat dihubungi:

```dotenv
OSM_USER_AGENT="SumatraMallSemanticExplorer/1.0 (contact: email-anda-yang-aktif@example.com)"
OSM_GEOCODING_URL=https://nominatim.openstreetmap.org/search
```

Ganti contoh email dengan email Anda sendiri, kemudian jalankan:

```powershell
php artisan optimize:clear
```

Jangan mengirim request geocoding berulang dalam jumlah besar. Aplikasi menyimpan hasil geocoding yang berhasil di cache. Layanan publik OpenStreetMap memiliki kebijakan penggunaan dan tidak menyediakan jaminan uptime.

## 4. Pastikan mall memiliki koordinat pada data RDF

Koordinat yang sudah ada di Fuseki akan digunakan langsung tanpa geocoding. Jika sebuah mall belum punya koordinat, tambahkan ke data Turtle yang Anda kelola, lalu impor/perbarui dataset Fuseki. Contoh untuk Sun Plaza:

```turtle
@prefix schema: <http://schema.org/> .
@prefix geo: <http://www.w3.org/2003/01/geo/wgs84_pos#> .

<http://sumateramall.org/resource/Mall/sun_plaza>
    schema:geo [
        schema:latitude "3.5822609" ;
        schema:longitude "98.6719101"
    ] .
```

Koordinat contoh berasal dari hasil pencarian OpenStreetMap untuk Sun Plaza, Medan. Verifikasi setiap lokasi sebelum menggunakannya untuk navigasi. Pertahankan atribusi OpenStreetMap pada peta.

Pastikan dataset Fuseki bernama `sumateramall` aktif dan endpoint query yang digunakan aplikasi adalah:

```text
http://localhost:3030/sumateramall/query
```

Uji endpoint dari browser. Endpoint query seharusnya merespons request SPARQL; membuka endpoint dengan metode `GET` tanpa query dapat menghasilkan `400` atau `405`, dan itu tidak selalu berarti Fuseki mati.

## 5. Jalankan aplikasi

Buka terminal pertama:

```powershell
cd C:\laragon\www\sumateramall-app
php artisan serve --host=127.0.0.1 --port=8000
```

Buka terminal kedua untuk mode pengembangan:

```powershell
cd C:\laragon\www\sumateramall-app
npm run dev
```

Jika menjalankan aset produksi, gunakan `npm run build` setelah perubahan frontend. Buka `http://127.0.0.1:8000`.

## 6. Uji peta dan izin lokasi

1. Buka halaman detail mall yang koordinatnya tersedia, misalnya `http://127.0.0.1:8000/malls/sun_plaza`.
2. Jika koordinat mall belum ada di RDF, tunggu hasil pencarian Nominatim. Bila ditolak, pastikan `OSM_USER_AGENT` sudah memakai email kontak yang valid, lalu jalankan `php artisan optimize:clear`.
3. Izinkan akses lokasi saat browser memintanya. Geolokasi browser hanya tersedia pada `localhost` atau koneksi HTTPS.
4. Buka halaman `/planner`, pilih setidaknya dua mall yang memiliki koordinat RDF. Pada halaman hasil, tekan **Mulai ikuti rute** dan izinkan akses lokasi.
5. Untuk produksi, gunakan HTTPS dan siapkan layanan geocoding/routing yang sesuai kapasitas serta kebijakan penggunaan Anda.

## 7. Uji chatbot

1. Pastikan migration database sudah berstatus `Ran` dan Fuseki bisa diakses.
2. Pastikan `CHAT_API_KEY`, `CHAT_API_MODEL`, dan `CHAT_API_BASE_URL` benar.
3. Jalankan `php artisan optimize:clear`, lalu muat ulang browser.
4. Coba pertanyaan seperti “Cari kafe di Sun Plaza”, “Fitur apa saja yang ada di SumatraMall?”, dan pertanyaan lanjutan pada percakapan yang sama.
5. Jika muncul pesan AI tidak tersedia, periksa `storage/logs/laravel.log`, validitas API key, nama model, URL dasar, koneksi internet, dan saldo/kuota penyedia AI.

Pertanyaan di luar fungsi/data SumatraMall akan ditolak oleh pemeriksaan konteks aplikasi. Jawaban tentang fakta mall/toko tetap dibatasi pada data RDF yang diberikan ke AI; lengkapi data di Fuseki agar jawabannya akurat.

## 8. Fitur semantik yang sudah tersedia

- Pencarian typo-tolerant untuk toko, mall, kategori, kota, serta query SPARQL `REGEX`.
- Query SPARQL `ASK` untuk memeriksa keberadaan nama toko.
- Perbandingan mall, rekomendasi berbasis graph, ringkasan statistik graph, dan hasil toko beserta mall.
- Filter jam buka, rating, kategori, status operasional, dan tingkat harga.
- Rute OSRM, pelacakan lokasi browser, ekspor itinerary, serta pencarian fasilitas sekitar melalui OpenStreetMap.

AI tidak menggantikan Fuseki: Fuseki perlu aktif untuk pertanyaan yang membutuhkan data mall/toko aktual.
