# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-11-06

### Added

- Beginn der Veranstaltung
- Informationsblock: Veranstalter, Ansprechpartner mit E-Mail, Bemerkungen
- Spendenlisten (z. B. Kuchenliste) mit benötigter Anzahl, öffentlich eintragen/austragen, Verwaltung im Tab „Spenden“, eigener Tab im Excel-Export
- Filter nach Stand und „nur freie Plätze“ auf der öffentlichen Seite

## [1.1.0] - 2026-11-06

### Added

- Eintragen direkt in der Schicht: Name beim freien Platz eingeben statt Auswahl + Formular
- Mehrere Personen von einem Gerät eintragen, „Schnell eintragen“ für bekannte Personen
- Kalender-Import (.ics) für einzelne Schichten, alle eigenen Schichten und (Verwaltung) alle Schichten
- E-Mail-Adresse ist freiwillig und kann pro Umfrage als Pflicht eingestellt werden

### Changed

- Durchgehend Nextcloud-Komponenten (NcSelect, NcTextField, NcChip, Nextcloud-Bestätigungsdialoge)

## [1.0.0] - 2026-11-06

### Added

- Helfer-Umfragen mit Schichten (Zeiten), Ständen/Aufgaben und benötigter Helferzahl
- Verwaltungsansicht in Nextcloud: Einteilung, Helferliste, Einstellungen, Helfer manuell eintragen/bearbeiten, Rollen
- Öffentliche Helferansicht über geteilten Link (ohne Konto), mehrere Schichten auf einmal, selbst austragen
- Enddatum: danach nur noch Änderungen in der Verwaltung
- Schließungsdatum: öffentlichen Link schließen oder löschen, Umfrage bleibt intern
- Excel-Export (Tabs „Helfer“ und „Einsätze“) in einen Nextcloud-Ordner, manuell oder automatisch nach dem Enddatum, sowie als Download
- Eigene Fragen, Gruppe/Verein-Abfrage, Umfrage kopieren
