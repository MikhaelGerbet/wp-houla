<?php
require_once "/var/www/vhosts/lucky-geek.com/httpdocs/wp-load.php";
delete_transient("wphouladev_bg_sync_status");
delete_transient("wphouladev_bg_sync_nonce");
echo "Transients cleared\n";
