<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use Kalakotra\EFormRenteR0820\Model\FieldConditions;
use Kalakotra\EFormRenteR0820\Model\Submission;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Ziffer 4.1 - 4.3 "Bestehender Krankenversicherungsschutz".
 * Printed flow: 4.1 ja -> 4.5; 4.2 ja -> 4.4; 4.2 nein -> 4.3; 4.3 nein -> 4.5; 4.3 ja -> 4.4.
 */
class Form_Step4 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 4;

    private static array $conditional_required = [
        'PrivateInsurance',
        'R0821Status',
        'ForeignInsurance',
        'R0822Status',
    ];

    /**
     * Ziffer 4.4 (Step 5) only after "ja" in 4.2 or 4.3; otherwise straight to 4.5 (Step 6).
     */
    public function getNextStep()
    {
        // own step data: read directly, the cross-step cache could be stale right after saveData()
        $data = (array) $this->loadData();

        foreach (Submission::FAMILY_STEP_GATE as $conditions) {
            if (FieldConditions::matches($data, $conditions)) {
                return Form_Step5::class;
            }
        }

        return Form_Step6::class;
    }

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Bestehender Krankenversicherungsschutz'), 4),
            DropdownField::create('StatutoryVoluntary', _t(__CLASS__ . '.StatutoryVoluntary', '4.1 Sind Sie freiwillig bei einer deutschen gesetzlichen Krankenkasse (AOK, Ersatzkasse, Betriebskrankenkasse, Innungskrankenkasse, Knappschaft oder landwirtschaftliche Krankenkasse) versichert?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.StatutoryVoluntary.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.StatutoryVoluntary.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --')
                ->setDescription(_t(__CLASS__ . '.StatutoryVoluntaryDescription', 'Ihre Krankenkasse übermittelt die erforderlichen Angaben dann selbst; eine Mitgliedsbescheinigung wird nicht benötigt.')),
            Wrapper::create(
                DropdownField::create('PrivateInsurance', _t(__CLASS__ . '.PrivateInsurance', '4.2 Sind Sie bei einem deutschen privaten Krankenversicherungsunternehmen versichert beziehungsweise Mitglied der Krankenversorgung der Bundesbahnbeamten (KVB), der Postbeamtenkrankenkasse (PBeaKK) oder einer anerkannten Solidargemeinschaft?'))
                    ->setSource([
                        'no' => _t(__CLASS__ . '.PrivateInsurance.no', 'nein'),
                        'yes' => _t(__CLASS__ . '.PrivateInsurance.yes', 'ja'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                Wrapper::create(
                    LiteralField::create('R0821Notice', '<div class="alert alert-warning">' . _t(__CLASS__ . '.R0821Notice', 'Bitte lassen Sie von Ihrem Versicherungsunternehmen, der KVB, der PBeaKK beziehungsweise Ihrer Solidargemeinschaft den Vordruck R0821 ausfüllen und bestätigen.') . '</div>'),
                    DropdownField::create('R0821Status', _t(__CLASS__ . '.R0821Status', 'Vordruck R0821'))
                        ->setSource([
                            'attached' => _t(__CLASS__ . '.R0821Status.attached', 'ist beigefügt'),
                            'later' => _t(__CLASS__ . '.R0821Status.later', 'wird nachgereicht'),
                            'forwarded' => _t(__CLASS__ . '.R0821Status.forwarded', 'wurde weitergeleitet'),
                        ])
                        ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                )
                    ->displayIf('PrivateInsurance')->isEqualTo('yes')->end(),
                Wrapper::create(
                    DropdownField::create('ForeignInsurance', _t(__CLASS__ . '.ForeignInsurance', '4.3 Sind Sie freiwillig bei einer ausländischen gesetzlichen Krankenversicherung oder bei einem privaten Krankenversicherungsunternehmen versichert, die oder das der Aufsicht eines Mitgliedstaates der EU (Vereinigtes Königreich nur bis 31.12.2020), Islands, Liechtensteins, Norwegens oder der Schweiz unterliegt?'))
                        ->setSource([
                            'no' => _t(__CLASS__ . '.ForeignInsurance.no', 'nein'),
                            'yes' => _t(__CLASS__ . '.ForeignInsurance.yes', 'ja'),
                        ])
                        ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                    Wrapper::create(
                        LiteralField::create('R0822Notice', '<div class="alert alert-warning">' . _t(__CLASS__ . '.R0822Notice', 'Bitte lassen Sie von Ihrem Versicherungsunternehmen den Vordruck R0822 ausfüllen und bestätigen.') . '</div>'),
                        DropdownField::create('R0822Status', _t(__CLASS__ . '.R0822Status', 'Vordruck R0822'))
                            ->setSource([
                                'attached' => _t(__CLASS__ . '.R0822Status.attached', 'ist beigefügt'),
                                'later' => _t(__CLASS__ . '.R0822Status.later', 'wird nachgereicht'),
                                'forwarded' => _t(__CLASS__ . '.R0822Status.forwarded', 'wurde weitergeleitet'),
                            ])
                            ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                    )
                        ->displayIf('ForeignInsurance')->isEqualTo('yes')->end(),
                )
                    ->displayIf('PrivateInsurance')->isEqualTo('no')->end(),
            )
                ->displayIf('StatutoryVoluntary')->isEqualTo('no')->end(),
            LiteralField::create('MultipleInsurersNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.MultipleInsurersNotice', 'Sind Sie bei mehreren Versicherungsunternehmen krankenversichert, muss jedes Unternehmen jeweils einen Vordruck R0821 beziehungsweise R0822 ausfüllen.') . '</div>'),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'StatutoryVoluntary',
        ]);
    }
}
