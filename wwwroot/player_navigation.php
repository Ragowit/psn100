<?php

declare(strict_types=1);

if (!isset($playerNavigation) || !$playerNavigation instanceof PlayerNavigation) {
    throw new RuntimeException('Player navigation data is missing.');
}

$links = $playerNavigation->getLinks();
?>
<div class="btn-group">
    <?php foreach ($links as $link) { ?>
        <?php $ariaCurrent = $link->getAriaCurrent(); ?>
        <a
            class="<?= htmlspecialchars($link->getButtonCssClass(), ENT_QUOTES, 'UTF-8'); ?>"
            href="<?= htmlspecialchars($link->getUrl(), ENT_QUOTES, 'UTF-8'); ?>"
            <?php if ($ariaCurrent !== null) { ?>aria-current="<?= htmlspecialchars($ariaCurrent, ENT_QUOTES, 'UTF-8'); ?>"<?php } ?>
        >
            <?= htmlspecialchars($link->getLabel(), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php } ?>
</div>
