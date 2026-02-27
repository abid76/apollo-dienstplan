-- Migration: employee-Namen auf ein Feld vereinheitlichen
-- Änderungen:
-- - Spalte first_name -> name umbenennen
-- - Spalte last_name entfernen
-- - Index idx_employees_last_name entfernen

ALTER TABLE employee
  CHANGE COLUMN first_name name VARCHAR(100) NOT NULL;

ALTER TABLE employee
  DROP COLUMN last_name;

