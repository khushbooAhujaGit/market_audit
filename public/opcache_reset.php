<?php
// ONE-TIME USE — delete this file immediately after running
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache cleared successfully.';
} else {
    echo 'OPcache not enabled or not available.';
}
