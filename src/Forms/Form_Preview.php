<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use app\forms\basemodel\Base_FinalStep;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\FieldType\DBField;

class Form_Preview extends Base_FinalStep
{
    private static $default_sort_order = 8;

    public function getFields(): FieldList
    {
        return FieldList::create(
            EmailField::create('ConfirmationEmail', _t(__CLASS__ . '.ConfirmationEmail', 'Ihre E-Mail-Adresse:')),
            TextareaField::create('Signature', _t('PDFMultiFormPage.Signature', 'Unterschrift'))
                ->setValue('')
                ->setFieldHolderTemplate('SignatureFormField_customholder')
                ->addExtraClass('form-control signature-field'),
            CheckboxField::create('AcceptTerms', DBField::create_field('HTMLText', _t(
                __CLASS__ . '.AcceptTerms',
                'Ich habe die <a href="/de/agb" target="_blank">AGB</a> und die '
                . '<a href="/de/datenschutz" target="_blank">Datenschutzerklärung</a> gelesen und akzeptiert.'
            ))),
            CheckboxField::create('AcceptNoReturn', DBField::create_field('HTMLText', _t(
                __CLASS__ . '.AcceptNoReturn',
                'Ich stimme ausdrücklich zu, dass Sie vor Ablauf der Widerrufsfrist mit der Ausführung des '
                . 'Vertrages beginnen. Mir ist bekannt, dass ich durch diese Zustimmung mit Beginn der '
                . 'Ausführung des Vertrages mein Widerrufsrecht verliere.'
            )))
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'ConfirmationEmail',
            'AcceptTerms',
            'AcceptNoReturn',
        ]);
    }

    /**
     * Signature is validated server-side only: the textarea is hidden (d-none) and filled by jSignature,
     * so an HTML "required" attribute would block the submit without a visible message.
     */
    public function validateStep($data, $form)
    {
        $signature = is_array($data) ? trim((string) ($data['Signature'] ?? '')) : '';

        if (!preg_match('#^data:image/(png|svg\+xml)#i', $signature)) {
            $form->sessionFieldError(
                _t(__CLASS__ . '.SignatureRequired', 'Bitte unterschreiben Sie den Antrag.'),
                'Signature'
            );

            return false;
        }

        return parent::validateStep($data, $form);
    }
}
