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
        if ( $this->is_admin_request() ) {
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
        // Kontrollera REST_REQUEST konstanten
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }

        // Kontrollera om det är en REST-förfrågan via URI
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        
        // Kontrollera standard REST-prefix
        $rest_prefix = rest_get_url_prefix();
        if ( strpos( $request_uri, '/' . $rest_prefix . '/' ) !== false ) {
            return true;
        }

        // Kontrollera wp-json (fallback)
        if ( strpos( $request_uri, '/wp-json/' ) !== false ) {
            return true;
        }

        return false;
    }

    /**
     * Kontrollera om det är en admin-relaterad förfrågan
     *
     * @return bool
     */
    private function is_admin_request() {
        // Standard WordPress admin-kontroll
        if ( is_admin() ) {
            return true;
        }

        // Kontrollera SCRIPT_NAME för admin-sidor
        $script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
        if ( strpos( $script_name, '/wp-admin/' ) !== false ) {
            return true;
        }

        // Kontrollera REQUEST_URI för admin-sidor
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( strpos( $request_uri, '/wp-admin/' ) !== false ) {
            return true;
        }

        return false;
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
        $splash_button_text = apply_filters( 'tranas_intranet_splash_button_text', __( 'Logga in på internwebben', 'tranas-intranet' ) );

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
                    <svg version="1.1" id="Lager_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 180 78" style="enable-background:new 0 0 180 78;" xml:space="preserve" aria-hidden="true" focusable="false" role="img">
                        <title>Tranås kommun logotyp</title>
                        <g>
                            <defs>
                                <rect id="SVGID_1_" width="180" height="78"/>
                            </defs>
                            <clipPath id="SVGID_00000054230319327971280830000017564410788539118481_">
                                <use xlink:href="#SVGID_1_"  style="overflow:visible;"/>
                            </clipPath>
                            <g style="clip-path:url(#SVGID_00000054230319327971280830000017564410788539118481_);">
                                <path class="st1" d="M78.6,22.5h-4.8v-3.3h13.4v3.3h-4.8v14h-3.9L78.6,22.5L78.6,22.5z"/>
                                <path class="st1" d="M89.9,19.2h6.2c3.7,0,6.7,1.3,6.7,5.4c0,4-3,5.7-6.7,5.7h-2.3v6.2h-3.9L89.9,19.2L89.9,19.2z M95.8,27.2
                                    c2.1,0,3.2-0.9,3.2-2.6c0-1.7-1.1-2.3-3.2-2.3h-2v4.9H95.8z M95.4,29.2l2.7-2.5l5.5,9.7h-4.4L95.4,29.2L95.4,29.2z"/>
                                <path class="st1" d="M109.2,19.2h4.7l5.4,17.3h-4.1l-2.3-8.7c-0.5-1.7-0.9-3.8-1.4-5.6h-0.1c-0.4,1.8-0.9,3.8-1.4,5.6l-2.3,8.7h-4
                                    L109.2,19.2L109.2,19.2z M107.4,29.3h8.3v3h-8.3V29.3z"/>
                                <path class="st1" d="M121,19.2h4l4.5,8.6l1.7,3.8h0.1c-0.2-1.8-0.5-4.2-0.5-6.3v-6.1h3.7v17.3h-4l-4.5-8.6l-1.7-3.8h-0.1
                                    c0.2,1.9,0.5,4.2,0.5,6.3v6.1H121V19.2z"/>
                                <path class="st1" d="M141.9,19.2h4.7l5.4,17.3h-4.1l-2.3-8.7c-0.5-1.7-0.9-3.8-1.4-5.6h-0.1c-0.4,1.8-0.9,3.8-1.4,5.6l-2.3,8.7h-4
                                    L141.9,19.2L141.9,19.2z M140,29.3h8.3v3H140V29.3z M141.1,15.4c0-1.6,1.2-2.6,3-2.6c1.8,0,3,1,3,2.6c0,1.6-1.2,2.6-3,2.6
                                    C142.3,18,141.1,17,141.1,15.4z M145.2,15.4c0-0.7-0.5-1.2-1.1-1.2c-0.6,0-1.1,0.5-1.1,1.2c0,0.7,0.5,1.2,1.1,1.2
                                    S145.2,16.1,145.2,15.4z"/>
                                <path class="st1" d="M152.7,34.3l2.2-2.7c1.2,1.1,2.8,1.8,4.2,1.8c1.6,0,2.4-0.6,2.4-1.7c0-1.1-1-1.4-2.5-2.1l-2.2-1
                                    c-1.8-0.7-3.5-2.2-3.5-4.7c0-2.9,2.6-5.1,6.2-5.1c2,0,4.1,0.8,5.6,2.3l-2,2.5c-1.1-0.9-2.2-1.4-3.6-1.4c-1.3,0-2.2,0.6-2.2,1.5
                                    c0,1.1,1.1,1.4,2.6,2l2.2,0.9c2.1,0.9,3.4,2.3,3.4,4.7c0,2.9-2.4,5.4-6.5,5.4C156.9,36.8,154.5,36,152.7,34.3L152.7,34.3z"/>
                                <path class="st1" d="M74.6,43.5h3.9v7.1h0.1l5.2-7.1h4.3l-5.2,6.9L89,60.7h-4.3l-4.2-7.3l-2,2.7v4.6h-3.9V43.5L74.6,43.5z"/>
                                <path class="st1" d="M89.4,52c0-5.6,3.2-8.9,7.9-8.9c4.7,0,7.9,3.3,7.9,8.9c0,5.6-3.2,9-7.9,9C92.6,61,89.4,57.7,89.4,52z
                                    M101.1,52c0-3.5-1.5-5.5-3.8-5.5c-2.4,0-3.8,2-3.8,5.5c0,3.5,1.5,5.7,3.8,5.7C99.6,57.7,101.1,55.5,101.1,52z"/>
                                <path class="st1" d="M108.3,43.5h4.3l2.8,7.7c0.3,1,0.7,2.1,1,3.2h0.1c0.3-1.1,0.6-2.2,1-3.2l2.7-7.7h4.3v17.3h-3.6v-6.3
                                    c0-1.7,0.3-4.2,0.5-5.9h-0.1l-1.4,4.1l-2.4,6.6h-2.2l-2.5-6.6l-1.4-4.1h-0.1c0.2,1.7,0.5,4.2,0.5,5.9v6.3h-3.5L108.3,43.5
                                    L108.3,43.5z"/>
                                <path class="st1" d="M128.6,43.5h4.3l2.8,7.7c0.3,1,0.6,2.1,1,3.2h0.1c0.3-1.1,0.6-2.2,1-3.2l2.7-7.7h4.3v17.3h-3.6v-6.3
                                    c0-1.7,0.3-4.2,0.5-5.9h-0.1l-1.4,4.1l-2.4,6.6h-2.2l-2.5-6.6l-1.4-4.1h-0.1c0.2,1.7,0.5,4.2,0.5,5.9v6.3h-3.5L128.6,43.5
                                    L128.6,43.5z"/>
                                <path class="st1" d="M148.7,52.7v-9.3h3.9v9.7c0,3.4,1.1,4.6,3,4.6c1.9,0,3.1-1.2,3.1-4.6v-9.7h3.8v9.3c0,5.8-2.4,8.3-6.8,8.3
                                    C151.1,61,148.7,58.5,148.7,52.7z"/>
                                <path class="st1" d="M166.4,43.5h4L175,52l1.7,3.8h0.1c-0.2-1.8-0.5-4.2-0.5-6.3v-6.1h3.7v17.3h-4l-4.5-8.6l-1.7-3.8h-0.1
                                    c0.2,1.9,0.5,4.2,0.5,6.3v6.1h-3.7V43.5z"/>
                                <path class="st1" d="M24.1,9.3c-2.5,0.1-5,0.3-7.5,0.6c0.1,0.8,0.2,1.7,0.2,2.5c2.5-0.2,4.9-0.4,7.4-0.6
                                    C24.2,11,24.1,10.1,24.1,9.3L24.1,9.3z"/>
                                <path class="st1" d="M44,6.4c-1.2-0.1-2.4-0.1-3.6-0.2c0,0.7-0.1,1.4-0.1,2c1.2,0,2.4,0.1,3.6,0.2C43.9,7.8,44,7.1,44,6.4L44,6.4z
                                    "/>
                                <path class="st1" d="M52.8,7.2c-1.2-0.1-2.4-0.3-3.6-0.4c-0.1,0.7-0.1,1.4-0.2,2c1.2,0.1,2.4,0.2,3.6,0.4
                                    C52.7,8.5,52.8,7.9,52.8,7.2L52.8,7.2z"/>
                                <path class="st1" d="M17.8,8.8c-0.1-0.7-0.1-1.4-0.2-2C16.4,6.9,15.2,7,14,7.2c0.1,0.7,0.1,1.4,0.2,2C15.4,9.1,16.6,8.9,17.8,8.8
                                    L17.8,8.8z"/>
                                <path class="st1" d="M26.4,8.3c0-0.7-0.1-1.4-0.1-2c-1.2,0-2.4,0.1-3.6,0.2c0,0.7,0.1,1.4,0.1,2C24,8.4,25.2,8.3,26.4,8.3
                                    L26.4,8.3z"/>
                                <path class="st1" d="M50.2,9.9c-2.5-0.2-5-0.4-7.5-0.6c0,0.8-0.1,1.7-0.1,2.5c2.5,0.1,4.9,0.3,7.4,0.6C50,11.5,50.1,10.7,50.2,9.9
                                    L50.2,9.9z"/>
                                <path class="st1" d="M13,16.4c2.4-0.3,4.8-0.5,7.2-0.7c-0.1-0.8-0.1-1.7-0.2-2.5c-2.5,0.2-4.9,0.4-7.3,0.7
                                    C12.8,14.7,12.9,15.5,13,16.4z"/>
                                <path class="st1" d="M28.6,15.2c0-0.8,0-1.7-0.1-2.5c-2.5,0.1-4.9,0.2-7.4,0.4c0.1,0.8,0.1,1.7,0.2,2.5
                                    C23.8,15.4,26.2,15.3,28.6,15.2z"/>
                                <path class="st1" d="M37,15.2c0-0.8,0-1.7,0.1-2.5c-2.5-0.1-4.9-0.1-7.4,0c0,0.8,0,1.7,0.1,2.5C32.2,15.1,34.6,15.1,37,15.2z"/>
                                <path class="st1" d="M45.6,13.1c-2.5-0.2-4.9-0.3-7.4-0.4c0,0.8,0,1.7-0.1,2.5c2.4,0.1,4.8,0.2,7.3,0.4
                                    C45.5,14.7,45.5,13.9,45.6,13.1z"/>
                                <path class="st1" d="M54.1,13.9c-2.4-0.3-4.9-0.5-7.3-0.7c-0.1,0.8-0.1,1.7-0.2,2.5c2.4,0.2,4.8,0.4,7.2,0.7
                                    C53.9,15.5,54,14.7,54.1,13.9z"/>
                                <path class="st1" d="M54.7,16.5c1.3,0.2,2.6,0.3,3.9,0.5c0.1-0.8,0.3-1.7,0.4-2.5c-1.3-0.2-2.6-0.4-4-0.6
                                    C55,14.8,54.9,15.7,54.7,16.5z"/>
                                <path class="st1" d="M11.7,14c-1.3,0.2-2.6,0.4-4,0.6C7.9,15.4,8,16.2,8.2,17c1.3-0.2,2.6-0.4,3.9-0.5C12,15.7,11.9,14.8,11.7,14z
                                    "/>
                                <path class="st1" d="M11.1,13c1.5-0.2,3-0.4,4.6-0.5c-0.1-0.8-0.2-1.7-0.3-2.5c-1,0.1-2,0.2-3,0.3c0,0.3,0.1,0.6,0.1,0.9
                                    C12,11.8,11.6,12.4,11.1,13z"/>
                                <path class="st1" d="M51.4,10c-0.1,0.8-0.2,1.7-0.3,2.5c1.6,0.2,3.1,0.3,4.7,0.6c-0.5-0.6-1-1.2-1.5-1.8c0-0.3,0.1-0.6,0.1-0.9
                                    C53.4,10.2,52.4,10.1,51.4,10L51.4,10z"/>
                                <path class="st1" d="M41.5,9.3c-0.9,0-1.8-0.1-2.7-0.1c0,0.3,0,0.6,0,0.9c-0.6,0.5-1.1,1-1.7,1.6c1.4,0,2.8,0.1,4.2,0.1
                                    C41.4,10.9,41.5,10.1,41.5,9.3z"/>
                                <path class="st1" d="M11.4,11c-0.2-1.9-0.5-3.8-0.7-5.7C11,5.1,11.5,5,11.9,4.8c-0.1-1.2-0.3-2.3-0.4-3.5
                                    c-0.9,0.1-1.8,0.2-2.6,0.3C8.8,2,8.9,2.4,9,2.9C8.4,2.9,7.8,3,7.2,3.1C7.2,2.7,7.1,2.3,7.1,1.9C6.2,2,5.3,2.1,4.4,2.3
                                    c0.1,0.4,0.1,0.8,0.2,1.2C4,3.6,3.4,3.7,2.8,3.8C2.8,3.4,2.7,3,2.6,2.6C1.7,2.7,0.9,2.9,0,3c0.2,1.2,0.4,2.3,0.6,3.5
                                    c0.4,0,0.9,0,1.4,0c0.3,1.9,0.7,3.8,1,5.7c0.9,0.6,1.7,1.2,2.6,1.8c1.3-0.2,2.7-0.4,4-0.6C10.2,12.6,10.8,11.8,11.4,11L11.4,11z
                                    M6.1,10.1C6,9.5,5.9,8.8,5.8,8.2C5.7,7.8,6,7.2,6.5,7.1C7,7.1,7.4,7.5,7.5,8c0.1,0.6,0.2,1.2,0.3,1.9C7.2,9.9,6.6,10,6.1,10.1
                                    L6.1,10.1z"/>
                                <path class="st1" d="M64.1,2.4c-0.1,0.4-0.1,0.8-0.2,1.2c-0.6-0.1-1.2-0.2-1.7-0.3c0.1-0.4,0.1-0.8,0.2-1.2
                                    c-0.9-0.1-1.8-0.3-2.6-0.4c-0.1,0.4-0.1,0.8-0.2,1.2c-0.6-0.1-1.2-0.2-1.8-0.2c0.1-0.4,0.1-0.8,0.2-1.2c-0.9-0.1-1.8-0.2-2.6-0.3
                                    c-0.1,1.2-0.3,2.3-0.4,3.5c0.4,0.1,0.9,0.3,1.3,0.4c-0.2,1.9-0.5,3.8-0.7,5.7c0.6,0.8,1.3,1.6,1.9,2.5c1.3,0.2,2.7,0.4,4,0.6
                                    c0.8-0.6,1.6-1.2,2.4-1.8c0.3-1.9,0.7-3.8,1-5.7c0.4,0,0.9,0,1.3,0c0.2-1.2,0.4-2.3,0.6-3.5C65.9,2.7,65,2.5,64.1,2.4L64.1,2.4z
                                    M61,8c-0.1,0.6-0.2,1.2-0.3,1.9c-0.6-0.1-1.1-0.2-1.7-0.3c0.1-0.6,0.2-1.2,0.3-1.9c0.1-0.4,0.5-0.9,1-0.8C60.7,7,61,7.6,61,8
                                    L61,8z"/>
                                <path class="st1" d="M48.3,17.7c-11.1-0.9-22.7-0.7-33.8,0.4c-2,0.2-4.1,0.5-6.1,0.8l0,43.6c0.2,4.9,1.2,7.6,6,9.3
                                    c5.1,1.8,11,1.4,16,3.8c1.1,0.5,2.5,1.4,3,2.6l0.1,0c0.5-1.5,2.4-2.4,3.9-3c4.8-1.9,10-1.7,14.9-3.3c4.9-1.6,6.1-4.3,6.3-9.3
                                    l0-43.6C55.1,18.3,51.7,17.9,48.3,17.7L48.3,17.7z M50.8,69.5c-5.3,1.6-11.1,1.3-16.1,3.9c-0.2,0.1-1.1,0.7-1.2,0.7
                                    c-0.2,0-0.6-0.3-0.8-0.4c-5.8-3.3-12.6-2.4-18.3-4.8c-3.2-1.4-3.3-3.5-3.5-6.7v-41c1.3-0.1,2.7-0.3,4-0.4
                                    c11.7-1.1,24.1-1.2,35.8-0.1c1.8,0.2,3.5,0.4,5.3,0.6v41C55.8,66.7,55.1,68.3,50.8,69.5L50.8,69.5z"/>
                                <path class="st1" d="M28.2,10.1c0-0.3,0-0.6,0-0.9c-1,0-2,0.1-2.9,0.1c0,0.8,0.1,1.7,0.1,2.5c1.5-0.1,3.1-0.1,4.6-0.1
                                    C29.4,11.1,28.8,10.6,28.2,10.1z"/>
                                <path class="st1" d="M29.2,3.9c0,1.9,0.1,3.8,0.1,5.8c0.8,0.7,1.5,1.4,2.2,2.1c1.4,0,2.7,0,4.1,0c0.7-0.7,1.4-1.4,2.1-2.1
                                    c0.1-1.9,0.1-3.8,0.1-5.8c0.4-0.1,0.8-0.2,1.3-0.2c0-1.2,0.1-2.4,0.1-3.5c-0.9,0-1.8-0.1-2.6-0.1c0,0.4,0,0.8,0,1.2
                                    c-0.6,0-1.2,0-1.7,0c0-0.4,0-0.8,0-1.2c-0.9,0-1.8,0-2.6,0c0,0.4,0,0.8,0,1.2c-0.6,0-1.2,0-1.8,0c0-0.4,0-0.8,0-1.2
                                    c-0.9,0-1.8,0-2.6,0.1c0,1.2,0.1,2.4,0.1,3.5C28.3,3.7,28.7,3.8,29.2,3.9z M33.5,5.1c0.5,0,0.9,0.5,0.8,1c0,0.6,0,1.3,0,1.9
                                    c-0.6,0-1.1,0-1.7,0c0-0.6,0-1.3,0-1.9C32.6,5.6,33,5.1,33.5,5.1z"/>
                                <path class="st1" d="M50.8,45.7c-0.5-1.3-2.7-2.9-4.1-2.9c-1.7,0-3.3,1.3-4.2,2.6c-1.2-1-2.6-1.8-4-2.6c-1.5-0.9-2.9-1.5-4.5-2.2
                                    c-3.8-1.6-3.8-5.6-2.8-9.1c0.4-0.9,1-2.4,1.8-3.4c1.3-1.2,2-3.4,0.4-4.6c-1.4-1-4.6,0.1-4.3,2.1c-2,2.3-4.2,4.4-6.2,6.6
                                    c-0.1,0.1-0.7,0.7-0.6,0.8c1-0.3,1.9-0.9,2.7-1.4c1.8-1.1,3.5-2.4,5.2-3.6c0.5,0,0.4,0.7,0.4,1c-0.2,1.7-1.7,3.7-2.4,5.3
                                    c-0.9,2-2,5.2-1.6,7.4c0.4,2.2,2.5,4.6,4.1,6.1c0.1,0.1,0.4,0.2,0.4,0.5c0,0.3-0.8,0.9-1,1.1c-0.2,0.2-0.9,0.6-1.1,0.6
                                    c-0.1,0-0.1,0.1-0.2,0c-1.2-1.4-2.4-2.9-3.6-4.3c-0.8-1-1.8-2.6-3.1-2.9c-1.1-0.3-2.1,0.2-2.7,1c-0.9,0.5-1.6,1.5-1.6,2.7
                                    c0,1.7,1.4,3.1,3.1,3.1c1.7,0,3.1-1.4,3.1-3.1c0,0,0-0.1,0-0.1c0-0.1,0.1-0.2,0.1-0.3c0-0.1,0-0.1,0.1-0.1
                                    c0.6,0.7,1.2,1.4,1.7,2.2c0.6,0.8,2,3.2,2.7,3.6c0.8,0.5,0.9,0,1.4-0.4c0.7-0.6,1.7-1,2.7-1c0.7,0,0.9,0.2,1.3,0.8
                                    c0.8,1.5,1.2,4.9,1.4,6.6c0.2,2-0.1,6.7-1.3,8.4c-0.6,0.9-0.9,0.4-1.7,0.8c-1,0.5-1.8,1.4-1.4,2.6c0.4-0.6,0.9-1.2,1.5-1.5
                                    c0.1,0,0.7-0.4,0.6-0.2c-0.7,0.7-0.6,1.5,0.1,2.2c0.3-1,1.1-2.2,2.2-2.5c0.7-0.1,1.2,0.3,1.8,0.5c-0.1-0.4-0.3-0.6-0.6-0.9
                                    c-0.1-0.1-0.5-0.2-0.6-0.3c-0.1-0.1-0.1-1.4-0.1-1.6c0.1-2.8,0.8-5.5,1.6-8.2c0.3-1.2-0.5-1.3-0.8-2.1c-0.1-0.4-0.3-1.2-0.3-1.6
                                    c0-0.1,0-0.3,0.1-0.4c0.1,0,1.2-0.3,1.4-0.3c1.2-0.4,2.3-0.9,3.4-1.2c0.4,1.4,1.5,2.2,2.9,2.3c0.2,0,0.8-0.1,0.8,0
                                    c0.4,0.3-0.1,1-0.1,1.1l0.7-0.4l0.7-0.7c0.1,0.3,0,0.7-0.1,1.1c0,0.1-0.1,0.1,0.1,0.1c0.8-0.1,1.9-1.4,2.4-1.9
                                    c0.1,0.4-0.3,0.9-0.5,1.2c0,0.1,0.2,0,0.2,0c0.6-0.2,1-1.6,1-2.1c0.2,0,0.1,0,0.1,0.1c0,0.3,0.1,0.6,0.1,0.8
                                    c0.7-0.6,0.7-1.6,0.8-2.4l0.2,0.8l0,0.4c0.1,0,0.1-0.1,0.2-0.2C51.1,50.7,51.2,46.7,50.8,45.7L50.8,45.7z"/>
                            </g>
                        </g>
                    </svg>
                </div>

                <div class="tranas-splash-card">
                    <!-- <h1 class="tranas-splash-title"><?php echo esc_html( $splash_title ); ?></h1>
                    <p class="tranas-splash-description"><?php echo esc_html( $splash_description ); ?></p> -->
                    
                    <a href="<?php echo esc_url( $login_url ); ?>" class="tranas-splash-button">
                        <?php echo esc_html( $splash_button_text ); ?>
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

