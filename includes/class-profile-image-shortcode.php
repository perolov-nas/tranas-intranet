<?php
/**
 * Shortcode för användarens profilbild
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för profilbild-shortcode
 */
class Tranas_Profile_Image_Shortcode {

    /**
     * Meta-nyckel för profilbild
     */
    const META_KEY = 'tranas_profile_image';

    /**
     * Tillåtna bildstorlekar
     */
    const SIZES = array( 'thumbnail', 'medium', 'large', 'full' );

    /**
     * Konstruktor - registrera shortcode och AJAX-handlers
     */
    public function __construct() {
        add_shortcode( 'tranas_profile_image_upload', array( $this, 'render_upload_shortcode' ) );
        add_shortcode( 'tranas_profile_image', array( $this, 'render_display_shortcode' ) );
        add_action( 'wp_ajax_tranas_upload_profile_image', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_tranas_remove_profile_image', array( $this, 'handle_remove' ) );

        // Ersätt Gravatar med vår profilbild
        add_filter( 'pre_get_avatar_data', array( $this, 'use_custom_avatar' ), 10, 2 );

        // Visa fältet i admin
        add_action( 'show_user_profile', array( $this, 'render_admin_field' ) );
        add_action( 'edit_user_profile', array( $this, 'render_admin_field' ) );
    }

    /**
     * Hämta användarens profilbild-URL
     *
     * @param int    $user_id Användar-ID (valfritt, standard är aktuell användare).
     * @param string $size    Bildstorlek (thumbnail, medium, large, full).
     * @return string|false Bild-URL eller false om ingen bild finns
     */
    public static function get_profile_image_url( $user_id = null, $size = 'thumbnail' ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $attachment_id = get_user_meta( $user_id, self::META_KEY, true );

        if ( ! $attachment_id ) {
            return false;
        }

        $image_url = wp_get_attachment_image_url( $attachment_id, $size );

        return $image_url ?: false;
    }

    /**
     * Hämta attachment-ID för användarens profilbild
     *
     * @param int $user_id Användar-ID (valfritt, standard är aktuell användare).
     * @return int|false Attachment-ID eller false om ingen bild finns
     */
    public static function get_profile_image_id( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $attachment_id = get_user_meta( $user_id, self::META_KEY, true );

        return $attachment_id ? intval( $attachment_id ) : false;
    }

    /**
     * Kolla om användaren har en anpassad profilbild
     *
     * @param int $user_id Användar-ID (valfritt, standard är aktuell användare).
     * @return bool
     */
    public static function has_profile_image( $user_id = null ) {
        return (bool) self::get_profile_image_url( $user_id );
    }

    /**
     * Ersätt WordPress standard-avatar med vår profilbild
     *
     * @param array $args        Avatar-argument.
     * @param mixed $id_or_email Användar-ID, e-post eller kommentarsobjekt.
     * @return array Uppdaterade argument
     */
    public function use_custom_avatar( $args, $id_or_email ) {
        // Hämta användar-ID
        $user_id = $this->get_user_id_from_mixed( $id_or_email );

        if ( ! $user_id ) {
            return $args;
        }

        // Kolla om användaren har en anpassad profilbild
        $profile_image_url = self::get_profile_image_url( $user_id, 'medium' );

        if ( $profile_image_url ) {
            $args['url']          = $profile_image_url;
            $args['found_avatar'] = true;
        }

        return $args;
    }

    /**
     * Hämta användar-ID från olika typer av input
     *
     * @param mixed $id_or_email Användar-ID, e-post, WP_User, WP_Post eller WP_Comment.
     * @return int|false Användar-ID eller false
     */
    private function get_user_id_from_mixed( $id_or_email ) {
        if ( is_numeric( $id_or_email ) ) {
            return (int) $id_or_email;
        }

        if ( is_string( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
            return $user ? $user->ID : false;
        }

        if ( $id_or_email instanceof WP_User ) {
            return $id_or_email->ID;
        }

        if ( $id_or_email instanceof WP_Post ) {
            return (int) $id_or_email->post_author;
        }

        if ( $id_or_email instanceof WP_Comment ) {
            if ( $id_or_email->user_id ) {
                return (int) $id_or_email->user_id;
            }
            $user = get_user_by( 'email', $id_or_email->comment_author_email );
            return $user ? $user->ID : false;
        }

        return false;
    }

    /**
     * Rendera uppladdnings-shortcode
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output
     */
    public function render_upload_shortcode( $atts ) {
        // Hoppa över rendering i admin/REST-kontext (t.ex. Gutenberg-editorn)
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-profile-image-wrapper"><p>' . esc_html__( '[Profilbilduppladdning visas här på frontend]', 'tranas-intranet' ) . '</p></div>';
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return sprintf(
                '<div class="tf-message tf-message--error">%s <a href="%s">%s</a></div>',
                esc_html__( 'Du måste vara inloggad för att ändra din profilbild.', 'tranas-intranet' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Logga in här', 'tranas-intranet' )
            );
        }

        $current_user   = wp_get_current_user();
        $current_image  = self::get_profile_image_url( $current_user->ID, 'medium' );
        $attachment_id  = get_user_meta( $current_user->ID, self::META_KEY, true );

        ob_start();
        ?>
        <div class="tranas-profile-image-wrapper">
            <form id="tranas-profile-image-form" class="tranas-image-upload" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tranas_profile_image_nonce', 'tranas_profile_image_nonce_field' ); ?>
                
                <!-- Meddelande-container för AJAX-svar -->
                <div class="tf-message-container tranas-image-upload__messages" role="alert" aria-live="polite" aria-atomic="true"></div>

                <!-- Drag-och-släpp-yta -->
                <div class="tranas-image-dropzone" 
                     id="tranas-profile-image-dropzone"
                     role="button"
                     tabindex="0"
                     aria-label="<?php esc_attr_e( 'Dra och släpp en bild här eller klicka för att välja fil', 'tranas-intranet' ); ?>">
                    
                    <!-- Förhandsvisning av befintlig bild -->
                    <div class="tranas-image-dropzone__preview <?php echo $current_image ? 'has-image' : ''; ?>" 
                         id="tranas-profile-image-preview"
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
                       id="tranas-profile-image-input" 
                       name="profile_image"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       class="tranas-image-upload__input"
                       aria-describedby="tranas-profile-image-help" />
                
                <p id="tranas-profile-image-help" class="tranas-image-upload__help">
                    <?php esc_html_e( 'Tillåtna format: JPG, PNG, GIF, WebP. Max storlek: 2 MB.', 'tranas-intranet' ); ?>
                </p>

                <!-- Knappar -->
                <div class="tranas-image-upload__actions">
                    <button type="button" 
                            id="tranas-profile-image-select-btn" 
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
                                id="tranas-profile-image-remove-btn" 
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
     * Rendera visnings-shortcode (för att visa profilbild på sidor)
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output
     */
    public function render_display_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'user_id' => 0,
                'size'    => 'thumbnail',
                'class'   => '',
            ),
            $atts,
            'tranas_profile_image'
        );

        $user_id = intval( $atts['user_id'] );

        if ( ! $user_id && is_user_logged_in() ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return '';
        }

        $user       = get_userdata( $user_id );
        $image_url  = self::get_profile_image_url( $user_id, $atts['size'] );
        $class      = 'tranas-profile-image ' . sanitize_html_class( $atts['class'] );

        if ( $image_url ) {
            return sprintf(
                '<img src="%s" alt="%s" class="%s" />',
                esc_url( $image_url ),
                esc_attr( $user->display_name ),
                esc_attr( $class )
            );
        }

        // Fallback till WordPress get_avatar
        return get_avatar( $user_id, $this->size_to_pixels( $atts['size'] ), '', $user->display_name, array( 'class' => $class ) );
    }

