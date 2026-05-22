<?php
// Require config variables
require 'config.inc.php';

// Absolute path for the application data file.
// This file stores fan presets plus optional runtime settings such as
// failsafe watchdog configuration and startup preset configuration.
define('APP_DATA_FILE', __DIR__ . '/presets.json');

function default_presets() {
	return [
		[
			'name' => 'Home_Quiet',
			'speeds' => [ 20 ],
		],
		[
			'name' => 'Home_Medium',
			'speeds' => [ 40 ],
		],
		[
			'name' => 'Home_High',
			'speeds' => [ 65 ],
		]
	];
}

// This works along with a systemd service | See readme for details
function default_failsafe_settings() {
	return [
		'enabled' => true,
		'threshold_c' => 80,
		'recovery_margin_c' => 5,
		'fan_speed' => 70,
		'interval_seconds' => 15,
	];
}

// This works along with a systemd service | See readme for details
function default_startup_settings() {
	return [
		'enabled' => true,
		'preset_name' => 'Home_Quiet',
		'delay_seconds' => 60,
	];
}

function default_runtime_state() {
	return [
		'last_preset_name' => 'Home_Quiet',
		'failsafe_active' => false,
	];
}

function default_app_data() {
	return [
		'presets' => default_presets(),
		'failsafe' => default_failsafe_settings(),
		'startup' => default_startup_settings(),
		'runtime' => default_runtime_state(),
	];
}

function get_app_data() {
	$defaults = default_app_data();

	if (!file_exists(APP_DATA_FILE))
		return $defaults;

	$raw = file_get_contents(APP_DATA_FILE);
	$decoded = json_decode($raw, true);

	if (!is_array($decoded))
		return $defaults;

	// Backwards compatibility:
	// Old presets.json was just an array of presets.
	if (isset($decoded[0]) && isset($decoded[0]['name'])) {
		return [
			'presets' => $decoded,
			'failsafe' => $defaults['failsafe'],
			'startup' => $defaults['startup'],
			'runtime' => $defaults['runtime'],
		];
	}

	return [
		'presets' => $decoded['presets'] ?? $defaults['presets'],
		'failsafe' => array_merge($defaults['failsafe'], $decoded['failsafe'] ?? []),
		'startup' => array_merge($defaults['startup'], $decoded['startup'] ?? []),
		'runtime' => array_merge($defaults['runtime'], $decoded['runtime'] ?? []),
	];
}

function save_app_data($data) {
	$encoded = json_encode($data, JSON_PRETTY_PRINT);
	file_put_contents(APP_DATA_FILE, $encoded);
	return $encoded;
}

function get_presets() {
	$data = get_app_data();
	return $data['presets'];
}

function detect_preset_name_from_fans($fans) {
	$presets = get_presets();
	$speeds = array_values($fans);

	foreach ($presets as $preset) {
		$preset_name = $preset['name'] ?? null;
		$preset_speeds = $preset['speeds'] ?? [];

		if (!$preset_name || empty($preset_speeds))
			continue;

		if (count($preset_speeds) === 1) {
			if (count($speeds) > 0 && count(array_filter($speeds, fn($speed) => intval($speed) !== intval($preset_speeds[0]))) === 0)
				return $preset_name;
		} else {
			if (array_map('intval', $speeds) === array_map('intval', $preset_speeds))
				return $preset_name;
		}
	}

	return null;
}

function get_runtime_state() {
	$data = get_app_data();
	$defaults = default_runtime_state();

	return array_merge($defaults, $data['runtime'] ?? []);
}

function save_runtime_state($runtime) {
	$data = get_app_data();

	$data['runtime'] = array_merge(
		default_runtime_state(),
		$data['runtime'] ?? [],
		$runtime
	);

	save_app_data($data);

	return $data['runtime'];
}

function get_startup_settings() {
	$data = get_app_data();

	$settings = $data['startup'];

	$settings['enabled'] = filter_var($settings['enabled'], FILTER_VALIDATE_BOOLEAN);
	$settings['preset_name'] = strval($settings['preset_name']);
	$settings['delay_seconds'] = intval($settings['delay_seconds']);

	if ($settings['delay_seconds'] < 0)
		$settings['delay_seconds'] = 0;

	if ($settings['delay_seconds'] > 300)
		$settings['delay_seconds'] = 300;

	return $settings;
}

function save_startup_settings($settings) {
	$data = get_app_data();

	$current = get_startup_settings();

	$data['startup'] = [
		'enabled' => isset($settings['enabled'])
			? filter_var($settings['enabled'], FILTER_VALIDATE_BOOLEAN)
			: $current['enabled'],

		'preset_name' => isset($settings['preset_name'])
			? strval($settings['preset_name'])
			: $current['preset_name'],

		'delay_seconds' => isset($settings['delay_seconds'])
			? intval($settings['delay_seconds'])
			: $current['delay_seconds'],
	];

	save_app_data($data);

	return get_startup_settings();
}

function get_failsafe_settings() {
	$data = get_app_data();

	$settings = $data['failsafe'];

	$settings['enabled'] = filter_var($settings['enabled'], FILTER_VALIDATE_BOOLEAN);
	$settings['threshold_c'] = intval($settings['threshold_c']);
	$settings['recovery_margin_c'] = intval($settings['recovery_margin_c']);
	$settings['fan_speed'] = intval($settings['fan_speed']);
	$settings['interval_seconds'] = intval($settings['interval_seconds']);

	if ($settings['threshold_c'] < 30)
		$settings['threshold_c'] = 30;

	if ($settings['threshold_c'] > 120)
		$settings['threshold_c'] = 120;

	if ($settings['recovery_margin_c'] < 1)
		$settings['recovery_margin_c'] = 1;

	if ($settings['recovery_margin_c'] > 30)
		$settings['recovery_margin_c'] = 30;

	if ($settings['fan_speed'] < 50)
		$settings['fan_speed'] = 50;

	if ($settings['fan_speed'] > 100)
		$settings['fan_speed'] = 100;

	if ($settings['interval_seconds'] < 5)
		$settings['interval_seconds'] = 5;

	if ($settings['interval_seconds'] > 60)
		$settings['interval_seconds'] = 60;

	return $settings;
}

function save_failsafe_settings($settings) {
	$data = get_app_data();

	$current = get_failsafe_settings();

	$data['failsafe'] = [
		'enabled' => isset($settings['enabled'])
			? filter_var($settings['enabled'], FILTER_VALIDATE_BOOLEAN)
			: $current['enabled'],

		'threshold_c' => isset($settings['threshold_c'])
			? intval($settings['threshold_c'])
			: $current['threshold_c'],

		'recovery_margin_c' => isset($settings['recovery_margin_c'])
			? intval($settings['recovery_margin_c'])
			: $current['recovery_margin_c'],

		'fan_speed' => isset($settings['fan_speed'])
			? intval($settings['fan_speed'])
			: $current['fan_speed'],

		'interval_seconds' => isset($settings['interval_seconds'])
			? intval($settings['interval_seconds'])
			: $current['interval_seconds'],
	];

	save_app_data($data);

	return get_failsafe_settings();
}

