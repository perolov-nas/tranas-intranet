<?php
/**
 * Hantering av användarens systempreferenser
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för användarens val av system
 */
class Tranas_System_Preferences {

    /**
     * Meta-nyckel för användarens valda system
     *
     * @var string
     */
    const META_KEY = 'tranas_user_systems';

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initiera hooks
     */
    private function init_hooks() {
        // Shortcode för inställningssidan
        add_shortcode( 'tranas_system_preferences', array( $this, 'render_preferences_shortcode' ) );

        // AJAX-handlers
        add_action( 'wp_ajax_tranas_save_system_preferences', array( $this, 'handle_save_preferences' ) );

        // Registrera meta för REST API
        add_action( 'init', array( $this, 'register_meta' ) );
    }

    /**
     * Registrera user meta
     */
    public function register_meta() {
        register_meta(
            'user',
            self::META_KEY,
            array(
                'type'              => 'array',
                'description'       => __( 'Användarens valda system', 'tranas-intranet' ),
                'single'            => true,
                'show_in_rest'      => array(
                    'schema' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type' => 'integer',
                        ),
                    ),
                ),
                'sanitize_callback' => array( $this, 'sanitize_systems' ),
            )
        );
    }

    /**
     * Sanera system-array
     *
     * @param mixed $value Värdet som ska saneras.
     * @return array Sanerad array med system-ID:n.
     */
    public function sanitize_systems( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        return array_map( 'absint', array_filter( $value ) );
    }

    /**
     * Hämta användarens valda system
     *
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return array Array med system-ID:n.
     */
    public function get_user_systems( $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( 0 === $user_id ) {
            return array();
        }

        $systems = get_user_meta( $user_id, self::META_KEY, true );

        if ( empty( $systems ) || ! is_array( $systems ) ) {
            return array();
        }

        return array_map( 'absint', $systems );
    }

    /**
     * Spara användarens valda system
     *
     * @param int   $user_id Användar-ID.
     * @param array $systems Array med system-ID:n.
     * @return bool True om sparningen lyckades.
     */
    public function save_user_systems( $user_id, $systems ) {
        $sanitized = $this->sanitize_systems( $systems );
        return update_user_meta( $user_id, self::META_KEY, $sanitized );
    }

    /**
     * Hämta alla tillgängliga system
     *
     * @return array Array med system-poster.
     */
    public function get_available_systems() {
        $args = array(
            'post_type'      => Tranas_System_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        /**
         * Filter för att anpassa argumenten för system-hämtning
         *
         * @param array $args Argument till get_posts().
         */
        $args = apply_filters( 'tranas_system_preferences_args', $args );

        return get_posts( $args );
    }

    /**
     * Rendera shortcode för system-inställningar
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output.
     */
    public function render_preferences_shortcode( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-system-preferences-wrapper"><p>' .
                esc_html__( '[Systeminställningar visas här på frontend]', 'tranas-intranet' ) .
                '</p></div>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return sprintf(
                '<div class="tf-message tf-message--error tranas-system-notice tranas-system-notice--warning">%s <a href="%s">%s</a></div>',
                esc_html__( 'Du måste vara inloggad för att anpassa dina system.', 'tranas-intranet' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Logga in här', 'tranas-intranet' )
            );
        }

        $atts = shortcode_atts(
            array(
                'title'       => __( 'Välj dina system', 'tranas-intranet' ),
                'description' => __( 'Markera de system du använder för att visa dem på din startsida.', 'tranas-intranet' ),
            ),
            $atts,
            'tranas_system_preferences'
        );

        $systems      = $this->get_available_systems();
        $user_systems = $this->get_user_systems();

        if ( empty( $systems ) ) {
            return '<div class="tf-message tf-message--info">' .
                esc_html__( 'Inga system finns tillgängliga.', 'tranas-intranet' ) .
                '</div>';
        }

        ob_start();
        ?>
        <div class="tranas-system-preferences-wrapper">
            <form id="tranas-system-preferences-form" class="tranas-system-preferences-form" method="post">
                <?php wp_nonce_field( 'tranas_system_preferences_nonce', 'tranas_system_nonce' ); ?>

                <!-- Meddelande-container för AJAX-svar -->
                <div class="tf-message-container tranas-system-messages" role="alert" aria-live="polite" aria-atomic="true"></div>

                <?php if ( ! empty( $atts['title'] ) ) : ?>
                    <h2 class="tranas-system-preferences__title"><?php echo esc_html( $atts['title'] ); ?></h2>
                <?php endif; ?>

                <?php if ( ! empty( $atts['description'] ) ) : ?>
                    <p class="tranas-system-preferences__description"><?php echo esc_html( $atts['description'] ); ?></p>
                <?php endif; ?>

                <fieldset class="tf-fieldset tranas-news-preferences__fieldset">
                    <legend class="screen-reader-text"><?php esc_html_e( 'Välj system', 'tranas-intranet' ); ?></legend>

                    <div class="tranas-news-preferences__actions-top">
                        <button type="button" class="tranas-news-preferences__select-all tf-button tf-button--secondary">
                            <?php esc_html_e( 'Markera alla', 'tranas-intranet' ); ?>
                        </button>
                        <button type="button" class="tranas-news-preferences__deselect-all tf-button tf-button--secondary">
                            <?php esc_html_e( 'Avmarkera alla', 'tranas-intranet' ); ?>
                        </button>
                    </div>

                    <div class="tranas-news-preferences__categories">
                        <?php foreach ( $systems as $system ) : ?>
                            <?php
                            $is_checked = in_array( $system->ID, $user_systems, true );
                            $field_id   = 'tranas-system-' . $system->ID;
                            ?>
                            <div class="tranas-news-preferences__category">
                                <label for="<?php echo esc_attr( $field_id ); ?>" class="tranas-news-preferences__label">
                                    <input
                                        type="checkbox"
                                        id="<?php echo esc_attr( $field_id ); ?>"
                                        name="systems[]"
                                        value="<?php echo esc_attr( $system->ID ); ?>"
                                        class="tranas-news-preferences__checkbox"
                                        <?php checked( $is_checked ); ?>
                                    />
                                    <span class="tranas-news-preferences__category-name">
                                        <?php echo esc_html( $system->post_title ); ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="tranas-system-preferences__actions">
                    <button type="submit" class="tf-submit tranas-system-preferences__submit">
                        <span class="tf-submit-text"><?php esc_html_e( 'Spara mina system', 'tranas-intranet' ); ?></span>
                        <span class="tf-submit-loading" aria-hidden="true" style="display:none;"><?php esc_html_e( 'Sparar...', 'tranas-intranet' ); ?></span>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Hantera AJAX-sparning av preferenser
     */
    public function handle_save_preferences() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_system_preferences_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att spara inställningar.', 'tranas-intranet' ) )
            );
        }

        $user_id = get_current_user_id();

        // Hämta valda system (tom array om inga valda)
        $systems = isset( $_POST['systems'] ) && is_array( $_POST['systems'] )
            ? array_map( 'absint', $_POST['systems'] )
            : array();

        // Spara systemen
        $result = $this->save_user_systems( $user_id, $systems );

        if ( false === $result ) {
            wp_send_json_error(
                array( 'message' => __( 'Kunde inte spara inställningarna. Försök igen.', 'tranas-intranet' ) )
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Dina systeminställningar har sparats!', 'tranas-intranet' ),
                'systems' => $systems,
            )
        );
    }
}

