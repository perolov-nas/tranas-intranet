<?php
/**
 * Plugin Name: Tranås Intranät
 * Plugin URI: https://tranas.se
 * Description: Intranätsanpassningar för Tranås kommun. Lägger till shortcodes och funktionalitet för användarhantering.
 * Version: 1.2.0
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
define( 'TRANAS_INTRANET_VERSION', '1.2.0' );
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
     * Instans av News Feed Preferences
     *
     * @var Tranas_News_Feed_Preferences
     */
    public $news_feed_preferences = null;

    /**
     * Instans av System Post Type
     *
     * @var Tranas_System_Post_Type
     */
    public $system_post_type = null;

    /**
     * Instans av System Preferences
     *
     * @var Tranas_System_Preferences
     */
    public $system_preferences = null;

    /**
     * Ladda in beroenden
     */
    private function load_dependencies() {
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-user-meta-fields.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-user-profile-shortcode.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-login-required.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-news-feed-preferences.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-news-feed-shortcode.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-system-post-type.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-system-preferences.php';
        require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-system-shortcode.php';
    }

    /**
     * Initiera hooks
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Registrera ACF JSON-sökvägar
        add_filter( 'acf/settings/load_json', array( $this, 'add_acf_json_load_point' ) );
        add_filter( 'acf/settings/save_json', array( $this, 'set_acf_json_save_point' ) );
        
        // Initiera inloggningskrav (kräver inloggning för hela sidan)
        new Tranas_Login_Required();
        
        // Initiera anpassade meta-fält
        $this->user_meta_fields = new Tranas_User_Meta_Fields();
        
        // Initiera shortcodes
        new Tranas_User_Profile_Shortcode( $this->user_meta_fields );
        
        // Initiera nyhetsflödes-funktionalitet
        $this->news_feed_preferences = new Tranas_News_Feed_Preferences();
        new Tranas_News_Feed_Shortcode( $this->news_feed_preferences );

        // Initiera System-posttypen
        $this->system_post_type = new Tranas_System_Post_Type();

        // Initiera System-preferenser
        $this->system_preferences = new Tranas_System_Preferences();

        // Initiera System-shortcode
        new Tranas_System_Shortcode( $this->system_preferences );
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

        wp_enqueue_script(
            'tranas-intranet-news-feed',
            TRANAS_INTRANET_PLUGIN_URL . 'assets/js/news-feed.js',
            array(),
            TRANAS_INTRANET_VERSION,
            true
        );

        wp_enqueue_script(
            'tranas-intranet-system-preferences',
            TRANAS_INTRANET_PLUGIN_URL . 'assets/js/system-preferences.js',
            array(),
            TRANAS_INTRANET_VERSION,
            true
        );

        // Lokalisera scripten med gemensam data
        $localize_data = array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'tranas_user_profile_nonce' ),
            'strings' => array(
                'saving'  => __( 'Sparar...', 'tranas-intranet' ),
                'saved'   => __( 'Uppgifterna har sparats!', 'tranas-intranet' ),
                'error'   => __( 'Ett fel uppstod. Försök igen.', 'tranas-intranet' ),
            ),
        );

        wp_localize_script( 'tranas-intranet-user-profile', 'tranasIntranet', $localize_data );
        wp_localize_script( 'tranas-intranet-news-feed', 'tranasIntranet', $localize_data );
        wp_localize_script( 'tranas-intranet-system-preferences', 'tranasIntranet', $localize_data );
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

    /**
     * Lägg till ACF JSON-laddningspunkt
     *
     * @param array $paths Befintliga sökvägar.
     * @return array Uppdaterade sökvägar.
     */
    public function add_acf_json_load_point( $paths ) {
        $paths[] = TRANAS_INTRANET_PLUGIN_DIR . 'acf-json';
        return $paths;
    }

    /**
     * Sätt ACF JSON-sparningspunkt
     *
     * @param string $path Befintlig sökväg.
     * @return string Uppdaterad sökväg.
     */
    public function set_acf_json_save_point( $path ) {
        return TRANAS_INTRANET_PLUGIN_DIR . 'acf-json';
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

    // Registrera posttypen och flusha permalänkar
    require_once TRANAS_INTRANET_PLUGIN_DIR . 'includes/class-system-post-type.php';
    $system_post_type = new Tranas_System_Post_Type();
    $system_post_type->register_post_type();
    flush_rewrite_rules();

    // Importera standardsystem om det inte redan gjorts
    tranas_intranet_import_default_systems();
}
register_activation_hook( __FILE__, 'tranas_intranet_activate' );

/**
 * Importera standardsystem från SYSTEMS.md
 */
function tranas_intranet_import_default_systems() {
    // Kolla om importen redan har körts
    if ( get_option( 'tranas_systems_imported' ) ) {
        return;
    }

    $systems_file = TRANAS_INTRANET_PLUGIN_DIR . 'SYSTEMS.md';

    if ( ! file_exists( $systems_file ) ) {
        return;
    }

    $content = file_get_contents( $systems_file );
    $lines   = explode( "\n", $content );

    foreach ( $lines as $line ) {
        // Ta bort "- " från början och trimma
        $system_name = trim( ltrim( $line, '-' ) );

        // Hoppa över tomma rader
        if ( empty( $system_name ) ) {
            continue;
        }

        // Kolla om systemet redan finns
        $existing = get_posts( array(
            'post_type'   => 'tranas_system',
            'title'       => $system_name,
            'post_status' => 'any',
            'numberposts' => 1,
        ) );

        if ( ! empty( $existing ) ) {
            continue;
        }

        // Skapa systemposten
        wp_insert_post( array(
            'post_type'   => 'tranas_system',
            'post_title'  => $system_name,
            'post_status' => 'publish',
        ) );
    }

    // Markera att importen är klar
    update_option( 'tranas_systems_imported', true );
}

/**
 * Städa upp vid avaktivering
 */
function tranas_intranet_deactivate() {
    // Flusha permalänkar för att ta bort custom post type regler
    flush_rewrite_rules();

    // Ta bort import-flaggan så systemen kan importeras igen vid nästa aktivering
    delete_option( 'tranas_systems_imported' );
}
register_deactivation_hook( __FILE__, 'tranas_intranet_deactivate' );

