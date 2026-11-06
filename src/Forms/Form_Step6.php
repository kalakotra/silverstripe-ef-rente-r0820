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
 * Ziffer 4.5 "Versicherungspflicht" + Ziffer 5 "Dokumentenzugang für sehbehinderte Menschen".
 */
class Form_Step6 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 6;

    private static $next_steps = Form_Step7::class;

    private static array $conditional_required = [
        'CompulsoryInsurer',
        'CompulsoryReason',
    ];

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Versicherungspflicht und Dokumentenzugang'), 4),
            DropdownField::create('CompulsoryInsurance', _t(__CLASS__ . '.CompulsoryInsurance', '4.5 Besteht oder bestand in der Zeit, für die Sie einen Zuschuss beantragen, Versicherungspflicht in einer deutschen oder ausländischen gesetzlichen Krankenversicherung?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.CompulsoryInsurance.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.CompulsoryInsurance.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --')
                ->setDescription(_t(__CLASS__ . '.CompulsoryInsuranceDescription', 'Der Zuschuss wird nicht gezahlt, solange Sie in einer deutschen oder ausländischen gesetzlichen Krankenversicherung pflichtversichert sind. Bitte auch angeben, wenn Sie zu Lasten eines anderen EU-Staates, Islands, Liechtensteins, Norwegens, der Schweiz oder des Vereinigten Königreichs bei einer deutschen Krankenkasse als Leistungsberechtigter eingeschrieben sind.')),
            Wrapper::create(
                TextField::create('CompulsoryInsurer', _t(__CLASS__ . '.CompulsoryInsurer', 'Name und Anschrift der Krankenkasse oder des Gesundheitsdienstes'))
                    ->setMaxLength(80),
                TextField::create('CompulsoryReason', _t(__CLASS__ . '.CompulsoryReason', 'Grund der Versicherungspflicht'))
                    ->setMaxLength(80)
                    ->setDescription(_t(__CLASS__ . '.CompulsoryReasonDescription', 'Zum Beispiel Beschäftigungsverhältnis, Versicherungspflicht als Arbeitsloser, Einwohnerkrankenversicherung.')),
            )
                ->displayIf('CompulsoryInsurance')->isEqualTo('yes')->end(),

            HeaderField::create('AccessibilityHeader', _t(__CLASS__ . '.AccessibilityHeader', 'Dokumentenzugang für sehbehinderte Menschen'), 5),
            LiteralField::create('AccessibilityNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.AccessibilityNotice', 'Menschen mit einer Behinderung (zum Beispiel blinde oder sehbehinderte Menschen) haben Anspruch darauf, Dokumente zusätzlich in einer für sie wahrnehmbaren Form zu erhalten.') . '</div>'),
            DropdownField::create('AccessibleFormat', _t(__CLASS__ . '.AccessibleFormat', 'Dokumente zusätzlich erhalten (optional)'))
                ->setSource([
                    'large_print' => _t(__CLASS__ . '.AccessibleFormat.large_print', 'als Großdruck'),
                    'braille_short' => _t(__CLASS__ . '.AccessibleFormat.braille_short', 'in Braille (Kurzschrift)'),
                    'braille_full' => _t(__CLASS__ . '.AccessibleFormat.braille_full', 'in Braille (Vollschrift)'),
                    'cd' => _t(__CLASS__ . '.AccessibleFormat.cd', 'als CD (Schriftdatei / Textdatei im ".doc"-Format)'),
                    'daisy' => _t(__CLASS__ . '.AccessibleFormat.daisy', 'als Hörmedium (CD-DAISY Format)'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.AccessibleFormatNone', 'nicht erforderlich') . ' --'),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'CompulsoryInsurance',
        ]);
    }
}
