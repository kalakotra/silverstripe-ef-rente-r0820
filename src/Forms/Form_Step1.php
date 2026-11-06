<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use Kalakotra\EFormRenteR0820\Model\Submission;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Kopf (Versicherungsnummer, Kennzeichen) + Ziffer 1 "Angaben zur Person".
 */
class Form_Step1 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 1;

    private static $next_steps = Form_Step2::class;

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Angaben zur Person'), 4),
            LiteralField::create('GeneralNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.GeneralNotice', 'Mit diesem Antrag (Vordruck R0820) beantragen Sie bei der Deutschen Rentenversicherung einen Zuschuss zu Ihrer freiwilligen oder privaten Krankenversicherung (§ 106 SGB VI). Bei Hinterbliebenen ist für die Witwe / den Witwer und für jede Waise jeweils ein eigener Antrag zu stellen.') . '</div>'),
            TextField::create('InsuranceNumber', _t(__CLASS__ . '.InsuranceNumber', 'Versicherungsnummer'))
                ->setMaxLength(15)
                ->setDescription(_t(__CLASS__ . '.InsuranceNumberDescription', '12 Zeichen, z. B. 12 150385 M 123. Bei Antrag auf Hinterbliebenenrente: Versicherungsnummer der / des verstorbenen Versicherten.')),
            TextField::create('Kennzeichen', _t(__CLASS__ . '.Kennzeichen', 'Kennzeichen (soweit bekannt)'))
                ->setMaxLength(4),
            TextField::create('LastName', _t(__CLASS__ . '.LastName', 'Name'))
                ->setMaxLength(45),
            TextField::create('FirstName', _t(__CLASS__ . '.FirstName', 'Vorname'))
                ->setMaxLength(45),
            TextField::create('NameSuffix', _t(__CLASS__ . '.NameSuffix', 'Namenszusatz (z. B. Freifrau, Graf) (optional)'))
                ->setMaxLength(28),
            TextField::create('NamePrefix', _t(__CLASS__ . '.NamePrefix', 'Vorsatzworte zum Namen (z. B. von, van, de) (optional)'))
                ->setMaxLength(28),
            TextField::create('Title', _t(__CLASS__ . '.Title', 'Titel (z. B. Prof. Dr. med.) (optional)'))
                ->setMaxLength(28),
            TextField::create('BirthName', _t(__CLASS__ . '.BirthName', 'Geburtsname (optional)'))
                ->setMaxLength(70),
            DateField::create('BirthDate', _t(__CLASS__ . '.BirthDate', 'Geburtsdatum')),
            TextField::create('Street', _t(__CLASS__ . '.Street', 'Straße, Hausnummer'))
                ->setMaxLength(55),
            TextField::create('AddressAddition', _t(__CLASS__ . '.AddressAddition', 'Adresszusatz (optional)'))
                ->setMaxLength(55),
            TextField::create('PostalCode', _t(__CLASS__ . '.PostalCode', 'Postleitzahl'))
                ->setMaxLength(5)
                ->setDescription(_t(__CLASS__ . '.PostalCodeDescription', 'Ausländische Postleitzahlen mit mehr als 5 Zeichen bitte im Feld Wohnort eintragen.')),
            TextField::create('City', _t(__CLASS__ . '.City', 'Wohnort'))
                ->setMaxLength(45),
            TextField::create('Country', _t(__CLASS__ . '.Country', 'Land (nur bei Wohnsitz im Ausland)'))
                ->setMaxLength(28),
            TextField::create('Phone', _t(__CLASS__ . '.Phone', 'Telefon (optional)'))
                ->setMaxLength(30),
            TextField::create('Fax', _t(__CLASS__ . '.Fax', 'Telefax (optional)'))
                ->setMaxLength(30),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'InsuranceNumber',
            'LastName',
            'FirstName',
            'BirthDate',
            'Street',
            'City',
        ]);
    }

    /**
     * Versicherungsnummer: 12 characters (8 digits, 1 letter, 3 digits) after removing spaces.
     */
    public function validateStep($data, $form)
    {
        $number = Submission::normalizeInsuranceNumber((string) ($form->getData()['InsuranceNumber'] ?? ''));

        if ($number !== '' && !preg_match(Submission::INSURANCE_NUMBER_PATTERN, $number)) {
            $message = _t(__CLASS__ . '.InsuranceNumberInvalid', 'Die Versicherungsnummer muss aus 12 Zeichen bestehen (8 Ziffern, 1 Buchstabe, 3 Ziffern).');
            $form->sessionFieldError($message, 'InsuranceNumber');
            $form->sessionMessage($message);

            return false;
        }

        return $this->validateConditionalRequired($data, $form) && parent::validateStep($data, $form);
    }
}
