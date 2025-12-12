<?php namespace Model\Travio\Providers;

use Model\Config\AbstractConfigProvider;

class ConfigProvider extends AbstractConfigProvider
{
	public static function migrations(): array
	{
		return [
			[
				'version' => '0.1.0',
				'migration' => function (array $currentConfig, string $env) {
					if ($currentConfig) // Already existing
						return $currentConfig;

					if (defined('INCLUDE_PATH') and file_exists(INCLUDE_PATH . 'app/config/Travio/config.php')) {
						// ModEl 3 migration
						require(INCLUDE_PATH . 'app/config/Travio/config.php');

						$config['auth'] = [
							'id' => $config['license'],
							'key' => $config['key'],
						];

						unset($config['license']);
						unset($config['key']);

						$config['target_types'] = $config['target-types'];
						unset($config['target-types']);

						$config['import']['master_data'] = $config['import']['master-data'];
						unset($config['import']['master-data']);

						$config['import']['payment_methods'] = $config['import']['payment-methods'];
						unset($config['import']['payment-methods']);

						$config['import']['payment_conditions'] = $config['import']['payment-conditions'];
						unset($config['import']['payment-conditions']);

						$config['import']['luggage_types'] = $config['import']['luggage-types'];
						unset($config['import']['luggage-types']);

						return $config;
					}

					return [
						'auth' => [
							'id' => null,
							'key' => null,
						],
						'target_types' => [
							[
								'search' => 'service',
								'type' => 2,
							],
						],
						'dev' => true,
						'import' => [
							'geo' => [
								'import' => true,
							],
							'services' => [
								'import' => true,
								'override' => [
									'name' => true,
									'price' => true,
									'classification' => true,
									'classification_level' => true,
									'min_date' => true,
									'max_date' => true,
									'lat' => true,
									'lng' => true,
									'descriptions' => true,
								],
							],
							'subservices' => [
								'import' => false,
							],
							'packages' => [
								'import' => true,
								'override' => [
									'name' => true,
									'price' => true,
								],
							],
							'tags' => [
								'import' => true,
							],
							'amenities' => [
								'import' => true,
							],
							'airports' => [
								'import' => true,
								'override' => [
									'name' => true,
									'departure' => true,
								],
							],
							'ports' => [
								'import' => true,
								'override' => [
									'name' => true,
									'departure' => true,
								],
							],
							'stations' => [
								'import' => true,
								'override' => [
									'name' => true,
								],
							],
							'master_data' => [
								'import' => false,
								'filters' => [],
							],
							'payment_methods' => [
								'import' => true,
								'filters' => [
									'visible_in' => 1,
								],
							],
							'payment_conditions' => [
								'import' => true,
							],
							'luggage_types' => [
								'import' => true,
							],
							'classifications' => [
								'import' => true,
							],
						],
					];
				},
			],
			[
				'version' => '0.1.13',
				'migration' => function (array $currentConfig, string $env) {
					$currentConfig['share_session_cart'] = true;
					return $currentConfig;
				},
			],
			[
				'version' => '0.3.4',
				'migration' => function (array $currentConfig, string $env) {
					$currentConfig['availability_dates'] = [
						'min_stay_from' => 'in',
						'out_weekdays_from' => 'out',
					];
					return $currentConfig;
				},
			],
			[
				'version' => '0.4.0',
				'migration' => function (array $currentConfig, string $env) {
					unset($currentConfig['availability_dates']);
					return $currentConfig;
				},
			],
			[
				'version' => '0.4.2',
				'migration' => function (array $currentConfig, string $env) {
					$currentConfig['import']['airports']['only_used'] = true;
					return $currentConfig;
				},
			],
		];
	}

	public static function templating(): array
	{
		return [
			'auth.id' => 'int',
			'auth.key',
			'dev' => 'bool',
		];
	}
}
