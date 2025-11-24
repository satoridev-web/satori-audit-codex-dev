<?php
/**
 * Core plugin orchestrator for SATORI Audit.
 *
 * @package Satori_Audit
 */

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Singleton flag.
	 *
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Option name for settings.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION = 'satori_audit_settings';

	/**
	 * Database version option key.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'satori_audit_db_version';

	/**
	 * Initialise the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		if ( true === self::$booted ) {
			return;
		}

		self::$booted = true;

		$instance = new self();
		$instance->hooks();
	}

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		// Register CPT.
		if ( class_exists( Cpt::class ) ) {
			if ( method_exists( Cpt::class, 'register' ) ) {
				Cpt::register();
			} elseif ( method_exists( Cpt::class, 'init' ) ) {
				Cpt::init();
			}
		}

		// Install / upgrade DB tables.
		if ( class_exists( Tables::class ) ) {
			if ( method_exists( Tables::class, 'install' ) ) {
				Tables::install();
			} elseif ( method_exists( Tables::class, 'maybe_upgrade' ) ) {
				Tables::maybe_upgrade();
			}
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Attach runtime hooks.
	 *
	 * @return void
	 */
	protected function hooks() {
		// Register CPT.
		add_action(
			'init',
			static function () {
				if ( class_exists( Cpt::class ) ) {
					if ( method_exists( Cpt::class, 'register' ) ) {
						Cpt::register();
					} elseif ( method_exists( Cpt::class, 'init' ) ) {
						Cpt::init();
					}
				}
			}
		);

		// Ensure DB tables are up to date.
		add_action(
			'init',
			static function () {
				if ( class_exists( Tables::class ) && method_exists( Tables::class, 'maybe_upgrade' ) ) {
					Tables::maybe_upgrade();
				}
			}
		);

		// Admin UI.
		if ( is_admin() && class_exists( Admin::class ) && method_exists( Admin::class, 'init' ) ) {
			Admin::init();
		}

		// Reports engine bootstrap.
                add_action(
                        'init',
                        static function () {
                                if ( class_exists( Reports::class ) && method_exists( Reports::class, 'init' ) ) {
                                        Reports::init();
                                }
                        }
                );

                // Notifications.
                add_action(
                        'init',
                        static function () {
                                if ( class_exists( Notifications::class ) && method_exists( Notifications::class, 'init' ) ) {
                                        Notifications::init();
                                }
                        }
                );

                // Automation / cron.
                add_action(
                        'init',
                        static function () {
                                if ( class_exists( Automation::class ) && method_exists( Automation::class, 'init' ) ) {
					Automation::init();
				}
			}
		);
	}

	/* -------------------------------------------------
	 * Settings helpers
	 * -------------------------------------------------*/

	/**
	 * Return all plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return $settings;
	}

	/**
	 * Get a single setting by key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Update a single setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function update_setting( $key, $value ) {
		$settings          = self::get_settings();
		$settings[ $key ]  = $value;

		update_option( self::SETTINGS_OPTION, $settings );
	}
}
