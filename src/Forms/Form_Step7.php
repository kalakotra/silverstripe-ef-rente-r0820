<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Ziffer 6 "Erklärung der Antragstellerin / des Antragstellers" + Ort (date = submission date).
 */
class Form_Step7 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 7;

    private static $next_steps = Form_Preview::class;

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Erklärung der Antragstellerin / des Antragstellers'), 4),
            LiteralField::create('DeclarationText', '<div class="alert alert-secondary">'
                . '<p>' . _t(__CLASS__ . '.DeclarationIntro', 'Ich versichere, dass ich sämtliche Angaben in diesem Vordruck nach bestem Wissen gemacht habe. Mir ist bekannt, dass wissentlich falsche Angaben zu einer strafrechtlichen Verfolgung führen können.') . '</p>'
                . '<p>' . _t(__CLASS__ . '.DeclarationDuties', 'Ich verpflichte mich, dem Rentenversicherungsträger die nachfolgenden Sachverhalte unverzüglich anzuzeigen:') . '</p><ul>'
                . '<li>' . _t(__CLASS__ . '.DutyA', 'a) die Beendigung oder das Ruhen der Versicherung, zu der der Zuschuss gezahlt wird (zum Beispiel bei Anspruch auf Krankenversorgung nach dem Bundesversorgungsgesetz, dem Bundesentschädigungsgesetz oder bei Auslandsaufenthalt),') . '</li>'
                . '<li>' . _t(__CLASS__ . '.DutyB', 'b) jede Veränderung der Beitragshöhe oder Prämienhöhe zur privaten Krankenversicherung,') . '</li>'
                . '<li>' . _t(__CLASS__ . '.DutyC', 'c) den Beginn einer Versicherungspflicht in der deutschen gesetzlichen Krankenversicherung (zum Beispiel durch die Aufnahme einer Beschäftigung, durch den Antrag auf eine weitere Rente, durch den Bezug von Übergangsgeld, Arbeitslosengeld oder Grundsicherungsgeld / Bürgergeld),') . '</li>'
                . '<li>' . _t(__CLASS__ . '.DutyD', 'd) den Beginn einer Versicherungspflicht in der ausländischen gesetzlichen Krankenversicherung (zum Beispiel bei Wohnsitzverlegung ins Ausland, bei Aufnahme einer ausländischen Beschäftigung oder aufgrund des Beginns einer ausländischen Rente),') . '</li>'
                . '<li>' . _t(__CLASS__ . '.DutyE', 'e) die "Einschreibung" als Leistungsberechtigter bei einer deutschen gesetzlichen Krankenkasse aufgrund der Rente eines anderen Staates der EU, Islands, Liechtensteins, Norwegens, der Schweiz oder des Vereinigten Königreichs,') . '</li>'
                . '<li>' . _t(__CLASS__ . '.DutyF', 'f) jede Änderung in den Verhältnissen des Familienangehörigen, dessen Beitragsaufwendungen bei der Zuschusszahlung berücksichtigt werden (eigene Rentenberechtigung, Eintritt von Versicherungspflicht, Änderungen der Beitragsaufwendungen oder des Gesamteinkommens) und') . '</li>'
                . '<li>' . _t(__CLASS__ . '.DutyG', 'g) den Verzug in einen anderen Staat.') . '</li>'
                . '</ul></div>'),
            CheckboxField::create('DeclarationAccepted', _t(__CLASS__ . '.DeclarationAccepted', 'Ich habe die Erklärung gelesen und bestätige sie.')),
            LiteralField::create('BeihilfeNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.BeihilfeNotice', 'Haben Sie Anspruch auf Beihilfe, fragen Sie bitte Ihre Beihilfestelle, ob sich der Zuschuss auf Ihren Beihilfeanspruch auswirkt. Sie können auf den Zuschuss oder Teile davon mit Wirkung für die Zukunft verzichten.') . '</div>'),
            TextField::create('Place', _t(__CLASS__ . '.Place', 'Ort'))
                ->setMaxLength(25)
                ->setDescription(_t(__CLASS__ . '.PlaceDescription', 'Das Datum wird beim Erstellen des PDF automatisch eingetragen. Bei Waisen unterschreibt der gesetzliche Vertreter.')),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'DeclarationAccepted',
            'Place',
        ]);
    }
}
