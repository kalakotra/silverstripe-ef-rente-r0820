<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Model;

use app\forms\basemodel\BaseSubmission;
use mikehaertl\pdftk\Pdf;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Injector\Injector;
use setasign\Fpdi\Fpdi;

/**
 * Antrag auf Zuschuss zur Krankenversicherung (§ 106 SGB VI), DRV Vordruck R0820-00 (Version 56034).
 *
 * Mapping conventions (see BaseSubmission::getArrayDataForPDF):
 *   'PDF field' => 'DbField'                       plain value
 *   'PDF field' => 'Method:DbField:Arg'            computed value
 *
 * Every yes/no question of this PDF is ONE checkbox field with several on-states whose names carry the printed
 * flow hint ("nein bitte weiter bei Ziffer 4"); AW_R0822 states end with a trailing space. All of them go through
 * StateValue + $state_map (state names taken from pdftk dump_data_fields_utf8).
 * Dates are comb fields (MaxLen 8) -> DateFormating:Field:dmY. The header (Versicherungsnummer, Kennzeichen)
 * repeats on pages 1-4 under the same field names and is filled everywhere by pdftk.
 */
class Submission extends BaseSubmission
{
    private static string $table_name = 'Kalakotra_EFormRenteR0820_Submission';

    /** Versicherungsnummer after normalizeInsuranceNumber(): area (2) + birth date (6) + letter + serial/check (3). */
    public const INSURANCE_NUMBER_PATTERN = '/^\d{8}[A-Z]\d{3}$/';

    /**
     * Ziffer 4.4 (Form_Step5) is only reached after "ja" in 4.2 or 4.3 (printed flow). Shared by the step
     * branching (Form_Step4::getNextStep) and the whole-step gate in $field_conditions.
     */
    public const FAMILY_STEP_GATE = [
        ['StatutoryVoluntary' => 'no', 'PrivateInsurance' => 'yes'],
        ['StatutoryVoluntary' => 'no', 'ForeignInsurance' => 'yes'],
    ];

    /** Verwandtschaftsverhältnis as printed in "Familienangehöriger (Name, Vorname, Geburtsdatum, ...)". */
    private const RELATIONSHIP = [
        'spouse' => 'Ehegatte',
        'partner' => 'Lebenspartner',
        'child' => 'Kind',
    ];

    /** MaxLen of "Höhe des monatlichen Gesamteinkommens" (ANGE_EINKOMMEN_1). */
    private const INCOME_MAX_LENGTH = 10;

    private static array $db = [
        // Kopf + Ziffer 1
        'InsuranceNumber' => 'Varchar(15)',
        'Kennzeichen' => 'Varchar(4)',
        'LastName' => 'Varchar(45)',
        'FirstName' => 'Varchar(45)',
        'NameSuffix' => 'Varchar(28)',
        'NamePrefix' => 'Varchar(28)',
        'Title' => 'Varchar(28)',
        'BirthName' => 'Varchar(70)',
        'BirthDate' => 'Date',
        'Street' => 'Varchar(55)',
        'AddressAddition' => 'Varchar(55)',
        'PostalCode' => 'Varchar(5)',
        'City' => 'Varchar(45)',
        'Country' => 'Varchar(28)',
        'Phone' => 'Varchar(30)',
        'Fax' => 'Varchar(30)',

        // Ziffer 2
        'FiledByRepresentative' => 'Varchar(10)',
        'RepresentativeName' => 'Varchar(90)',
        'RepresentativeCapacity' => 'Varchar(20)',
        'RepresentativeStreet' => 'Varchar(55)',
        'RepresentativeAddressAddition' => 'Varchar(55)',
        'RepresentativePostalCode' => 'Varchar(5)',
        'RepresentativeCity' => 'Varchar(45)',
        'RepresentativeCountry' => 'Varchar(28)',
        'RepresentativePhone' => 'Varchar(30)',
        'RepresentativeFax' => 'Varchar(30)',

        // Ziffer 3
        'OtherPension' => 'Varchar(10)',
        'OtherPensionInsurer' => 'Varchar(50)',
        'OtherPensionNumber' => 'Varchar(20)',
        'OtherPensionStatus' => 'Varchar(20)',
        'OtherPensionSince' => 'Date',
        'OtherPensionAppliedOn' => 'Date',
        'OtherPensionSubsidy' => 'Varchar(10)',

        // Ziffer 4.1 - 4.3
        'StatutoryVoluntary' => 'Varchar(10)',
        'PrivateInsurance' => 'Varchar(10)',
        'R0821Status' => 'Varchar(20)',
        'ForeignInsurance' => 'Varchar(10)',
        'R0822Status' => 'Varchar(20)',

        // Ziffer 4.4, 4.4.1
        'FamilyContributions' => 'Varchar(10)',
        'FamilyMemberLastName' => 'Varchar(25)',
        'FamilyMemberFirstName' => 'Varchar(20)',
        'FamilyMemberBirthDate' => 'Date',
        'FamilyMemberRelationship' => 'Varchar(20)',
        'FamilyMemberIncome' => 'Decimal(8,2)',
        'FamilyMemberPensioner' => 'Varchar(10)',
        'FamilyMemberPensionInsurer' => 'Varchar(50)',
        'FamilyMemberPensionNumber' => 'Varchar(20)',
        'FamilyMemberPensionSince' => 'Date',

        // Ziffer 4.5, 5
        'CompulsoryInsurance' => 'Varchar(10)',
        'CompulsoryInsurer' => 'Varchar(80)',
        'CompulsoryReason' => 'Varchar(80)',
        'AccessibleFormat' => 'Varchar(20)',

        // Ziffer 6
        'DeclarationAccepted' => 'Boolean',
        'Place' => 'Varchar(25)',
    ];

