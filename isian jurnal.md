# Draf Isian Jurnal — SumatraMall Semantic Explorer

> **Petunjuk sebelum pengajuan:** ganti seluruh teks dalam `[kurung siku]` dengan data penulis yang benar. Draf ini mengikuti urutan dan unsur pada `Template Jurnal.docx`, tetapi bagian statistik yang tidak sesuai dengan penelitian rekayasa sistem dijelaskan secara terbuka. Jangan mengisi hasil survei, nama reviewer, keputusan reviewer, atau afiliasi dengan informasi rekaan. Abstrak perlu dipastikan tetap 200–250 kata setelah penyuntingan.

## Judul

# Perancangan Sistem Eksplorasi Pusat Perbelanjaan Sumatera Berbasis Web Semantik

*(Judul terdiri atas 9 kata; template meminta maksimal 14 kata. Terapkan Times New Roman Regular 20 pt saat memindahkan naskah ke DOCX.)*

**[Nama Penulis 1]¹, [Nama Penulis 2]², [Nama Penulis 3]³**  
¹,²,³ [Program Studi/Institusi yang benar], [Kota], Indonesia  
¹ [email penulis 1], ² [email penulis 2], ³ [email penulis 3]

## ABSTRAK

Informasi pusat perbelanjaan di Sumatera tersebar dalam beragam berkas dan belum selalu menyediakan hubungan yang mudah ditelusuri antara mall, toko, kategori, kota, dan provinsi. Penelitian ini bertujuan merancang dan mengimplementasikan SumatraMall Semantic Explorer, aplikasi web yang mengintegrasikan data RDF dengan pencarian dan eksplorasi mall. Metode penelitian berupa rekayasa sistem yang meliputi pemeriksaan kebutuhan, penggabungan dan normalisasi dataset, perancangan arsitektur, implementasi fitur, serta verifikasi otomatis. Data dimodelkan sebagai graph RDF dan disimpan pada Apache Jena Fuseki; aplikasi dibangun menggunakan Laravel dan PHP, sedangkan SPARQL digunakan untuk mengambil relasi dan menerapkan filter. Sistem menyediakan pencarian toko dan mall, filter lokasi dan atribut, detail tenant, peta OpenStreetMap, pencarian amenitas, perencanaan rute, rekomendasi berbasis kategori dan rating, estimasi budget, ekspor itinerary, serta chatbot yang dapat memanfaatkan konteks RDF. Pada pengujian dataset, proses merger menghasilkan 40.416 triple, menormalisasi 2.872 data toko dan 106 data mall sumber, mencakup 10 provinsi serta 9 kategori; graph juga memuat 107 resource mall bertipe karena satu resource legacy. Pengujian otomatis Laravel terakhir lulus pada 46 tes dengan 187 assertion. Hasil ini menunjukkan bahwa relasi data semantik dapat diintegrasikan ke alur eksplorasi web yang dapat diuji. Namun, pengujian belum mengukur kepuasan pengguna, performa produksi, atau akurasi relevansi pencarian. Penelitian selanjutnya perlu melakukan evaluasi usability, memvalidasi koordinat dan metadata, mengukur kualitas pencarian, serta menguji kestabilan layanan eksternal.

**Kata Kunci:** Web Semantik; RDF; SPARQL; Apache Jena Fuseki; pencarian mall; Sumatera

## PENDAHULUAN

Informasi mall dan tenant umumnya disajikan melalui halaman web atau katalog yang berdiri sendiri. Pengunjung yang ingin mengetahui toko pada mall tertentu, membandingkan pilihan berdasarkan kategori, atau mencari lokasi mall di kota berbeda harus menggabungkan informasi dari beberapa halaman. Tantangan tidak hanya terletak pada banyaknya data, tetapi juga pada perbedaan format dan hubungan antardata: toko merupakan bagian dari mall, mall berada di kota dan provinsi tertentu, dan toko mempunyai kategori serta atribut lain. Jika hubungan tersebut hanya disajikan sebagai teks bebas atau daftar terpisah, pencarian lintas entitas menjadi sulit dipelihara dan dikembangkan.

