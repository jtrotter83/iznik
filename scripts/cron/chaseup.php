<?php

# Fake user site.
# TODO Messy.
$_SERVER['HTTP_HOST'] = "www.ilovefreegle.org";

require_once dirname(__FILE__) . '/../../include/config.php';
require_once(IZNIK_BASE . '/include/db.php');
require_once(IZNIK_BASE . '/include/mail/Relevant.php');
global $dbhr, $dbhm;

$lockh = lockScript(basename(__FILE__));

$m = new Message($dbhr, $dbhm);
$mysqltime = date("Y-m-d", max(strtotime("06-sep-2016"), strtotime("Midnight 90 days ago")));
$count = $m->processIntendedOutcomes();
error_log("Processed $count intended");
$count = $m->chaseUp(Group::GROUP_FREEGLE, $mysqltime);
error_log("Sent $count chaseups");

unlockScript($lockh);