    /**
     * @var array<string, string>
     */
    private static array $field_mapping = [
        // header, pages 1-4
        'PAF_VSNR_trim' => 'InsuranceNumberCompact:InsuranceNumber:12',
        'PAF_AIGR' => 'Kennzeichen',

        // page 1: Ziffer 1
        'Q_PAF_Ber_Name' => 'LastName',
        'Q_PAF_Ber_Vorname' => 'FirstName',
        'BER_NZ_1' => 'NameSuffix',
        'BER_VORSATZ_1' => 'NamePrefix',
        'BER_TITEL_1' => 'Title',
        'Q_PAF_Ber_GebName' => 'BirthName',
        'Q_PAF_Ber_GebDat_trim' => 'DateFormating:BirthDate:dmY',
        'Q_PAF_Ber_Straße_Postfach' => 'Street',
        'TEL_1' => 'Phone',
        'BER_ADR_ZUSATZ' => 'AddressAddition',
        'FAX_1' => 'Fax',
        'Q_PAF_Ber_PLZ' => 'PostalCode',
        'Q_PAF_Ber_Wohnort' => 'PlaceLine:City:Country',

        // page 1: Ziffer 2
        'VERT_N_VN_D_1' => 'RepresentativeName',
        'AW_EIGENSCHAFT_1' => 'StateValue:RepresentativeCapacity:AW_EIGENSCHAFT_1',
        'VERT_ADR_1' => 'RepresentativeStreet',
        'TEL_2' => 'RepresentativePhone',
        'VERT_ADR_ZUSATZ' => 'RepresentativeAddressAddition',
        'FAX_2' => 'RepresentativeFax',
        'VERT_PLZ_1' => 'RepresentativePostalCode',
        'VERT_ORT_1' => 'PlaceLine:RepresentativeCity:RepresentativeCountry',

        // page 2: Ziffer 3
        'AW_WEITERERENTE_1' => 'StateValue:OtherPension:AW_WEITERERENTE_1',
        'VERS_TRAEGER_1' => 'InsurerLine:OtherPensionInsurer:OtherPensionNumber',
        'RENTE_SEIT_1' => 'DateFormating:OtherPensionSince:dmY',
        'RENTE_BEANTR_1' => 'DateFormating:OtherPensionAppliedOn:dmY',
        'AW_BEREITSZUSCHUSSKV_1' => 'StateValue:OtherPensionSubsidy:AW_BEREITSZUSCHUSSKV_1',

        // page 2: Ziffer 4.1 - 4.4
        'AW_BESTKV_1' => 'StateValue:StatutoryVoluntary:AW_BESTKV_1',
        'AW_KVPRIV_1' => 'StateValue:PrivateInsurance:AW_KVPRIV_1',
        'AW_R0821' => 'StateValue:R0821Status:AW_R0821',
        'AW_KVPRIV_AUSL_1' => 'StateValue:ForeignInsurance:AW_KVPRIV_AUSL_1',
        'AW_R0822' => 'StateValue:R0822Status:AW_R0822',
        'AW_KVPRIV_AUFW_1' => 'StateValue:FamilyContributions:AW_KVPRIV_AUFW_1',
        'ANGE_N_VN_1' => 'FamilyMemberLine:FamilyMemberLastName:line',
        'ANGE_EINKOMMEN_1' => 'Money:FamilyMemberIncome:EUR',

        // page 3: Ziffer 4.4.1, 4.5, 5
        'AW_ANGERENTNER_1' => 'StateValue:FamilyMemberPensioner:AW_ANGERENTNER_1',
        'ANGE_RENTETRAEGER_VSNR_1' => 'InsurerLine:FamilyMemberPensionInsurer:FamilyMemberPensionNumber',
        'ANG_RENTE_SEIT_1' => 'DateFormating:FamilyMemberPensionSince:dmY',
        'AW_VERSICHERUNGSPFLICHT_1' => 'StateValue:CompulsoryInsurance:AW_VERSICHERUNGSPFLICHT_1',
        'VERS_KRANKENKASSE_1' => 'CompulsoryInsurer',
        'VERS_PFLICHTGRUND_1' => 'CompulsoryReason',
        'AW_BARRIERE_1' => 'StateValue:AccessibleFormat:AW_BARRIERE_1',

        // page 4: Ziffer 6
        'VERS_O_DAT_1' => 'PlaceDate:Place:d.m.Y',
    ];

