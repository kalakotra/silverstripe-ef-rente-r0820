<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use app\forms\basemodel\BaseMultiForm;
use app\models\FormSubmission;
use Kalakotra\EFormRenteR0820\Model\Submission;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\SingleSelectField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Antrag auf Zuschuss zur Krankenversicherung (§ 106 SGB VI), Deutsche Rentenversicherung Vordruck R0820.
 */
class Form extends BaseMultiForm
{
    private static $start_step = Form_Step1::class;

    public static function getFormName(): string
    {
        return 'Antrag auf Zuschuss zur Krankenversicherung (R0820)';
    }

    public function finish($data, $form)
    {
        if (parent::finish($data, $form) === false) {
            return false;
        }

        $allData = $this->mergeStepData($this->getActiveSteps());
        if ($allData === []) {
            return $this->controller->redirectBack();
        }

        try {
            $submission = Submission::create();
            $submission->update($allData);
            $submission->write();

            $formSubmission = FormSubmission::create();
            $formSubmission->Email = $submission->ConfirmationEmail;
            $formSubmission->SubmissionID = $submission->ID;
            $formSubmission->Status = 0; // pending
            $formSubmission->ParentPageID = $this->controller->data()->ID;
            $formSubmission->write();

            $this->controller->getRequest()->getSession()->set('FormSubmissionID', $formSubmission->ID);
        } catch (\Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                'R0820 submission failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $form->sessionError(_t(__CLASS__ . '.SAVE_ERROR', 'Es gab ein Problem beim Speichern Ihrer Daten. Bitte versuchen Sie es erneut.'));

            return $this->controller->redirectBack();
        }

        return $this->controller->redirect($this->controller->Link('/finished'));
    }

    /**
     * Preview only shows steps on the currently active path
     * (e.g. Ziffer 4.4 disappears when 4.2/4.3 were answered "nein" later).
     */
    public function PreviewSteps(): ArrayList
    {
        $activeClasses = array_map(
            static fn (MultiFormStep $step): string => (string) $step->ClassName,
            $this->getActiveSteps()
        );

        return parent::PreviewSteps()->filterByCallback(
            static fn ($item): bool => in_array((string) $item->ClassName, $activeClasses, true)
        );
    }

    /**
     * Labels come from the step's own field titles (already translated for the current locale,
     * German default otherwise), so the preview shows exactly what the step showed.
     */
    public function resolveFieldLabel(string $stepClass, string $fieldKey): string
    {
        return $this->stepMeta($stepClass)['titles'][$fieldKey] ?? parent::resolveFieldLabel($stepClass, $fieldKey);
    }

    public function resolveStepTitle(MultiFormStep $step): string
    {
        return $this->stepMeta((string) $step->ClassName, $step)['titles']['IntroText']
            ?? parent::resolveStepTitle($step);
    }

    /**
     * Type-aware preview values based on Submission::$db (the base implementation detects dates by
     * regex-scanning step sources for "DateField::create('Name'"); Decimal values in German notation.
     */
    public function formatPreviewValue(string $stepClass, string $key, $value, ?MultiFormStep $step = null): string
    {
        $type = Submission::config()->get('db')[$key] ?? null;

        if ($type === 'Date' && is_string($value) && $value !== '') {
            return $this->DateFormatting($value);
        }

        if (is_string($type) && str_starts_with($type, 'Decimal') && is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.') . ' €';
        }

        if ($type === 'Boolean') {
            if ($value === null || $value === '' || !$value) {
                return ''; // unchecked or cleared because hidden -> skipped in preview
            }

            return _t(__CLASS__ . '.BOOLEAN_YES', 'Ja');
        }

        // option label straight from the field source: translated if a lang file exists,
        // German default otherwise (base only resolves options that exist in a lang file)
        if (is_scalar($value) && $value !== '') {
            $label = $this->stepMeta($stepClass, $step)['options'][$key][(string) $value] ?? null;
            if ($label !== null) {
                return $label;
            }
        }

        return parent::formatPreviewValue($stepClass, $key, $value, $step);
    }

    /** @var array<string, array{titles: array<string, string>, options: array<string, array<string, string>>}> */
    private array $stepMetaCache = [];

    /**
     * Field titles and dropdown sources of a step class.
     *
     * @return array{titles: array<string, string>, options: array<string, array<string, string>>}
     */
    private function stepMeta(string $stepClass, ?MultiFormStep $step = null): array
    {
        if (isset($this->stepMetaCache[$stepClass])) {
            return $this->stepMetaCache[$stepClass];
        }

        $meta = ['titles' => [], 'options' => []];
        $instance = $step ?? (is_a($stepClass, MultiFormStep::class, true) ? $stepClass::singleton() : null);

        if ($instance) {
            foreach ($instance->getFields()->flattenFields() as $field) {
                if (!$field instanceof FormField) {
                    continue;
                }

                $title = trim(strip_tags((string) $field->Title()));
                if ($title !== '') {
                    $meta['titles'][$field->getName()] = $title;
                }

                if ($field instanceof SingleSelectField) {
                    $meta['options'][$field->getName()] = array_map('strval', $field->getSource());
                }
            }
        }

        return $this->stepMetaCache[$stepClass] = $meta;
    }

    /**
     * Walks the step chain from the start step using each step's getNextStep(),
     * so branches the user abandoned (but which still exist in the session) are excluded.
     *
     * @return array<int, MultiFormStep>
     */
    public function getActiveSteps(): array
    {
        $stepsByClass = [];
        $steps = MultiFormStep::get()->filter('SessionID', $this->getMultiFormSession()->ID);

        foreach ($steps as $step) {
            $step->setForm($this);
            $stepsByClass[$step->ClassName] = $step;
        }

        $active = [];
        $class = $this->config()->get('start_step');

        while (is_string($class) && isset($stepsByClass[$class]) && !isset($active[$class])) {
            $step = $stepsByClass[$class];
            $active[$class] = $step;

            if ($step->isFinalStep()) {
                break;
            }

            $class = $step->getNextStep();
        }

        return array_values($active);
    }
}
