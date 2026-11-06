# Antrag auf Zuschuss zur Krankenversicherung (R0820)

Form module for the e-formulare.de PDF filler (`kalakotra/pdf-editor`).
Fills the Deutsche Rentenversicherung form **R0820 – Antrag auf Zuschuss zur Krankenversicherung (§ 106 SGB VI)**
(R0820-00, Version 56034, ABF-FORTAQ 2/2025, 6 pages, 39 text + 12 checkbox-group fields) and stamps the applicant's
signature on page 4.

Requires the pdf-editor app base classes (`app\forms\basemodel\*`, `app\models\FormSubmission`).

## Installation

pdf-editor `composer.json`:

```json
"repositories": [
    { "type": "git", "url": "https://github.com/kalakotra/silverstripe-ef-rente-r0820" }
],
"require": {
    "kalakotra/silverstripe-ef-rente-r0820": "^1.0"
}
```

```bash
composer update kalakotra/silverstripe-ef-rente-r0820
vendor/bin/sake db:build --flush
```

`db:build` is mandatory: `DBClassName` is an enum, without it the steps load as plain `MultiFormStep`.

## CMS setup (PDFMultiFormPage)

| Tab | Setting |
|---|---|
| PDF | `PDF File` (slot `PDFFile`) = original fillable `R0820.pdf` from deutsche-rentenversicherung.de (pages 5–6 *Erläuterungen* may be omitted) |
| Form | `Form Class Name` = **Antrag auf Zuschuss zur Krankenversicherung (R0820)** |
| Customising | `SignaturePositionX/Y` – leave empty (module defaults, see below) |

Single document – no annex slots. Forms R0821 / R0822 are filled by the insurer and are only referenced (status checkboxes).

## Flow

| Step | Ziffer | Branching |
|---|---|---|
| Form_Step1 | Kopf (Versicherungsnummer, Kennzeichen), 1 Angaben zur Person | |
| Form_Step2 | 2 Antragstellung durch andere Personen | |
| Form_Step3 | 3.1 weitere Rente (bezogen seit / beantragt am), 3.2 Zuschuss zur weiteren Rente | |
| Form_Step4 | 4.1 freiwillig gesetzlich → 4.2 privat (R0821) → 4.3 ausländisch (R0822) | 4.2 or 4.3 "ja" → Step5, else Step6 |
| Form_Step5 | 4.4 Beitragsaufwendungen Familienangehörige, 4.4.1 Rentner | |
| Form_Step6 | 4.5 Versicherungspflicht, 5 Dokumentenzugang (optional) | |
| Form_Step7 | 6 Erklärung (a–g), Ort | |
| Form_Preview | E-Mail, Unterschrift, AGB | |

Printed flow is mirrored exactly: 4.1 ja → 4.5; 4.2 ja → 4.4 (4.3 skipped); 4.3 nein → 4.5.
"Ort, Datum" = Ort + submission date.

## How it works

- **`Submission::$field_mapping`** – `'PDF field' => 'DbField'` or `'PDF field' => 'Method:DbField:Arg'`.
  Comb fields: Versicherungsnummer (12, `InsuranceNumberCompact` strips spaces), Kennzeichen (4), PLZ (5),
  dates (8 → `DateFormating:Field:dmY`). Composite one-line fields: `PlaceLine` (Wohnort, Land),
  `InsurerLine` (Träger, Versicherungsnummer), `FamilyMemberLine`, `Money` (German notation, `EUR` while it fits),
  `PlaceDate`.
- **`Submission::$state_map`** – every question is ONE checkbox field with several on-states whose names carry
  the printed flow hint (`nein bitte weiter bei Ziffer 4`, …). `AW_R0822` states end with a **trailing space**.
  State names are taken from `pdftk dump_data_fields_utf8` (pypdf mangles the umlauts).
- **`Submission::$field_conditions`** – single source of truth for dependencies (mirrors the display-logic):
  step cleanup (`StepTrait::saveData`), `$conditional_required` and `onBeforeWrite()` cleanup, so hidden values never
  reach the PDF. Form_Step5 is gated as a whole by `Submission::FAMILY_STEP_GATE` (also used by `Form_Step4::getNextStep()`).
- **Versicherungsnummer** – normalised (spaces/dashes removed, upper case) and validated in Step 1 (`\d{8}[A-Z]\d{3}`).
- **Fixed 10 pt fields** – every text field has `setMaxLength` matching its PDF width (verified with max-length test fills).
- **Replacement font** – `fonts/LiberationSans-Regular.ttf` (SIL OFL 1.1) as pdftk `replacement_font` (ž, ć, đ);
  pdftk builds without that option fall back to ASCII-transliterated values (checkbox states stay exact).
  Config: `Submission.replacement_font` (module-relative or absolute path, `''` disables).

## Signature

Stamped with FPDI next to "Unterschrift der Antragstellerin / des Antragstellers" (mm from the top-left corner):

```yaml
Kalakotra\EFormRenteR0820\Model\Submission:
  signature_positions:
    main: { page: 4, x: 100.0, y: 151.0, width: 40.0 }
```

`PDFMultiFormPage.SignaturePositionX/Y` override x/y.

## Translations

`lang/` – ar, bg, bs, cs, de, en, es, fr, it, pl, ro, ru, tr, uk. All labels are literal
`_t(__CLASS__ . '.Key', 'Deutsch')` calls (i18nTextCollectorTask-compatible).

## License

BSD-3-Clause, see [LICENSE](LICENSE). `fonts/LiberationSans-Regular.ttf`: SIL Open Font License 1.1,
see [fonts/LICENSE-LiberationSans.txt](fonts/LICENSE-LiberationSans.txt).
