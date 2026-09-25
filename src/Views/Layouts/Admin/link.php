<?php
# ======================================================================
# GLOBAL CSS & HEAD ASSETS INCLUSION FILE
# Holds all foundational CSS and asset dependencies included within 
# the <head> element. Files are ordered by cascade priority:
# Core Framework -> Icon Fonts -> Plugins -> Base Layout -> Overrides
# ======================================================================
?>

<!-- Favicons & Web Icons -->
<link rel="icon" type="image/x-icon" href="<?php echo FAVICON; ?>">
<link rel="apple-touch-icon" href="<?php echo FAVICON; ?>">

<!-- Root  -->
<link rel="stylesheet" type="text/css" href="<?php echo BASE_MAIN_ASSETS_PATH; ?>css/root.css?v=<?php echo FILE_VERSION; ?>">

<!-- Typography & Google Fonts (Optional) -->
<link rel="preconnect" href="https://fonts.gstatic.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">

<!-- Vendor Bootstrap Files -->
<link rel="stylesheet" type="text/css" href="<?php echo BASE_VENDOR_ASSETS_PATH; ?>bootstrap/bootstrap.min.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_VENDOR_ASSETS_PATH; ?>bootstrap-icons/bootstrap-icons.css?v=<?php echo FILE_VERSION; ?>">
<!-- <link rel="stylesheet" type="text/css" href="<?php echo BASE_VENDOR_ASSETS_PATH; ?>font-awesome/all.min.css?v=<?php echo FILE_VERSION; ?>"> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- ADMIN CSS Files -->
 
<!-- Custom Template [ADMIN] -->
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/custom.min.css?v=<?php echo FILE_VERSION; ?>"> <!-- kaiadmin-lite -->

<!-- Plugin CSS [ADMIN] -->
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/plugins.min.css?v=<?php echo FILE_VERSION; ?>">

<!-- Additional CSS [ADMIN] -->
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/dropify/dropify.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/custom-sweetalert.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/selectize.bootstrap5.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/bootstrap-datetimepicker.min.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/jquery.timepicker.min.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/tabulator.min.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/tabulator_bootstrap.min.css?v=<?php echo FILE_VERSION; ?>">

<!-- Additional Custom Styles -->
<link rel="stylesheet" type="text/css" href="<?php echo BASE_ASSETS_PATH; ?>Admin/css/styles.css?v=<?php echo FILE_VERSION; ?>">

<!-- MAIN CSS Files -->
<!-- <link rel="stylesheet" type="text/css" href="<?php echo BASE_MAIN_ASSETS_PATH; ?>css/digital-profile.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_MAIN_ASSETS_PATH; ?>css/main-style.css?v=<?php echo FILE_VERSION; ?>">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_MAIN_ASSETS_PATH; ?>css/custom-style.css?v=<?php echo FILE_VERSION; ?>"> -->
