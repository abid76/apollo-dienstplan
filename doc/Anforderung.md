# Dienstplan
- Planung von Schichten und Rollen für Mitarbeiter einer Nachrichten-Redaktion
- Geplant für 25-50 Mitarbeiter

# Technische Constraints
- Datenbank: MariaDB
- Backend: php
- Frontend: php+html+javascript

# Modell
## Schicht
- Wochentage
- Uhrzeit von, bis

## Rollen
- Bezeichnung, Kürzel

## Mitarbeiter
- Name, Vorname
- Zulässige Wochentage
- Zulässige Schichten
- Rollen
- Anzahl Schichten pro Woche

## Regeln
- Schicht
- Rolle
- Anzahl

## Dienstplan
- Wochentag
  - Schicht
    - Mitarbeiter

# Funktionen
## Datenbarbeitung
- CRUD-Funktionen für Schichten, Rollen, Mitarbeiter, Regeln

## Erstellung Dienstplan
- Angabe Zeitraum in Wochen mit Startdatum
- Für jeden Tag
  - Für jede Schicht
    - Für jede Schichtbelegungsregel
      - Verteile Mitarbeiter zufällig, so dass Anzahl Rollen erfüllt
- Für jeden Mitarbeiter
  - Verteile Mitarbeiter auf Schicht anhand zulässiger Wochentage, Schichten, etc. (maximal eine Schicht pro Tag)
- Anzeige
  - Spalten: Wochentage
  - Zeilen: Mitarbeiter
