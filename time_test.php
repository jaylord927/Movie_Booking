<?php
require_once 'includes/config.php';
echo "Current Time: " . date('Y-m-d H:i:s');
echo "<br>Timezone: " . date_default_timezone_get();
?>