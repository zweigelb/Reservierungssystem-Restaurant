# Restaurant Reservierung – WordPress Plugin
**Version:** 1.2.1  
**Autor:** agentur zweigelb

---

## Installation

1. Ordner `restaurant-reservierung` in `/wp-content/plugins/` hochladen
2. Plugin im WP-Admin unter **Plugins** aktivieren
3. Unter **Reservierungen → Einstellungen** konfigurieren
4. Shortcode `[restaurant_reservierung]` auf einer Seite einbinden

---

## Features

- **Buchungsformular** per Shortcode: `[restaurant_reservierung]`
- **Wochentage** frei konfigurierbar (Standard: Mi–Sa)
- **Zeitslots** 17:00–21:00 Uhr, 15-Minuten-Intervall (konfigurierbar)
- **Kapazität** max. 20 Personen pro Slot (konfigurierbar)
- **Manuelle Bestätigung**: Jede Buchung muss einzeln bestätigt werden
- **E-Mails:**
  - Gast erhält Eingangsbestätigung (ausstehend)
  - Admin erhält Benachrichtigung bei neuer Anfrage
  - Gast erhält Bestätigung **oder** Ablehnung – nur beim Betroffenen, niemals bei anderen Buchungen im selben Slot
- **Tage sperren**: Urlaub, Betriebsferien, große Gesellschaften
- **DSGVO-Checkbox** im Formular
- Kein externer Dienst, keine monatlichen Kosten

---

## Admin-Bereich

| Seite | Pfad |
|-------|------|
| Reservierungen | Reservierungen → Übersicht |
| Tage sperren | Reservierungen → Tage sperren |
| Einstellungen | Reservierungen → Einstellungen |

---

## Einstellungen

| Option | Standard |
|--------|---------|
| Restaurant Name | Blogname |
| Admin E-Mail | WP Admin-E-Mail |
| Slots Start | 17:00 |
| Slots Ende | 21:00 |
| Intervall | 15 Minuten |
| Max. Personen/Slot | 20 |
| Wochentage | Mi, Do, Fr, Sa |

---

## Datenschutz

Das Plugin speichert Name, E-Mail, Telefon, Datum, Uhrzeit, Personenzahl und optionale Anmerkungen in der WordPress-Datenbank. Keine Daten werden an Dritte übertragen. Tabellen: `wp_rr_reservierungen`, `wp_rr_gesperrt`.

---

## Shortcode-Attribute

```
[restaurant_reservierung]
```

Aktuell ohne Attribute. Styling passt sich dem Theme an.

---

## Anpassung

Der Datenschutz-Link im Formular zeigt auf `/datenschutz`. Anpassen in:  
`includes/class-frontend.php` → Zeile mit `href="/datenschutz"`
