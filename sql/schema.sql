-- MariaDB Schema für Dienstplan-Anwendung
-- Basierend auf doc/Anforderung.md

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS shift (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Montag ... 6=Sonntag',
  time_from TIME NOT NULL,
  time_to TIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shift_weekday (
  shift_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Montag ... 6=Sonntag',
  PRIMARY KEY (shift_id, weekday),
  CONSTRAINT fk_sw_shift
    FOREIGN KEY (shift_id) REFERENCES shift (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  shortcode VARCHAR(10) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_roles_shortcode (shortcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  max_shifts_per_week TINYINT UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_allowed_weekday (
  employee_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Montag ... 6=Sonntag',
  PRIMARY KEY (employee_id, weekday),
  CONSTRAINT fk_eaw_employee
    FOREIGN KEY (employee_id) REFERENCES employee (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_allowed_shift (
  employee_id INT UNSIGNED NOT NULL,
  shift_id INT UNSIGNED NOT NULL,
  max_per_week TINYINT UNSIGNED NULL COMMENT 'Max. Anzahl dieser Schicht pro Woche (NULL = unbegrenzt)',
  PRIMARY KEY (employee_id, shift_id),
  CONSTRAINT fk_eas_employee
    FOREIGN KEY (employee_id) REFERENCES employee (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eas_shift
    FOREIGN KEY (shift_id) REFERENCES shift (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS employee_role (
  employee_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (employee_id, role_id),
  CONSTRAINT fk_er_employee
    FOREIGN KEY (employee_id) REFERENCES employee (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_er_role
    FOREIGN KEY (role_id) REFERENCES role (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rule (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  required_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  required_count_exact TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=genauer Wert, 0=Mindestwert',
  PRIMARY KEY (id),
  UNIQUE KEY uniq_rule (shift_id, role_id),
  CONSTRAINT fk_rules_shift
    FOREIGN KEY (shift_id) REFERENCES shift (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rules_role
    FOREIGN KEY (role_id) REFERENCES role (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  start_date DATE NOT NULL,
  weeks TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_plan_start_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_entry (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  shift_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_plan_entry_plan_date (plan_id, date),
  KEY idx_plan_entry_employee_date (employee_id, date),
  CONSTRAINT fk_pe_plan
    FOREIGN KEY (plan_id) REFERENCES plan (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pe_shift
    FOREIGN KEY (shift_id) REFERENCES shift (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pe_employee
    FOREIGN KEY (employee_id) REFERENCES employee (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pe_role
    FOREIGN KEY (role_id) REFERENCES role (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
CREATE TABLE IF NOT EXISTS holiday (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  PRIMARY KEY (id),
  KEY idx_holiday_employee_date (employee_id, date_from, date_to),
  CONSTRAINT fk_holiday_employee
    FOREIGN KEY (employee_id) REFERENCES employee (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

