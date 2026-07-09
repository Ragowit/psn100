<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusUpdateResult.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusUpdateResultPresenter.php';

final class TrophyStatusUpdateResultPresenterTest extends TestCase
{
    public function testRenderToHtmlEscapesTrophyNamesAndStatusText(): void
    {
        $presenter = new TrophyStatusUpdateResultPresenter();
        $result = new TrophyStatusUpdateResult(
            ['1 (Test &amp; Trophy)', '2 (<script>)'],
            'unobtainable &amp; locked',
        );

        $html = $presenter->renderToHtml($result);

        $this->assertSame(
            '<p>Trophy ID 1 (Test &amp;amp; Trophy)<br>Trophy ID 2 (&lt;script&gt;)<br>is now set as unobtainable &amp;amp; locked</p>',
            $html,
        );
    }
}
