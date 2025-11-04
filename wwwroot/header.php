<?php
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

            .diff-row {
                position: relative;
            }

            .diff-row.diff-row-added::before,
            .diff-row.diff-row-removed::before {
                content: '';
                position: absolute;
                top: 0;
                bottom: 0;
                left: 0.25rem;
                width: 0.2rem;
                border-radius: 0.2rem;
                opacity: 0.7;
            }

            .diff-row.diff-row-added::before {
                background-color: rgba(63, 185, 80, 0.6);
            }

            .diff-row.diff-row-removed::before {
                background-color: rgba(248, 81, 73, 0.6);
            }

            .diff-cell {
                position: relative;
                overflow: hidden;
            }

            .diff-cell::after {
                content: '';
                position: absolute;
                inset: 0;
                border-radius: 0.35rem;
                background-color: transparent;
                pointer-events: none;
                transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
                z-index: 0;
            }

            .diff-cell-content {
                position: relative;
                z-index: 1;
                display: block;
            }

            .diff-cell-added::after {
                background-color: rgba(63, 185, 80, 0.18);
            }

            .diff-cell-removed::after {
                background-color: rgba(248, 81, 73, 0.2);
            }

            .diff-cell.diff-cell-highlight::after {
                inset: 0.15rem 0.35rem;
            }

            .diff-cell-added.diff-cell-highlight::after {
                background-color: rgba(63, 185, 80, 0.35);
                box-shadow: 0 0 0 1px rgba(46, 160, 67, 0.4);
            }

            .diff-cell-removed.diff-cell-highlight::after {
                background-color: rgba(248, 81, 73, 0.38);
                box-shadow: 0 0 0 1px rgba(248, 81, 73, 0.45);
            }

            .diff-cell-icon .diff-cell-content {
                display: flex;
                justify-content: center;
            }

            .diff-icon {
                border-radius: 0.5rem;
                border: 2px solid transparent;
                background-color: rgba(0, 0, 0, 0.25);
            }

            .diff-cell-added.diff-cell-icon .diff-icon {
                border-color: rgba(63, 185, 80, 0.55);
                background-color: rgba(46, 160, 67, 0.2);
            }

            .diff-cell-added.diff-cell-icon.diff-cell-highlight .diff-icon {
                border-color: rgba(63, 185, 80, 0.85);
            }

            .diff-cell-removed.diff-cell-icon .diff-icon {
                border-color: rgba(248, 81, 73, 0.55);
                background-color: rgba(248, 81, 73, 0.22);
            }

            .diff-cell-removed.diff-cell-icon.diff-cell-highlight .diff-icon {
                border-color: rgba(248, 81, 73, 0.85);
            }
        </style>

        <title><?= $title; ?></title>
    </head>
    <body>
        <?php require_once("nav.php"); ?>
