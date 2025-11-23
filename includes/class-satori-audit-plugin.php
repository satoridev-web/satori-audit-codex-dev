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
		if ( is_admin() && class_exists( Admin::class ) && method_exists( Admin::class, 'init' ) ) {
			Admin::init();
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

            $value = self::traverse_settings( $settings, $key );

            return null !== $value ? $value : $default;
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

            $settings = self::set_traversed_value( $settings, $key, $value );

            update_option( 'satori_audit_settings', $settings );
    }

    /**
     * Traverse a settings array using dot notation.
     *
     * @param array  $settings Stored settings array.
     * @param string $path     Dot-notated path or simple key.
     * @return mixed|null
     */
    protected static function traverse_settings( array $settings, string $path ) {
            if ( '' === $path ) {
                    return null;
            }

            if ( false === strpos( $path, '.' ) ) {
                    return array_key_exists( $path, $settings ) ? $settings[ $path ] : null;
            }

            $segments = explode( '.', $path );
            $value    = $settings;

            foreach ( $segments as $segment ) {
                    if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
                            $value = $value[ $segment ];
                            continue;
                    }

                    return null;
            }

            return $value;
    }

    /**
     * Set a value in the settings array using dot notation.
     *
     * @param array  $settings Settings array.
     * @param string $path     Dot-notated path or simple key.
     * @param mixed  $value    Value to set.
     * @return array
     */
    protected static function set_traversed_value( array $settings, string $path, $value ): array {
            if ( '' === $path ) {
                    return $settings;
            }

            if ( false === strpos( $path, '.' ) ) {
                    $settings[ $path ] = $value;
                    return $settings;
            }

            $segments = explode( '.', $path );
            $target   =& $settings;

            foreach ( $segments as $segment ) {
                    if ( ! isset( $target[ $segment ] ) || ! is_array( $target[ $segment ] ) ) {
                            $target[ $segment ] = array();
                    }

                    $target =& $target[ $segment ];
            }

            $target = $value;

            return $settings;
    }
}
