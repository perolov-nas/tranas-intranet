<?php
/**
 * Klass för att kräva inloggning på hela webbplatsen
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hanterar inloggningskrav för hela webbplatsen
 */
class Tranas_Login_Required {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'check_login_status' ) );
    }

    /**
     * Kontrollera om användaren är inloggad
     */
    public function check_login_status() {
        // Hoppa över kontroll för admin-sidor
        if ( is_admin() ) {
            return;
        }

        // Hoppa över för inloggnings- och registreringssidor
        if ( $this->is_login_page() ) {
            return;
        }

        // Hoppa över för AJAX-anrop
        if ( wp_doing_ajax() ) {
            return;
        }

        // Hoppa över för REST API-anrop
        if ( $this->is_rest_api_request() ) {
            return;
        }

        // Hoppa över för cron-jobb
        if ( wp_doing_cron() ) {
            return;
        }

        // Om användaren inte är inloggad, visa splash-screen
        if ( ! is_user_logged_in() ) {
            $this->display_splash_screen();
            exit;
        }
    }

    /**
     * Kontrollera om det är inloggningssidan
     *
     * @return bool
     */
    private function is_login_page() {
        $login_pages = array(
            'wp-login.php',
            'wp-register.php',
        );

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

        foreach ( $login_pages as $page ) {
            if ( strpos( $request_uri, $page ) !== false ) {
                return true;
            }
        }

        // Kolla även för anpassade inloggningssidor via filter
        $custom_login_pages = apply_filters( 'tranas_intranet_login_pages', array() );
        foreach ( $custom_login_pages as $page_id ) {
            if ( is_page( $page_id ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kontrollera om det är en REST API-begäran
     *
     * @return bool
     */
    private function is_rest_api_request() {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        
        return strpos( $request_uri, rest_get_url_prefix() ) !== false;
    }

    /**
     * Visa splash-screen för ej inloggade användare
     */
    private function display_splash_screen() {
        $login_url = wp_login_url( home_url( $_SERVER['REQUEST_URI'] ?? '' ) );
        $site_name = get_bloginfo( 'name' );
        $site_description = get_bloginfo( 'description' );
        $logo_url = get_site_icon_url( 512 );

        // Tillåt anpassning via filter
        $login_url = apply_filters( 'tranas_intranet_login_url', $login_url );
        $splash_title = apply_filters( 'tranas_intranet_splash_title', $site_name );
        $splash_description = apply_filters( 'tranas_intranet_splash_description', __( 'Välkommen till vårt intranät. Logga in för att fortsätta.', 'tranas-intranet' ) );
        $splash_button_text = apply_filters( 'tranas_intranet_splash_button_text', __( 'Logga in', 'tranas-intranet' ) );

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( $splash_title ); ?> - <?php esc_html_e( 'Logga in', 'tranas-intranet' ); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="tranas-splash-screen">
            <div class="tranas-splash-container">
                <div class="tranas-splash-logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
                </div>

                <div class="tranas-splash-card">
                    <h1 class="tranas-splash-title"><?php echo esc_html( $splash_title ); ?></h1>
                    <p class="tranas-splash-description"><?php echo esc_html( $splash_description ); ?></p>
                    
                    <a href="<?php echo esc_url( $login_url ); ?>" class="tranas-splash-button">
                        <?php echo esc_html( $splash_button_text ); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>

                <div class="tranas-splash-footer">
                    <?php
                    printf(
                        /* translators: %s: site name */
                        esc_html__( '© %1$s %2$s. Alla rättigheter förbehållna.', 'tranas-intranet' ),
                        esc_html( date( 'Y' ) ),
                        esc_html( $site_name )
                    );
                    ?>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}

