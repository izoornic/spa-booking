-- =====================================================================
-- IMPORT + VERIFIKACIJA — na CILJNOJ bazi (spa-booking) na produkciji.
--
-- 1) Uvoz (generisani fajl je samodovoljan: sadrži FK off + transakciju):
--      mysql -u KORISNIK -p -h HOST --default-character-set=utf8mb4 \
--            SPA_BOOKING_BAZA < vlasnici_data.sql
--
-- 2) Zatim pokreni provere ispod na istoj bazi.
-- =====================================================================

-- Brojevi uvezenog:
SELECT
    (SELECT COUNT(*) FROM zgrada)  AS zgrada,
    (SELECT COUNT(*) FROM stan)    AS stan,
    (SELECT COUNT(*) FROM vlasnik) AS vlasnik;

-- Svaki token je jedinstven i dužine 64 (mora: uniq = vlasnik, max_len = 64):
SELECT
    COUNT(*)                       AS vlasnika,
    COUNT(DISTINCT token)          AS jedinstvenih_tokena,
    MAX(CHAR_LENGTH(token))        AS max_duzina_tokena
FROM vlasnik;

-- Nema vlasnika bez stana, ni stana bez zgrade (mora 0 redova):
SELECT 'vlasnik_bez_stana' AS problem, COUNT(*) AS koliko
FROM vlasnik v LEFT JOIN stan s ON s.id = v.stan_id WHERE s.id IS NULL
UNION ALL
SELECT 'stan_bez_zgrade', COUNT(*)
FROM stan s LEFT JOIN zgrada z ON z.id = s.zgrada_id WHERE z.id IS NULL;

-- Uzorak podataka:
SELECT v.id, s.broj AS stan, v.ime, v.prezime, v.email, v.telefon, LEFT(v.token, 10) AS token
FROM vlasnik v JOIN stan s ON s.id = v.stan_id
ORDER BY v.id
LIMIT 10;

-- ---------------------------------------------------------------------
-- Ako treba PONOVITI uvoz iste zgrade (obriši prethodni pokušaj):
--   SET @ZID := <zid>;
--   DELETE v FROM vlasnik v JOIN stan s ON s.id = v.stan_id WHERE s.zgrada_id = @ZID;
--   DELETE FROM stan WHERE zgrada_id = @ZID;
--   DELETE FROM zgrada WHERE id = @ZID;
