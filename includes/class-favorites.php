<?php
/**
 * Hantering av användarens favoriter
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för användarens favoriter (sidor och poster)
 */
class Tranas_Favorites {

    /**
     * Meta-nyckel för användarens favoriter
     *
     * @var string
     */
    const META_KEY = 'tranas_user_favorites';

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
        // Shortcodes
        add_shortcode( 'tranas_favorite_button', array( $this, 'render_favorite_button' ) );
        add_shortcode( 'tranas_favorites_count', array( $this, 'render_favorites_count' ) );
        add_shortcode( 'tranas_favorites_list', array( $this, 'render_favorites_list' ) );

        // AJAX-handlers
        add_action( 'wp_ajax_tranas_toggle_favorite', array( $this, 'handle_toggle_favorite' ) );

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
                'description'       => __( 'Användarens favoritinlägg', 'tranas-intranet' ),
                'single'            => true,
                'show_in_rest'      => array(
                    'schema' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type' => 'integer',
                        ),
                    ),
                ),
                'sanitize_callback' => array( $this, 'sanitize_favorites' ),
            )
        );
    }

    /**
     * Sanera favorit-array
     *
     * @param mixed $value Värdet som ska saneras.
     * @return array Sanerad array med post-ID:n.
     */
    public function sanitize_favorites( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        return array_map( 'absint', array_filter( $value ) );
    }

    /**
     * Hämta användarens favoriter
     *
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return array Array med post-ID:n.
     */
    public function get_user_favorites( $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( 0 === $user_id ) {
            return array();
        }

        $favorites = get_user_meta( $user_id, self::META_KEY, true );

        if ( empty( $favorites ) || ! is_array( $favorites ) ) {
            return array();
        }

        return array_map( 'absint', $favorites );
    }

    /**
     * Spara användarens favoriter
     *
     * @param int   $user_id   Användar-ID.
     * @param array $favorites Array med post-ID:n.
     * @return bool True om sparningen lyckades.
     */
    public function save_user_favorites( $user_id, $favorites ) {
        $sanitized = $this->sanitize_favorites( $favorites );
        return update_user_meta( $user_id, self::META_KEY, $sanitized );
    }

    /**
     * Kontrollera om ett inlägg är en favorit
     *
     * @param int $post_id Post-ID.
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return bool True om inlägget är en favorit.
     */
    public function is_favorite( $post_id, $user_id = 0 ) {
        $favorites = $this->get_user_favorites( $user_id );
        return in_array( absint( $post_id ), $favorites, true );
    }

    /**
     * Lägg till ett inlägg som favorit
     *
     * @param int $post_id Post-ID.
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return bool True om tillagd.
     */
    public function add_favorite( $post_id, $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( 0 === $user_id ) {
            return false;
        }

        $favorites = $this->get_user_favorites( $user_id );
        $post_id   = absint( $post_id );

        if ( ! in_array( $post_id, $favorites, true ) ) {
            $favorites[] = $post_id;
            return $this->save_user_favorites( $user_id, $favorites );
        }

        return true;
    }

    /**
     * Ta bort ett inlägg från favoriter
     *
     * @param int $post_id Post-ID.
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return bool True om borttagen.
     */
    public function remove_favorite( $post_id, $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( 0 === $user_id ) {
            return false;
        }

        $favorites = $this->get_user_favorites( $user_id );
        $post_id   = absint( $post_id );
        $key       = array_search( $post_id, $favorites, true );

        if ( false !== $key ) {
            unset( $favorites[ $key ] );
            return $this->save_user_favorites( $user_id, array_values( $favorites ) );
        }

        return true;
    }

    /**
     * Toggla favorit-status
     *
     * @param int $post_id Post-ID.
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return array Array med 'is_favorite' och 'count'.
     */
    public function toggle_favorite( $post_id, $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        $is_favorite = $this->is_favorite( $post_id, $user_id );

        if ( $is_favorite ) {
            $this->remove_favorite( $post_id, $user_id );
            $is_favorite = false;
        } else {
            $this->add_favorite( $post_id, $user_id );
            $is_favorite = true;
        }

        return array(
            'is_favorite' => $is_favorite,
            'count'       => count( $this->get_user_favorites( $user_id ) ),
        );
    }

    /**
     * Hämta antal favoriter för en användare
     *
     * @param int $user_id Användar-ID (standard: nuvarande användare).
     * @return int Antal favoriter.
     */
    public function get_favorites_count( $user_id = 0 ) {
        return count( $this->get_user_favorites( $user_id ) );
    }

    /**
     * Rendera favorit-knapp shortcode
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output.
     */
    public function render_favorite_button( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<span class="tranas-favorite-button">' .
                esc_html__( '[Favoritknapp]', 'tranas-intranet' ) .
                '</span>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'post_id' => get_the_ID(),
                'class'   => '',
            ),
            $atts,
            'tranas_favorite_button'
        );

        $post_id = absint( $atts['post_id'] );

        if ( 0 === $post_id ) {
            return '';
        }

        // Verifiera att posten finns
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        $is_favorite = $this->is_favorite( $post_id );
        $extra_class = ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '';

        $button_class = 'tranas-favorite-button';
        if ( $is_favorite ) {
            $button_class .= ' tranas-favorite-button--active';
        }
        $button_class .= $extra_class;

        $aria_label = $is_favorite
            ? __( 'Ta bort från favoriter', 'tranas-intranet' )
            : __( 'Lägg till i favoriter', 'tranas-intranet' );

        $aria_pressed = $is_favorite ? 'true' : 'false';

        ob_start();
        ?>
        <button
            type="button"
            class="<?php echo esc_attr( $button_class ); ?>"
            data-post-id="<?php echo esc_attr( $post_id ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'tranas_favorites_nonce' ) ); ?>"
            aria-label="<?php echo esc_attr( $aria_label ); ?>"
            aria-pressed="<?php echo esc_attr( $aria_pressed ); ?>"
            title="<?php echo esc_attr( $aria_label ); ?>"
        >
            <svg class="tranas-favorite-button__icon" xmlns="http://www.w3.org/2000/svg" viewBox="1.5 2.5 18 17" width="24" height="24" aria-hidden="true" focusable="false">
                <path class="tranas-favorite-button__heart" d="m7.24264069 2.24264069c.5-2.5 4.34314571-2.65685425 6.00000001-1 1.6034073 1.60340734 1.4999617 4.3343931 0 6l-6.00000001 6.00000001-6-6.00000001c-1.65685425-1.65685425-1.65685425-4.34314575 0-6 1.54996042-1.54996043 5.5-1.5 6 1z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" transform="translate(3.257 4.257)"/>
            </svg>
            <span class="screen-reader-text"><?php echo esc_html( $aria_label ); ?></span>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendera favorit-räknare shortcode
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output.
     */
    public function render_favorites_count( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<span class="tranas-favorites-count">' .
                esc_html__( '[Favoritantal]', 'tranas-intranet' ) .
                '</span>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'class'     => '',
                'show_zero' => 'false',
                'link'      => '',
            ),
            $atts,
            'tranas_favorites_count'
        );

        $count      = $this->get_favorites_count();
        $show_zero  = filter_var( $atts['show_zero'], FILTER_VALIDATE_BOOLEAN );
        $link       = ! empty( $atts['link'] ) ? esc_url( $atts['link'] ) : '';
        $extra_class = ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '';

        // Dölj om antal är 0 och show_zero är false
        if ( 0 === $count && ! $show_zero ) {
            $extra_class .= ' tranas-favorites-count--hidden';
        }

        $wrapper_class = 'tranas-favorites-count header-item' . $extra_class;

        $aria_label = sprintf(
            /* translators: %d: antal favoriter */
            _n( '%d favorit', '%d favoriter', $count, 'tranas-intranet' ),
            $count
        );

        ob_start();

        $tag = ! empty( $link ) ? 'a' : 'span';
        $href = ! empty( $link ) ? ' href="' . esc_url( $link ) . '"' : '';
        ?>
        <<?php echo esc_html( $tag ); ?><?php echo $href; ?>
            class="<?php echo esc_attr( $wrapper_class ); ?>"
            aria-label="<?php echo esc_attr( $aria_label ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'tranas_favorites_nonce' ) ); ?>"
        >
            <svg class="tranas-favorites-count__icon" xmlns="http://www.w3.org/2000/svg" viewBox="1.5 2.5 18 17" width="20" height="20" aria-hidden="true" focusable="false">
                <path d="m7.24264069 2.24264069c.5-2.5 4.34314571-2.65685425 6.00000001-1 1.6034073 1.60340734 1.4999617 4.3343931 0 6l-6.00000001 6.00000001-6-6.00000001c-1.65685425-1.65685425-1.65685425-4.34314575 0-6 1.54996042-1.54996043 5.5-1.5 6 1z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" transform="translate(3.257 4.257)"/>
            </svg>
            <span class="tranas-favorites-count__number" data-count="<?php echo esc_attr( $count ); ?>"><?php echo esc_html( $count ); ?></span>
            <span class="screen-reader-text"><?php echo esc_html( $aria_label ); ?></span>
        </<?php echo esc_html( $tag ); ?>>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendera favorit-lista shortcode
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output.
     */
    public function render_favorites_list( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-favorites-list">' .
                esc_html__( '[Favoritlista visas här på frontend]', 'tranas-intranet' ) .
                '</div>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return sprintf(
                '<div class="tf-message tf-message--error tranas-favorites-notice tranas-favorites-notice--warning">%s <a href="%s">%s</a></div>',
                esc_html__( 'Du måste vara inloggad för att se dina favoriter.', 'tranas-intranet' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Logga in här', 'tranas-intranet' )
            );
        }

        $atts = shortcode_atts(
            array(
                'class'          => '',
                'post_type'      => '',           // Filtrera på post-typ (kommaseparerad lista)
                'limit'          => -1,           // Antal att visa (-1 = alla)
                'orderby'        => 'title',      // title, date, modified
                'order'          => 'ASC',        // ASC eller DESC
                'show_thumbnail' => 'true',       // Visa utvald bild
                'show_excerpt'   => 'false',      // Visa utdrag
                'show_date'      => 'false',      // Visa datum
                'show_type'      => 'false',      // Visa post-typ
                'show_remove'    => 'true',       // Visa ta bort-knapp
                'empty_message'  => '',           // Meddelande om listan är tom
                'layout'         => 'list',       // list eller grid
            ),
            $atts,
            'tranas_favorites_list'
        );

        $favorite_ids = $this->get_user_favorites();

        // Om inga favoriter
        if ( empty( $favorite_ids ) ) {
            $empty_message = ! empty( $atts['empty_message'] )
                ? $atts['empty_message']
                : __( 'Du har inga sparade favoriter ännu.', 'tranas-intranet' );

            return '<div class="tranas-favorites-list tranas-favorites-list--empty">' .
                '<p class="tranas-favorites-list__empty-message">' . esc_html( $empty_message ) . '</p>' .
                '</div>';
        }

        // Bygg query-argument
        $query_args = array(
            'post__in'       => $favorite_ids,
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => sanitize_key( $atts['orderby'] ),
            'order'          => strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
            'post_status'    => 'publish',
        );

        // Filtrera på post-typ om angiven
        if ( ! empty( $atts['post_type'] ) ) {
            $post_types = array_map( 'trim', explode( ',', $atts['post_type'] ) );
            $query_args['post_type'] = $post_types;
        } else {
            $query_args['post_type'] = 'any';
        }

        // Hämta favorit-poster
        $favorites_query = new WP_Query( $query_args );

        if ( ! $favorites_query->have_posts() ) {
            $empty_message = ! empty( $atts['empty_message'] )
                ? $atts['empty_message']
                : __( 'Inga favoriter hittades.', 'tranas-intranet' );

            return '<div class="tranas-favorites-list tranas-favorites-list--empty">' .
                '<p class="tranas-favorites-list__empty-message">' . esc_html( $empty_message ) . '</p>' .
                '</div>';
        }

        // Konvertera attribut till booleans
        $show_thumbnail = filter_var( $atts['show_thumbnail'], FILTER_VALIDATE_BOOLEAN );
        $show_excerpt   = filter_var( $atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN );
        $show_date      = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
        $show_type      = filter_var( $atts['show_type'], FILTER_VALIDATE_BOOLEAN );
        $show_remove    = filter_var( $atts['show_remove'], FILTER_VALIDATE_BOOLEAN );

        $extra_class   = ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '';
        $layout_class  = 'grid' === $atts['layout'] ? ' tranas-favorites-list--grid' : ' tranas-favorites-list--list';
        $wrapper_class = 'tranas-favorites-list' . $layout_class . $extra_class;

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $wrapper_class ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'tranas_favorites_nonce' ) ); ?>">
            <ul class="tranas-favorites-list__items">
                <?php while ( $favorites_query->have_posts() ) : $favorites_query->the_post(); ?>
                    <?php
                    $post_id   = get_the_ID();
                    $permalink = get_permalink();
                    $title     = get_the_title();
                    $post_type_obj = get_post_type_object( get_post_type() );
                    $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : '';
                    ?>
                    <li class="tranas-favorites-list__item" data-post-id="<?php echo esc_attr( $post_id ); ?>">
                        <?php if ( $show_thumbnail && has_post_thumbnail() ) : ?>
                            <a href="<?php echo esc_url( $permalink ); ?>" class="tranas-favorites-list__thumbnail">
                                <?php the_post_thumbnail( 'thumbnail' ); ?>
                            </a>
                        <?php endif; ?>

                        <div class="tranas-favorites-list__content">
                            <h3 class="tranas-favorites-list__title">
                                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
                            </h3>

                            <?php if ( $show_type && $post_type_label ) : ?>
                                <span class="tranas-favorites-list__type"><?php echo esc_html( $post_type_label ); ?></span>
                            <?php endif; ?>

                            <?php if ( $show_date ) : ?>
                                <time class="tranas-favorites-list__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                                    <?php echo esc_html( get_the_date() ); ?>
                                </time>
                            <?php endif; ?>

                            <?php if ( $show_excerpt && has_excerpt() ) : ?>
                                <p class="tranas-favorites-list__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ( $show_remove ) : ?>
                            <button
                                type="button"
                                class="tranas-favorites-list__remove tranas-favorite-button tranas-favorite-button--active"
                                data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'tranas_favorites_nonce' ) ); ?>"
                                aria-label="<?php echo esc_attr( sprintf( __( 'Ta bort %s från favoriter', 'tranas-intranet' ), $title ) ); ?>"
                                title="<?php echo esc_attr( __( 'Ta bort från favoriter', 'tranas-intranet' ) ); ?>"
                            >
                                <svg class="tranas-favorite-button__icon" xmlns="http://www.w3.org/2000/svg" viewBox="1.5 2.5 18 17" width="20" height="20" aria-hidden="true" focusable="false">
                                    <path class="tranas-favorite-button__heart" d="m7.24264069 2.24264069c.5-2.5 4.34314571-2.65685425 6.00000001-1 1.6034073 1.60340734 1.4999617 4.3343931 0 6l-6.00000001 6.00000001-6-6.00000001c-1.65685425-1.65685425-1.65685425-4.34314575 0-6 1.54996042-1.54996043 5.5-1.5 6 1z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" transform="translate(3.257 4.257)"/>
                                </svg>
                                <span class="screen-reader-text"><?php esc_html_e( 'Ta bort från favoriter', 'tranas-intranet' ); ?></span>
                            </button>
                        <?php endif; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Hantera AJAX-toggla favorit
     */
    public function handle_toggle_favorite() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_favorites_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att spara favoriter.', 'tranas-intranet' ) )
            );
        }

        // Hämta post-ID
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( 0 === $post_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Ogiltigt inläggs-ID.', 'tranas-intranet' ) )
            );
        }

        // Verifiera att posten finns
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error(
                array( 'message' => __( 'Inlägget hittades inte.', 'tranas-intranet' ) )
            );
        }

        // Toggla favorit
        $result = $this->toggle_favorite( $post_id );

        $message = $result['is_favorite']
            ? __( 'Tillagd i favoriter!', 'tranas-intranet' )
            : __( 'Borttagen från favoriter', 'tranas-intranet' );

        wp_send_json_success(
            array(
                'message'     => $message,
                'is_favorite' => $result['is_favorite'],
                'count'       => $result['count'],
                'post_id'     => $post_id,
            )
        );
    }
}