Web Semantik menawarkan cara merepresentasikan informasi melalui identitas resource, kosakata yang dapat dipahami mesin, dan relasi eksplisit. Berners-Lee, Hendler, dan Lassila (2001) menguraikan visi Web Semantik sebagai perluasan web dengan data terstruktur dan bermakna bagi pemrosesan. Bizer, Heath, dan Berners-Lee (2009) membahas prinsip Linked Data untuk menerbitkan serta menghubungkan data terstruktur di web. Hogan et al. (2021) meninjau knowledge graph, model representasi, bahasa query, serta pemanfaatannya pada kumpulan data yang beragam. Ketiga karya tersebut menjadi dasar konseptual pengorganisasian data, bukan evaluasi langsung atas aplikasi mall Sumatera.

Pada konteks eksplorasi pusat perbelanjaan, kebutuhan sistem meliputi penyatuan katalog mall dan toko, pencarian yang dapat memanfaatkan relasi lokasi/kategori, dan penyajian hasil dalam antarmuka yang mudah digunakan. Sistem yang dirancang dalam penelitian ini menggabungkan data RDF dengan aplikasi web Laravel. Graph menyimpan entitas mall, toko, kota, provinsi, kategori, dan atribut yang tersedia; Fuseki menyediakan endpoint SPARQL; aplikasi menyajikan hasil pencarian, detail, peta, rekomendasi, estimasi, rute, dan percakapan berbasis konteks.

Tujuan penelitian adalah: (1) merancang proses normalisasi sumber RDF ke dalam kosakata yang dapat dipakai aplikasi; (2) membangun integrasi query SPARQL pada fitur penemuan mall dan toko; (3) menyediakan fitur eksplorasi yang memanfaatkan data lokasi dan atribut; serta (4) memverifikasi fungsi utama melalui pengujian otomatis. Kontribusi penelitian berada pada implementasi integrasi data dan aplikasi untuk konteks mall Sumatera. Penelitian ini tidak mengklaim model kecerdasan buatan baru, bukti peningkatan kepuasan, ataupun performa yang telah diuji pada skala produksi.

## TINJAUAN PUSTAKA

### Web Semantik dan RDF

Resource Description Framework (RDF) merepresentasikan pernyataan sebagai triple subjek–predikat–objek. Bentuk ini dapat menyatakan, misalnya, sebuah toko memiliki nama tertentu, berada di dalam mall tertentu, serta mempunyai kategori tertentu. URI memberikan identitas bagi entitas dan properti sehingga data dari beberapa sumber dapat dihubungkan. Dalam penelitian ini, model graf membedakan resource mall, toko, kota, provinsi, dan kategori, kemudian memakai relasi eksplisit untuk menyatakan lokasi dan pengelompokan.

### Linked Data dan kosakata

Prinsip Linked Data mendorong penggunaan identifier yang dapat dirujuk serta relasi yang eksplisit untuk menghubungkan data. Kosakata Schema.org dipakai untuk properti umum yang sesuai, seperti nama, alamat, gambar, dan geografi; kosakata `http://sumateramall.org/ontology#` menyatakan kelas dan hubungan domain aplikasi. Proses merger memetakan data lama yang memakai kosakata sumber ke resource kanonis aplikasi, tanpa menghapus triple sumber yang digabungkan.

### SPARQL dan knowledge graph

SPARQL merupakan bahasa untuk mencocokkan pola graf RDF dan meminta hasil berupa solusi query. Pola graf dapat menggabungkan toko, mall, kota, serta kategori, sementara klausa filter mempersempit hasil berdasarkan nilai atribut. Tipe query `SELECT` mengembalikan binding, sedangkan `ASK` menjawab pertanyaan boolean mengenai keberadaan pola. Pembedaan ini digunakan pada pencarian daftar hasil dan pemeriksaan nama toko. Query dibangun server-side, masukan literal di-encode, dan IRI internal divalidasi untuk membatasi pembentukan pola query.

### Rekayasa aplikasi web

Laravel menyediakan routing, validasi, controller, view server-side, cache, session, logging, dan akses database. Pada sistem ini, data katalog utama disimpan sebagai graph semantik, sementara database relasional menangani data operasional seperti log percakapan. Pemisahan tersebut memungkinkan query hubungan katalog menggunakan SPARQL dan state aplikasi dikelola melalui mekanisme relational/session yang lazim.

## STUDI LITERATUR

Bagian ini merangkum karya terdahulu yang mendukung landasan teknologi. Karya-karya berikut bersifat konseptual dan tinjauan bidang; karya tersebut tidak diperlakukan sebagai eksperimen pembanding yang mengukur aplikasi ini.

