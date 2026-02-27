-- Migration: Tabelle employee_allowed_weekday_shift (Einschränkung Schichten pro Wochentag)
-- Nur ausführen, wenn die Tabellen employee_allowed_weekday und employee_allowed_shift bereits existieren.

CREATE TABLE IF NOT EXISTS employee_allowed_weekday_shift (
  employee_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Montag ... 6=Sonntag',
  shift_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (employee_id, weekday, shift_id),
  CONSTRAINT fk_eaws_weekday
    FOREIGN KEY (employee_id, weekday) REFERENCES employee_allowed_weekday (employee_id, weekday)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eaws_shift
    FOREIGN KEY (employee_id, shift_id) REFERENCES employee_allowed_shift (employee_id, shift_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
