<?php
/**
 * Shortcode för att visa system (externa länkar)
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för System-shortcode
 */
class Tranas_System_Shortcode {

    /**
     * Instans av System Preferences
     *
     * @var Tranas_System_Preferences
     */
    private $preferences;

    /**
     * Konstruktor
     *
     * @param Tranas_System_Preferences $preferences Instans av preferenser-klassen.
     */
    public function __construct( $preferences ) {
        $this->preferences = $preferences;
        add_shortcode( 'tranas_system', array( $this, 'render_shortcode' ) );
    }

    /**
     * Rendera shortcode för systemlistan
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output.
     */
    public function render_shortcode( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-system-wrapper"><p>' .
                esc_html__( '[Systemlista visas här på frontend]', 'tranas-intranet' ) .
                '</p></div>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        $atts = shortcode_atts(
            array(
                'posts_per_page'  => -1,
                'orderby'         => 'title',
                'order'           => 'ASC',
                'layout'          => 'grid', // 'grid', 'list'
                'columns'         => 3,
                'show_thumbnail'  => 'true',
                'link_target'     => '_blank', // '_blank', '_self'
                'title'           => __( 'Mina system', 'tranas-intranet' ),
                'show_title'      => 'true',
                'edit_url'        => '',
                'edit_text'       => __( 'Redigera mina system', 'tranas-intranet' ),
                'user_selection'  => 'true', // Filtrera på användarens val
                'fallback'        => 'none', // 'all', 'none' - vad som visas om inga system valda
            ),
            $atts,
            'tranas_system'
        );

        // Konvertera string-booleans
        $show_thumbnail  = filter_var( $atts['show_thumbnail'], FILTER_VALIDATE_BOOLEAN );
        $show_title      = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
        $user_selection  = filter_var( $atts['user_selection'], FILTER_VALIDATE_BOOLEAN );

        // Hämta användarens valda system
        $user_systems    = array();
        $is_personalized = false;

        if ( $user_selection && is_user_logged_in() ) {
            $user_systems    = $this->preferences->get_user_systems();
            $is_personalized = ! empty( $user_systems );
        }

        // Om user_selection är aktiverat och användaren inte har valt några system
        if ( $user_selection && ! $is_personalized ) {
            if ( 'none' === $atts['fallback'] ) {
                return $this->render_no_selection_message( $atts );
            }
            // fallback 'all' - visa alla system
        }

        // Bygg query-argument
        $query_args = array(
            'post_type'      => Tranas_System_Post_Type::POST_TYPE,
            'posts_per_page' => intval( $atts['posts_per_page'] ),
            'post_status'    => 'publish',
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
        );

        // Filtrera på användarens val om det finns
        if ( $user_selection && $is_personalized ) {
            $query_args['post__in'] = $user_systems;
        }

        /**
         * Filter för att anpassa query-argumenten
         *
         * @param array $query_args Query-argument.
         * @param array $atts       Shortcode-attribut.
         */
        $query_args = apply_filters( 'tranas_system_query_args', $query_args, $atts );

        $query = new WP_Query( $query_args );

        if ( ! $query->have_posts() ) {
            wp_reset_postdata();
            return $this->render_empty_message();
        }

        $columns_class = 'tranas-system--columns-' . absint( $atts['columns'] );

