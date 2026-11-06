<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Ziffer 3 "Weiterer Rentenbezug" (3.1, 3.2).
 */
class Form_Step3 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 3;

    private static $next_steps = Form_Step4::class;

    private static array $conditional_required = [
        'OtherPensionInsurer',
        'OtherPensionStatus',
        'OtherPensionSince',
        'OtherPensionAppliedOn',
        'OtherPensionSubsidy',
    ];

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Weiterer Rentenbezug'), 4),
            LiteralField::create('OtherPensionNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.OtherPensionNotice', 'Beziehen Sie mehrere Renten der gesetzlichen Rentenversicherung (zum Beispiel Rente wegen Alters und Hinterbliebenenrente), wird der Zuschuss aus der Summe dieser Renten berechnet und zu einer dieser Renten gezahlt.') . '</div>'),
            DropdownField::create('OtherPension', _t(__CLASS__ . '.OtherPension', '3.1 Beziehen Sie eine weitere Rente aus der deutschen gesetzlichen Rentenversicherung oder ist eine weitere Rente beantragt?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.OtherPension.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.OtherPension.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                TextField::create('OtherPensionInsurer', _t(__CLASS__ . '.OtherPensionInsurer', 'Rentenversicherungsträger'))
                    ->setMaxLength(50),
                TextField::create('OtherPensionNumber', _t(__CLASS__ . '.OtherPensionNumber', 'Versicherungsnummer der weiteren Rente (optional)'))
                    ->setMaxLength(20),
                DropdownField::create('OtherPensionStatus', _t(__CLASS__ . '.OtherPensionStatus', 'Die weitere Rente wird'))
                    ->setSource([
                        'receiving' => _t(__CLASS__ . '.OtherPensionStatus.receiving', 'bereits bezogen'),
                        'applied' => _t(__CLASS__ . '.OtherPensionStatus.applied', 'ist beantragt'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                Wrapper::create(
                    DateField::create('OtherPensionSince', _t(__CLASS__ . '.OtherPensionSince', 'seit')),
                )
                    ->displayIf('OtherPensionStatus')->isEqualTo('receiving')->end(),
                Wrapper::create(
                    DateField::create('OtherPensionAppliedOn', _t(__CLASS__ . '.OtherPensionAppliedOn', 'beantragt am')),
                )
                    ->displayIf('OtherPensionStatus')->isEqualTo('applied')->end(),
                DropdownField::create('OtherPensionSubsidy', _t(__CLASS__ . '.OtherPensionSubsidy', '3.2 Wird zu der weiteren Rente bereits ein Zuschuss zur Krankenversicherung gezahlt oder ist dieser beantragt?'))
                    ->setSource([
                        'no' => _t(__CLASS__ . '.OtherPensionSubsidy.no', 'nein'),
                        'yes' => _t(__CLASS__ . '.OtherPensionSubsidy.yes', 'ja'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            )
                ->displayIf('OtherPension')->isEqualTo('yes')->end(),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'OtherPension',
        ]);
    }
}
