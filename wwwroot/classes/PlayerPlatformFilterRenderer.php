<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerPlatformFilterOptions.php';
require_once __DIR__ . '/Html.php';

final readonly class PlayerPlatformFilterRenderer
{
    public function __construct(private string $buttonLabel = 'Filter')
    {
    }

    #[\NoDiscard]
    public static function createDefault(): self
    {
        return new self('Filter');
    }

    /**
     * @param array<string, string> $hiddenInputs
     */
    #[\NoDiscard]
    public function render(PlayerPlatformFilterOptions $options, array $hiddenInputs = []): string
    {
        $dropdownControls = $this->renderDropdownControls($options);
        $hiddenInputsHtml = $this->renderHiddenInputs($hiddenInputs);

        return <<<HTML
<form method="get">
    {$hiddenInputsHtml}
    <div class="input-group d-flex justify-content-end">
        {$dropdownControls}
    </div>
</form>
HTML;
    }

    #[\NoDiscard]
    public function renderDropdownControls(PlayerPlatformFilterOptions $options): string
    {
        $buttonLabel = Html::escape($this->buttonLabel);
        $optionItems = $this->renderOptionItems($options);

        return <<<HTML
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            {$buttonLabel}
        </button>
        <ul class="dropdown-menu p-2">
{$optionItems}
        </ul>
HTML;
    }

    #[\NoDiscard]
    public function renderOptionItems(PlayerPlatformFilterOptions $options): string
    {
        return $options->getOptions()
            |> (fn (array $optionList): array => array_map($this->renderOption(...), $optionList))
            |> (fn (array $optionItems): string => implode(PHP_EOL, $optionItems));
    }

    private function renderOption(PlayerPlatformFilterOption $option): string
    {
        $inputId = Html::escape($option->getInputId());
        $inputName = Html::escape($option->getInputName());
        $label = Html::escape($option->getLabel());
        $checkedAttribute = $option->isSelected() ? ' checked' : '';

        return <<<HTML
            <li>
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"{$checkedAttribute}
                        value="true"
                        onChange="this.form.submit()"
                        id="{$inputId}"
                        name="{$inputName}"
                    >
                    <label class="form-check-label" for="{$inputId}">
                        {$label}
                    </label>
                </div>
            </li>
HTML;
    }

    /**
     * @param array<string, string> $hiddenInputs
     */
    private function renderHiddenInputs(array $hiddenInputs): string
    {
        $inputs = [];

        foreach ($hiddenInputs as $name => $value) {
            $inputs[] = sprintf(
                '<input type="hidden" name="%s" value="%s">',
                Html::escape($name),
                Html::escape($value)
            );
        }

        return implode(PHP_EOL, $inputs);
    }
}
