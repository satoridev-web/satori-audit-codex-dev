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
}
