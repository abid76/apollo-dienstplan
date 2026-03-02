-- Migration: Optionales Feld max_per_week in employee_allowed_shift
-- Nur ausführen, wenn die Tabelle employee_allowed_shift bereits existiert.

ALTER TABLE employee_allowed_shift
  ADD COLUMN max_per_week INT UNSIGNED NULL
  COMMENT 'Max. Anzahl dieser Schicht pro Woche (NULL = unbegrenzt)'
  AFTER shift_id;
