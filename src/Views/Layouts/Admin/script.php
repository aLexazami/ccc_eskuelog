<!-- CORE JS LIBRARIES -->

<!-- MAIN JS Files -->
<script type="text/javascript" src="<?php echo BASE_MAIN_ASSETS_PATH; ?>js/jquery.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_MAIN_ASSETS_PATH; ?>js/main.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_MAIN_ASSETS_PATH; ?>js/app.js?v=<?php echo FILE_VERSION; ?>"></script>

<!-- VENDOR JS Files -->
<script type="text/javascript" src="<?php echo BASE_VENDOR_ASSETS_PATH; ?>bootstrap/bootstrap.bundle.min.js?v=<?php echo FILE_VERSION; ?>"></script>

<!-- UTILITY JS LIBRARIES -->
<script type="text/javascript" src="<?php echo BASE_MAIN_ASSETS_PATH; ?>js/moment.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_MAIN_ASSETS_PATH; ?>js/moment-timezone-with-data.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/popper.min.js?v=<?php echo FILE_VERSION; ?>"></script>

<!-- ADMIN TEMPLATE JS File -->
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/custom.js?v=<?php echo FILE_VERSION; ?>"></script> <!-- kaiadmin-lite -->

<!-- ADMIN PLUGINS JS File -->
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/bootstrap-notify/bootstrap-notify.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/sweetalert/sweetalert.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/jquery-scrollbar/jquery.scrollbar.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/chart.js/chart.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/jquery.sparkline/jquery.sparkline.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/chart-circle/circles.min.js?v=<?php echo FILE_VERSION; ?>"></script>

<!-- UI COMPONENTS ADMIN JS Files -->
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/bootstrap-datetimepicker.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/jquery.timepicker.min.js?v=<?php echo FILE_VERSION; ?>"></script>

<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/tabulator.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/xlsx.full.min.js?v=<?php echo FILE_VERSION; ?>"></script>

<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/selectize.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/clipboard.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/dropify/dropify.js?v=<?php echo FILE_VERSION; ?>"></script>

<!-- ADDITIONAL JS  -->
<script type="text/javascript" src="<?php echo BASE_MAIN_ASSETS_PATH; ?>js/custom-notification.js?v=<?php echo FILE_VERSION; ?>"></script>

<!-- FONTS AND ICON JS Files -->
<script type="text/javascript" src="<?php echo BASE_ADMIN_ASSETS_PATH; ?>js/plugin/webfont/webfont.min.js?v=<?php echo FILE_VERSION; ?>"></script>
<script>
    WebFont.load({
        google: {
            "families": ["Public Sans:300,400,500,600,700"]
        },
        custom: {
            "families": ["Font Awesome 5 Solid", "Font Awesome 5 Regular", "Font Awesome 5 Brands", "simple-line-icons"],
            urls: ['<?php echo BASE_ADMIN_ASSETS_PATH; ?>css/fonts.min.css']
        },
        active: function() {
            sessionStorage.fonts = true;
        }
    });
</script>

<!-- SCRIPTS -->
<script>
    (function() {
        /** date and time */
        var set_server_time = <?php echo "'" . DATE_TIME . "';\r\n"; ?>
        var serverOffset = moment(set_server_time).diff(new Date());
        var clock_id = datetime();

        function datetime() {
            setInterval(function() {
                if (document.getElementById('now')) {
                    var now_server = moment();
                    now_server.add(serverOffset, 'milliseconds');
                    var timeNow = now_server.format('ddd | MMMM DD, YYYY h:mm:ss A');
                    $('#now').html(timeNow);
                    $('#printTime').html('Date Printed : ' + timeNow);
                } else {
                    clearInterval(clock_id);
                }
            }, 1000);
        }
    })();
</script>