    /**
     * Stored value -> on-state of the PDF checkbox group (pdftk dump_data_fields_utf8, exact incl. spaces).
     *
     * @var array<string, array<string, string>>
     */
    private static array $state_map = [
        'AW_EIGENSCHAFT_1' => [
            'legal' => 'gesetzlicher Vertreter',
            'guardian' => 'Vormund',
            'custodian' => 'Betreuer',
            'authorized' => 'Bevollmächtigter',
        ],
        'AW_WEITERERENTE_1' => [
            'no' => 'nein bitte weiter bei Ziffer 4',
            'yes' => 'ja',
        ],
        'AW_BEREITSZUSCHUSSKV_1' => [
            'no' => 'nein',
            'yes' => 'ja',
        ],
        'AW_BESTKV_1' => [
            'no' => 'nein',
            'yes' => 'ja bitte weiter bei Ziffer 4 Punkt 5',
        ],
        'AW_KVPRIV_1' => [
            'no' => 'nein bitte weiter bei Ziffer 4 Punkt 3',
            'yes' => 'ja bitte von Ihrem Versicherungsunternehmen der KVB der PBeaKK beziehungsweise Ihrer Solidargemeinschaft Vordruck R0821 ausfüllen lassen',
        ],
        'AW_R0821' => [
            'attached' => 'ist beigefügt bitte weiter bei Ziffer 4 Punkt 4',
            'later' => 'wird nachgereicht bitte weiter bei Ziffer 4 Punkt 4',
            'forwarded' => 'wurde weitergeleitet bitte weiter bei Ziffer 4 Punkt 4',
        ],
        'AW_KVPRIV_AUSL_1' => [
            'no' => 'nein bitte weiter bei Ziffer 4 Punkt 5',
            'yes' => 'ja bitte von Ihrem Versicherungsunternehmen Vordruck R0822 ausfüllen lassen',
        ],
        // trailing spaces are part of the state names
        'AW_R0822' => [
            'attached' => 'ist beigefügt ',
            'later' => 'wird nachgereicht ',
            'forwarded' => 'wurde weitergeleitet ',
        ],
        'AW_KVPRIV_AUFW_1' => [
            'no' => 'nein bitte weiter bei Ziffer 4 Punkt 5',
            'yes' => 'ja bitte Beitragsaufwendungen ebenfalls im Vordruck R0821 / R0822 eintragen lassen',
        ],
        'AW_ANGERENTNER_1' => [
            'no' => 'nein',
            'yes' => 'ja',
        ],
        'AW_VERSICHERUNGSPFLICHT_1' => [
            'no' => 'nein',
            'yes' => 'ja',
        ],
        'AW_BARRIERE_1' => [
            'large_print' => 'als Großdruck',
            'braille_short' => 'in Braille Kurzschrift',
            'braille_full' => 'in Braille Vollschrift',
            'cd' => 'als CD Schriftdatei oder Textdatei im doc Format',
            'daisy' => 'als Hörmedium im CD DAISY Format',
        ],
    ];

