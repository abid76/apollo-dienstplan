-- Migration: Spalte weekday aus shift entfernen (Wochentage liegen in shift_weekday)
-- Ausführen, wenn die Tabelle shift noch die Spalte weekday enthält.

ALTER TABLE shift DROP COLUMN weekday;
