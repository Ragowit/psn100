<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyStatusUpdateResult.php';
require_once __DIR__ . '/../Html.php';

final class TrophyStatusUpdateResultPresenter
{
    public function renderToHtml(TrophyStatusUpdateResult $result): string
    {
        $html = '<p>';

        foreach ($result->getTrophyNames() as $trophyName) {
            $html .= 'Trophy ID ' . Html::escape($trophyName) . '<br>';
        }

        $html .= 'is now set as ' . Html::escape($result->getStatusText()) . '</p>';

        return $html;
    }
}
