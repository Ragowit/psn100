<!doctype html>
<html lang="en">
    <head>
        <?php
        if (!isset($showCookie)) {
            ?>
            <!-- Global site tag (gtag.js) - Google Analytics -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=UA-153854358-1"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', 'UA-153854358-1');
            </script>
            <?php
        }
        ?>
        
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

        <meta name="description" content="Check your leaderboard position against other PlayStation trophy hunters!">
        <meta name="author" content="Markus 'Ragowit' Persson, and other contributors via GitHub project">

        <?php
        if (isset($metaData)) {
            echo "<link rel=\"canonical\" href=\"". $metaData->url ."\" />";
            echo "<meta property=\"og:description\" content=\"". $metaData->description ."\">";
            echo "<meta property=\"og:image\" content=\"". $metaData->image ."\">";
            echo "<meta property=\"og:site_name\" content=\"PSN 100%\">";
            echo "<meta property=\"og:title\" content=\"". $metaData->title ."\">";
            echo "<meta property=\"og:type\" content=\"article\">";
            echo "<meta property=\"og:url\" content=\"". $metaData->url ."\">";
            echo "<meta name=\"twitter:card\" content=\"summary_large_image\">";
            echo "<meta name=\"twitter:image:alt\" content=\"". $metaData->title ."\">";
        }
        ?>

        <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">

        <title><?= $title; ?></title>
    </head>
    <body style="padding-top: 4rem;">
        <?php require_once("nav.php"); ?>

        <?php
        if (isset($showCookie) && $showCookie === true) {
            ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                This site uses cookies for analytics and personalized content. By continuing to browse this site, you agree to this use.
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php
        }
        ?>