1. **Berners-Lee, Hendler, dan Lassila (2001)** memaparkan gagasan Web Semantik dengan data yang memiliki struktur dan makna agar dapat diproses lebih baik oleh perangkat lunak. Gagasan ini relevan terhadap pemodelan relasi mall–toko–lokasi, tetapi artikel tersebut bukan evaluasi pencarian mall.
2. **Bizer, Heath, dan Berners-Lee (2009)** menjelaskan Linked Data sebagai praktik publikasi dan penghubungan data terstruktur pada web, termasuk perkembangan aplikasi yang mengeksploitasi Web of Data. Prinsip keterhubungan data menjadi rujukan untuk membuat URI dan relasi yang konsisten pada graph SumatraMall.
3. **Hogan et al. (2021)** memberikan tinjauan komprehensif mengenai knowledge graph, model graf, bahasa query dan validasi, serta teknik yang terkait dengan pemanfaatan koleksi data. Tinjauan tersebut mendukung penggunaan graph dan query semantik sebagai dasar sistem, sekaligus menegaskan bahwa kualitas representasi dan query merupakan bagian penting dari rekayasa knowledge graph.

Kesenjangan yang ditangani penelitian ini bukan ketiadaan konsep RDF/SPARQL, melainkan kebutuhan implementasi terintegrasi yang mengubah berbagai berkas katalog mall/toko menjadi graph yang dapat digunakan melalui fitur web. Evaluasi komparatif dengan sistem lain masih diperlukan pada penelitian lanjutan.

## METODE PENELITIAN

### Rancangan penelitian

Penelitian menggunakan metode rekayasa sistem berbasis studi kasus data mall dan toko di Sumatera. Tahapan pekerjaan meliputi:

1. **Pemeriksaan kebutuhan dan sumber data:** meninjau struktur data RDF/Turtle, atribut dan relasi yang tersedia, serta fitur aplikasi yang dibutuhkan.
2. **Penggabungan dan normalisasi:** membaca sumber RDF, menggabungkan triple, memetakan resource legacy ke kosakata kanonis, dan menormalkan kategori.
3. **Perancangan arsitektur:** memisahkan lapisan presentasi, controller/aplikasi, layanan query, triplestore, database relasional, dan integrasi geografis/AI.
4. **Implementasi:** membangun halaman dan endpoint Laravel yang memakai SPARQL untuk fitur katalog, serta layanan peta/rute, estimasi, ekspor, dan chatbot.
5. **Verifikasi:** menjalankan parser pada output data dan suite pengujian Laravel untuk memeriksa perilaku fungsional yang tercakup.

Tahapan tersebut menggambarkan proses pengembangan yang dikerjakan; penelitian ini tidak menyatakan telah menjalankan eksperimen formal terhadap pengguna.

### Populasi dan sampel

Populasi penelitian berupa seluruh resource mall dan toko yang memenuhi struktur dan syarat normalisasi di dataset RDF yang disediakan. Pengambilan data dilakukan dengan pendekatan total sampling terhadap record yang dapat dinormalisasi, bukan pengambilan sampel responden. Dalam eksekusi merger yang diverifikasi, skrip melaporkan 106 data mall sumber dinormalisasi dan 2.872 toko. Setelah output dibaca sebagai graph, terdapat 107 resource bertipe mall, 10 provinsi, dan 9 kategori; satu resource mall legacy yang sudah berada di sumber menjelaskan perbedaan jumlah tersebut. Dataset yang dihasilkan memuat 40.416 triple.

Jumlah tersebut adalah hasil penghitungan data pada snapshot pengujian, bukan jaminan kelengkapan seluruh mall atau tenant di Sumatera. Asal dataset, tanggal pengumpulan, perizinan, dan kriteria inklusi setiap sumber perlu dicantumkan penulis sebelum submission.

### Instrumen dan lingkungan

Instrumen verifikasi terdiri atas skrip Python `scraper/merge_dataset.py` dan pengujian otomatis aplikasi Laravel/Pest. Sistem dibangun dengan PHP 8.3 dan Laravel 13.17; frontend menggunakan Blade, JavaScript, Tailwind CSS, Vite, dan Leaflet. Data RDF di-query melalui Apache Jena Fuseki menggunakan SPARQL. Basis data relasional dikonfigurasi melalui `.env.example` untuk MySQL; pengujian aplikasi menggunakan konfigurasi pengujian terisolasi. Layanan OpenStreetMap/Nominatim, Overpass, OSRM, dan NVIDIA merupakan integrasi eksternal yang ketersediaan aktualnya bergantung pada konfigurasi dan jaringan.

