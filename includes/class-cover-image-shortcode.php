<?php
/**
 * Shortcode för användarens bakgrundsbild (cover image)
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för bakgrundsbild-shortcode
 */
class Tranas_Cover_Image_Shortcode {

    /**
     * Meta-nyckel för bakgrundsbild
     */
    const META_KEY = 'tranas_cover_image';

    /**
     * CSS-klass för hero-elementet som ska få bakgrundsbilden
     */
    const HERO_CLASS = 'user-hero';

    /**
     * Sökväg till fallback-bild (relativt plugin-mappen)
     */
    const FALLBACK_IMAGE = 'assets/img/fallback-cover.jpg';

    /**
     * Konstruktor - registrera shortcode och AJAX-handlers
     */
    public function __construct() {
        add_shortcode( 'tranas_cover_image_upload', array( $this, 'render_shortcode' ) );
        add_action( 'wp_ajax_tranas_upload_cover_image', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_tranas_remove_cover_image', array( $this, 'handle_remove' ) );
        
        // Injicera inline CSS för att visa bakgrundsbilden på hero-elementet
        add_action( 'wp_head', array( $this, 'output_hero_background_css' ), 100 );
    }

    /**
     * Hämta fallback-bildens URL
     *
     * @return string Fallback-bildens URL
     */
    public static function get_fallback_image_url() {
        return TRANAS_INTRANET_PLUGIN_URL . self::FALLBACK_IMAGE;
    }

    /**
     * Skriv ut inline CSS för hero-bakgrundsbilden
     */
    public function output_hero_background_css() {
        // Endast för inloggade användare
        if ( ! is_user_logged_in() ) {
            return;
        }

        $cover_url = self::get_cover_image_url();

        // Använd fallback om ingen egen bild finns
        if ( ! $cover_url ) {
            $cover_url = self::get_fallback_image_url();
        }

        ?>
        <style id="tranas-user-hero-bg">
            .<?php echo esc_attr( self::HERO_CLASS ); ?> {
                background-image: url('<?php echo esc_url( $cover_url ); ?>') !important;
            }
        </style>
        <?php
    }

    /**
     * Hämta användarens bakgrundsbild-URL
     *
     * @param int $user_id Användar-ID (valfritt, standard är aktuell användare)
     * @return string|false Bild-URL eller false om ingen bild finns
     */
    public static function get_cover_image_url( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $attachment_id = get_user_meta( $user_id, self::META_KEY, true );

        if ( ! $attachment_id ) {
            return false;
        }

        $image_url = wp_get_attachment_image_url( $attachment_id, 'full' );

        return $image_url ?: false;
    }

    /**
     * Rendera shortcode
     *
     * @param array $atts Shortcode-attribut
     * @return string HTML-output
     */
    public function render_shortcode( $atts ) {
        // Hoppa över rendering i admin/REST-kontext (t.ex. Gutenberg-editorn)
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-cover-upload-wrapper"><p>' . esc_html__( '[Bakgrundsbilduppladdning visas här på frontend]', 'tranas-intranet' ) . '</p></div>';
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return sprintf(
                '<div class="tf-message tf-message--error">%s <a href="%s">%s</a></div>',
                esc_html__( 'Du måste vara inloggad för att ändra din bakgrundsbild.', 'tranas-intranet' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Logga in här', 'tranas-intranet' )
            );
        }

        $current_user   = wp_get_current_user();
        $current_image  = self::get_cover_image_url( $current_user->ID );
        $attachment_id  = get_user_meta( $current_user->ID, self::META_KEY, true );

        ob_start();
        ?>
        <div class="tranas-cover-upload-wrapper">
            <form id="tranas-cover-image-form" class="tranas-image-upload" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tranas_cover_image_nonce', 'tranas_cover_nonce' ); ?>
                
                <!-- Meddelande-container för AJAX-svar -->
                <div class="tf-message-container tranas-image-upload__messages" role="alert" aria-live="polite" aria-atomic="true"></div>

                <!-- Drag-och-släpp-yta -->
                <div class="tranas-image-dropzone" 
                     id="tranas-cover-dropzone"
                     role="button"
                     tabindex="0"
                     aria-label="<?php esc_attr_e( 'Dra och släpp en bild här eller klicka för att välja fil', 'tranas-intranet' ); ?>">
                    
                    <!-- Förhandsvisning av befintlig bild -->
                    <div class="tranas-image-dropzone__preview <?php echo $current_image ? 'has-image' : ''; ?>" 
                         id="tranas-cover-preview"
                         <?php if ( $current_image ) : ?>
                             style="background-image: url('<?php echo esc_url( $current_image ); ?>');"
                         <?php endif; ?>>
                        
                        <div class="tranas-image-dropzone__overlay">
                            <svg class="tranas-image-dropzone__icon" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <p class="tranas-image-dropzone__text">
                                <?php esc_html_e( 'Dra och släpp en bild här', 'tranas-intranet' ); ?>
                            </p>
                            <p class="tranas-image-dropzone__subtext">
                                <?php esc_html_e( 'eller klicka för att välja fil', 'tranas-intranet' ); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Drag-aktiv-indikator -->
                    <div class="tranas-image-dropzone__drag-active" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <p><?php esc_html_e( 'Släpp bilden här', 'tranas-intranet' ); ?></p>
                    </div>

                    <!-- Laddningsindikator -->
                    <div class="tranas-image-dropzone__loading" aria-hidden="true">
                        <div class="tranas-image-dropzone__spinner"></div>
                        <p><?php esc_html_e( 'Laddar upp...', 'tranas-intranet' ); ?></p>
                    </div>
                </div>

                <!-- Gömd filinput -->
                <input type="file" 
                       id="tranas-cover-file-input" 
                       name="cover_image"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       class="tranas-image-upload__input"
                       aria-describedby="tranas-cover-help" />
                
                <p id="tranas-cover-help" class="tranas-image-upload__help">
                    <?php esc_html_e( 'Tillåtna format: JPG, PNG, GIF, WebP. Max storlek: 5 MB.', 'tranas-intranet' ); ?>
                </p>

                <!-- Knappar -->
                <div class="tranas-image-upload__actions">
                    <button type="button" 
                            id="tranas-cover-select-btn" 
                            class="tf-submit tranas-image-upload__btn tranas-image-upload__btn--select">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <?php esc_html_e( 'Ladda upp bild', 'tranas-intranet' ); ?>
                    </button>

                    <?php if ( $current_image ) : ?>
                        <button type="button" 
                                id="tranas-cover-remove-btn" 
                                class="tf-submit tranas-image-upload__btn tranas-image-upload__btn--remove"
                                data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                <line x1="10" y1="11" x2="10" y2="17"/>
                                <line x1="14" y1="11" x2="14" y2="17"/>
                            </svg>
                            <?php esc_html_e( 'Ta bort bild', 'tranas-intranet' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Hantera bilduppladdning via AJAX
     */
    public function handle_upload() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_cover_image_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att ladda upp en bakgrundsbild.', 'tranas-intranet' ) )
            );
        }

        // Kolla att fil finns
        if ( empty( $_FILES['cover_image'] ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Ingen fil vald.', 'tranas-intranet' ) )
            );
        }

        $file = $_FILES['cover_image'];

        // Kolla filstorlek (max 5 MB)
        $max_size = 5 * 1024 * 1024; // 5 MB
        if ( $file['size'] > $max_size ) {
            wp_send_json_error(
                array( 'message' => __( 'Filen är för stor. Max storlek är 5 MB.', 'tranas-intranet' ) )
            );
        }

        // Validera filtyp
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
        $file_type     = wp_check_filetype( $file['name'] );
        $mime_type     = $file['type'];

        if ( ! in_array( $mime_type, $allowed_types, true ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Ogiltig filtyp. Endast JPG, PNG, GIF och WebP är tillåtna.', 'tranas-intranet' ) )
            );
        }

        // Kräv WordPress media-funktioner
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $current_user = wp_get_current_user();

        // Ta bort gammal bild om den finns
        $old_attachment_id = get_user_meta( $current_user->ID, self::META_KEY, true );
        if ( $old_attachment_id ) {
            wp_delete_attachment( $old_attachment_id, true );
        }

        // Ladda upp ny bild
        $attachment_id = media_handle_upload( 'cover_image', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error(
                array( 'message' => $attachment_id->get_error_message() )
            );
        }

        // Spara attachment-ID till user meta
        update_user_meta( $current_user->ID, self::META_KEY, $attachment_id );

        // Hämta bild-URL
        $image_url = wp_get_attachment_image_url( $attachment_id, 'full' );

        wp_send_json_success(
            array(
                'message'       => __( 'Bakgrundsbilden har uppdaterats!', 'tranas-intranet' ),
                'image_url'     => $image_url,
                'attachment_id' => $attachment_id,
            )
        );
    }

    /**
     * Hantera borttagning av bakgrundsbild via AJAX
     */
    public function handle_remove() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_cover_image_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att ta bort din bakgrundsbild.', 'tranas-intranet' ) )
            );
        }

        $current_user  = wp_get_current_user();
        $attachment_id = get_user_meta( $current_user->ID, self::META_KEY, true );

        if ( $attachment_id ) {
            // Ta bort bilden från mediabiblioteket
            wp_delete_attachment( $attachment_id, true );
        }

        // Ta bort meta-värdet
        delete_user_meta( $current_user->ID, self::META_KEY );

        wp_send_json_success(
            array( 'message' => __( 'Bakgrundsbilden har tagits bort.', 'tranas-intranet' ) )
        );
    }
}

