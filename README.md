# SPFPU

The UTHM PPUU File and Correspondence Management System is a PHP 8.3/MariaDB 10.11 application for recording the metadata of incoming and outgoing correspondence. The actual correspondence documents are not stored.

## Production installation

1. Set up a UTF-8 MariaDB database and an application user with privileges limited to that database.
2. Copy `.env.example` to `.env`, enter the production values, and ensure that the file can only be read by the Apache account.
3. Run `composer install --no-dev --classmap-authoritative`.
4. Run `php bin/console migrate`.
5. Export the `SPFPU_ADMIN_*` variables, run `php bin/console seed:admin` once, and then remove those variables from the shell environment.
6. Make `public/` the only `DocumentRoot`. Grant the Apache account write access only to `storage/imports/`.
7. Schedule `php bin/console imports:cleanup` to run every 15 minutes.

Apache 2.4 example:

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

Set `session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Strict`, `expose_php=Off`, `display_errors=Off`, `upload_max_filesize=5M`, and `post_max_size=6M` in the production PHP configuration. Use HTTPS only. The application uses the Asia/Kuala Lumpur time zone and terminates sessions after eight hours of inactivity.

## CSV

Admin imports are only permitted for an empty current volume. The limit is 10,000 rows/5 MB. Accepted headers are: `No.`/`Bil.`, `Type`/`Jenis`/`Masuk/Keluar`, `DOL`/`Surat Bertarikh`, `From/To`/`Daripada/Kepada`, `Received/Sent`/`Dimasukkan/Dihantar`, `Matter`/`Perkara`, and `Remarks`/`Catatan`. Dates may use `DD.MM.YYYY`, `DD/MM/YYYY`, or ISO format during import. All imported fields except `No.`/`Bil.` may be blank; nonblank values are still validated.

## Backup and recovery

Admins can download a compressed SQL dump after confirming their current password. The dump is streamed using `mariadb-dump --single-transaction` and is not stored on the server. To restore it, disable access to the application, validate the file, and use an external DBA procedure such as `gzip -dc backup.sql.gz | mariadb -u ... spfpu`. Archived data must also be restored only by a DBA, based on `archive_batch`, within a transaction, and after a full backup has been made.

## Testing

Run `php tests/smoke.php` for checks without development dependencies, or `composer install && composer test` for PHPUnit. MariaDB integration tests must use a dedicated database, not production data.

## Operational security

- `app/`, `database/`, `storage/`, `tests/`, `.env`, and backups are located outside the web root.
- Do not log or export passwords/hashes. Audit logging filters sensitive field names.
- Ensure that the `BACKUP_BINARY` binary exists and that the Apache account does not have broader shell access than necessary.
- Monitor Apache/PHP logs and the rate of login attempts. Deactivated accounts cannot log in, but their history is retained.