### Variabel, hipotesis, dan kriteria pengujian

Penelitian ini berfokus pada artefak perangkat lunak, bukan hubungan kausal antarvariabel responden. Oleh karena itu, hipotesis statistik dan uji-t seperti contoh template tidak digunakan. Hipotesis kerja fungsional dirumuskan sebagai berikut:

- **H0:** pada skenario uji yang tercakup, aplikasi tidak dapat memproses data katalog semantik melalui fungsi-fungsi yang disyaratkan.
- **H1:** pada skenario uji yang tercakup, aplikasi dapat memproses data katalog semantik dan memberikan respons fitur sesuai kriteria yang diuji.

Hasil pengujian otomatis digunakan untuk menilai kriteria fungsional pada cakupan tes, bukan untuk mengestimasi parameter populasi atau membuktikan kepuasan pengguna. Data yang dilaporkan berupa jumlah triple/resource hasil parser dan jumlah tes/assertion yang lulus.

### Teknik analisis

Analisis dilakukan secara deskriptif dan fungsional:

1. jumlah triple dan jumlah resource dihitung dari graph hasil merge;
2. suite otomatis dijalankan dan jumlah tes/assertion lulus dicatat;
3. fitur yang diuji dipetakan ke perilaku sistem; dan
4. keterbatasan cakupan uji dilaporkan terpisah dari kesimpulan implementasi.

Tidak dilakukan regresi linear, uji validitas kuesioner, uji reliabilitas instrumen, atau pengukuran statistik kepuasan karena penelitian ini tidak mengumpulkan data survei.

## HASIL DAN PEMBAHASAN

### Hasil penggabungan data

Proses merger membaca 59 berkas dengan ekstensi `.ttl` pada direktori sumber. Berkas `alldta-sumateramall.ttl` tidak diproses karena malformed dan dataset lengkap tersedia; satu tanda kutip di data nama toko diperbaiki di memori. Proses selesai menghasilkan graph 40.416 triple, 2.872 resource toko yang dinormalisasi, 10 provinsi, 9 kategori, serta 106 record mall sumber yang dinormalisasi. Parser graph menemukan 107 resource mall bertipe karena graph sumber juga memuat satu resource mall legacy di luar hitungan normalisasi tersebut.

Normalisasi memungkinkan aplikasi menggunakan pola URI dan relasi yang seragam untuk query katalog, meskipun data awal disusun dengan kosakata serta struktur berbeda. Namun, hasil gabungan tidak otomatis menjamin atribut sumber benar atau terkini. Khususnya, koordinat, rating, harga, jam buka, gambar, dan status operasional perlu diperiksa terhadap sumber asal.

### Hasil implementasi dan verifikasi fitur

| Area | Implementasi yang diverifikasi pada kode/uji | Catatan interpretasi |
|---|---|---|
| Query dan pencarian | SPARQL untuk mall/toko, filter kategori/lokasi/status/rating/harga/jam, pemeringkatan fuzzy, dan pemeriksaan nama dengan `ASK`. | Belum ada hasil precision/recall dari query pengguna berlabel. |
| Halaman mall | Beranda, direktori, detail dan atribut toko memakai hasil graph; nilai opsional hanya ditampilkan jika tersedia. | Kelengkapan bergantung pada data sumber. |
| Peta dan lokasi | Leaflet dengan tile OpenStreetMap, marker berdasarkan koordinat RDF, geocoding Nominatim saat diminta, dan amenitas Overpass. | Layanan publik dapat membatasi request; mall tanpa koordinat memerlukan proses geocoding. |
| Rekomendasi dan budget | Pemeringkatan query kategori/rating serta perhitungan budget berdasar `priceLevel` atau nilai fallback yang dapat dikonfigurasi. | Nilai budget adalah perkiraan, bukan harga resmi; rekomendasi bukan model terlatih. |
| Rute dan ekspor | OSRM untuk matriks/rute jalan; fallback estimasi diberi label; itinerary dapat diekspor PDF dan ICS. | Planner mensyaratkan koordinat mall; slot kalender dua jam adalah konvensi sistem. |
| Chatbot | Intent rule-based, konteks dari hasil Fuseki, opsi jawaban NVIDIA, histori sesi, dan pembatasan request. | Belum dievaluasi dengan benchmark faktualitas atau pengujian keamanan prompt yang menyeluruh. |
| Otomatisasi pengujian | `php artisan test` lulus pada 46 tes dan 187 assertion dalam eksekusi terakhir yang tercatat. | Tidak menggantikan pengujian lapangan, user acceptance test, uji beban, maupun uji layanan eksternal produksi. |

