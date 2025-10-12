<?php
require_once __DIR__ . '/classes/NavigationState.php';
require_once __DIR__ . '/classes/NavigationMenu.php';

$navigationState = NavigationState::fromGlobals($_SERVER, $_GET);
$navigationMenu = NavigationMenu::createDefault($navigationState);
?>

<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-2">
    <div class="container">
        <a class="navbar-brand" href="/">
            <img src="/img/logo-via-logohub.png" alt="PSN 100%" height="24">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <form action="/game" class="d-flex" role="search">
                <input type="hidden" name="sort" value="<?= $navigationState->getSort(); ?>">
                <input type="hidden" name="player" value="<?= $navigationState->getPlayer(); ?>">
                <input type="hidden" name="filter" value="<?= $navigationState->getFilter(); ?>">
                <input class="form-control me-2" name="search" type="search" placeholder="Search game..." aria-label="Search" value="<?= $navigationState->getSearch(); ?>">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>

            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
                <?php foreach ($navigationMenu->getItems() as $item) { ?>
                    <?php
                    $linkClass = htmlspecialchars($item->getLinkCssClass(), ENT_QUOTES, 'UTF-8');
                    $href = htmlspecialchars($item->getHref(), ENT_QUOTES, 'UTF-8');
                    $ariaCurrent = $item->getAriaCurrentValue();
                    $ariaAttribute = $ariaCurrent !== null
                        ? ' aria-current="' . htmlspecialchars($ariaCurrent, ENT_QUOTES, 'UTF-8') . '"'
                        : '';
                    ?>
                    <li class="nav-item">
                        <a class="<?= $linkClass; ?>" href="<?= $href; ?>"<?= $ariaAttribute; ?>>
                            <?= htmlspecialchars($item->getLabel(), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</nav>
