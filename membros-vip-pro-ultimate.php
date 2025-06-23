<?php
/**
 * Plugin Name:       Membros VIP Pro Ultimate by AR
 * Plugin URI:        https://github.com/alexrudson/membros-vip-pro-ultimate
 * Description:       O "canivete suíço" para gerenciamento de membros, grupos, conteúdo restrito, convites, arquivos, widgets e segurança.
 * Version:           3.3.0 (Final Secure Version)
 * Author:            Alex Rudson
 * Author URI:        https://alexrudson.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       membros-vip-pro
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) die;

define( 'MVPU_VERSION', '3.3.0' );
define( 'MVPU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

final class Membros_VIP_Pro_Ultimate {
    private static $instance;

    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }

    private function setup_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    public function init_plugin() {
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-cpt.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-settings.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-user-profile.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-invitations.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-content-restriction.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-file-access.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-widget-control.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-reports.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-templates.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-security.php'; // Adicionado

        new MVPU_CPT();
        new MVPU_Settings();
        new MVPU_User_Profile();
        new MVPU_Invitations();
        $GLOBALS['mvpu_content_restriction'] = new MVPU_Content_Restriction();
        new MVPU_File_Access();
        new MVPU_Widget_Control();
        new MVPU_Reports();
        new MVPU_Templates();
        new MVPU_Security(); // Adicionado
    }

    public function activate() {
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-cpt.php';
        require_once MVPU_PLUGIN_DIR . 'includes/class-mvpu-file-access.php';
        $cpt_manager = new MVPU_CPT();
        $cpt_manager->register_all_cpts();
        MVPU_File_Access::add_rewrite_rules();
        flush_rewrite_rules();
        MVPU_File_Access::create_secure_directory();
        if ( ! wp_next_scheduled( 'mvpu_daily_expiration_check' ) ) {
            wp_schedule_event( time(), 'daily', 'mvpu_daily_expiration_check' );
        }
    }

    public function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook( 'mvpu_daily_expiration_check' );
    }
    
    private function __construct() {}
}

function MVPU_run() {
    return Membros_VIP_Pro_Ultimate::instance();
}
MVPU_run();