<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerNavigation.php';
require_once __DIR__ . '/Html.php';

final class PlayerNavigationRenderer
{
    public function render(PlayerNavigation $navigation): string
    {
        $links = array_map(
            $this->renderLink(...),
            $navigation->getLinks()
        );

        $linksHtml = implode(PHP_EOL, $links);

        return <<<HTML
<div class="btn-group d-flex align-items-stretch">
{$linksHtml}
</div>
HTML;
    }

    private function renderLink(PlayerNavigationLink $link): string
    {
        $cssClass = Html::escape($link->getButtonCssClass() . ' d-flex align-items-center justify-content-center');
        $url = Html::escape($link->getUrl());
        $label = Html::escape($link->getLabel());
        $ariaAttribute = $this->renderAriaAttribute($link->getAriaCurrent());

        return sprintf(
            '    <a class="%s" href="%s"%s>%s</a>',
            $cssClass,
            $url,
            $ariaAttribute,
            $label
        );
    }

    private function renderAriaAttribute(?string $ariaCurrent): string
    {
        if ($ariaCurrent === null) {
            return '';
        }

        $value = Html::escape($ariaCurrent);

        return ' aria-current="' . $value . '"';
    }
}
