-- =====================================================================
-- EXPORT — pokreni na IZVORNOJ bazi (skyvortex_com_db_6) na produkciji.
--
-- Ova skripta NE menja podatke — ona GENERIŠE gotov INSERT SQL za
-- spa-booking bazu i ispisuje ga na izlaz. Preusmeri izlaz u fajl
-- (npr. vlasnici_data.sql) i taj fajl zatim pokreni na spa-booking bazi.
--
-- Podešavanja:
--   @ZID  — zid zgrade koju uvoziš (vidi 00_preflight.sql)
--
-- Preneti ID-jevi: zgrada.id = zid, stan.id = sid, vlasnik.id = vid.
-- (Ciljna baza mora biti PRAZNA — inače koristi mapiranje umesto ovoga.)
--
-- Uvozi se JEDAN vlasnik po stanu: aktivan (active=1), najnoviji po
-- (pocetak, vid). Token se generiše kao SHA2-256 (64 heks znaka, unique).
--
-- Pokretanje (cPanel Terminal ili SSH), obavezno utf8mb4:
--   mysql -u KORISNIK -p -h HOST --default-character-set=utf8mb4 \
--         --skip-column-names --batch --raw \
--         skyvortex_com_db_6 < 01_export_vlasnici.sql > vlasnici_data.sql
--
-- (Za phpMyAdmin bez CLI-a koristi 03_export_phpmyadmin.sql — vraća celu
--  skriptu u jednoj ćeliji za lako kopiranje.)
-- =====================================================================

SET @ZID := 102;   -- <== UPIŠI zid zgrade

SELECT sql_line FROM (
    SELECT 0 AS ord, 'SET FOREIGN_KEY_CHECKS=0;' AS sql_line
    UNION ALL
    SELECT 1, 'START TRANSACTION;'

    -- ---- ZGRADA (adresa = adresa, zip, sediste spojeni) ----
    UNION ALL
    SELECT 2, CONCAT(
        'INSERT INTO zgrada (id, naziv, adresa, created_at, updated_at) VALUES (',
        z.zid, ', ', QUOTE(TRIM(z.naziv)), ', ',
        QUOTE(NULLIF(TRIM(CONCAT_WS(', ',
            NULLIF(TRIM(z.adresa), ''),
            NULLIF(TRIM(z.zip), ''),
            NULLIF(TRIM(z.sediste), ''))), '')),
        ', NOW(), NOW());'
    )
    FROM zgrada z
    WHERE z.zid = @ZID

    -- ---- STANOVI (broj = stanbr, sprat: 0 -> NULL, ima_dug = 0) ----
    UNION ALL
    SELECT 3, CONCAT(
        'INSERT INTO stan (id, zgrada_id, broj, sprat, ima_dug, created_at, updated_at) VALUES (',
        s.sid, ', ', s.zid, ', ', QUOTE(TRIM(s.stanbr)), ', ',
        IF(s.sprat = 0, 'NULL', QUOTE(s.sprat)), ', 0, NOW(), NOW());'
    )
    FROM stan s
    WHERE s.zid = @ZID

    -- ---- VLASNICI (jedan po stanu; prazan email/tel -> NULL) ----
    UNION ALL
    SELECT 4, CONCAT(
        'INSERT INTO vlasnik (id, stan_id, ime, prezime, email, telefon, token, aktivan, created_at, updated_at) VALUES (',
        v.vid, ', ', v.sid, ', ',
        QUOTE(TRIM(v.ime)), ', ', QUOTE(TRIM(v.prezime)), ', ',
        QUOTE(NULLIF(TRIM(v.email), '')), ', ',
        QUOTE(NULLIF(TRIM(v.tel), '')), ', ',
        QUOTE(SHA2(CONCAT(v.vid, '-', UUID()), 256)), ', 1, NOW(), NOW());'
    )
    FROM vlasnik v
    JOIN stan s ON s.sid = v.sid AND s.zid = @ZID
    WHERE v.active = 1
      AND v.vid = (
          SELECT v2.vid FROM vlasnik v2
          WHERE v2.sid = v.sid AND v2.active = 1
          ORDER BY v2.pocetak DESC, v2.vid DESC
          LIMIT 1
      )

    UNION ALL
    SELECT 98, 'COMMIT;'
    UNION ALL
    SELECT 99, 'SET FOREIGN_KEY_CHECKS=1;'
) t
ORDER BY ord;
