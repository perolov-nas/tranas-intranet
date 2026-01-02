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
     * Konstruktor
     */
    public function __construct() {
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
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'layout'         => 'grid', // 'grid', 'list'
                'columns'        => 3,
                'show_thumbnail' => 'true',
                'link_target'    => '_blank', // '_blank', '_self'
                'title'          => __( 'Mina system', 'tranas-intranet' ),
                'show_title'     => 'true',
                'edit_url'       => '',
                'edit_text'      => __( 'Redigera mina system', 'tranas-intranet' ),
            ),
            $atts,
            'tranas_system'
        );

        // Konvertera string-booleans
        $show_thumbnail = filter_var( $atts['show_thumbnail'], FILTER_VALIDATE_BOOLEAN );
        $show_title     = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );

        // Bygg query-argument
        $query_args = array(
            'post_type'      => Tranas_System_Post_Type::POST_TYPE,
            'posts_per_page' => intval( $atts['posts_per_page'] ),
            'post_status'    => 'publish',
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
        );

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
                            <div class="tranas-system__placeholder-icon">
                                <span class="dashicons dashicons-external"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="tranas-system__content">
                    <h3 class="tranas-system__title"><?php the_title(); ?></h3>
                    <?php if ( '_blank' === $link_target ) : ?>
                        <span class="tranas-system__external-indicator" aria-hidden="true">
                            <span class="dashicons dashicons-external"></span>
                        </span>
                    <?php endif; ?>
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
}

