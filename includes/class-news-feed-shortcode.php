<?php
/**
 * Shortcode för att visa personligt nyhetsflöde
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för nyhetsflödets shortcode
 */
class Tranas_News_Feed_Shortcode {

    /**
     * Instans av News Feed Preferences
     *
     * @var Tranas_News_Feed_Preferences
     */
    private $preferences;

    /**
     * Konstruktor
     *
     * @param Tranas_News_Feed_Preferences $preferences Instans av preferenser-klassen
     */
    public function __construct( $preferences ) {
        $this->preferences = $preferences;
        $this->init_hooks();
    }

    /**
     * Initiera hooks
     */
    private function init_hooks() {
        add_shortcode( 'tranas_news_feed', array( $this, 'render_shortcode' ) );
    }

    /**
     * Rendera shortcode för nyhetsflödet
     *
     * @param array $atts Shortcode-attribut
     * @return string HTML-output
     */
    public function render_shortcode( $atts ) {
        // Hoppa över rendering i admin/REST-kontext
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return '<div class="tranas-news-feed-wrapper"><p>' . 
                esc_html__( '[Nyhetsflöde visas här på frontend]', 'tranas-intranet' ) . 
                '</p></div>';
        }

        // Debug - visa endast på frontend för admin
        if ( current_user_can( 'manage_options' ) && isset( $_GET['debug_feed'] ) ) {
            echo '<div style="background:#ffe0e0;padding:10px;margin:10px 0;border:2px solid red;">';
            echo '<strong>DEBUG:</strong> Shortcode körs!<br>';
            echo 'Raw $atts: <pre>' . print_r( $atts, true ) . '</pre>';
            echo '</div>';
        }

        // Säkerställ att $atts är en array
        if ( ! is_array( $atts ) ) {
            $atts = array();
        }

        $atts = shortcode_atts(
            array(
                'posts_per_page'     => 10,
                'post_type'          => 'post',
                'taxonomy'           => 'category',
                'show_empty_message' => 'true',
                'show_settings_link' => 'true',
                'settings_url'       => '',
                'fallback'           => 'all', // 'all', 'none', eller 'latest'
                'layout'             => 'list', // 'list', 'grid', 'compact'
                'show_excerpt'       => 'true',
                'show_date'          => 'true',
                'show_category'      => 'true',
                'show_thumbnail'     => 'true',
                'excerpt_length'     => 30,
                'date_format'        => '',
            ),
            $atts,
            'tranas_news_feed'
        );

        // Konvertera string-booleans
        $show_empty_message = filter_var( $atts['show_empty_message'], FILTER_VALIDATE_BOOLEAN );
        $show_settings_link = filter_var( $atts['show_settings_link'], FILTER_VALIDATE_BOOLEAN );
        $show_excerpt       = filter_var( $atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN );
        $show_date          = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
        $show_category      = filter_var( $atts['show_category'], FILTER_VALIDATE_BOOLEAN );
        $show_thumbnail     = filter_var( $atts['show_thumbnail'], FILTER_VALIDATE_BOOLEAN );

        // Hämta användarens kategorier
        $user_categories = array();
        $is_personalized = false;

        if ( is_user_logged_in() ) {
            $user_categories = $this->preferences->get_user_categories();
            $is_personalized = ! empty( $user_categories );
        }

