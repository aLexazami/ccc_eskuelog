<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';

// Dynamic CSS cache-busting using system file modification time
$customCssPath = ROOT_PATH . '/assets/css/style.css';
$cssVersion = file_exists($customCssPath) ? filemtime($customCssPath) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'E-Log & Queue System', ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Local Bootstrap 5 CSS -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/vendor/bootstrap/css/bootstrap.min.css">

    <!-- Local Bootstrap Icons -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">

    <!-- Custom CSS Overrides with Auto Cache-Busting -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css?v=<?= $cssVersion; ?>">

    <?= $extraCss ?? '' ?>
</head>
<body>