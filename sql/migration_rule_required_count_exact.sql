-- Migration: Regel-Tabelle um required_count_exact erweitern
-- Ausführen, wenn die Tabelle rule bereits existiert (z. B. vor dem neuen Schema-Deploy).

ALTER TABLE rule
  ADD COLUMN required_count_exact TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1=genauer Wert, 0=Mindestwert'
  AFTER required_count;