    /**
     * Declarative dependencies (mirrored by display-logic in the steps), see FieldConditions.
     * Order matters: parents before children (a cleared parent disables its children).
     *
     * @var array<int, array<int, mixed>>
     */
    private static array $field_conditions = [
        // Ziffer 2
        [
            ['RepresentativeName', 'RepresentativeCapacity', 'RepresentativeStreet', 'RepresentativeAddressAddition',
                'RepresentativePostalCode', 'RepresentativeCity', 'RepresentativeCountry', 'RepresentativePhone', 'RepresentativeFax'],
            ['FiledByRepresentative' => 'yes'],
        ],
        // Ziffer 3
        [['OtherPensionInsurer', 'OtherPensionNumber', 'OtherPensionStatus', 'OtherPensionSubsidy'], ['OtherPension' => 'yes']],
        [['OtherPensionSince'], ['OtherPensionStatus' => 'receiving']],
        [['OtherPensionAppliedOn'], ['OtherPensionStatus' => 'applied']],
        // Ziffer 4.1 - 4.3
        [['PrivateInsurance'], ['StatutoryVoluntary' => 'no']],
        [['R0821Status'], ['PrivateInsurance' => 'yes']],
        [['ForeignInsurance'], ['PrivateInsurance' => 'no']],
        [['R0822Status'], ['ForeignInsurance' => 'yes']],
        // Ziffer 4.4: whole step (Form_Step5) gated by 4.2 / 4.3
        [['FamilyContributions'], ...self::FAMILY_STEP_GATE],
        [
            ['FamilyMemberLastName', 'FamilyMemberFirstName', 'FamilyMemberBirthDate', 'FamilyMemberRelationship',
                'FamilyMemberIncome', 'FamilyMemberPensioner'],
            ['FamilyContributions' => 'yes'],
        ],
        [['FamilyMemberPensionInsurer', 'FamilyMemberPensionNumber', 'FamilyMemberPensionSince'], ['FamilyMemberPensioner' => 'yes']],
        // Ziffer 4.5
        [['CompulsoryInsurer', 'CompulsoryReason'], ['CompulsoryInsurance' => 'yes']],
    ];

    /**
     * Signatures in mm from the top-left corner ("Unterschrift der Antragstellerin / des Antragstellers", page 4).
     * PDFMultiFormPage.SignaturePositionX/Y override x/y of "main".
     *
     * @var array<string, array{page: int, x: float, y: float, width: float}>
     */
    private static array $signature_positions = [
        'main' => ['page' => 4, 'x' => 100.0, 'y' => 151.0, 'width' => 40.0],
    ];

    /**
     * TTF used by pdftk for field appearances (ž, ć ... are dropped by pdftk's own appearance generation).
     * Relative to the module root or absolute; set to '' to disable. Liberation Sans: SIL OFL 1.1, see fonts/.
     */
    private static string $replacement_font = 'fonts/LiberationSans-Regular.ttf';

    /** Signature purposes whose position follows the CMS override. */
    private const CMS_POSITION_KEYS = ['main'];

