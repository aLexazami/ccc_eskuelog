<?php
// includes/footer.php
?>
    <!-- Local Bootstrap 5 JS Bundle -->
    <script src="<?= BASE_URL; ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Optional Page-Specific JS Injection -->
    <?= $extraJs ?? '' ?>

</body>
</html>