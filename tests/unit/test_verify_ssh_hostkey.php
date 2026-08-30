<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Standalone: `php tests/unit/test_verify_ssh_hostkey.php`. Stubs the core |
 | data layer and exercises every branch of                                 |
 | plugin_routerconfigs_verify_ssh_hostkey().                               |
 +-------------------------------------------------------------------------+
*/

// The plugin's real log helper and settings array run in a minimal stub
// environment here; hide their notices so only the logic under test speaks.
error_reporting(E_ERROR | E_PARSE);

foreach (['POLLER_VERBOSITY_NONE' => 0, 'POLLER_VERBOSITY_LOW' => 1, 'POLLER_VERBOSITY_MEDIUM' => 2, 'POLLER_VERBOSITY_HIGH' => 3, 'POLLER_VERBOSITY_DEBUG' => 4, 'POLLER_VERBOSITY_DEVDBG' => 5] as $k => $v) {
	if (!defined($k)) {
		define($k, $v);
	}
}

function __($text) {
	return $text;
}

function read_config_option($name, $default = false) {
	return $GLOBALS['t_opt'][$name] ?? $default;
}

function db_column_exists($table, $column) {
	return $GLOBALS['t_col'] ?? true;
}

function db_fetch_cell_prepared($sql, $params = []) {
	return $GLOBALS['t_stored'] ?? null;
}

function db_execute_prepared($sql, $params = []) {
	$GLOBALS['t_updates'][] = $params;

	return true;
}

function cacti_log($message, $print = false, $type = '') {
	return true;
}

require __DIR__ . '/../../include/functions.php';

$failures = 0;

function check($label, $ok) {
	global $failures;

	print ($ok ? '  ok: ' : '  FAIL: ') . $label . "\n";

	if (!$ok) {
		$failures++;
	}
}

function reset_state() {
	$GLOBALS['t_opt']     = [];
	$GLOBALS['t_col']     = true;
	$GLOBALS['t_stored']  = null;
	$GLOBALS['t_updates'] = [];
}

// Option off: always proceed, no storage touched.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = '';
check('option off proceeds without touching storage',
	plugin_routerconfigs_verify_ssh_hostkey(1, 'AA:BB') === true && count($GLOBALS['t_updates']) === 0);

// Option on, no fingerprint available: refuse.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
check('missing fingerprint is refused', plugin_routerconfigs_verify_ssh_hostkey(1, false) === false);

// Option on, storage column absent: degrade to allow (never break an install).
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_col']                                 = false;
check('absent storage column allows the connection',
	plugin_routerconfigs_verify_ssh_hostkey(1, 'AA:BB') === true);

// Option on, nothing stored: trust on first use, record the fingerprint.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = null;
$ok                                               = plugin_routerconfigs_verify_ssh_hostkey(7, 'AA:BB:CC') === true;
check('first use records the fingerprint and proceeds',
	$ok && $GLOBALS['t_updates'] === [['AA:BB:CC', 7]]);

// Option on, stored matches: proceed, no re-store.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = 'AA:BB:CC';
check('matching fingerprint proceeds without re-storing',
	plugin_routerconfigs_verify_ssh_hostkey(7, 'AA:BB:CC') === true && count($GLOBALS['t_updates']) === 0);

// Option on, stored differs: refuse (possible MITM).
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = 'AA:BB:CC';
check('changed fingerprint is refused',
	plugin_routerconfigs_verify_ssh_hostkey(7, 'DD:EE:FF') === false);

if ($failures > 0) {
	fwrite(STDERR, "\n$failures check(s) failed\n");

	exit(1);
}

print "\nall checks passed\n";
exit(0);