function get_thermal_data() {
        global $ILO_HOST, $ILO_USERNAME, $ILO_PASSWORD;  // From config.inc.php

        $curl_handle = curl_init("https://$ILO_HOST/redfish/v1/chassis/1/Thermal");

        curl_setopt($curl_handle, CURLOPT_USERPWD, "$ILO_USERNAME:$ILO_PASSWORD");

        // Disable SSL verification
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);

        $raw_ilo_data = curl_exec($curl_handle);

		if (!$raw_ilo_data) {
				curl_close($curl_handle);
				return [
						'fans' => [],
						'temperatures' => [],
				];
		}

		curl_close($curl_handle);

        $ilo_data = json_decode($raw_ilo_data, true);
		if (!is_array($ilo_data)) {
			return [
					'fans' => [],
					'temperatures' => [],
			];
		}

        $fans = [];
        foreach (($ilo_data['Fans'] ?? []) as $fan) {
                if (isset($fan['FanName']))
                        $fans[$fan['FanName']] = $fan['CurrentReading'] ?? null;
        }

        $temperatures = [];
        foreach (($ilo_data['Temperatures'] ?? []) as $temp) {
                $name = $temp['Name'] ?? $temp['PhysicalContext'] ?? 'Unknown';
                $reading = $temp['ReadingCelsius'] ?? null;

                // Skip sensors with no live reading
                if ($reading === null)
                        continue;

                $temperatures[$name] = [
                        'value' => $reading,
                        'status' => $temp['Status']['Health'] ?? 'Unknown',
                        'upper_warning' => $temp['UpperThresholdNonCritical'] ?? null,
                        'upper_critical' => $temp['UpperThresholdCritical'] ?? null,
                ];
        }

        return [
                'fans' => $fans,
                'temperatures' => $temperatures,
        ];
}

function get_fans() {
        $thermal = get_thermal_data();
        return $thermal['fans'];
}

function get_temperatures() {
        $thermal = get_thermal_data();
        return $thermal['temperatures'];
}

function apply_preset_array($preset) {
	$preset_name = $preset['name'] ?? 'Unnamed preset';
	$speeds = $preset['speeds'] ?? [];

	if (empty($speeds))
		return [
			'ok' => false,
			'message' => "Preset '$preset_name' has no speeds.",
		];

	// If preset has one speed, apply it to all fans.
	if (count($speeds) === 1) {
		$FANS = get_fans();

		if (empty($FANS))
			return [
				'ok' => false,
				'message' => 'No fans found.',
			];

		$speeds = array_fill(0, count($FANS), intval($speeds[0]));
	}

	$FANS = get_fans();

	if (empty($FANS))
		return [
			'ok' => false,
			'message' => 'No fans found.',
		];

	$ssh_handle = ssh2_connect($GLOBALS['ILO_HOST'], 22);

	if (!$ssh_handle)
		return [
			'ok' => false,
			'message' => 'SSH connection to iLO failed.',
		];

	if (!ssh2_auth_password($ssh_handle, $GLOBALS['ILO_USERNAME'], $GLOBALS['ILO_PASSWORD']))
		return [
			'ok' => false,
			'message' => 'SSH authentication to iLO failed.',
		];

	$fan_names = array_keys($FANS);
	$updated = 0;

	foreach ($speeds as $i => $speed) {
		if (!isset($fan_names[$i]))
			continue;

		$speed = intval($speed);

		if ($speed < $GLOBALS['MINIMUM_FAN_SPEED'])
			$speed = $GLOBALS['MINIMUM_FAN_SPEED'];

		if ($speed > 100)
			$speed = 100;

		$pwm_value = ceil($speed / 100 * 255);

		$stream = ssh2_exec($ssh_handle, "fan p $i max $pwm_value");
		stream_set_blocking($stream, true);
		stream_get_contents($stream);
		fclose($stream);

		$stream = ssh2_exec($ssh_handle, "fan p $i min 255");
		stream_set_blocking($stream, true);
		stream_get_contents($stream);
		fclose($stream);

		$updated++;
		usleep(200000);
	}

	return [
		'ok' => $updated > 0,
		'preset' => $preset_name,
		'updated' => $updated,
		'total_fans' => count($fan_names),
		'message' => "Preset '$preset_name' applied to $updated fan(s).",
	];
}

function apply_preset_by_name($preset_name) {
	$presets = get_presets();

	foreach ($presets as $preset) {
		if (($preset['name'] ?? '') === $preset_name) {
			$result = apply_preset_array($preset);

			if (count($preset['speeds'] ?? []) === 1) {
				$result['mode'] = 'single-speed';
				$result['speed'] = intval($preset['speeds'][0]);
			} else {
				$result['mode'] = 'multi-speed';
			}

			return $result;
		}
	}

	return [
		'ok' => false,
		'message' => "Preset '$preset_name' not found.",
	];
}

function apply_startup_preset() {
	$settings = get_startup_settings();

	if (!$settings['enabled']) {
		return [
			'ok' => true,
			'skipped' => true,
			'settings' => $settings,
			'message' => 'Startup preset disabled.',
		];
	}

	if ($settings['delay_seconds'] > 0)
		sleep($settings['delay_seconds']);

	$result = apply_preset_by_name($settings['preset_name']);
	$result['settings'] = $settings;

	if (($result['ok'] ?? false) === true) {
		save_runtime_state([
			'last_preset_name' => $settings['preset_name'],
		]);
	}

	return $result;
}

