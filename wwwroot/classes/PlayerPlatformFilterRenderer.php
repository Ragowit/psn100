<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerPlatformFilterOptions.php';

final class PlayerPlatformFilterRenderer
{
    private string $buttonLabel;

    public function __construct(string $buttonLabel = 'Filter')
    {
        $this->buttonLabel = $buttonLabel;
    }

    public static function createDefault(): self
    {
        return new self('Filter');
    }

    public function render(PlayerPlatformFilterOptions $options): string
    {
        $dropdownControls = $this->renderDropdownControls($options);

        return <<<HTML
<form>
    <div class="input-group d-flex justify-content-end">
        {$dropdownControls}
    </div>
</form>
HTML;
    }

    public function renderDropdownControls(PlayerPlatformFilterOptions $options): string
    {
        $buttonLabel = htmlspecialchars($this->buttonLabel, ENT_QUOTES, 'UTF-8');
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

    public function renderOptionItems(PlayerPlatformFilterOptions $options): string
    {
        $optionItems = array_map(
            fn (PlayerPlatformFilterOption $option): string => $this->renderOption($option),
            $options->getOptions()
        );

        return implode(PHP_EOL, $optionItems);
    }

    private function renderOption(PlayerPlatformFilterOption $option): string
    {
        $inputId = htmlspecialchars($option->getInputId(), ENT_QUOTES, 'UTF-8');
        $inputName = htmlspecialchars($option->getInputName(), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($option->getLabel(), ENT_QUOTES, 'UTF-8');
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
}
