<?php

declare(strict_types=1);

namespace Kalakotra\EFormRenteR0820\Forms;

use Kalakotra\EFormRenteR0820\Model\FieldConditions;
use Kalakotra\EFormRenteR0820\Model\Submission;

/**
 * Behaviour shared by all R0820 steps (trait instead of an abstract base class,
 * because DbBuild instantiates every DataObject subclass via singleton()).
 *
 * - saveData(): clears values of fields hidden by display-logic
 * - validateStep(): enforces `conditional_required` fields only while they are visible
 *
 * Dependency rules: Submission::$field_conditions (mirrored by display-logic in getFields()).
 * Using classes may declare: private static array $conditional_required = ['FieldName', ...];
 */
trait StepTrait
{
    /**
     * @param array<string, mixed>|mixed $data
     */
    public function saveData($data)
    {
        if (is_array($data)) {
            $data = FieldConditions::apply($data, self::fieldConditionRules(), true);
        }

        return parent::saveData($data);
    }

    /**
     * @param array<string, mixed>|mixed $data
     */
    public function validateStep($data, $form)
    {
        return $this->validateConditionalRequired($data, $form) && parent::validateStep($data, $form);
    }

    /**
     * Steps overriding validateStep() call this to keep the conditional-required check.
     *
     * @param array<string, mixed>|mixed $data
     */
    protected function validateConditionalRequired($data, $form): bool
    {
        $required = (array) ($this->config()->get('conditional_required') ?? []);
        if ($required === []) {
            return true;
        }

        // Use the form's normalised data, NOT the raw request ($data): unchecked checkboxes are
        // missing from the request, which made their dependent fields look active (and the
        // resulting error was attached to a hidden field -> silent redirect back to the same step).
        $data = $form->getData();
        $rules = self::fieldConditionRules();
        $missing = [];

        foreach ($required as $fieldName) {
            if (!FieldConditions::isActive($fieldName, $data, $rules)) {
                continue;
            }

            $value = $data[$fieldName] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                continue;
            }

            $field = $form->Fields()->dataFieldByName($fieldName);
            $label = $field ? strip_tags((string) $field->Title()) : $fieldName;
            $missing[] = $label;

            $form->sessionFieldError(
                _t(__TRAIT__ . '.REQUIRED', '"{name}" ist erforderlich.', ['name' => $label]),
                $fieldName
            );
        }

        if ($missing !== []) {
            // form-level summary, so a failed validation is never invisible
            $form->sessionMessage(_t(
                __TRAIT__ . '.REQUIRED_SUMMARY',
                'Bitte füllen Sie die folgenden Pflichtfelder aus: {fields}',
                ['fields' => implode(', ', $missing)]
            ));
        }

        return $missing === [];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function fieldConditionRules(): array
    {
        return (array) Submission::config()->get('field_conditions');
    }
}