    /**
     * Konvertera storlek till pixlar
     *
     * @param string $size Storlek.
     * @return int Pixlar
     */
    private function size_to_pixels( $size ) {
        $sizes = array(
            'thumbnail' => 96,
            'medium'    => 300,
            'large'     => 600,
            'full'      => 1024,
        );

        return isset( $sizes[ $size ] ) ? $sizes[ $size ] : 96;
    }

    /**
     * Rendera fält i WordPress admin
     *
     * @param WP_User $user Användarobjekt.
     */
    public function render_admin_field( $user ) {
        $profile_image_url = self::get_profile_image_url( $user->ID, 'thumbnail' );
        ?>
        <h2><?php esc_html_e( 'Profilbild', 'tranas-intranet' ); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th>
                        <label><?php esc_html_e( 'Aktuell profilbild', 'tranas-intranet' ); ?></label>
                    </th>
                    <td>
                        <?php if ( $profile_image_url ) : ?>
                            <img src="<?php echo esc_url( $profile_image_url ); ?>" 
                                 alt="<?php echo esc_attr( $user->display_name ); ?>"
                                 style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover;" />
                            <p class="description">
                                <?php esc_html_e( 'Användaren har laddat upp en egen profilbild.', 'tranas-intranet' ); ?>
                            </p>
                        <?php else : ?>
                            <?php echo get_avatar( $user->ID, 96 ); ?>
                            <p class="description">
                                <?php esc_html_e( 'Ingen anpassad profilbild. Gravatar eller standardbild visas.', 'tranas-intranet' ); ?>
                            </p>
                        <?php endif; ?>
                        <p class="description">
                            <?php esc_html_e( 'Användaren kan ändra sin profilbild via frontend.', 'tranas-intranet' ); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Hantera bilduppladdning via AJAX
     */
    public function handle_upload() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_profile_image_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att ladda upp en profilbild.', 'tranas-intranet' ) )
            );
        }

        // Kolla att fil finns
        if ( empty( $_FILES['profile_image'] ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Ingen fil vald.', 'tranas-intranet' ) )
            );
        }

        $file = $_FILES['profile_image'];

        // Kolla filstorlek (max 2 MB för profilbilder)
        $max_size = 2 * 1024 * 1024; // 2 MB
        if ( $file['size'] > $max_size ) {
            wp_send_json_error(
                array( 'message' => __( 'Filen är för stor. Max storlek är 2 MB.', 'tranas-intranet' ) )
            );
        }

        // Validera filtyp
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
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
        $attachment_id = media_handle_upload( 'profile_image', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error(
                array( 'message' => $attachment_id->get_error_message() )
            );
        }

        // Spara attachment-ID till user meta
        update_user_meta( $current_user->ID, self::META_KEY, $attachment_id );

        // Hämta bild-URLs
        $image_url_thumb  = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
        $image_url_medium = wp_get_attachment_image_url( $attachment_id, 'medium' );

        wp_send_json_success(
            array(
                'message'        => __( 'Profilbilden har uppdaterats!', 'tranas-intranet' ),
                'image_url'      => $image_url_medium,
                'thumbnail_url'  => $image_url_thumb,
                'attachment_id'  => $attachment_id,
            )
        );
    }

    /**
     * Hantera borttagning av profilbild via AJAX
     */
    public function handle_remove() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_profile_image_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att ta bort din profilbild.', 'tranas-intranet' ) )
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

        // Hämta Gravatar-URL som fallback
        $gravatar_url = get_avatar_url( $current_user->ID, array( 'size' => 96 ) );

        wp_send_json_success(
            array(
                'message'      => __( 'Profilbilden har tagits bort.', 'tranas-intranet' ),
                'gravatar_url' => $gravatar_url,
            )
        );
    }
}

