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
 *
 * Responsible for wiring up CPTs, tables, admin UI, reports,
 * automation, and any other core services.
 */
class Plugin {

	/**
	 * Singleton-like flag.
	 *
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Initialise the plugin.
	 *
	 * Called from satori-audit.php on plugins_loaded.
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
	 * Plugin activation tasks.
	 *
	 * Called from the register_activation_hook in satori-audit.php.
	 *
	 * @return void
	 */
	public static function activate() {
		// Ensure CPT and tables are registered on activation.
		if ( class_exists( Cpt::class ) && method_exists( Cpt::class, 'register' ) ) {
			Cpt::register();
		}

		if ( class_exists( Tables::class ) && method_exists( Tables::class, 'install' ) ) {
			Tables::install();
		}

		// Flush rewrite rules if CPT exists.
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Attach hooks for the runtime lifecycle.
	 *
	 * @return void
	 */
	protected function hooks() {
		// Register CPT and related structures.
		add_action(
			'init',
			static function () {
				if ( class_exists( Cpt::class ) && method_exists( Cpt::class, 'register' ) ) {
					Cpt::register();
				}
			}
		);

		// Ensure DB tables are available.
		add_action(
			'init',
			static function () {
				if ( class_exists( Tables::class ) && method_exists( Tables::class, 'maybe_upgrade' ) ) {
					Tables::maybe_upgrade();
				}
			}
		);

                // Admin UI (menus + screens).
                if ( is_admin() ) {
                        if ( class_exists( Screen_Settings::class ) && method_exists( Screen_Settings::class, 'init' ) ) {
                                Screen_Settings::init();
                        }

                        if ( class_exists( Admin::class ) && method_exists( Admin::class, 'init' ) ) {
                                Admin::init();
                        }
                }

		// Reports engine.
		add_action(
			'init',
			static function () {
				if ( class_exists( Reports::class ) && method_exists( Reports::class, 'init' ) ) {
					Reports::init();
				}
			}
		);

		// Automation (cron etc).
		add_action(
			'init',
			static function () {
				if ( class_exists( Automation::class ) && method_exists( Automation::class, 'init' ) ) {
					Automation::init();
				}
			}
		);
	}


        /**
         * Retrieve a stored setting from the consolidated option.
         *
         * @param string $key     Setting key.
         * @param mixed  $default Default value if not set.
         * @return mixed
         */
        public static function get_setting( string $key, $default = null ) {
                $settings = get_option( 'satori_audit_settings', array() );
                $settings = is_array( $settings ) ? $settings : array();

                return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
        }

        /**
         * Update a single setting while preserving existing values.
         *
         * @param string $key   Setting key.
         * @param mixed  $value Value to store.
         * @return void
         */
        public static function update_setting( string $key, $value ): void {
                $settings = get_option( 'satori_audit_settings', array() );
                $settings = is_array( $settings ) ? $settings : array();

                $settings[ $key ] = $value;

                update_option( 'satori_audit_settings', $settings );
        }
}
