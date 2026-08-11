# SPFPU

Sistem Pengurusan Fail dan Persuratan PPUU UTHM ialah aplikasi PHP 8.3/MariaDB 10.11 untuk merekod metadata surat masuk dan keluar. Dokumen surat sebenar tidak disimpan.

## Pemasangan produksi

1. Sediakan pangkalan data MariaDB UTF-8 dan pengguna aplikasi dengan hak kepada pangkalan data itu sahaja.
2. Salin `.env.example` kepada `.env`, isi nilai produksi, dan pastikan fail itu hanya boleh dibaca oleh akaun Apache.
3. Jalankan `composer install --no-dev --classmap-authoritative`.
4. Jalankan `php bin/console migrate`.
5. Eksport pemboleh ubah `SPFPU_ADMIN_*`, jalankan `php bin/console seed:admin` sekali, kemudian padam pemboleh ubah tersebut daripada persekitaran shell.
6. Jadikan `public/` satu-satunya `DocumentRoot`. Beri akaun Apache hak tulis hanya kepada `storage/imports/`.
7. Jadualkan `php bin/console imports:cleanup` setiap 15 minit.

Contoh Apache 2.4:

```apache
<VirtualHost *:80>
    ServerName spfpu.example.uthm.edu.my
    Redirect permanent / https://spfpu.example.uthm.edu.my/
</VirtualHost>
<VirtualHost *:443>
    ServerName spfpu.example.uthm.edu.my
    DocumentRoot /var/www/spfpu/public
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/spfpu.example.uthm.edu.my/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/spfpu.example.uthm.edu.my/privkey.pem
    <Directory /var/www/spfpu/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

Tetapkan `session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Strict`, `expose_php=Off`, `display_errors=Off`, `upload_max_filesize=5M`, dan `post_max_size=6M` dalam konfigurasi PHP produksi. Gunakan HTTPS sahaja. Aplikasi menetapkan zon waktu Asia/Kuala Lumpur dan menamatkan sesi selepas lapan jam tanpa aktiviti.

## CSV

Import Admin hanya untuk jilid semasa yang kosong. Hadnya 10,000 baris/5 MB. Pengepala diterima: `No.`/`Bil.`, `Type`/`Jenis`/`Masuk/Keluar`, `DOL`/`Surat Bertarikh`, `From/To`/`Daripada/Kepada`, `Received/Sent`/`Dimasukkan/Dihantar`, `Matter`/`Perkara`, dan `Remarks`/`Catatan`. Tarikh boleh menggunakan `DD.MM.YYYY`, `DD/MM/YYYY`, atau ISO semasa import.

## Sandaran dan pemulihan

Admin boleh memuat turun dump SQL termampat selepas pengesahan kata laluan semasa. Dump distrim menggunakan `mariadb-dump --single-transaction` dan tidak disimpan pada pelayan. Untuk pemulihan, hentikan akses aplikasi, sahkan fail, dan gunakan prosedur DBA luaran seperti `gzip -dc backup.sql.gz | mariadb -u ... spfpu`. Data arkib juga hanya dipulihkan oleh DBA berdasarkan `archive_batch` dalam transaksi dan selepas sandaran penuh.

## Ujian

Jalankan `php tests/smoke.php` untuk semakan tanpa dependensi pembangunan atau `composer install && composer test` untuk PHPUnit. Ujian integrasi MariaDB hendaklah menggunakan pangkalan data khusus, bukan data produksi.

## Keselamatan operasi

- `app/`, `database/`, `storage/`, `tests/`, `.env`, dan sandaran berada di luar web root.
- Jangan log atau eksport kata laluan/hash. Audit menyaring nama medan sensitif.
- Pastikan binari `BACKUP_BINARY` wujud dan akaun Apache tidak mempunyai akses shell yang lebih luas daripada perlu.
- Pantau log Apache/PHP dan kadar cubaan log masuk. Akaun dinyahaktif tidak boleh log masuk tetapi sejarahnya dikekalkan.