        // Bygg query-argument
        $query_args = array(
            'post_type'      => $atts['post_type'],
            'posts_per_page' => absint( $atts['posts_per_page'] ),
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Lägg till kategori-filter om användaren har valt kategorier
        if ( $is_personalized ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => $atts['taxonomy'],
                    'field'    => 'term_id',
                    'terms'    => $user_categories,
                    'operator' => 'IN',
                ),
            );
        } elseif ( 'none' === $atts['fallback'] ) {
            // Visa inget om ingen personalisering och fallback är 'none'
            return $this->render_no_preferences_message( $atts, $show_settings_link );
        }
        // Om fallback är 'all' eller 'latest', visa alla inlägg (ingen tax_query)

        /**
         * Filter för att anpassa query-argumenten
         *
         * @param array $query_args     Query-argument
         * @param array $atts           Shortcode-attribut
         * @param array $user_categories Användarens valda kategorier
         */
        $query_args = apply_filters( 'tranas_news_feed_query_args', $query_args, $atts, $user_categories );

        $query = new WP_Query( $query_args );

        if ( ! $query->have_posts() ) {
            wp_reset_postdata();
            
            if ( $show_empty_message ) {
                return $this->render_empty_message( $atts, $show_settings_link, $is_personalized );
            }
            return '';
        }

        ob_start();
        ?>
        <div class="tranas-news-feed tranas-news-feed--<?php echo esc_attr( $atts['layout'] ); ?>" 
             data-personalized="<?php echo $is_personalized ? 'true' : 'false'; ?>">
            
            <?php if ( $is_personalized && $show_settings_link ) : ?>
                <div class="tranas-news-feed__header">
                    <span class="tranas-news-feed__personalized-badge">
                        <?php esc_html_e( 'Ditt personliga flöde', 'tranas-intranet' ); ?>
                    </span>
                    <?php if ( ! empty( $atts['settings_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $atts['settings_url'] ); ?>" class="tranas-news-feed__settings-link">
                            <?php esc_html_e( 'Ändra inställningar', 'tranas-intranet' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="tranas-news-feed__posts">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php
                    $this->render_post_item(
                        $atts,
                        $show_excerpt,
                        $show_date,
                        $show_category,
                        $show_thumbnail
                    );
                    ?>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Rendera ett enskilt inlägg i flödet
     *
     * @param array $atts           Shortcode-attribut
     * @param bool  $show_excerpt   Visa utdrag
     * @param bool  $show_date      Visa datum
     * @param bool  $show_category  Visa kategori
     * @param bool  $show_thumbnail Visa miniatyrbild
     */
    private function render_post_item( $atts, $show_excerpt, $show_date, $show_category, $show_thumbnail ) {
        $post_id     = get_the_ID();
        $date_format = ! empty( $atts['date_format'] ) ? $atts['date_format'] : get_option( 'date_format' );
        $categories  = get_the_terms( $post_id, $atts['taxonomy'] );
        ?>
        <article class="tranas-news-feed__item" id="news-item-<?php echo esc_attr( $post_id ); ?>">
            <?php if ( $show_thumbnail && has_post_thumbnail() ) : ?>
                <div class="tranas-news-feed__thumbnail">
                    <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
                        <?php the_post_thumbnail( 'medium', array( 'class' => 'tranas-news-feed__image' ) ); ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="tranas-news-feed__content">
                <header class="tranas-news-feed__item-header">
                    <h3 class="tranas-news-feed__title">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title(); ?>
                        </a>
                    </h3>

                    <?php if ( $show_date || $show_category ) : ?>
                        <div class="tranas-news-feed__meta">
                            <?php if ( $show_date ) : ?>
                                <time class="tranas-news-feed__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                                    <?php echo esc_html( get_the_date( $date_format ) ); ?>
                                </time>
                            <?php endif; ?>

                            <?php if ( $show_category && ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
                                <div class="tranas-news-feed__categories">
                                    <?php foreach ( $categories as $category ) : ?>
                                        <span class="tranas-news-feed__category">
                                            <?php echo esc_html( $category->name ); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <?php if ( $show_excerpt ) : ?>
                    <div class="tranas-news-feed__excerpt">
                        <?php
                        $excerpt_length = absint( $atts['excerpt_length'] );
                        echo wp_trim_words( get_the_excerpt(), $excerpt_length, '&hellip;' );
                        ?>
                    </div>
                <?php endif; ?>

                <footer class="tranas-news-feed__item-footer">
                    <a href="<?php the_permalink(); ?>" class="tranas-news-feed__read-more">
                        <?php esc_html_e( 'Läs mer', 'tranas-intranet' ); ?>
                        <span class="screen-reader-text">
                            <?php
                            /* translators: %s: inläggets titel */
                            printf( esc_html__( 'om %s', 'tranas-intranet' ), get_the_title() );
                            ?>
                        </span>
                    </a>
                </footer>
            </div>
        </article>
        <?php
    }

    /**
     * Rendera meddelande när inga inlägg finns
     *
     * @param array $atts               Shortcode-attribut
     * @param bool  $show_settings_link Visa länk till inställningar
     * @param bool  $is_personalized    Om flödet är personaliserat
     * @return string HTML-output
     */
    private function render_empty_message( $atts, $show_settings_link, $is_personalized ) {
        ob_start();
        ?>
        <div class="tranas-news-feed tranas-news-feed--empty">
            <div class="tf-message tf-message--info tranas-news-feed__empty-message">
                <?php if ( $is_personalized ) : ?>
                    <p><?php esc_html_e( 'Det finns inga nyheter i dina valda kategorier just nu.', 'tranas-intranet' ); ?></p>
                    <?php if ( $show_settings_link && ! empty( $atts['settings_url'] ) ) : ?>
                        <p>
                            <a href="<?php echo esc_url( $atts['settings_url'] ); ?>">
                                <?php esc_html_e( 'Ändra dina inställningar', 'tranas-intranet' ); ?>
                            </a>
                            <?php esc_html_e( 'för att se fler nyheter.', 'tranas-intranet' ); ?>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p><?php esc_html_e( 'Det finns inga nyheter att visa just nu.', 'tranas-intranet' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendera meddelande när användaren inte har valt kategorier
     *
     * @param array $atts               Shortcode-attribut
     * @param bool  $show_settings_link Visa länk till inställningar
     * @return string HTML-output
     */
    private function render_no_preferences_message( $atts, $show_settings_link ) {
        ob_start();
        ?>
        <div class="tranas-news-feed tranas-news-feed--no-preferences">
            <div class="tf-message tf-message--info tranas-news-feed__no-preferences-message">
                <?php if ( is_user_logged_in() ) : ?>
                    <p><?php esc_html_e( 'Du har inte valt några kategorier för ditt nyhetsflöde ännu.', 'tranas-intranet' ); ?></p>
                    <?php if ( $show_settings_link && ! empty( $atts['settings_url'] ) ) : ?>
                        <p>
                            <a href="<?php echo esc_url( $atts['settings_url'] ); ?>" class="tf-button">
                                <?php esc_html_e( 'Välj kategorier', 'tranas-intranet' ); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p>
                        <?php esc_html_e( 'Logga in för att se ditt personliga nyhetsflöde.', 'tranas-intranet' ); ?>
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

