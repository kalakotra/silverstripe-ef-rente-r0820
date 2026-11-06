<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Ziffer 2 "Antragstellung durch andere Personen".
 */
class Form_Step2 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 2;

    private static $next_steps = Form_Step3::class;

    private static array $conditional_required = [
        'RepresentativeName',
        'RepresentativeCapacity',
        'RepresentativeStreet',
        'RepresentativeCity',
    ];

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Antragstellung durch andere Personen'), 4),
            DropdownField::create('FiledByRepresentative', _t(__CLASS__ . '.FiledByRepresentative', 'Wird der Antrag in Vertretung gestellt?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.FiledByRepresentative.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.FiledByRepresentative.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                LiteralField::create('RepresentativeNotice', '<div class="alert alert-warning">' . _t(__CLASS__ . '.RepresentativeNotice', 'Bitte fügen Sie dem Antrag die Vollmacht oder den Beschluss des Gerichts bei.') . '</div>'),
                TextField::create('RepresentativeName', _t(__CLASS__ . '.RepresentativeName', 'Name, Vorname / Dienststelle (gegebenenfalls Aktenzeichen)'))
                    ->setMaxLength(90),
                DropdownField::create('RepresentativeCapacity', _t(__CLASS__ . '.RepresentativeCapacity', 'in der Eigenschaft als'))
                    ->setSource([
                        'legal' => _t(__CLASS__ . '.RepresentativeCapacity.legal', 'gesetzlicher Vertreter'),
                        'guardian' => _t(__CLASS__ . '.RepresentativeCapacity.guardian', 'Vormund'),
                        'custodian' => _t(__CLASS__ . '.RepresentativeCapacity.custodian', 'Betreuer'),
                        'authorized' => _t(__CLASS__ . '.RepresentativeCapacity.authorized', 'Bevollmächtigter'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                TextField::create('RepresentativeStreet', _t(__CLASS__ . '.RepresentativeStreet', 'Straße, Hausnummer'))
                    ->setMaxLength(55),
                TextField::create('RepresentativeAddressAddition', _t(__CLASS__ . '.RepresentativeAddressAddition', 'Adresszusatz (optional)'))
                    ->setMaxLength(55),
                TextField::create('RepresentativePostalCode', _t(__CLASS__ . '.RepresentativePostalCode', 'Postleitzahl'))
                    ->setMaxLength(5),
                TextField::create('RepresentativeCity', _t(__CLASS__ . '.RepresentativeCity', 'Wohnort'))
                    ->setMaxLength(45),
                TextField::create('RepresentativeCountry', _t(__CLASS__ . '.RepresentativeCountry', 'Land (nur bei Wohnsitz im Ausland)'))
                    ->setMaxLength(28),
                TextField::create('RepresentativePhone', _t(__CLASS__ . '.RepresentativePhone', 'Telefon (optional)'))
                    ->setMaxLength(30),
                TextField::create('RepresentativeFax', _t(__CLASS__ . '.RepresentativeFax', 'Telefax (optional)'))
                    ->setMaxLength(30),
            )
                ->displayIf('FiledByRepresentative')->isEqualTo('yes')->end(),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'FiledByRepresentative',
        ]);
    }
}