### Pembahasan arsitektur

Pemisahan graph RDF dan database relasional membuat penyimpanan mengikuti kebutuhan data. Hubungan lintas entitas mall, toko, kota, provinsi, dan kategori disimpan dalam graf, sementara pencatatan percakapan ditangani oleh tabel aplikasi. Laravel menjadi lapisan orkestrasi agar browser tidak perlu mengetahui detail query Fuseki atau memegang kunci layanan pihak ketiga.

SPARQL mendukung query relasional atas graph, sehingga sebuah halaman pencarian dapat mengambil nama toko sekaligus nama mall, kota, dan kategori. Validasi nilai serta konstruksi literal query server-side penting karena teks pencarian berasal dari pengguna. Pemeringkatan fuzzy di PHP menambah toleransi terhadap variasi masukan, tetapi kualitasnya perlu diuji dengan dataset relevansi yang disusun secara terpisah.

Integrasi geografi memperluas hasil katalog menjadi konteks lokasi. Leaflet menyajikan peta, Nominatim membantu menemukan koordinat saat dibutuhkan, Overpass menyediakan objek amenitas, dan OSRM memberikan rute jalan. Ketergantungan pada koordinat RDF dan layanan eksternal membatasi ketersediaan fitur ketika data atau jaringan tidak lengkap. Label estimasi ketika fallback digunakan diperlukan agar perkiraan jarak tidak disalahartikan sebagai navigasi terverifikasi.

Chatbot menggabungkan pencocokan intent dengan pengambilan data graf sebelum meminta model bahasa menyusun jawaban. Pola ini menyediakan grounding terbatas pada record hasil query dan mendukung jawaban sederhana tanpa layanan AI. Meskipun demikian, penggunaan konteks terpilih tidak menjamin semua pernyataan generatif benar; evaluasi faktualitas, respons ketika data kosong, dan keamanan prompt masih diperlukan.

### Keterbatasan hasil

Pengujian yang dilaporkan berfokus pada keberhasilan parser dan tes otomatis. Penelitian belum melakukan survei atau observasi pengunjung, belum menilai kemudahan penggunaan dengan instrumen tervalidasi, belum mengukur waktu respons pada produksi, dan belum melakukan perbandingan relevansi dengan baseline. Ketergantungan pada endpoint publik juga berarti keberhasilan tes perangkat lunak tidak menjamin geocoding, amenitas, rute, atau AI selalu tersedia.

## KESIMPULAN

Penelitian ini menghasilkan rancangan dan implementasi SumatraMall Semantic Explorer yang menghubungkan data katalog mall/toko dengan Web Semantik. Proses Python menggabungkan dan menormalisasi sumber RDF menjadi graph 40.416 triple yang mencakup 2.872 toko, 10 provinsi, 9 kategori, 106 data mall sumber yang dinormalisasi, serta satu resource mall legacy tambahan pada hasil parsing. Aplikasi Laravel menyediakan pencarian SPARQL, detail mall dan tenant, peta/amenitas, rekomendasi, estimasi budget, perencanaan rute, ekspor itinerary, dan chatbot dengan konteks RDF. Pengujian otomatis terakhir menunjukkan 46 tes dan 187 assertion lulus.

Hasil tersebut mendukung kesimpulan bahwa integrasi graph RDF dengan aplikasi web dapat menyediakan alur eksplorasi yang beragam pada data yang tersedia. Kesimpulan ini terbatas pada fungsi yang diuji dan tidak membuktikan akurasi data menyeluruh, kinerja produksi, peningkatan kepuasan, ataupun kualitas navigasi untuk seluruh kondisi. Penelitian berikutnya perlu melakukan validasi data lapangan, evaluasi relevansi pencarian dan faktualitas chatbot, uji usability dengan responden, pengukuran performa, serta verifikasi koordinat dan layanan eksternal.

## UCAPAN TERIMA KASIH (opsional)

[Tuliskan ucapan terima kasih hanya kepada pihak yang benar-benar memberi dukungan dan telah menyetujui pencantuman namanya.]

## REFERENSI

