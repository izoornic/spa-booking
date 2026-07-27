-- =====================================================================
-- EXPORT za phpMyAdmin — verzija koja vraća CELU skriptu u JEDNOJ ćeliji.
-- Koristi kad izvor i cilj NISU na istom serveru (nema cross-DB), a radiš
-- kroz phpMyAdmin (bez CLI-a). Alternativa CLI verziji: 01_export_vlasnici.sql
--
-- POSTUPAK:
--   1) Na IZVORNOM serveru (skyvortex_com_db_6) → phpMyAdmin → izaberi tu
--      bazu → tab "SQL" → nalepi ovaj upit, upiši @ZID, klikni "Go".
--   2) Rezultat je jedna ćelija (kolona import_sql). Ako je tekst skraćen,
--      uključi "Show full texts" (Options iznad rezultata) pa selektuj i
--      KOPIRAJ ceo sadržaj ćelije.
--   3) Na CILJNOM serveru → phpMyAdmin → izaberi spa-booking bazu → tab
--      "SQL" → nalepi kopirano → "Go". (Skripta sadrži FK off + transakciju.)
--
-- Preneti ID-jevi (zid→zgrada.id, sid→stan.id, vid→vlasnik.id); ciljna baza
-- mora biti PRAZNA. Jedan aktivan vlasnik po stanu; token = SHA2-256 (unique).
-- =====================================================================

SET SESSION group_concat_max_len = 1000000000;   -- da se dugačak izlaz ne odseče
SET @ZID := 102;                                    -- <== UPIŠI zid zgrade

SELECT CONCAT_WS('\n',
    'SET FOREIGN_KEY_CHECKS=0;',
    'START TRANSACTION;',

    -- ---- ZGRADA (adresa = adresa, zip, sediste spojeni) ----
    (SELECT GROUP_CONCAT(CONCAT(
        'INSERT INTO zgrada (id, naziv, adresa, created_at, updated_at) VALUES (',
        z.zid, ', ', QUOTE(TRIM(z.naziv)), ', ',
        QUOTE(NULLIF(TRIM(CONCAT_WS(', ',
            NULLIF(TRIM(z.adresa), ''),
            NULLIF(TRIM(z.zip), ''),
            NULLIF(TRIM(z.sediste), ''))), '')),
        ', NOW(), NOW());'
    ) SEPARATOR '\n')
     FROM zgrada z WHERE z.zid = @ZID),

    -- ---- STANOVI (broj = stanbr, sprat: 0 -> NULL, ima_dug = 0) ----
    (SELECT GROUP_CONCAT(CONCAT(
        'INSERT INTO stan (id, zgrada_id, broj, sprat, ima_dug, created_at, updated_at) VALUES (',
        s.sid, ', ', s.zid, ', ', QUOTE(TRIM(s.stanbr)), ', ',
        IF(s.sprat = 0, 'NULL', QUOTE(s.sprat)), ', 0, NOW(), NOW());'
    ) SEPARATOR '\n')
     FROM stan s WHERE s.zid = @ZID),

    -- ---- VLASNICI (jedan po stanu; prazan email/tel -> NULL) ----
    (SELECT GROUP_CONCAT(CONCAT(
        'INSERT INTO vlasnik (id, stan_id, ime, prezime, email, telefon, token, aktivan, created_at, updated_at) VALUES (',
        v.vid, ', ', v.sid, ', ',
        QUOTE(TRIM(v.ime)), ', ', QUOTE(TRIM(v.prezime)), ', ',
        QUOTE(NULLIF(TRIM(v.email), '')), ', ',
        QUOTE(NULLIF(TRIM(v.tel), '')), ', ',
        QUOTE(SHA2(CONCAT(v.vid, '-', UUID()), 256)), ', 1, NOW(), NOW());'
    ) SEPARATOR '\n')
     FROM vlasnik v
     JOIN stan s ON s.sid = v.sid AND s.zid = @ZID
     WHERE v.active = 1
       AND v.vid = (
           SELECT v2.vid FROM vlasnik v2
           WHERE v2.sid = v.sid AND v2.active = 1
           ORDER BY v2.pocetak DESC, v2.vid DESC
           LIMIT 1
       )),

    'COMMIT;',
    'SET FOREIGN_KEY_CHECKS=1;'
) AS import_sql;
