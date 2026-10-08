# Cover Produk — SIMRS Web

Gambar sampul (cover) produk untuk **SIMRS Web v2.0.0** — aplikasi Sistem Informasi
Manajemen Rumah Sakit berbasis PHP + MySQL + AdminLTE 4.

## Berkas

| Berkas | Ukuran | Keterangan |
|--------|--------|-----------|
| `cover.png` | 1280×640 | Versi utama (rasio 2:1, ukuran sosial/OG standar) |
| `cover@2x.png` | 2560×1280 | Versi retina untuk layar beresolusi tinggi |
| `cover.html` | — | Sumber desain (HTML/CSS murni, tanpa dependensi) |

## Pemakaian

- **README / dokumentasi**

  ```html
  <p align="center">
    <img src="assets/cover/cover.png" alt="SIMRS Web — Hospital Information System" width="720">
  </p>
  ```

  ```markdown
  ![SIMRS Web](assets/cover/cover.png)
  ```

- **Open Graph / media sosial** — arahkan `og:image` ke URL absolut `.../assets/cover/cover.png` (1280×640 sudah sesuai anjuran).

- **Presentasi / proposal** — gunakan `cover@2x.png` agar tetap tajam saat dicetak.

## Menyetel ulang gambar

Desain dibuat dari satu berkas HTML mandiri, sehingga mudah diperbarui:

```bash
# 1) ubah teks/warna pada cover.html, lalu render ulang
chromium --headless=new --no-sandbox --hide-scrollbars \
  --force-device-scale-factor=2 --window-size=1280,640 \
  --virtual-time-budget=9000 --screenshot=cover@2x.png \
  "file://$PWD/assets/cover/cover.html"

# 2) turunkan ke 1280×640 sebagai versi utama
python3 -c "from PIL import Image; \
Image.open('cover@2x.png').convert('RGB').resize((1280,640), Image.LANCZOS).save('cover.png')"
```

Palet warna mengikuti tema aplikasi: `#0b3d91` → `#1565c0` → `#00b4d8`,
aksen mint `#41f0c8`. Tipografi: **Sora** (judul), **Plus Jakarta Sans** (teks),
**JetBrains Mono** (label teknis).
