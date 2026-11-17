# News AI

Plugin WordPress yang otomatis memposting **berita terbaru seputar Artificial Intelligence dari seluruh dunia** lewat RSS (TechCrunch, VentureBeat, MIT Tech Review, The Verge, plus Google News di berbagai negara & bahasa), dengan interval default **setiap 1 menit**.

## Fitur

- Interval dapat diatur (default 1 menit) lewat WP-Cron
- Banyak sumber global & multibahasa (ID, EN, DE, FR, ES, PT, JA, KO, ZH)
- Rotasi feed (round-robin) agar ringan: maks. 3 feed per siklus
- Anti-duplikat berdasarkan hash URL **dan** judul
- Filter kata kunci multibahasa, batas usia berita, batas post per siklus
- Status post: terbit / draf / menunggu review
- Kategori otomatis dibuat ("Berita AI")
- Tombol "Jalankan sekarang" dan log aktivitas di halaman admin
- Hanya memuat **ringkasan + tautan & atribusi sumber** (tidak menyalin artikel penuh)

## Instalasi

1. Unduh repo sebagai ZIP (`Code → Download ZIP`) atau clone ke `wp-content/plugins/news-ai`
2. Aktifkan di **Plugins**
3. Buka menu **News AI** untuk mengatur

## Agar benar-benar jalan tiap menit

WP-Cron hanya terpicu saat ada pengunjung. Untuk akurasi per menit, pakai cron server:

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```bash
# crontab -e
* * * * * curl -s "https://domain-anda.com/wp-cron.php?doing_wp_cron" > /dev/null 2>&1
```

## Catatan penting

- **Hak cipta / ToS:** plugin hanya menyimpan ringkasan pendek + link sumber. Periksa ketentuan tiap penyedia feed sebelum dipakai komersial.
- **Kualitas & SEO:** posting 1 berita/menit (~1.440/hari) berisiko dianggap *spam/thin content* oleh mesin pencari dan membebani database. Pertimbangkan interval lebih longgar atau status *Draf* untuk dimoderasi.
- Google News memakai tautan redirect dan deskripsi pendek, jadi ringkasannya bisa singkat.

## Struktur

```
news-ai/
├── news-ai.php          # bootstrap, cron, hook
├── uninstall.php
└── includes/
    ├── core.php         # opsi, ambil RSS, filter, buat post
    └── admin.php        # halaman pengaturan
```

## Lisensi

GPL-2.0-or-later
