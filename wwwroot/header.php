<?php require_once __DIR__ . '/classes/PageMetaData.php'; ?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"> -->

        <meta name="description" content="Check your leaderboard position against other PlayStation trophy hunters!">
        <meta name="author" content="Markus 'Ragowit' Persson, and other contributors via GitHub project">

        <?php
        if (isset($metaData) && $metaData instanceof PageMetaData && !$metaData->isEmpty()) {
            $canonicalUrl = htmlspecialchars($metaData->getUrl() ?? '', ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($metaData->getDescription() ?? '', ENT_QUOTES, 'UTF-8');
            $image = htmlspecialchars($metaData->getImage() ?? '', ENT_QUOTES, 'UTF-8');
            $titleMeta = htmlspecialchars($metaData->getTitle() ?? '', ENT_QUOTES, 'UTF-8');

            echo "<link rel=\"canonical\" href=\"" . $canonicalUrl . "\" />";
            echo "<meta property=\"og:description\" content=\"" . $description . "\">";
            echo "<meta property=\"og:image\" content=\"" . $image . "\">";
            echo "<meta property=\"og:site_name\" content=\"PSN 100%\">";
            echo "<meta property=\"og:title\" content=\"" . $titleMeta . "\">";
            echo "<meta property=\"og:type\" content=\"article\">";
            echo "<meta property=\"og:url\" content=\"" . $canonicalUrl . "\">";
            echo "<meta name=\"twitter:card\" content=\"summary_large_image\">";
            echo "<meta name=\"twitter:image:alt\" content=\"" . $titleMeta . "\">";
        }
        ?>

        <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
        <link rel="icon" href="/img/favicon.ico">
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

        <style>
            .trophy-bronze {
                color: #c46438;
            }

            .trophy-silver {
                color: #777777;
            }
            
            .trophy-gold {
                color: #c2903e;
            }
            
            .trophy-platinum {
                color: #667fb2;
            }

            .trophy-common {
                color: #ffffff;
            }

            .trophy-uncommon {
                color: #1eff00;
            }

            .trophy-rare {
                color: #0070dd;
            }

            .trophy-epic {
                color: #a335ee;
            }

            .trophy-legendary {
                color: #ff8000;
            }

            /* Until Bootstrap fixes dark theme colors on tables */
            .table-success {
                --bs-table-color: var(--bs-success-text-emphasis);
                --bs-table-bg: var(--bs-success-bg-subtle);
                --bs-table-border-color: var(--bs-success-border-subtle);
                border-color: var(--bs-table-border-color);
            }

            .table-success a {
                color: var(--bs-success-text-emphasis);
            }

            .table-success a:hover {
                text-decoration-color: var(--bs-success-text-emphasis) !important;
            }

            .table-info {
                --bs-table-color: var(--bs-info-text-emphasis);
                --bs-table-bg: var(--bs-info-bg-subtle);
                --bs-table-border-color: var(--bs-info-border-subtle);
                border-color: var(--bs-table-border-color);
            }

            .table-info a {
                color: var(--bs-info-text-emphasis);
            }

            .table-info a:hover {
                text-decoration-color: var(--bs-info-text-emphasis) !important;
            }

            .table-primary {
                --bs-table-color: var(--bs-primary-text-emphasis);
                --bs-table-bg: var(--bs-primary-bg-subtle);
                --bs-table-border-color: var(--bs-primary-border-subtle);
                border-color: var(--bs-table-border-color);
            }

            .table-primary a {
                color: var(--bs-primary-text-emphasis);
            }

            .table-primary a:hover {
                text-decoration-color: var(--bs-primary-text-emphasis) !important;
            }

            .table-warning {
                --bs-table-color: var(--bs-warning-text-emphasis);
                --bs-table-bg: var(--bs-warning-bg-subtle);
                --bs-table-border-color: var(--bs-warning-border-subtle);
                border-color: var(--bs-table-border-color);
            }

            .table-warning a {
                color: var(--bs-warning-text-emphasis);
            }

            .table-warning a:hover {
                text-decoration-color: var(--bs-warning-text-emphasis) !important;
            }
        </style>

        <title><?= $title; ?></title>
    </head>
    <body>
        <?php require_once("nav.php"); ?>
