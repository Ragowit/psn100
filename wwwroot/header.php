<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/PageMetaData.php';
require_once __DIR__ . '/classes/PageMetaDataRenderer.php';

$metaTagHtml = '';
if (isset($metaData) && $metaData instanceof PageMetaData) {
    $renderer = new PageMetaDataRenderer();
    $metaTagHtml = $renderer->render($metaData);
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"> -->

        <meta name="description" content="Check your leaderboard position against other PlayStation trophy hunters!">
        <meta name="author" content="Markus 'Ragowit' Persson, and other contributors via GitHub project">

        <?= $metaTagHtml; ?>

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

            .trophy-earned-cell {
                position: relative;
                isolation: isolate;
            }

            .trophy-earned-cell::before {
                content: "";
                position: absolute;
                inset: 0;
                margin: auto;
                width: 3rem;
                height: 3rem;
                background-color: var(--trophy-earned-color, transparent);
                mask-image: url('/img/trophy-platinum.svg');
                -webkit-mask-image: url('/img/trophy-platinum.svg');
                mask-repeat: no-repeat;
                -webkit-mask-repeat: no-repeat;
                mask-position: center;
                -webkit-mask-position: center;
                mask-size: 3rem;
                -webkit-mask-size: 3rem;
                pointer-events: none;
            }

        </style>

        <script src="/js/localized-date-formatter.js" defer></script>

        <title><?= $title; ?></title>
    </head>
    <body>
        <?php require_once("nav.php"); ?>