Format berikut disusun mendekati APA edisi ke-6. Periksa kembali aturan final jurnal dan konsistensi sitasi sebelum pengiriman.

Berners-Lee, T., Hendler, J., & Lassila, O. (2001). The semantic web. *Scientific American, 284*(5), 34–43. https://doi.org/10.1038/scientificamerican0501-34

Bizer, C., Heath, T., & Berners-Lee, T. (2009). Linked data—The story so far. *International Journal on Semantic Web and Information Systems, 5*(3), 1–22. https://doi.org/10.4018/jswis.2009081901

Cyganiak, R., Wood, D., & Lanthaler, M. (Eds.). (2014). *RDF 1.1 concepts and abstract syntax*. World Wide Web Consortium. https://www.w3.org/TR/rdf11-concepts/

Hogan, A., Blomqvist, E., Cochez, M., d’Amato, C., de Melo, G., Gutierrez, C., ... Zimmermann, A. (2021). Knowledge graphs. *ACM Computing Surveys, 54*(4), Article 71, 1–37. https://doi.org/10.1145/3447772

Prud’hommeaux, E., & Seaborne, A. (Eds.). (2008). *SPARQL query language for RDF*. World Wide Web Consortium. https://www.w3.org/TR/rdf-sparql-query/

World Wide Web Consortium. (2013). *SPARQL 1.1 query language*. https://www.w3.org/TR/sparql11-query/

> Tambahkan referensi tentang asal dataset mall/toko, metode rekayasa perangkat lunak yang benar-benar digunakan, serta kebijakan/lisensi gambar dan layanan peta. Sitasi placeholder tidak boleh diterbitkan sebagai sumber.

## Authors Submission Checklist

Isi berdasarkan pemeriksaan penulis sebenarnya sebelum naskah dikirim. Jangan menulis “OK” sebelum butir tersebut sudah diverifikasi.

| Pemeriksaan | Status |
|---|---|
| Semua komentar reviewer terdahulu sudah ditanggapi; perubahan revisi disorot kuning. | [Belum ada data reviewer / isi setelah revisi] |
| Judul memuat maksimal 14 kata dan mengikuti format jurnal. | [Periksa saat format DOCX] |
| Nama, urutan penulis, afiliasi, email, dan persetujuan semua penulis sudah benar. | [Belum diisi] |
| Abstrak berjumlah 200–250 kata setelah revisi. | [Hitung ulang setelah penyuntingan] |
| Pendahuluan mencantumkan minimal tiga penelitian/rujukan terdahulu yang relevan dan terverifikasi. | [Periksa relevansi dan tambah studi aplikasi sejenis] |
| Metode, sumber dataset, tanggal data, dan prosedur dapat direproduksi. | [Tambahkan asal dan tanggal dataset] |
| Jumlah resource, triple, versi perangkat lunak, dan hasil tes telah diperiksa ulang. | [Verifikasi pada snapshot submission] |
| Tidak ada hasil survei/performa/klaim yang dibuat tanpa pengukuran. | [Periksa naskah final] |
| Izin, atribusi, dan lisensi dataset, foto mall, logo, dan konten eksternal telah dipastikan. | [Konfirmasi kepada pemilik/sumber data] |
| Referensi mengikuti gaya APA 6th Edition dan semua sitasi memiliki entri referensi. | [Periksa format akhir jurnal] |
| `.env`, API key, kredensial, dan data personal tidak ikut terunggah. | [Periksa lampiran/repository] |

### Judul Naskah

Perancangan Sistem Eksplorasi Pusat Perbelanjaan Sumatera Berbasis Web Semantik

### Tanggapan kepada Reviewer

#### Reviewer #1

- Komentar: [Belum diterima]
- Tanggapan/perubahan: [Isi setelah review]
- Rekomendasi akhir: [Pilih satu: Accepted without modification / Accepted with minor corrections / Accepted with major modification / Rejected]

#### Reviewer #2

- Komentar: [Belum diterima]
- Tanggapan/perubahan: [Isi setelah review]
- Rekomendasi akhir: [Pilih satu: Accepted without modification / Accepted with minor corrections / Accepted with major modification / Rejected]

#### Reviewer #3

- Komentar: [Belum diterima]
- Tanggapan/perubahan: [Isi setelah review]
- Rekomendasi akhir: [Pilih satu: Accepted without modification / Accepted with minor corrections / Accepted with major modification / Rejected]