    /**
     * Fallback when pdftk cannot use the replacement font: values are transliterated to ASCII
     * (German rules first, remaining diacritics stripped via Normalizer).
     */
    private const TRANSLITERATION = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        'đ' => 'd', 'Đ' => 'D', 'ł' => 'l', 'Ł' => 'L', 'ı' => 'i', 'ø' => 'o', 'Ø' => 'O', 'æ' => 'ae', 'Æ' => 'Ae',
        'œ' => 'oe', 'Œ' => 'Oe', '„' => '"', '“' => '"', '”' => '"', '‚' => "'", '‘' => "'", '’' => "'", '–' => '-', '—' => '-',
        '€' => 'EUR',
    ];

    /**
     * Safety net: values of inactive (hidden) fields are never stored, whatever the steps delivered.
     */
    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $rules = (array) $this->config()->get('field_conditions');
        $data = [];
        foreach (FieldConditions::referencedFields($rules) as $field) {
            $data[$field] = $this->getField($field);
        }

        $cleaned = FieldConditions::apply($data, $rules);
        foreach ($cleaned as $field => $value) {
            if ($value === null && $data[$field] !== null) {
                $this->setField($field, null);
            }
        }

        $number = self::normalizeInsuranceNumber((string) $this->getField('InsuranceNumber'));
        $this->setField('InsuranceNumber', $number !== '' ? $number : null);
    }

    /** "12 150385 m 123" -> "12150385M123". */
    public static function normalizeInsuranceNumber(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-\/.]+/', '', $value));
    }

    // ---------------------------------------------------------------------------------------------
    // Mapping helpers (signature fixed by BaseSubmission::getArrayDataForPDF: method($field, $arg))
    // ---------------------------------------------------------------------------------------------

    /** Checkbox group: stored value -> on-state of $pdfField (via $state_map). */
    public function StateValue(string $fieldName, string $pdfField): string
    {
        $map = (array) ($this->config()->get('state_map')[$pdfField] ?? []);
        $value = $this->getField($fieldName);

        if (is_bool($value)) {
            $value = (int) $value;
        }

        return (string) ($map[(string) ($value ?? '')] ?? '');
    }

    /** Comb field "Versicherungsnummer" (MaxLen $length): no spaces, upper case. */
    public function InsuranceNumberCompact(string $fieldName, string $length): string
    {
        return substr(self::normalizeInsuranceNumber($this->text($fieldName)), 0, (int) $length);
    }

    /** "Wohnort, Land": "Sarajevo, Bosnien und Herzegowina" (country only when given). */
    public function PlaceLine(string $fieldName, string $countryField): string
    {
        return $this->join(', ', [$this->text($fieldName), $this->text($countryField)]);
    }

    /** "Rentenversicherungsträger, Versicherungsnummer". */
    public function InsurerLine(string $fieldName, string $numberField): string
    {
        return $this->join(', ', [$this->text($fieldName), $this->text($numberField)]);
    }

    /** "Familienangehöriger (Name, Vorname, Geburtsdatum, Verwandtschaftsverhältnis)". */
    public function FamilyMemberLine(string $fieldName, string $unused = ''): string
    {
        $birthDate = (string) ($this->getField('FamilyMemberBirthDate') ?? '');

        return $this->join(', ', [
            $this->text($fieldName),
            $this->text('FamilyMemberFirstName'),
            $birthDate !== '' ? date('d.m.Y', (int) strtotime($birthDate)) : '',
            self::RELATIONSHIP[(string) $this->getField('FamilyMemberRelationship')] ?? '',
        ]);
    }

    /** "1.234,56" (+ " EUR" while it fits the 10-character field); empty when not set. */
    public function Money(string $fieldName, string $currency): string
    {
        $value = $this->getField($fieldName);
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '';
        }

        $amount = number_format((float) $value, 2, ',', '.');
        $withCurrency = $amount . ' ' . $currency;

        return mb_strlen($withCurrency) <= self::INCOME_MAX_LENGTH ? $withCurrency : $amount;
    }

    /** "Ort, Datum": place + submission date. */
    public function PlaceDate(string $fieldName, string $format): string
    {
        $created = (string) ($this->getField('Created') ?? '');
        $timestamp = $created !== '' ? (int) strtotime($created) : time();

        return $this->join(', ', [$this->text($fieldName), date($format, $timestamp)]);
    }

    private function text(string $fieldName): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) ($this->getField($fieldName) ?? '')));
    }

    /**
     * @param array<int, string> $parts
     */
    private function join(string $glue, array $parts): string
    {
        return implode($glue, array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    // ---------------------------------------------------------------------------------------------
    // PDF generation
    // ---------------------------------------------------------------------------------------------

    public function generateFilledPDF(): Pdf
    {
        $basePdfPath = getcwd() . '/assets/' . $this->FormSubmission()->getBasePDFLink('PDFFile');
        $filled = $this->fillPdf($basePdfPath);

        $signature = trim((string) $this->Signature);
        if ($signature === '') {
            return new Pdf($filled);
        }

        $tmpSignature = $this->writeSignatureImage($signature);

        try {
            $outputPath = $this->stampSignature($filled, $tmpSignature);
        } finally {
            @unlink($tmpSignature);
            @unlink($filled);
        }

        return new Pdf($outputPath);
    }

    /**
     * Fills + flattens into a temp file with the replacement font; pdftk builds without "replacement_font"
     * (pdftk < 3.0) fall back to a fill with ASCII-transliterated values.
     */
    private function fillPdf(string $basePdfPath): string
    {
        $target = sys_get_temp_dir() . '/r0820_filled_' . uniqid('', true) . '.pdf';
        $font = $this->replacementFontPath();

        $pdf = $this->newFilledPdf($basePdfPath, $font);
        if ($pdf->saveAs($target)) {
            return $target;
        }

        $error = $pdf->getError();
        if ($font !== null) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                'R0820: pdftk fill with replacement_font failed, retrying with ASCII transliteration: ' . $error
            );
            $pdf = $this->newFilledPdf($basePdfPath, null);
            if ($pdf->saveAs($target)) {
                return $target;
            }
            $error = $pdf->getError();
        }

        throw new RuntimeException('pdftk error: ' . $error);
    }

    private function newFilledPdf(string $basePdfPath, ?string $font): Pdf
    {
        $data = $this->getArrayDataForPDF();
        if ($font === null) {
            // checkbox states keep their exact names (umlauts, trailing spaces)
            $states = array_keys((array) $this->config()->get('state_map'));
            foreach ($data as $pdfField => $value) {
                if (is_string($value) && !in_array((string) $pdfField, $states, true)) {
                    $data[$pdfField] = $this->toAscii($value);
                }
            }
        }

        $pdf = new Pdf($basePdfPath);
        $pdf->fillForm($data)
            ->needAppearances();

        if ($font !== null) {
            $pdf->replacementFont($font);
        }

        return $pdf->flatten();
    }

    private function toAscii(string $value): string
    {
        $value = strtr($value, self::TRANSLITERATION);

        if (class_exists(\Normalizer::class)) {
            $value = (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($value, \Normalizer::FORM_D));
        }

        return (string) preg_replace('/[^\x20-\x7E]/', '', $value);
    }

    /** Config replacement_font: absolute path or relative to the module root; empty disables it. */
    private function replacementFontPath(): ?string
    {
        $font = trim((string) $this->config()->get('replacement_font'));
        if ($font === '') {
            return null;
        }

        $path = str_starts_with($font, '/') ? $font : dirname(__DIR__, 2) . '/' . $font;

        return is_file($path) ? $path : null;
    }

    /**
     * Places the signature on every page listed in $signature_positions
     * (negative page = counted from the end).
     */
    private function stampSignature(string $sourcePdf, string $signaturePng): string
    {
        $holder = $this->getMyHolderPage();
        $overrideX = (float) ($holder?->SignaturePositionX ?: 0);
        $overrideY = (float) ($holder?->SignaturePositionY ?: 0);

        $fpdi = new Fpdi();
        $fpdi->SetAutoPageBreak(false);
        $pageCount = $fpdi->setSourceFile($sourcePdf);

        // page number => positions to stamp
        $stamps = [];
        foreach ((array) $this->config()->get('signature_positions') as $key => $position) {
            if (!is_array($position) || !isset($position['page'])) {
                continue;
            }

            $page = (int) $position['page'];
            $page = $page < 0 ? $pageCount + $page + 1 : $page;
            if ($page < 1 || $page > $pageCount) {
                continue;
            }

            if (in_array($key, self::CMS_POSITION_KEYS, true)) {
                $position['x'] = $overrideX > 0 ? $overrideX : $position['x'];
                $position['y'] = $overrideY > 0 ? $overrideY : $position['y'];
            }

            $stamps[$page][] = $position;
        }

        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($template);

            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($template, 0, 0, $size['width'], $size['height']);

            foreach ($stamps[$i] ?? [] as $position) {
                $fpdi->Image($signaturePng, (float) $position['x'], (float) $position['y'], (float) $position['width'], 0, 'PNG');
            }
        }

        $outputPath = sys_get_temp_dir() . '/r0820_signed_' . uniqid('', true) . '.pdf';
        $fpdi->Output('F', $outputPath);

        return $outputPath;
    }

    /**
     * jSignature delivers a PNG data URI; base64 SVG is supported as fallback (needs Imagick).
     */
    private function writeSignatureImage(string $signature): string
    {
        $path = sys_get_temp_dir() . '/r0820_sig_' . uniqid('', true) . '.png';

        if (preg_match('#^data:image/png;base64,(.+)$#i', $signature, $matches)) {
            $png = base64_decode($matches[1], true);
            if ($png === false || file_put_contents($path, $png) === false) {
                throw new RuntimeException('Invalid PNG signature payload.');
            }

            return $path;
        }

        $svgBase64 = preg_match('#^data:image/svg\+xml(?:;charset=[^;]+)?;base64,(.+)$#i', $signature, $matches)
            ? $matches[1]
            : $signature;
        $svg = base64_decode($svgBase64, true);
        if ($svg === false) {
            throw new RuntimeException('Invalid signature payload.');
        }

        $imagick = new \Imagick();
        $imagick->readImageBlob($svg);
        $imagick->setBackgroundColor(new \ImagickPixel('white'));
        $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $imagick->setImageFormat('png');
        $imagick->writeImage($path);
        $imagick->clear();

        return $path;
    }
}
