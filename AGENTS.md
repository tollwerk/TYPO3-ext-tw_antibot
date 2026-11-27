# Anweisungen für KI-Agenten

Dieses Projekt folgt den [tollwerk guidelines](https://code.tollwerk.net/tollwerk/guidelines). Sie sind verbindlich für Menschen und KI-Agenten. Vor jeder Änderung lesen; in PHP-Projekten liegen sie nach `composer install` auch in `vendor/tollwerk/guidelines/guidelines/`.

## Profile

Dieses Projekt übernimmt die Basis der guidelines und diese Profile, wie in `guidelines.json` eingestellt:

- `typo3`
- `frontend`

## Über dieses Projekt

`tw_antibot` bringt Spamschutz ohne CAPTCHA für TYPO3 14: Schutzfelder und den Hinweis bei einer Sperre als Komponenten, das Urteil über eine Einsendung aus jedem Formular, Sperren und Stopwords als Datensätze und einen Adapter für EXT:form. Die Prüfungen selbst macht die Bibliothek `jkphl/antibot`. Die Dokumentation ist Deutsch, Code, Kommentare und Commit-Nachrichten sind Englisch.

- **Prüfungen:** `composer check` führt `guidelines gate` aus, dieselben Prüfungen wie die Pipeline, nach `npm ci` auch die für CSS. Die Browsertests laufen mit `npm run test:browser`, nicht im Gate.
- **Entwicklung:** Mit `COMPOSER=composer.local.json` bindet Composer die Klone von `tw-base` und `jkphl/antibot` daneben ein; `composer.local.json` steht in `.git/info/exclude`.

## Regeln dieses Projekts

- **Docblocks:** `@category Tollwerk`, `@package Tollwerk\TwAntibot`.
- **Geprüfte Ordner:** `Classes`, `Configuration` und `tests`, statt `packages` und `tests` einer TYPO3-Anwendung; eine Extension hat ihren Code an der Wurzel.
- **Barrierefreiheit:** Alles erfüllt mindestens WCAG 2.2 AA und funktioniert vollständig ohne JavaScript. Der Schutz verlangt von niemandem etwas: kein CAPTCHA, kein Rätsel, keine Rechenarbeit im Browser.
- **Der Kern kennt EXT:form nicht.** Alles für EXT:form steht unter `Classes/Form` und wird nur geladen, wenn EXT:form installiert ist.
- **Unabhängig von tw_fields.** Die Schutzfelder sind eine eigene Komponente, die in jedes Formular passt.
- **Jede Prüfung des Inhalts schaltet ein Formular einzeln ein.** Keine Prüfung wirkt, die niemand gewählt hat.

## Abweichungen von den guidelines

Keine. Eine Abweichung braucht die Freigabe des Maintainers und steht hier mit ihrem Grund.
