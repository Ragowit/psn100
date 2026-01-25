<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerNavigation.php';

final class PlayerNavigationRenderer
{
    public function render(PlayerNavigation $navigation): string
    {
        $links = array_map(
            fn (PlayerNavigationLink $link): string => $this->renderLink($link),
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
        $cssClass = htmlspecialchars($link->getButtonCssClass() . ' d-flex align-items-center justify-content-center', ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($link->getUrl(), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($link->getLabel(), ENT_QUOTES, 'UTF-8');
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

        $value = htmlspecialchars($ariaCurrent, ENT_QUOTES, 'UTF-8');

        return ' aria-current="' . $value . '"';
    }
}
