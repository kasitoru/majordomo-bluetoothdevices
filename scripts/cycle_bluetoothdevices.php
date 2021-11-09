<?php
/*
	Bluetooth Devices module for MajorDoMo
	Author: Sergey Avdeev <avdeevsv91@gmail.com>
	URL: https://github.com/kasitoru/majordomo-bluetoothdevices
*/

chdir(dirname(__FILE__).'/../');

include_once('./config.php');
include_once('./lib/loader.php');
include_once('./lib/threads.php');

set_time_limit(0);

$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once('./load_settings.php');

include_once(DIR_MODULES.'control_modules/control_modules.class.php');
$ctl = new control_modules();

include_once(DIR_MODULES.'bluetoothdevices/bluetoothdevices.class.php');
$bluetoothdevices_module = new bluetoothdevices();

echo date('Y/m/d H:i:s').' Running bluetooth scanner'.PHP_EOL;

$scan_time = 0;
$latest_check = 0;
$check_period = 10;

while(true) {

	if ((time() - $latest_check) >= $check_period) {
		$latest_check = time();
		setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);
	}

	$bluetoothdevices_module->getConfig();

	if(time()-$scan_time > intval($bluetoothdevices_module->config['scanInterval'])) {
		$scan_time = time();
		$bluetoothdevices_module->processCycle();
	}

	if(file_exists('./reboot') || isset($_GET['onetime'])) {
		$db->Disconnect();
		exit;
	}
	sleep(1);
}

DebMes('Unexpected close of cycle: '.basename(__FILE__));

?>