function thermal_failsafe_check() {
	$settings = get_failsafe_settings();

	$runtime = get_runtime_state();
	$clear_threshold = max(0, $settings['threshold_c'] - $settings['recovery_margin_c']);

	if (!$settings['enabled']) {
		return [
			'triggered' => false,
			'enabled' => false,
			'settings' => $settings,
			'message' => 'Failsafe disabled',
		];
	}

	$temperatures = get_temperatures();
	$hot_sensors = [];

	foreach ($temperatures as $name => $temp) {
		$value = intval($temp['value'] ?? 0);
		$status = $temp['status'] ?? 'Unknown';

		// Ignore unavailable / fake sensors
		if ($status === 'Unknown' || $value <= 0)
			continue;

		if ($value >= $settings['threshold_c']) {
			$hot_sensors[$name] = [
				'value' => $value,
				'status' => $status,
				'upper_critical' => $temp['upper_critical'] ?? null,
			];
		}
	}

	if (!empty($hot_sensors)) {
		$FANS = get_fans();

		$failsafe_preset = [
			'name' => '__FAILSAFE__',
			'speeds' => array_fill(0, count($FANS), $settings['fan_speed']),
		];

		$fan_result = apply_preset_array($failsafe_preset);

		save_runtime_state([
			'failsafe_active' => true,
		]);

		return [
			'triggered' => true,
			'enabled' => true,
			'settings' => $settings,
			'fan_update_ok' => $fan_result['ok'] ?? false,
			'fan_update_result' => $fan_result,
			'hot_sensors' => $hot_sensors,
			'message' => 'Thermal failsafe triggered. Fans forced to emergency speed.',
		];
	}

	if (($runtime['failsafe_active'] ?? false) === true) {
		$max_temp = 0;

		foreach ($temperatures as $temp) {
			$value = intval($temp['value'] ?? 0);
			$status = $temp['status'] ?? 'Unknown';

			if ($status !== 'Unknown' && $value > $max_temp)
				$max_temp = $value;
		}

		if ($max_temp <= $clear_threshold) {
			$restore_preset = $runtime['last_preset_name'] ?? null;
			$restore_result = null;

			if ($restore_preset) {
				$restore_result = apply_preset_by_name($restore_preset);
			}

			save_runtime_state([
				'failsafe_active' => false,
			]);

			return [
				'triggered' => false,
				'enabled' => true,
				'restored' => true,
				'restore_preset' => $restore_preset,
				'restore_result' => $restore_result,
				'settings' => $settings,
				'hot_sensors' => [],
				'message' => "Temperatures OK. Failsafe cleared and previous preset restored.",
			];
		}

		return [
			'triggered' => false,
			'enabled' => true,
			'restored' => false,
			'settings' => $settings,
			'hot_sensors' => [],
			'message' => "Temperatures below failsafe threshold but waiting for clear threshold before restoring preset.",
		];
	}

	return [
		'triggered' => false,
		'enabled' => true,
		'settings' => $settings,
		'hot_sensors' => [],
		'message' => 'Temperatures OK',
	];
}

if (PHP_SAPI === 'cli') {
	$command = $argv[1] ?? null;

	if ($command === 'failsafe-check') {
		$result = thermal_failsafe_check();

		echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

		exit($result['triggered'] ? 2 : 0);
	}

	if ($command === 'failsafe-settings') {
		echo json_encode(get_failsafe_settings(), JSON_PRETTY_PRINT) . PHP_EOL;
		exit(0);
	}

	if ($command === 'startup-settings') {
		echo json_encode(get_startup_settings(), JSON_PRETTY_PRINT) . PHP_EOL;
		exit(0);
	}

	if ($command === 'apply-startup-preset') {
		$result = apply_startup_preset();

		echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

		exit(($result['ok'] ?? false) ? 0 : 1);
	}

	echo "Usage:\n";
	echo "  php index.php failsafe-check\n";
	echo "  php index.php failsafe-settings\n";
	exit(1);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $THERMAL = get_thermal_data();
    $FANS = $THERMAL['fans'];
    $TEMPERATURES = $THERMAL['temperatures'];

	// Return startup settings with ?api=startup-settings
	if (isset($_GET['api']) && $_GET['api'] == 'startup-settings') {
		header('Content-Type: application/json');
		die(json_encode(get_startup_settings(), JSON_PRETTY_PRINT));
	}

	// Return failsafe settings with ?api=failsafe-settings
	if (isset($_GET['api']) && $_GET['api'] == 'failsafe-settings') {
		header('Content-Type: application/json');
		die(json_encode(get_failsafe_settings(), JSON_PRETTY_PRINT));
	}

	// Run failsafe check with ?api=failsafe-check
	if (isset($_GET['api']) && $_GET['api'] == 'failsafe-check') {
		header('Content-Type: application/json');
		die(json_encode(thermal_failsafe_check(), JSON_PRETTY_PRINT));
	}

	// Return fans in JSON format with ?api=fans
	if (isset($_GET['api']) && $_GET['api'] == 'fans') {
		header('Content-Type: application/json');
		die(json_encode($FANS));
	}

	// Return temperatures in JSON format with ?api=temperatures
	if (isset($_GET['api']) && $_GET['api'] == 'temperatures') {
			header('Content-Type: application/json');
			die(json_encode($TEMPERATURES));
	}

	$PRESETS = get_presets();

	// Return presets in JSON format with ?api=presets
	if (isset($_GET['api']) && $_GET['api'] == 'presets') {
		header('Content-Type: application/json');
		die(json_encode($PRESETS));
	}

} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get POST JSON data from JS fetch()
	$data = json_decode(file_get_contents('php://input'), true);

	if (isset($data['action']))  // Check if the action key exists
		if ($data['action'] === 'fans' || $data['action'] === 'presets' || $data['action'] === 'failsafe' || $data['action'] === 'startup')  // Check if the action is valid
			if ($data['action'] === 'fans' && isset($data['fans'])) {  // Set fans speeds
				$FANS = get_fans();

				if (is_int($data['fans']))  // Example: "fans": 50 - set all fans to 50%
					$data['fans'] = array_fill_keys(array_keys($FANS), $data['fans']);  // Fill the array with the same speeds

				$updated = 0;
				$connected = false;
				$ssh_handle = null;
				foreach ($data['fans'] as $fan => $speed) {
					if (array_key_exists($fan, $FANS)) {  // Check if the fan name is valid
						$fan_index = array_search($fan, array_keys($FANS));
						if (($speed >= $MINIMUM_FAN_SPEED && $speed <= 100) && $speed != $FANS[$fan]) {  // Check if the speed is valid and different from the current fan's speed
							if (!$connected) {  // Connect to iLO (only once)
								$ssh_handle = ssh2_connect($ILO_HOST, 22);
								ssh2_auth_password($ssh_handle, $ILO_USERNAME, $ILO_PASSWORD);
								$connected = true;
							}

							$stream = ssh2_exec($ssh_handle, "fan p $fan_index max " . ceil($speed / 100 * 255));
							stream_set_blocking($stream, true);
							stream_get_contents($stream);

							$stream = ssh2_exec($ssh_handle, "fan p $fan_index min 255");
							stream_set_blocking($stream, true);
							stream_get_contents($stream);

							$updated++;
						}
					} else
						die("Invalid fan name: $fan");
				}

				// Wait until the fans are set
				if ($updated > 0)
					do
						$FANS = get_fans();
					while ($FANS !== array_merge($FANS, $data['fans']));  // Wait until the fans are updated
				
				$matched_preset = detect_preset_name_from_fans($data['fans']);

				if ($matched_preset !== null) {
					save_runtime_state([
						'last_preset_name' => $matched_preset,
					]);
				}
				die(json_encode($FANS, JSON_PRETTY_PRINT));

			} else if ($data['action'] === 'presets' && isset($data['presets'])) {  // Save presets to presets.json
				$app_data = get_app_data();
				$app_data['presets'] = $data['presets'];

				$raw_presets = save_app_data($app_data);

				header('Content-Type: application/json');
				die(json_encode($app_data['presets'], JSON_PRETTY_PRINT));

			} else if ($data['action'] === 'failsafe' && isset($data['failsafe'])) {
				$updated_settings = save_failsafe_settings($data['failsafe']);

				header('Content-Type: application/json');
				die(json_encode($updated_settings, JSON_PRETTY_PRINT));

			} else if ($data['action'] === 'startup' && isset($data['startup'])) {
				$updated_settings = save_startup_settings($data['startup']);

				header('Content-Type: application/json');
				die(json_encode($updated_settings, JSON_PRETTY_PRINT));

			} else
				die('Invalid request: missing "fans", "presets", "failsafe", or "startup" key.');
		else
			die('Invalid request: invalid "action" value.');
	else
		die('Invalid request: missing "action" key.');

	// Catch edge cases
	die('Invalid request.');
}
?>

