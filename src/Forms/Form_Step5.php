<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Ziffer 4.4 / 4.4.1 "Beitragsaufwendungen für Familienangehörige" (only after "ja" in 4.2 or 4.3).
 */
class Form_Step5 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 5;

    private static $next_steps = Form_Step6::class;

    private static array $conditional_required = [
        'FamilyMemberLastName',
        'FamilyMemberFirstName',
        'FamilyMemberBirthDate',
        'FamilyMemberRelationship',
        'FamilyMemberIncome',
        'FamilyMemberPensioner',
        'FamilyMemberPensionInsurer',
        'FamilyMemberPensionSince',
    ];

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Beitragsaufwendungen für Familienangehörige'), 4),
            LiteralField::create('FamilyNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.FamilyNotice', 'Beitragsaufwendungen für Ehegatten, Lebenspartner oder Kinder können berücksichtigt werden, wenn der Familienangehörige mit seinem Gesamteinkommen 1/7 der monatlichen Bezugsgröße nicht übersteigt (2026: 565 EUR; bei geringfügiger Beschäftigung 603 EUR), selbst nicht in einer deutschen oder ausländischen gesetzlichen Krankenversicherung pflichtversichert ist und selbst als Rentenbezieher keinen Anspruch auf den Zuschuss zur Krankenversicherung hat.') . '</div>'),
            DropdownField::create('FamilyContributions', _t(__CLASS__ . '.FamilyContributions', '4.4 Sollen Beitragsaufwendungen bei einer privaten Krankenversicherung für Familienangehörige berücksichtigt werden?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.FamilyContributions.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.FamilyContributions.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                LiteralField::create('FamilyFormNotice', '<div class="alert alert-warning">' . _t(__CLASS__ . '.FamilyFormNotice', 'Bitte lassen Sie die Beitragsaufwendungen ebenfalls im Vordruck R0821 / R0822 eintragen.') . '</div>'),
                TextField::create('FamilyMemberLastName', _t(__CLASS__ . '.FamilyMemberLastName', 'Name des Familienangehörigen'))
                    ->setMaxLength(25),
                TextField::create('FamilyMemberFirstName', _t(__CLASS__ . '.FamilyMemberFirstName', 'Vorname'))
                    ->setMaxLength(20),
                DateField::create('FamilyMemberBirthDate', _t(__CLASS__ . '.FamilyMemberBirthDate', 'Geburtsdatum')),
                DropdownField::create('FamilyMemberRelationship', _t(__CLASS__ . '.FamilyMemberRelationship', 'Verwandtschaftsverhältnis'))
                    ->setSource([
                        'spouse' => _t(__CLASS__ . '.FamilyMemberRelationship.spouse', 'Ehegatte / Ehegattin'),
                        'partner' => _t(__CLASS__ . '.FamilyMemberRelationship.partner', 'Lebenspartner / Lebenspartnerin'),
                        'child' => _t(__CLASS__ . '.FamilyMemberRelationship.child', 'Kind'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                NumericField::create('FamilyMemberIncome', _t(__CLASS__ . '.FamilyMemberIncome', 'Höhe des monatlichen Gesamteinkommens des Familienangehörigen (EUR)'))
                    ->setScale(2)
                    ->setDescription(_t(__CLASS__ . '.FamilyMemberIncomeDescription', 'Kein Einkommen: bitte 0 eintragen.')),
                DropdownField::create('FamilyMemberPensioner', _t(__CLASS__ . '.FamilyMemberPensioner', '4.4.1 Ist der Familienangehörige Rentner?'))
                    ->setSource([
                        'no' => _t(__CLASS__ . '.FamilyMemberPensioner.no', 'nein'),
                        'yes' => _t(__CLASS__ . '.FamilyMemberPensioner.yes', 'ja'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                Wrapper::create(
                    TextField::create('FamilyMemberPensionInsurer', _t(__CLASS__ . '.FamilyMemberPensionInsurer', 'Rentenversicherungsträger'))
                        ->setMaxLength(50),
                    TextField::create('FamilyMemberPensionNumber', _t(__CLASS__ . '.FamilyMemberPensionNumber', 'Versicherungsnummer (optional)'))
                        ->setMaxLength(20),
                    DateField::create('FamilyMemberPensionSince', _t(__CLASS__ . '.FamilyMemberPensionSince', 'Rente seit')),
                )
                    ->displayIf('FamilyMemberPensioner')->isEqualTo('yes')->end(),
            )
                ->displayIf('FamilyContributions')->isEqualTo('yes')->end(),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'FamilyContributions',
        ]);
    }
}