        ob_start();
        ?>
        <div class="tranas-system tranas-system--<?php echo esc_attr( $atts['layout'] ); ?> <?php echo esc_attr( $columns_class ); ?>">
            <?php if ( $show_title || ! empty( $atts['edit_url'] ) ) : ?>
                <div class="tranas-system__header">
                    <?php if ( $show_title && ! empty( $atts['title'] ) ) : ?>
                        <h2 class="tranas-system__heading"><?php echo esc_html( $atts['title'] ); ?></h2>
                    <?php endif; ?>
                    <?php if ( ! empty( $atts['edit_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $atts['edit_url'] ); ?>" class="tranas-system__edit-link tf-button tf-button--secondary">
                            <?php echo esc_html( $atts['edit_text'] ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="tranas-system__list">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php $this->render_system_item( $atts, $show_thumbnail ); ?>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Rendera ett enskilt system i listan
     *
     * @param array $atts           Shortcode-attribut.
     * @param bool  $show_thumbnail Visa miniatyrbild.
     */
    private function render_system_item( $atts, $show_thumbnail ) {
        $post_id      = get_the_ID();
        $external_url = get_field( 'external_url', $post_id );
        $link_target  = esc_attr( $atts['link_target'] );

        // Om ingen extern länk finns, använd permalink som fallback
        if ( empty( $external_url ) ) {
            $external_url = get_permalink();
            $link_target  = '_self';
        }

        $rel_attr = '_blank' === $link_target ? 'noopener noreferrer' : '';
        ?>
        <a href="<?php echo esc_url( $external_url ); ?>" 
           class="tranas-system__item" 
           target="<?php echo $link_target; ?>"
           <?php echo $rel_attr ? 'rel="' . esc_attr( $rel_attr ) . '"' : ''; ?>>
            <article class="tranas-system__card">
                <?php if ( $show_thumbnail ) : ?>
                    <div class="tranas-system__thumbnail">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <?php the_post_thumbnail( 'medium', array( 'alt' => get_the_title() ) ); ?>
                        <?php else : ?>
                            <svg class="tranas-system__fallback-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 810.42 676.44" aria-hidden="true">
                                <path d="M691.47,662.7H118.94c-5.66,0-10.2-4.7-9.99-10.36h0c.95-26.65,22.6-47.53,49.28-47.53h156.89c5.8,0,10.5-4.7,10.5-10.5v-30.74c0-5.8-4.7-10.5-10.5-10.5H84.27c-38.67,0-70.02-31.35-70.02-70.02V83.76C14.25,45.09,45.59,13.75,84.26,13.75h641.89c38.67,0,70.02,31.35,70.02,70.02v399.28c0,38.67-31.35,70.02-70.02,70.02h-230.86c-5.77,0-10.5,4.72-10.5,10.5v30.74c0,5.77,4.72,10.5,10.5,10.5h156.89c26.67,0,48.32,20.88,49.28,47.53h0c.2,5.66-4.33,10.36-9.99,10.36ZM131.2,642.7h548.01c-4.44-10.57-14.85-17.89-27.03-17.89h-166.89c-11.3,0-20.5-9.2-20.5-20.5v-50.74c0-11.3,9.2-20.5,20.5-20.5h250.86c22.07,0,40.02-17.95,40.02-40.02V73.77c0-22.07-17.95-40.02-40.02-40.02H74.27c-22.07,0-40.02,17.95-40.02,40.02v419.28c0,22.07,17.95,40.02,40.02,40.02h250.86c11.3,0,20.5,9.2,20.5,20.5v50.74c0,11.3-9.2,20.5-20.5,20.5h-166.89c-12.18,0-22.6,7.32-27.03,17.89Z"/>
                                <g>
                                    <path d="M151.79,123.04h-50.57c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h50.57c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M327.54,123.04h-78.72c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h78.72c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M212.48,123.04h-25.17c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h25.17c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M281.51,170.33H101.23c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h180.29c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M202.36,217.61h-101.14c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h101.14c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M296,217.61h-55.35c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h55.35c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M414.42,264.9h-62.94c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h62.94c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M314.89,264.9H101.23c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h213.67c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                </g>
                                <g>
                                    <path d="M165.95,368.54h-70.36c-1.99,0-3.61-4.14-3.61-9.25s1.62-9.25,3.61-9.25h70.36c1.99,0,3.61,4.14,3.61,9.25s-1.62,9.25-3.61,9.25Z"/>
                                    <path d="M374.48,368.54h-165.22c-4.68,0-8.48-4.14-8.48-9.25s3.8-9.25,8.48-9.25h165.22c4.68,0,8.48,4.14,8.48,9.25s-3.8,9.25-8.48,9.25Z"/>
                                    <path d="M202.36,415.82h-101.14c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h101.14c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                    <path d="M314.89,319.11H101.23c-5.11,0-9.25-4.14-9.25-9.25s4.14-9.25,9.25-9.25h213.67c5.11,0,9.25,4.14,9.25,9.25s-4.14,9.25-9.25,9.25Z"/>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="tranas-system__content">
                    <h5 class="tranas-system__title"><?php the_title(); ?></h5>
                </div>
            </article>
        </a>
        <?php
    }

    /**
     * Rendera meddelande när inga system finns
     *
     * @return string HTML-output.
     */
    private function render_empty_message() {
        ob_start();
        ?>
        <div class="tranas-system tranas-system--empty">
            <div class="tf-message tf-message--info">
                <p><?php esc_html_e( 'Inga system har lagts till ännu.', 'tranas-intranet' ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendera meddelande när användaren inte valt några system
     *
     * @param array $atts Shortcode-attribut.
     * @return string HTML-output.
     */
    private function render_no_selection_message( $atts ) {
        ob_start();
        ?>
        <div class="tranas-system tranas-system--no-selection">
            <div class="tf-message tf-message--info">
                <?php if ( is_user_logged_in() ) : ?>
                    <p><?php esc_html_e( 'Du har inte valt några system ännu.', 'tranas-intranet' ); ?></p>
                    <?php if ( ! empty( $atts['edit_url'] ) ) : ?>
                        <p>
                            <a href="<?php echo esc_url( $atts['edit_url'] ); ?>" class="tf-button">
                                <?php esc_html_e( 'Välj dina system', 'tranas-intranet' ); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p>
                        <?php esc_html_e( 'Logga in för att se dina system.', 'tranas-intranet' ); ?>
                        <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
                            <?php esc_html_e( 'Logga in här', 'tranas-intranet' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

