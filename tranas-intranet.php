<?php
/**
 * Plugin Name: Tranås Intranät
 * Plugin URI: https://tranas.se
 * Description: Intranätsanpassningar för Tranås kommun. Lägger till shortcodes och funktionalitet för användarhantering.
 * Version: 1.0.0
 * Author: Tranås kommun
 * Author URI: https://tranas.se
 * Text Domain: tranas-intranet
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin-konstanter
define( 'TRANAS_INTRANET_VERSION', '1.0.0' );
define( 'TRANAS_INTRANET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRANAS_INTRANET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Huvudklass för Tranås Intranät-plugin
 */
class Tranas_Intranet {

    /**
     * Singleton-instans
     */
    private static $instance = null;

    /**
     * Hämta singleton-instansen
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Instans av User Meta Fields
     *
     * @var Tranas_User_Meta_Fields
     */
    public $user_meta_fields = null;

    /**
     * Ladda in beroenden
     */
    private function load_dependencies() {
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-user-meta-fields.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-user-profile-shortcode.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-login-required.php';
    }

    /**
     * Initiera hooks
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        
        // Initiera inloggningskrav (kräver inloggning för hela sidan)
        new Tranas_Login_Required();
        
        // Initiera anpassade meta-fält
        $this->user_meta_fields = new Tranas_User_Meta_Fields();
        
        // Initiera shortcodes
        new Tranas_User_Profile_Shortcode( $this->user_meta_fields );
    }

    /**
     * Ladda in assets
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'tranas-intranet-user-profile',
            TRANAS_INTRANET_PLUGIN_URL . 'assets/js/user-profile.js',
            array( 'jquery' ),
            TRANAS_INTRANET_VERSION,
            true
        );

        wp_localize_script(
            'tranas-intranet-user-profile',
            'tranasIntranet',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tranas_user_profile_nonce' ),
                'strings' => array(
                    'saving'  => __( 'Sparar...', 'tranas-intranet' ),
                    'saved'   => __( 'Uppgifterna har sparats!', 'tranas-intranet' ),
                    'error'   => __( 'Ett fel uppstod. Försök igen.', 'tranas-intranet' ),
                ),
            )
        );
    }

    /**
     * Ladda översättningar
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'tranas-intranet',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }
}

// Starta pluginet
function tranas_intranet_init() {
    return Tranas_Intranet::get_instance();
}
add_action( 'plugins_loaded', 'tranas_intranet_init' );

/**
 * Migrera gamla meta-nycklar till nya vid aktivering
 */
function tranas_intranet_activate() {
    global $wpdb;

    // Migrera tranas_card_number -> tranas_quick_number
    $migrations = array(
        'tranas_card_number' => 'tranas_quick_number',
    );

    foreach ( $migrations as $old_key => $new_key ) {
        // Hämta alla användare med det gamla fältet
        $users_with_old_meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $old_key
            )
        );

        foreach ( $users_with_old_meta as $row ) {
            // Kolla om användaren redan har det nya fältet
            $existing = get_user_meta( $row->user_id, $new_key, true );
            
            if ( empty( $existing ) && ! empty( $row->meta_value ) ) {
                // Flytta värdet till nya nyckeln
                update_user_meta( $row->user_id, $new_key, $row->meta_value );
            }
            
            // Ta bort gamla nyckeln
            delete_user_meta( $row->user_id, $old_key );
        }
    }
}
register_activation_hook( __FILE__, 'tranas_intranet_activate' );

