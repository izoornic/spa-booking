-- =====================================================================
-- PREFLIGHT — pokreni na IZVORNOJ bazi (skyvortex_com_db_6) na produkciji
-- Cilj: izabrati zgradu (zid) i proveriti podatke pre exporta.
-- Ove upite pokreni interaktivno (phpMyAdmin ili mysql klijent).
-- =====================================================================

-- 1) Lista zgrada sa brojem stanova i stanova koji imaju aktivnog vlasnika.
--    Iz ove liste pronađi zid zgrade koju uvoziš.
SELECT
    z.zid,
    z.naziv,
    COUNT(DISTINCT s.sid)                                              AS stanova,
    COUNT(DISTINCT CASE WHEN v.active = 1 THEN s.sid END)             AS stanova_sa_vlasnikom
FROM zgrada z
LEFT JOIN stan s    ON s.zid = z.zid
LEFT JOIN vlasnik v ON v.sid = s.sid
GROUP BY z.zid, z.naziv
ORDER BY z.naziv;

-- ---------------------------------------------------------------------
-- Podesi zid izabrane zgrade za provere ispod:
SET @ZID := 0;   -- <== UPIŠI zid

-- 2) Duplikati / prazni broj stana (target ima UNIQUE(zgrada_id, broj)).
--    Mora da vrati 0 redova; ako ne, ručno počisti izvor ili odluči šta sa duplikatom.
SELECT TRIM(stanbr) AS broj, COUNT(*) AS koliko
FROM stan
WHERE zid = @ZID
GROUP BY TRIM(stanbr)
HAVING COUNT(*) > 1 OR TRIM(stanbr) = '' OR stanbr IS NULL;

-- 3) Stanovi bez ijednog aktivnog vlasnika (biće uvezeni kao stan bez pristupa).
SELECT s.sid, TRIM(s.stanbr) AS broj
FROM stan s
WHERE s.zid = @ZID
  AND NOT EXISTS (SELECT 1 FROM vlasnik v WHERE v.sid = s.sid AND v.active = 1);

-- 4) Stanovi sa VIŠE aktivnih vlasnika (uvozimo samo jednog — najnovijeg po pocetak/vid).
SELECT s.sid, TRIM(s.stanbr) AS broj, COUNT(*) AS aktivnih_vlasnika
FROM stan s
JOIN vlasnik v ON v.sid = s.sid AND v.active = 1
WHERE s.zid = @ZID
GROUP BY s.sid, s.stanbr
HAVING COUNT(*) > 1;