<!DOCTYPE html>
<html x-data :class="$store.darkMode.active ? 'dark' : ''">
	<head>
		<title>iLO Fans Controller</title>

		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="icon" type="image/x-icon" href="./favicon.ico">

		<!-- Fonts (DM Sans & JetBrains Mono) -->
		<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&family=JetBrains+Mono&display=swap" rel="stylesheet">

		<!-- Alpine.js -->
		<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

		<!-- Tailwind CSS -->
		<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
		<style type="text/tailwindcss">
			/* https://alpinejs.dev/directives/cloak */
			[x-cloak] {
				display: none !important;
			}

			:root.dark {
				color-scheme: dark;
			}

			.outline-button {
				@apply outline-none select-none cursor-pointer disabled:cursor-not-allowed transition duration-75 rounded-md border border-emerald-500 dark:disabled:border-emerald-500/20
							 enabled:hover:border-emerald-600 enabled:dark:hover:border-emerald-400 text-emerald-500 enabled:hover:text-emerald-600
							 enabled:dark:hover:text-emerald-400 dark:disabled:text-emerald-500/20 disabled:border-emerald-500/40 disabled:text-emerald-500/40;
			}

			/* Custom inputs style */
			input, .input {
				@apply transition-all duration-75 outline-none border rounded-md dark:shadow bg-gray-50 border-gray-175
							 disabled:opacity-50 placeholder-gray-300 dark:text-gray-200 dark:placeholder-gray-750 dark:bg-gray-900 dark:focus:border-gray-825
							 dark:border-gray-875 dark:enabled:hover:border-gray-825 hover:border-gray-275 focus:border-gray-275;
			}

			.tooltip {
				@apply z-10 py-0.5 px-2 rounded-md select-none absolute max-h-max min-w-max bg-gray-50 border-gray-150 text-gray-800
							 shadow-md border dark:border-gray-800 dark:bg-gray-850 dark:text-gray-200;
			}
			
			/* https://tailwindcss.com/docs/dark-mode#toggling-dark-mode-manually */
			@custom-variant dark (&:where(.dark, .dark *));

			@theme {
				--font-sans: "DM Sans", sans-serif;
				--font-mono: "JetBrains Mono", monospace;

				--color-gray-975: #0A0A0A;
				--color-gray-950: #0F0F10;
				--color-gray-925: #141415;
				--color-gray-900: #19191A;

				--color-gray-875: #232324;
				--color-gray-850: #28282A;
				--color-gray-825: #2D2D2F;
				--color-gray-800: #323234;

				--color-gray-775: #3C3C3E;
				--color-gray-750: #414144;
				--color-gray-725: #464649;
				--color-gray-700: #4B4B4E;

				--color-gray-675: #555558;
				--color-gray-650: #5A5A5E;
				--color-gray-625: #5F5F63;
				--color-gray-600: #646468;

				--color-gray-575: #69696D;
				--color-gray-550: #737378;
				--color-gray-525: #78787D;
				--color-gray-500: #7D7D82;

				--color-gray-475: #87878C;
				--color-gray-450: #8D8D91;
				--color-gray-425: #929296;
				--color-gray-400: #97979B;

				--color-gray-375: #A1A1A5;
				--color-gray-350: #A7A7AA;
				--color-gray-325: #ACACAF;
				--color-gray-300: #B1B1B4;

				--color-gray-275: #BBBBBE;
				--color-gray-250: #C1C1C3;
				--color-gray-225: #C6C6C8;
				--color-gray-200: #CBCBCD;

				--color-gray-175: #D5D5D7;
				--color-gray-150: #E0E0E1;
				--color-gray-125: #E0E0E1;
				--color-gray-100: #E5E5E6;

				--color-gray-75: #EFEFF0;
				--color-gray-50: #F5F5F5;
				--color-gray-25: #FAFAFA;
			}
		</style>
	</head>

	<body class="w-full dark:bg-gray-950 transition-colors duration-75">
		
		<main class="p-5 pb-8 sm:px-10 max-w-[90rem] mx-auto">
			<div class="flex items-center justify-between mb-5 sm:mb-7">
				<div class="flex items-center space-x-2">
					<!-- Direct iLO link -->
					<div x-data="{ showTooltip: false }" class="relative">
						<a
							href="<?php echo $ILO_DIRECT_URL ?? ('https://' . $ILO_HOST); ?>"
							target="_blank"
							@mouseenter="showTooltip = true"
							@mouseleave="showTooltip = false"
							class="inline-flex items-center justify-center"
						>
							<img
								src="./ilo_page_link_direct.png"
								alt="Open iLO directly"
								class="h-14 w-14 rounded-xl object-contain transform transition-transform duration-75 hover:scale-105 active:scale-90"
								draggable="false"
							>
						</a>

						<p
							x-cloak
							x-show="showTooltip"
							x-transition:enter="transition ease-out duration-100"
							x-transition:enter-start="opacity-0 scale-90"
							x-transition:enter-end="opacity-100 scale-100"
							x-transition:leave="transition ease-in duration-100"
							x-transition:leave-start="opacity-100 scale-100"
							x-transition:leave-end="opacity-0 scale-90"
							class="tooltip origin-left top-0 bottom-0 left-full my-auto ml-2 text-xs whitespace-nowrap"
						>
							Open iLO directly at <?php echo $ILO_HOST; ?>
						</p>
					</div>

					<?php if ($SHOW_ILO_TUNNEL_LINK ?? false): ?>
					<!-- SSH tunnel iLO link -->
					<div x-data="{ showTooltip: false }" class="relative">
						<a
							href="<?php echo $ILO_TUNNEL_URL ?? 'https://localhost:8443/'; ?>"
							target="_blank"
							@mouseenter="showTooltip = true"
							@mouseleave="showTooltip = false"
							class="inline-flex items-center justify-center"
						>
							<img
								src="./ilo_page_link_ssh.png"
								alt="Open iLO through SSH tunnel"
								class="h-14 w-14 rounded-xl object-contain transform transition-transform duration-75 hover:scale-105 active:scale-90"
								draggable="false"
							>
						</a>

						<p
							x-cloak
							x-show="showTooltip"
							x-transition:enter="transition ease-out duration-100"
							x-transition:enter-start="opacity-0 scale-90"
							x-transition:enter-end="opacity-100 scale-100"
							x-transition:leave="transition ease-in duration-100"
							x-transition:leave-start="opacity-100 scale-100"
							x-transition:leave-end="opacity-0 scale-90"
							class="tooltip origin-left top-0 bottom-0 left-full my-auto ml-2 text-xs whitespace-nowrap"
						>
							Open iLO via SSH tunnel at <?php echo $ILO_TUNNEL_URL ?? 'https://localhost:8443/'; ?>
						</p>
					</div>
					<?php endif; ?>
				</div>
				<div class="flex flex-col">
					<h1 class="font-bold text-2xl sm:text-3xl dark:text-white text-black select-none">
						iLO Fans Controller
					</h1>

					<div class="flex items-center justify-between gap-3">
						<p class="text-sm font-normal self-end pb-0.5 italic dark:text-gray-250 text-gray-450 select-none">
							Extended by
							<a
								href="https://github.com/MJ-meo-dmt/ilo-fans-controller"
								target="_blank"
								class="font-semibold dark:text-orange-300 dark:hover:text-orange-200 text-orange-600 hover:text-orange-700 select-text transition-colors duration-75"
							>
								MJ-meo-dmt
							</a>
							<span class="dark:text-gray-650 text-gray-350"> · forked from </span>
							<a
								href="https://github.com/alex3025/ilo-fans-controller"
								target="_blank"
								class="font-semibold dark:text-sky-300 dark:hover:text-sky-200 text-sky-600 hover:text-sky-700 select-text transition-colors duration-75"
							>
								alex3025/ilo-fans-controller 
							</a>
						</p>

						<!-- Version -->
						<a
							href="https://github.com/MJ-meo-dmt/ilo-fans-controller"
							target="_blank"
							class="text-xs select-none font-mono px-2 py-0.5 rounded-full transition-colors duration-75 dark:bg-gray-925 dark:hover:bg-gray-900
									dark:focus:bg-gray-900 dark:text-gray-750 dark:hover:text-gray-650 dark:focus:text-gray-650 bg-gray-25 hover:bg-gray-50
									focus:bg-gray-50 text-gray-450 hover:text-gray-600 focus:text-gray-600"
						>
							v1.0.0
						</a>
					</div>
				</div>

				<div class="mb-3 sm:mb-0">
					<div x-data="{ showTooltip: false }" class="relative" @mouseover.away="showTooltip = false">
						<!-- Theme Switcher -->
						<button
							class="cursor-pointer transition-colors duration-75 p-2 sm:p-1.5 dark:shadow-sm leading-none rounded-full dark:bg-gray-900 dark:text-gray-600
										dark:hover:bg-gray-875 dark:hover:text-gray-500 dark:focus:text-gray-500 dark:focus:bg-gray-875 bg-gray-50 text-gray-300
										hover:bg-gray-75 hover:text-gray-400 focus:bg-gray-75 focus:text-gray-400"
							@click="$store.darkMode.cycleMode()"
							@mouseenter="showTooltip = !showTooltip"
						>
							<template x-if="$store.darkMode.state === 'system'">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
									<path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
								</svg>
							</template>
							<template x-if="$store.darkMode.state === 'light'">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
									<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
								</svg>
							</template>
							<template x-if="$store.darkMode.state === 'dark'">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
									<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
								</svg>
							</template>
						</button>

						<!-- Theme Switcher Tooltip -->
						<div
							x-cloak
							x-show="showTooltip"
							x-transition:enter="transition ease-out duration-100"
							x-transition:enter-start="opacity-0 scale-90"
							x-transition:enter-end="opacity-100 scale-100"
							x-transition:leave="transition ease-in duration-100"
							x-transition:leave-start="opacity-100 scale-100"
							x-transition:leave-end="opacity-0 scale-90"
							class="tooltip origin-right top-0 bottom-0 right-full my-auto mr-2 text-xs"
						>
							<p x-text="$store.darkMode.state.charAt(0).toUpperCase() + $store.darkMode.state.slice(1)"></p>
						</div>
					</div>
				</div>
			</div>

			<div class="flex flex-col sm:flex-row items-center">
				<div x-data="{ showTooltip: false }" class="relative">
					<div class="dark:text-gray-300 text-gray-400 select-none sm:mb-0 mb-2.5 mr-2.5 flex items-center space-x-1">
						<div @mouseenter="showTooltip = !showTooltip" @mouseover.away="showTooltip = false">
							<svg
								xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
								class="w-5 h-5 cursor-help dark:hover:text-gray-175 hover:text-gray-500 transition-colors duration-75"
							>
								<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
							</svg>
						</div>
						<p>
							Presets:
						</p>
					</div>

					<!-- Presets Info Tooltip -->
					<p
						x-cloak
						x-show="showTooltip"
						x-transition:enter="transition ease-out duration-100"
						x-transition:enter-start="opacity-0 scale-90"
						x-transition:enter-end="opacity-100 scale-100"
						x-transition:leave="transition ease-in duration-100"
						x-transition:leave-start="opacity-100 scale-100"
						x-transition:leave-end="opacity-0 scale-90"
						class="tooltip origin-top sm:origin-top-left top-full -left-full sm:left-0 -mt-1.5 sm:mt-1.5 !py-1.5 !px-2.5 text-sm"
					>
						To delete a preset, right click on it.<br>
						On mobile, long press it.
					</p>
				</div>

				<div class="flex flex-wrap w-full gap-2.5">
					<template x-for="(preset, index) in $store.presets.presets" :key="index">
						<button
							class="outline-button flex-1 sm:px-1.5 px-2 py-1.5 sm:py-0.5 min-w-max text-sm"
							:class="$store.presets.currentPreset == index ? '!font-semibold' : ''"
							x-text="preset.name"
							:disabled="$store.app.isLoading"
							@click="$store.presets.applyPreset(index)"
							@contextmenu="$store.presets.onRightClick($event, index)"
						></button>
					</template>
					<button
						class="input cursor-pointer flex-1 border-dashed !bg-transparent sm:px-1.5 px-2 py-1.5 sm:py-0.5 sm:max-w-max text-sm dark:text-gray-875
									dark:hover:text-gray-825 dark:focus:text-gray-825 text-gray-175 hover:text-gray-275 focus:text-gray-275"
						:disabled="$store.app.isLoading"
						@click="$store.presets.newPreset()"
					>
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 mx-auto">
							<path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
						</svg>
					</button>
				</div>
			</div>

			<!-- Top 10 hottest sensors -->
			<div class="mt-7">
				<div class="flex items-center justify-between">
					<h1 class="text-2xl font-semibold select-none dark:text-white text-black">Hottest Sensors</h1>

					<p class="text-xs font-mono dark:text-gray-750 text-gray-350 select-none">
						Top 10 live readings
					</p>
				</div>

				<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-10 gap-2.5 mt-5">
					<template x-for="hot in $store.temperatures.hottest()" :key="hot.name">
						<div class="rounded-md border border-gray-175 dark:border-gray-875 bg-gray-25 dark:bg-gray-925 px-3 py-2">
							<p class="text-xs font-medium truncate dark:text-gray-300 text-gray-500" x-text="hot.name"></p>

							<div class="mt-1">
								<p class="font-mono text-xl font-semibold dark:text-white text-black">
									<span x-text="hot.value"></span>°C
								</p>
							</div>
						</div>
					</template>
				</div>
			</div>

			<div class="mt-7 grid grid-cols-1 xl:grid-cols-2 gap-8 items-start">
				<section>
					<div class="flex items-center justify-between">
						<h1 class="text-2xl font-semibold select-none dark:text-white text-black">Temperatures</h1>

						<p class="text-xs font-mono dark:text-gray-750 text-gray-350 select-none">
							Live Redfish readings
						</p>
					</div>

					<!-- Full sensor table -->
					<div class="mt-5 overflow-hidden rounded-md border border-gray-175 dark:border-gray-875">
						<div class="max-h-[24rem] overflow-y-auto">
							<table class="w-full text-sm">
								<thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-925 border-b border-gray-175 dark:border-gray-875">
									<tr class="text-left text-xs font-mono uppercase tracking-wide dark:text-gray-500 text-gray-400">
										<th class="px-3 py-2 font-medium">Sensor</th>
										<th class="px-3 py-2 font-medium text-center">Status</th>
										<th class="px-3 py-2 font-medium text-right">Temp</th>
										<th class="px-3 py-2 font-medium text-right">Critical</th>
									</tr>
								</thead>

								<tbody class="divide-y divide-gray-100 dark:divide-gray-875">
									<template x-for="sensor in $store.temperatures.visible()" :key="sensor.name">
										<tr class="dark:bg-gray-950 bg-white hover:bg-gray-25 dark:hover:bg-gray-925 transition-colors duration-75">
											<td class="px-3 py-1.5 dark:text-gray-200 text-gray-650 font-medium">
												<span x-text="sensor.name"></span>
											</td>

											<td class="px-3 py-1.5 text-center">
												<span
													class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-mono"
													:class="sensor.status === 'OK'
														? 'bg-emerald-500/10 text-emerald-500'
														: 'bg-red-500/10 text-red-500'"
													x-text="sensor.status">
												</span>
											</td>

											<td class="px-3 py-1.5 text-right font-mono font-semibold dark:text-white text-black">
												<span x-text="sensor.value"></span>°C
											</td>

											<td class="px-3 py-1.5 text-right font-mono dark:text-gray-500 text-gray-400">
												<template x-if="sensor.upper_critical">
													<span><span x-text="sensor.upper_critical"></span>°C</span>
												</template>
												<template x-if="!sensor.upper_critical">
													<span>-</span>
												</template>
											</td>
										</tr>
									</template>
								</tbody>
							</table>
						</div>
					</div>
				</section>

				<section>
					<div class="flex items-center w-full justify-between">
						<h1 class="text-2xl font-semibold select-none dark:text-white text-black">Fans</h1>

						<div class="flex items-center space-x-2">
							<label
								for="edit-all"
								class="text-sm font-medium select-none transition-colors duration-75"
								:class="[ $store.app.editAll ? 'dark:text-gray-300 text-gray-700' : 'dark:text-gray-700 text-gray-300', $store.app.isLoading ? '!opacity-50' : '' ]"
							>
								Edit all
							</label>

							<!-- Switch -->
							<button
								id="edit-all"
								class="input cursor-pointer group h-5 w-10 !rounded-full px-0.5 flex items-center"
								:class="$store.app.editAll ? 'dark:!border-gray-825 !border-gray-275' : ''"
								:disabled="$store.app.isLoading"
								@click="$store.app.editAll = !$store.app.editAll"
							>
								<span
									class="h-3.5 w-3.5 rounded-full transform transition-all duration-100"
									:class="$store.app.editAll ? 'bg-emerald-500 translate-x-5' : 'dark:bg-gray-825 bg-gray-175'"
								></span>
							</button>
						</div>
					</div>

					<div class="space-y-6 w-full mt-5">
						<template x-for="(speed, name) in $store.fans.fans" :key="name">
							<div class="flex flex-col sm:flex-row sm:items-center justify-between group sm:space-y-0 space-y-3">
								<div class="w-full flex items-center space-x-3 transform-opacity duration-75">
									<p
										class="dark:text-gray-200 text-gray-650 group-hover:text-black dark:group-hover:text-white
											dark:group-focus:text-white transition-colors duration-75 font-medium text-lg no-break select-none peer w-12"
										:class="$store.app.isLoading ? 'dark:!text-gray-200' : ''"
										x-text="name"
									></p>

									<input
										type="range"
										min="<?php echo $MINIMUM_FAN_SPEED; ?>"
										max="100"
										class="touch-none w-full flex-1 border appearance-none [&::-webkit-slider-thumb]:transition-colors
													[&::-webkit-slider-thumb]:duration-75 [&::-webkit-slider-thumb]:appearance-none cursor-pointer [&::-webkit-slider-thumb]:bg-emerald-500
													[&::-webkit-slider-thumb]:w-6 [&::-webkit-slider-thumb]:h-6 sm:[&::-webkit-slider-thumb]:w-5 sm:[&::-webkit-slider-thumb]:h-5
													[&::-webkit-slider-thumb]:rounded-full enabled:[&::-webkit-slider-thumb]:hover:bg-emerald-600 dark:enabled:[&::-webkit-slider-thumb]:hover:bg-emerald-400
												enabled:[&::-webkit-slider-thumb]:focus:bg-emerald-600 dark:enabled:[&::-webkit-slider-thumb]:focus:bg-emerald-400
												enabled:[&::-webkit-slider-thumb]:peer-hover:bg-emerald-600 dark:enabled:[&::-webkit-slider-thumb]:peer-hover:bg-emerald-400
													enabled:peer-hover:border-gray-175 dark:enabled:peer-hover:border-gray-825 h-5 sm:h-3.5 !rounded-full disabled:cursor-default"
										:disabled="$store.app.isLoading"
										x-model.number="$store.fans.fans[name]"
										@input="$store.fans.setSpeed(name, $store.fans.fans[name])"
										@pointerdown="$store.fans.startEditing()"
										@pointerup="$store.fans.stopEditingSoon()"
										@touchstart="$store.fans.startEditing()"
										@touchend="$store.fans.stopEditingSoon()"
										@focus="$store.fans.startEditing()"
										@blur="$store.fans.stopEditingSoon()"
									>
								</div>

								<div x-data="{ originalSpeed: speed }" class="select-none items-center flex flex-row sm:flex-row">
									<input
										type="number"
										min="<?php echo $MINIMUM_FAN_SPEED; ?>"
										max="100"
										required
										class="w-16 sm:ml-3 max-w-max px-1.5 py-0.5 font-mono text-gray-800"
										:placeholder="originalSpeed"
										:disabled="$store.app.isLoading"
										x-model.number="$store.fans.fans[name]"
										@input="$store.fans.startEditing(); $store.fans.setSpeed(name, $store.fans.fans[name])"
										@focus="$store.fans.startEditing()"
										@blur="$store.fans.stopEditingSoon()"
									>

									<button
										class="outline-button mx-3 sm:mr-0 px-1 text-sm"
										type="button"
										@click="$store.fans.fans[name] = originalSpeed; $store.fans.setSpeed(name, originalSpeed)"
										:disabled="$store.fans.fans[name] == originalSpeed || $store.app.isLoading"
									>Reset</button>

									<div class="sm:hidden h-px w-full bg-gray-100 dark:bg-gray-900"></div>
								</div>
							</div>
						</template>
					</div>

					<div class="flex flex-col items-center mt-7 sm:space-y-3 space-y-4">
						<button
							type="button"
							class="!outline-none transition-all duration-75 sm:h-10 h-11 sm:w-[15rem] items-center w-full flex justify-center bg-emerald-500 hover:bg-emerald-500/90
										active:bg-emerald-500/80 px-2 py-1.5 rounded-md text-white font-medium select-none cursor-pointer disabled:cursor-progress disabled:!bg-emerald-500/60 disabled:text-opacity-60"
							@click="$store.fans.stopEditingNow(); $store.app.applySpeeds()"
							:disabled="$store.app.isLoading"
						>
							<template x-if="!$store.app.isLoading">
								<div class="flex items-center space-x-1.5">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
										<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
									</svg>
									<span>Set speeds</span>
								</div>
							</template>
							<template x-if="$store.app.isLoading">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5">
									<path fill="currentColor" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25"/>
									<path fill="currentColor" d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z">
										<animateTransform attributeName="transform" dur="0.75s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/>
									</path>
								</svg>
							</template>
						</button>

						<p x-show="$store.app.requestTime" class="text-sm dark:text-gray-750 text-gray-350 select-none font-mono">
							Executed in <span x-text="$store.app.requestTime >= 1000 ? ($store.app.requestTime / 1000).toFixed(2) + 's' : $store.app.requestTime + 'ms'"></span>
						</p>
					</div>
				</section>

				<div class="mt-7 rounded-md border border-gray-175 dark:border-gray-875 bg-gray-25 dark:bg-gray-925 px-4 py-3">
					<div class="flex items-center justify-between">
						<div>
							<h2 class="text-lg font-semibold dark:text-white text-black select-none">Thermal Failsafe</h2>
							<p class="text-xs font-mono dark:text-gray-750 text-gray-350 select-none">
								Independent watchdog settings
							</p>
						</div>

						<label class="flex items-center space-x-2 text-sm dark:text-gray-300 text-gray-600">
							<span>Enabled</span>
							<input type="checkbox" x-model="$store.failsafeSettings.settings.enabled">
						</label>
					</div>

					<div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-4">
						<label class="flex flex-col text-sm dark:text-gray-300 text-gray-600">
							<span class="mb-1">Threshold °C</span>
							<input
								type="number"
								min="30"
								max="120"
								class="px-2 py-1 font-mono"
								x-model.number="$store.failsafeSettings.settings.threshold_c"
							>
						</label>

						<label class="flex flex-col text-sm dark:text-gray-300 text-gray-600">
							<span class="mb-1">Recovery margin °C</span>
							<input
								type="number"
								min="1"
								max="30"
								class="px-2 py-1 font-mono"
								x-model.number="$store.failsafeSettings.settings.recovery_margin_c"
							>
						</label>

						<label class="flex flex-col text-sm dark:text-gray-300 text-gray-600">
							<span class="mb-1">Emergency fan %</span>
							<input
								type="number"
								min="50"
								max="100"
								class="px-2 py-1 font-mono"
								x-model.number="$store.failsafeSettings.settings.fan_speed"
							>
						</label>

						<label class="flex flex-col text-sm dark:text-gray-300 text-gray-600">
							<span class="mb-1">Check interval sec</span>
							<input
								type="number"
								min="5"
								max="60"
								class="px-2 py-1 font-mono"
								x-model.number="$store.failsafeSettings.settings.interval_seconds"
							>
						</label>
					</div>

					<div class="flex items-center justify-between mt-4">
						<p class="text-xs font-mono dark:text-gray-650 text-gray-350" x-text="$store.failsafeSettings.message"></p>

						<button
							type="button"
							class="outline-button px-3 py-1 text-sm"
							@click="$store.failsafeSettings.save()"
						>
							Save failsafe
						</button>
					</div>
				</div>

				<div class="mt-7 rounded-md border border-gray-175 dark:border-gray-875 bg-gray-25 dark:bg-gray-925 px-4 py-3">
					<div class="flex items-center justify-between">
						<div>
							<h2 class="text-lg font-semibold dark:text-white text-black select-none">Startup Preset</h2>
							<p class="text-xs font-mono dark:text-gray-750 text-gray-350 select-none">
								Applied automatically when the server boots
							</p>
						</div>

						<label class="flex items-center space-x-2 text-sm dark:text-gray-300 text-gray-600">
							<span>Enabled</span>
							<input type="checkbox" x-model="$store.startupSettings.settings.enabled">
						</label>
					</div>

					<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
						<label class="flex flex-col text-sm dark:text-gray-300 text-gray-600">
							<span class="mb-1">Preset</span>
							<select
								class="px-2 py-1 font-mono input"
								x-model="$store.startupSettings.settings.preset_name"
							>
								<template x-for="preset in $store.presets.presets" :key="preset.name">
									<option :value="preset.name" x-text="preset.name"></option>
								</template>
							</select>
						</label>

						<label class="flex flex-col text-sm dark:text-gray-300 text-gray-600">
							<span class="mb-1">Startup delay sec</span>
							<input
								type="number"
								min="0"
								max="300"
								class="px-2 py-1 font-mono"
								x-model.number="$store.startupSettings.settings.delay_seconds"
							>
						</label>
					</div>

					<div class="flex items-center justify-between mt-4">
						<p class="text-xs font-mono dark:text-gray-650 text-gray-350" x-text="$store.startupSettings.message"></p>

						<button
							type="button"
							class="outline-button px-3 py-1 text-sm"
							@click="$store.startupSettings.save()"
						>
							Save startup
						</button>
					</div>
				</div>
			</div>
		</main>

		<script lang="javascript">
			document.addEventListener('alpine:init', () => {
				Alpine.store('darkMode', {
					active: false,
					state: null,

					updateState() {
						if (!('theme' in localStorage)) {
							this.state = 'system';
							this.active = window.matchMedia('(prefers-color-scheme: dark)').matches;
						} else {
							this.state = localStorage.theme;
							this.active = localStorage.theme === 'dark';
						}
					},

					cycleMode() {
						switch (this.state) {
							case 'system':
								localStorage.theme = 'light';
								this.state = 'light';
								break;
							case 'light':
								localStorage.theme = 'dark';
								this.state = 'dark';
								break;
							default:
								localStorage.removeItem('theme');
								this.state = 'system';
						}
						this.updateState();
					},

					init() {
						this.updateState();
					}
				});

				Alpine.store('failsafeSettings', {
					settings: <?php echo json_encode(get_failsafe_settings()); ?>,
					message: '',

					async refresh() {
						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=failsafe-settings');

						if (res.ok) {
							this.settings = await res.json();
						}
					},

					async save() {
						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
							method: 'POST',
							body: JSON.stringify({
								action: 'failsafe',
								failsafe: this.settings
							}),
						});

						if (res.ok) {
							this.settings = await res.json();
							this.message = 'Failsafe settings saved.';
						} else {
							this.message = 'Failed to save failsafe settings.';
						}

						setTimeout(() => this.message = '', 3000);
					}
				});

				Alpine.store('startupSettings', {
					settings: <?php echo json_encode(get_startup_settings()); ?>,
					message: '',

					async refresh() {
						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=startup-settings');

						if (res.ok) {
							this.settings = await res.json();
						}
					},

					async save() {
						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
							method: 'POST',
							body: JSON.stringify({
								action: 'startup',
								startup: this.settings
							}),
						});

						if (res.ok) {
							this.settings = await res.json();
							this.message = 'Startup settings saved.';
						} else {
							this.message = 'Failed to save startup settings.';
						}

						setTimeout(() => this.message = '', 3000);
					}
				});

				Alpine.store('temperatures', {
					temperatures: <?php echo json_encode($TEMPERATURES); ?>,

					normalized() {
						return Object.entries(this.temperatures)
							.map(([name, temp]) => ({
								name,
								value: Number(temp.value ?? 0),
								status: temp.status ?? 'Unknown',
								upper_warning: temp.upper_warning ?? null,
								upper_critical: temp.upper_critical ?? null,
							}));
					},

					visible() {
						return this.normalized()
							.filter(sensor =>
								sensor.status !== 'Unknown' &&
								sensor.value > 0
							);
					},

					hottest() {
						return this.visible()
							.slice()
							.sort((a, b) => b.value - a.value)
							.slice(0, 10);
					},

					async refresh() {
						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=temperatures');

						if (res.ok) {
							const updatedTemperatures = await res.json();
							this.temperatures = updatedTemperatures;
						}
					},

					init() {
						setInterval(() => this.refresh(), 5000);
					}
				});

				Alpine.store('fans', {
					fans: <?php echo json_encode($FANS); ?>,
					refreshSeconds: 5,
					isEditing: false,
					editTimer: null,

					startEditing() {
						this.isEditing = true;

						if (this.editTimer) {
							clearTimeout(this.editTimer);
							this.editTimer = null;
						}
					},

					stopEditingSoon() {
						if (this.editTimer)
							clearTimeout(this.editTimer);

						this.editTimer = setTimeout(() => {
							this.isEditing = false;
							this.editTimer = null;
						}, 3000);
					},

					stopEditingNow() {
						if (this.editTimer) {
							clearTimeout(this.editTimer);
							this.editTimer = null;
						}

						this.isEditing = false;
					},

					async refresh() {
						if (this.isEditing || Alpine.store('app')?.isLoading)
							return;

						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=fans');

						if (res.ok) {
							const updatedFans = await res.json();
							this.fans = updatedFans;
							Alpine.store('presets').detectPreset();
						}
					},

					init() {
						setInterval(() => this.refresh(), this.refreshSeconds * 1000);
					},

					setSpeed(fan, rawSpeed) {
						this.startEditing();

						const speed = parseInt(rawSpeed);

						if (speed >= <?php echo $MINIMUM_FAN_SPEED; ?> && speed <= 100) {
							if (Alpine.store('app').editAll) {
								for (const fanName in this.fans)
									this.fans[fanName] = speed;
							} else {
								this.fans[fan] = speed;
							}
						}

						Alpine.store('presets').detectPreset();
					}
				});

				Alpine.store('presets', {
					currentPreset: null,
					presets: <?php echo json_encode($PRESETS); ?>,  // Get the presets from the server

					async updatePresets() {
						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
							method: 'POST',
							body: JSON.stringify({ action: 'presets', presets: this.presets }),
						});

						if (res.ok) {  // Get the updated presets back from the server
							const updatedPresets = await res.json();
							this.presets = updatedPresets;
						}
					},

					applyPreset(index) {
						const preset = this.presets[index];
						const speeds = preset.speeds;
						const fans = Alpine.store('fans').fans;

						if (speeds.length === 1)  // Apply the same speed for all the fans
							Object.keys(fans).forEach(fan => {
								fans[fan] = speeds[0];
							});
						else  // Apply the speed for each fan
							Object.keys(speeds).forEach(i => {
								fans[Object.keys(fans)[i]] = speeds[i];
							});

						this.currentPreset = index;
					},

					newPreset() {
						const name = prompt('Enter the name of the new preset:');
						if (name && name.trim().length > 0) {
							const fans = Alpine.store('fans').fans;
							const speeds = Object.values(fans);

							// Check if a preset with the same speeds already exists
							const existingPreset = this.presets.find(preset => preset.speeds.length === 1 ? speeds.every(speed => speed === preset.speeds[0]) : preset.speeds.join(',') === speeds.join(','));
							if (existingPreset) {
								alert(`A preset with the same speeds already exists (${existingPreset.name}).`);
								return;
							}

							this.presets.push({ name: name.trim(), speeds });
							this.currentPreset = this.presets.length - 1;

							this.updatePresets();  // Save the presets in the presets.json file
						}
					},

					onRightClick(event, index) {  // On right click on a preset, delete it
						event.preventDefault();
						if (confirm('Are you sure you want to delete this preset?')) {
							this.presets.splice(index, 1);
							this.currentPreset = null;

							this.updatePresets();  // Save the presets in the presets.json file
						}
					},

					detectPreset() {  // Try to detect and set the current preset
						const fans = Alpine.store('fans').fans;
						const speeds = Object.values(fans);

						Object.keys(this.presets).forEach(presetIndex => {
							const preset = this.presets[presetIndex];
							const presetSpeeds = preset.speeds;

							if (speeds.every((speed, i) => speed === presetSpeeds[ presetSpeeds.length === speeds.length ? i : 0 ])) {
								this.currentPreset = presetIndex;
								return;
							}
						});
					},

					init() { this.detectPreset(); }
				});

				Alpine.store('app', {
					editAll: false,
					isLoading: false,
					requestTime: null,

					async applySpeeds() {
						this.isLoading = true;
						this.requestTime = null;
						currentTimestamp = new Date().getTime();

						const fans = Alpine.store('fans').fans;

						const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
							method: 'POST',
							body: JSON.stringify({ action: 'fans', fans }),
						});

						if (res.ok) {
							const updatedFans = await res.json();
							Alpine.store('fans').fans = updatedFans;

							this.requestTime = new Date().getTime() - currentTimestamp;
						}

						this.isLoading = false;
					}
				});
			});
		</script>
	</body>
</html>
