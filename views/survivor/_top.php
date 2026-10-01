<?php
/**
 * Opening of every survivor page (/submit, /my-report, /share and the
 * "coming soon" page): the public header without its navigation, the quick
 * exit and the hotline strip. Close with survivor/_bottom.php.
 *
 * Set before requiring: $title (neutral: "Share", "Your page", "Files"),
 * $quickExitSignOut (the area's sign-out path, or leave unset).
 */
use EchoDial\Deck\Deck;

$noindex = true;
$share = false;
$hideNav = true;
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container survivor-page stack stack-6">
