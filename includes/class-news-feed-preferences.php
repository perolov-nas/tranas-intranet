<?php
/**
 * Hantering av användarens nyhetsflödes-inställningar
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för användarens val av kategorier i nyhetsflödet
 */
class Tranas_News_Feed_Preferences {

    /**
     * Meta-nyckel för användarens valda kategorier
     *
     * @var string
     */
    const META_KEY = 'tranas_news_categories';

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
        add_shortcode( 'tranas_news_preferences', array( $this, 'render_preferences_shortcode' ) );
        
        // AJAX-handlers
        add_action( 'wp_ajax_tranas_save_news_preferences', array( $this, 'handle_save_preferences' ) );
        
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
                'description'       => __( 'Användarens valda kategorier för nyhetsflödet', 'tranas-intranet' ),
                'single'            => true,
                'show_in_rest'      => array(
                    'schema' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type' => 'integer',
                        ),
                    ),
                ),
                'sanitize_callback' => array( $this, 'sanitize_categories' ),
            )
        );
    }

    /**
     * Sanera kategori-array
     *
     * @param mixed $value Värdet som ska saneras
     * @return array Sanerad array med kategori-ID:n
     */
    public function sanitize_categories( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        return array_map( 'absint', array_filter( $value ) );
    }

    /**
     * Hämta användarens valda kategorier
     *
     * @param int $user_id Användar-ID (standard: nuvarande användare)
     * @return array Array med kategori-ID:n
     */
    public function get_user_categories( $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( 0 === $user_id ) {
            return array();
        }

        $categories = get_user_meta( $user_id, self::META_KEY, true );
        
        if ( empty( $categories ) || ! is_array( $categories ) ) {
            return array();
        }

        return array_map( 'absint', $categories );
    }

    /**
     * Spara användarens valda kategorier
     *
     * @param int   $user_id    Användar-ID
     * @param array $categories Array med kategori-ID:n
     * @return bool True om sparningen lyckades
     */
    public function save_user_categories( $user_id, $categories ) {
        $sanitized = $this->sanitize_categories( $categories );
        return update_user_meta( $user_id, self::META_KEY, $sanitized );
    }

    /**
     * Hämta alla tillgängliga kategorier
     *
     * @param string $taxonomy Taxonomi att hämta (standard: category)
     * @return array Array med kategoriobjekt
     */
    public function get_available_categories( $taxonomy = 'category' ) {
        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        /**
         * Filter för att anpassa argumenten för kategori-hämtning
         *
         * @param array  $args     Argument till get_terms()
         * @param string $taxonomy Aktuell taxonomi
         */
        $args = apply_filters( 'tranas_news_feed_category_args', $args, $taxonomy );

        return get_terms( $args );
    }

    /**
     * Rendera shortcode för kategori-inställningar
     *
     * @param array $atts Shortcode-attribut
     * @return string HTML-output
     */
    public function render_preferences_shortcode( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-news-preferences-wrapper"><p>' . 
                esc_html__( '[Nyhetsflödes-inställningar visas här på frontend]', 'tranas-intranet' ) . 
                '</p></div>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return sprintf(
                '<div class="tf-message tf-message--error tranas-news-notice tranas-news-notice--warning">%s <a href="%s">%s</a></div>',
                esc_html__( 'Du måste vara inloggad för att anpassa ditt nyhetsflöde.', 'tranas-intranet' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Logga in här', 'tranas-intranet' )
            );
        }

        $atts = shortcode_atts(
            array(
                'taxonomy'    => 'category',
                'title'       => __( 'Anpassa ditt nyhetsflöde', 'tranas-intranet' ),
                'description' => __( 'Välj vilka kategorier du vill se i ditt nyhetsflöde.', 'tranas-intranet' ),
            ),
            $atts,
            'tranas_news_preferences'
        );

        $categories       = $this->get_available_categories( $atts['taxonomy'] );
        $user_categories  = $this->get_user_categories();

        if ( empty( $categories ) ) {
            return '<div class="tf-message tf-message--info">' . 
                esc_html__( 'Inga kategorier finns tillgängliga.', 'tranas-intranet' ) . 
                '</div>';
        }

        ob_start();
        ?>
        <div class="tranas-news-preferences-wrapper">
            <form id="tranas-news-preferences-form" class="tranas-news-preferences-form" method="post">
                <?php wp_nonce_field( 'tranas_news_preferences_nonce', 'tranas_news_nonce' ); ?>
                <input type="hidden" name="taxonomy" value="<?php echo esc_attr( $atts['taxonomy'] ); ?>" />
                
                <!-- Meddelande-container för AJAX-svar -->
                <div class="tf-message-container tranas-news-messages" role="alert" aria-live="polite" aria-atomic="true"></div>

                <?php if ( ! empty( $atts['title'] ) ) : ?>
                    <h2 class="tranas-news-preferences__title"><?php echo esc_html( $atts['title'] ); ?></h2>
                <?php endif; ?>

                <?php if ( ! empty( $atts['description'] ) ) : ?>
                    <p class="tranas-news-preferences__description"><?php echo esc_html( $atts['description'] ); ?></p>
                <?php endif; ?>

                <fieldset class="tf-fieldset tranas-news-preferences__fieldset">
                    <legend class="screen-reader-text"><?php esc_html_e( 'Välj kategorier', 'tranas-intranet' ); ?></legend>
                    
                    <div class="tranas-news-preferences__actions-top">
                        <button type="button" class="tranas-news-preferences__select-all tf-button tf-button--secondary">
                            <?php esc_html_e( 'Markera alla', 'tranas-intranet' ); ?>
                        </button>
                        <button type="button" class="tranas-news-preferences__deselect-all tf-button tf-button--secondary">
                            <?php esc_html_e( 'Avmarkera alla', 'tranas-intranet' ); ?>
                        </button>
                    </div>

                    <div class="tranas-news-preferences__categories">
                        <?php foreach ( $categories as $category ) : ?>
                            <?php
                            $is_checked = in_array( $category->term_id, $user_categories, true );
                            $field_id   = 'tranas-cat-' . $category->term_id;
                            ?>
                            <div class="tranas-news-preferences__category">
                                <label for="<?php echo esc_attr( $field_id ); ?>" class="tranas-news-preferences__label">
                                    <input
                                        type="checkbox"
                                        id="<?php echo esc_attr( $field_id ); ?>"
                                        name="categories[]"
                                        value="<?php echo esc_attr( $category->term_id ); ?>"
                                        class="tranas-news-preferences__checkbox"
                                        <?php checked( $is_checked ); ?>
                                    />
                                    <span class="tranas-news-preferences__category-name">
                                        <?php echo esc_html( $category->name ); ?>
                                    </span>
                                    <?php if ( $category->count > 0 ) : ?>
                                        <span class="tranas-news-preferences__category-count">
                                            (<?php echo esc_html( $category->count ); ?>)
                                        </span>
                                    <?php endif; ?>
                                </label>
                                <?php if ( ! empty( $category->description ) ) : ?>
                                    <p class="tranas-news-preferences__category-description">
                                        <?php echo esc_html( $category->description ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="tranas-news-preferences__actions">
                    <button type="submit" class="tf-submit tranas-news-preferences__submit">
                        <span class="tf-submit-text"><?php esc_html_e( 'Spara inställningar', 'tranas-intranet' ); ?></span>
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
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_news_preferences_nonce' ) ) {
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

        // Hämta valda kategorier (tom array om inga valda)
        $categories = isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) 
            ? array_map( 'absint', $_POST['categories'] ) 
            : array();

        // Spara kategorierna
        $result = $this->save_user_categories( $user_id, $categories );

        if ( false === $result ) {
            wp_send_json_error(
                array( 'message' => __( 'Kunde inte spara inställningarna. Försök igen.', 'tranas-intranet' ) )
            );
        }

        wp_send_json_success(
            array( 
                'message'    => __( 'Dina nyhetsflödes-inställningar har sparats!', 'tranas-intranet' ),
                'categories' => $categories,
            )
        );
    }
}

