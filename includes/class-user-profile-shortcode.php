<?php
/**
 * Shortcode för användarprofilformulär
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för användarprofilens shortcode
 */
class Tranas_User_Profile_Shortcode {

    /**
     * Instans av User Meta Fields
     *
     * @var Tranas_User_Meta_Fields
     */
    private $user_meta_fields;

    /**
     * Konstruktor - registrera shortcode och AJAX-handlers
     *
     * @param Tranas_User_Meta_Fields $user_meta_fields Instans av meta-fälthanteraren
     */
    public function __construct( $user_meta_fields ) {
        $this->user_meta_fields = $user_meta_fields;
        
        add_shortcode( 'tranas_user_profile', array( $this, 'render_shortcode' ) );
        add_action( 'wp_ajax_tranas_update_user_profile', array( $this, 'handle_profile_update' ) );
    }

    /**
     * Hämta alla redigerbara användarfält (standard WordPress-fält)
     *
     * @return array Lista med fält
     */
    private function get_user_fields() {
        $fields = array(
            'user_login' => array(
                'label'    => __( 'Användarnamn', 'tranas-intranet' ),
                'type'     => 'text',
                'readonly' => true,
                'section'  => 'account',
            ),
            'user_email' => array(
                'label'    => __( 'E-postadress', 'tranas-intranet' ),
                'type'     => 'email',
                'required' => true,
                'section'  => 'account',
            ),
            'first_name' => array(
                'label'   => __( 'Förnamn', 'tranas-intranet' ),
                'type'    => 'text',
                'section' => 'personal',
            ),
            'last_name' => array(
                'label'   => __( 'Efternamn', 'tranas-intranet' ),
                'type'    => 'text',
                'section' => 'personal',
            ),
            'nickname' => array(
                'label'    => __( 'Smeknamn', 'tranas-intranet' ),
                'type'     => 'text',
                'required' => true,
                'section'  => 'personal',
            ),
            'display_name' => array(
                'label'   => __( 'Visningsnamn', 'tranas-intranet' ),
                'type'    => 'select',
                'section' => 'personal',
            ),
            'user_url' => array(
                'label'   => __( 'Webbplats', 'tranas-intranet' ),
                'type'    => 'url',
                'section' => 'contact',
            ),
            'description' => array(
                'label'   => __( 'Biografisk information', 'tranas-intranet' ),
                'type'    => 'textarea',
                'section' => 'about',
            ),
        );

        // Lägg till anpassade meta-fält
        $custom_fields = $this->user_meta_fields->get_fields();
        foreach ( $custom_fields as $field_key => $field_data ) {
            $fields[ $field_key ] = $field_data;
        }

        return $fields;
    }

    /**
     * Hämta sektionsetiketter
     *
     * @return array Sektionsetiketter
     */
    private function get_sections() {
        $sections = array(
            'account'  => __( 'Kontoinformation', 'tranas-intranet' ),
            'personal' => __( 'Personlig information', 'tranas-intranet' ),
            'contact'  => __( 'Kontaktinformation', 'tranas-intranet' ),
            'about'    => __( 'Om dig', 'tranas-intranet' ),
        );

        return $sections;
    }

    /**
     * Generera visningsnamnsalternativ
     *
     * @param WP_User $user Användarobjekt
     * @return array Alternativ för visningsnamn
     */
    private function get_display_name_options( $user ) {
        $options = array();

        // Användarnamn
        $options[ $user->user_login ] = $user->user_login;

        // Förnamn
        if ( ! empty( $user->first_name ) ) {
            $options[ $user->first_name ] = $user->first_name;
        }

        // Efternamn
        if ( ! empty( $user->last_name ) ) {
            $options[ $user->last_name ] = $user->last_name;
        }

        // Förnamn Efternamn
        if ( ! empty( $user->first_name ) && ! empty( $user->last_name ) ) {
            $full_name = $user->first_name . ' ' . $user->last_name;
            $options[ $full_name ] = $full_name;
        }

        // Efternamn Förnamn
        if ( ! empty( $user->first_name ) && ! empty( $user->last_name ) ) {
            $reverse_name = $user->last_name . ' ' . $user->first_name;
            $options[ $reverse_name ] = $reverse_name;
        }

        // Smeknamn
        if ( ! empty( $user->nickname ) && ! isset( $options[ $user->nickname ] ) ) {
            $options[ $user->nickname ] = $user->nickname;
        }

        return $options;
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
            return '<div class="tranas-profile-form-wrapper"><p>' . esc_html__( '[Användarprofilformulär visas här på frontend]', 'tranas-intranet' ) . '</p></div>';
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            return sprintf(
                '<div class="tf-message tf-message--error tranas-profile-notice tranas-profile-notice--warning">%s <a href="%s">%s</a></div>',
                esc_html__( 'Du måste vara inloggad för att redigera din profil.', 'tranas-intranet' ),
                esc_url( wp_login_url( get_permalink() ) ),
                esc_html__( 'Logga in här', 'tranas-intranet' )
            );
        }

        $atts = shortcode_atts(
            array(
                'fields'  => '', // Tom = alla fält, annars kommaseparerad lista
                'exclude' => '', // Fält att exkludera
            ),
            $atts,
            'tranas_user_profile'
        );

        $current_user = wp_get_current_user();
        $fields       = $this->get_user_fields();
        $sections     = $this->get_sections();

        // Filtrera fält om specificerat
        if ( ! empty( $atts['fields'] ) ) {
            $include_fields = array_map( 'trim', explode( ',', $atts['fields'] ) );
            $fields = array_intersect_key( $fields, array_flip( $include_fields ) );
        }

        // Exkludera fält om specificerat
        if ( ! empty( $atts['exclude'] ) ) {
            $exclude_fields = array_map( 'trim', explode( ',', $atts['exclude'] ) );
            $fields = array_diff_key( $fields, array_flip( $exclude_fields ) );
        }

        // Gruppera fält efter sektion
        $grouped_fields = array();
        foreach ( $fields as $field_key => $field_data ) {
            $section = isset( $field_data['section'] ) ? $field_data['section'] : 'other';
            if ( ! isset( $grouped_fields[ $section ] ) ) {
                $grouped_fields[ $section ] = array();
            }
            $grouped_fields[ $section ][ $field_key ] = $field_data;
        }

        ob_start();
        ?>
        <div class="tranas-profile-form-wrapper">
            <form id="tranas-user-profile-form" class="tranas-profile-form" method="post">
                <?php wp_nonce_field( 'tranas_user_profile_nonce', 'tranas_profile_nonce' ); ?>
                
                <!-- Meddelande-container för AJAX-svar -->
                <div class="tf-message-container tranas-profile-messages" role="alert" aria-live="polite" aria-atomic="true"></div>

                <?php foreach ( $grouped_fields as $section_key => $section_fields ) : ?>
                    <?php if ( isset( $sections[ $section_key ] ) ) : ?>
                        <fieldset class="tf-fieldset tranas-profile-section tranas-profile-section--<?php echo esc_attr( $section_key ); ?>">
                            <h3 class="tf-label tranas-profile-section__title"><?php echo esc_html( $sections[ $section_key ] ); ?></h3>
                            
                            <div class="tranas-profile-fields">
                                <?php foreach ( $section_fields as $field_key => $field_data ) : ?>
                                    <?php $this->render_field( $field_key, $field_data, $current_user ); ?>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="tranas-profile-actions">
                    <button type="submit" class="tf-submit tranas-profile-submit">
                        <span class="tf-submit-text tranas-profile-submit__text"><?php esc_html_e( 'Spara ändringar', 'tranas-intranet' ); ?></span>
                        <span class="tf-submit-loading tranas-profile-submit__loading" aria-hidden="true" style="display:none;"><?php esc_html_e( 'Sparar...', 'tranas-intranet' ); ?></span>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Rendera ett enskilt fält
     *
     * @param string  $field_key  Fältnyckeln
     * @param array   $field_data Fältdata
     * @param WP_User $user       Användarobjekt
     */
    private function render_field( $field_key, $field_data, $user ) {
        // Hämta värdet - antingen från user object eller user meta
        if ( strpos( $field_key, 'tranas_' ) === 0 ) {
            // Anpassat meta-fält
            $value = get_user_meta( $user->ID, $field_key, true );
        } else {
            // Standard WordPress-fält
            $value = $user->$field_key;
        }

        $field_id    = 'tranas-field-' . $field_key;
        $is_readonly = isset( $field_data['readonly'] ) && $field_data['readonly'];
        $is_required = isset( $field_data['required'] ) && $field_data['required'];
        $placeholder = isset( $field_data['placeholder'] ) ? $field_data['placeholder'] : '';
        $required_attr = $is_required ? 'required aria-required="true"' : '';
        ?>
        <div class="tf-field tranas-profile-field tranas-profile-field--<?php echo esc_attr( $field_data['type'] ); ?>">
            <label for="<?php echo esc_attr( $field_id ); ?>" class="tf-label tranas-profile-field__label">
                <?php echo esc_html( $field_data['label'] ); ?>
                <?php if ( $is_required ) : ?>
                    <span class="tf-required tranas-profile-field__required" aria-hidden="true">*</span>
                    <span class="screen-reader-text"><?php esc_html_e( '(obligatoriskt)', 'tranas-intranet' ); ?></span>
                <?php endif; ?>
            </label>

            <?php if ( $field_data['type'] === 'textarea' ) : ?>
                <textarea
                    id="<?php echo esc_attr( $field_id ); ?>"
                    name="<?php echo esc_attr( $field_key ); ?>"
                    class="tranas-profile-field__input tranas-profile-field__textarea"
                    rows="4"
                    placeholder="<?php echo esc_attr( $placeholder ); ?>"
                    <?php echo $is_readonly ? 'readonly' : ''; ?>
                    <?php echo $required_attr; ?>
                ><?php echo esc_textarea( $value ); ?></textarea>

            <?php elseif ( $field_data['type'] === 'select' ) : ?>
                <select
                    id="<?php echo esc_attr( $field_id ); ?>"
                    name="<?php echo esc_attr( $field_key ); ?>"
                    class="tranas-profile-field__input tranas-profile-field__select"
                    <?php echo $required_attr; ?>
                >
                    <?php
                    $options = $this->get_display_name_options( $user );
                    foreach ( $options as $option_value => $option_label ) :
                    ?>
                        <option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
                            <?php echo esc_html( $option_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php else : ?>
                <input
                    type="<?php echo esc_attr( $field_data['type'] ); ?>"
                    id="<?php echo esc_attr( $field_id ); ?>"
                    name="<?php echo esc_attr( $field_key ); ?>"
                    value="<?php echo esc_attr( $value ); ?>"
                    class="tranas-profile-field__input"
                    placeholder="<?php echo esc_attr( $placeholder ); ?>"
                    <?php echo $is_readonly ? 'readonly' : ''; ?>
                    <?php echo $required_attr; ?>
                />
            <?php endif; ?>

            <?php if ( $is_readonly ) : ?>
                <p class="tf-file-info tranas-profile-field__help"><?php esc_html_e( 'Detta fält kan inte ändras.', 'tranas-intranet' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Hantera profiluppdatering via AJAX
     */
    public function handle_profile_update() {
        // Verifiera nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tranas_user_profile_nonce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Säkerhetsvalidering misslyckades. Ladda om sidan och försök igen.', 'tranas-intranet' ) )
            );
        }

        // Kolla om användaren är inloggad
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(
                array( 'message' => __( 'Du måste vara inloggad för att uppdatera din profil.', 'tranas-intranet' ) )
            );
        }

        $current_user = wp_get_current_user();
        $user_id      = $current_user->ID;
        $fields       = $this->get_user_fields();
        $user_data    = array( 'ID' => $user_id );
        $meta_fields  = array( 'first_name', 'last_name', 'nickname', 'description' );
        $errors       = array();

        // Hämta anpassade meta-fältnycklar
        $custom_meta_keys = array_keys( $this->user_meta_fields->get_fields() );

        // Validera och samla in data
        foreach ( $fields as $field_key => $field_data ) {
            // Hoppa över readonly-fält
            if ( isset( $field_data['readonly'] ) && $field_data['readonly'] ) {
                continue;
            }

            // Hämta värdet
            $value = isset( $_POST[ $field_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) : '';

            // Specialhantering för description (tillåt mer formatering)
            if ( $field_key === 'description' ) {
                $value = isset( $_POST[ $field_key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field_key ] ) ) : '';
            }

            // Specialhantering för e-post
            if ( $field_key === 'user_email' ) {
                if ( ! is_email( $value ) ) {
                    $errors[] = __( 'Ange en giltig e-postadress.', 'tranas-intranet' );
                    continue;
                }
                
                // Kolla om e-posten redan används av någon annan
                $existing_user = get_user_by( 'email', $value );
                if ( $existing_user && $existing_user->ID !== $user_id ) {
                    $errors[] = __( 'Denna e-postadress används redan av ett annat konto.', 'tranas-intranet' );
                    continue;
                }
            }

            // Validera URL
            if ( $field_key === 'user_url' && ! empty( $value ) ) {
                $value = esc_url_raw( $value );
            }

            // Kolla obligatoriska fält
            if ( isset( $field_data['required'] ) && $field_data['required'] && empty( $value ) ) {
                $errors[] = sprintf(
                    /* translators: %s: fältnamn */
                    __( 'Fältet "%s" är obligatoriskt.', 'tranas-intranet' ),
                    $field_data['label']
                );
                continue;
            }

            // Hantera anpassade meta-fält
            if ( in_array( $field_key, $custom_meta_keys, true ) ) {
                $this->user_meta_fields->update_field_value( $user_id, $field_key, $value );
            }
            // Hantera WordPress standard meta-fält
            elseif ( in_array( $field_key, $meta_fields, true ) ) {
                update_user_meta( $user_id, $field_key, $value );
            }
            // Hantera WordPress user data
            else {
                $user_data[ $field_key ] = $value;
            }
        }

        // Kolla om det finns fel
        if ( ! empty( $errors ) ) {
            wp_send_json_error(
                array( 'message' => implode( '<br>', $errors ) )
            );
        }

        // Uppdatera användaren
        $result = wp_update_user( $user_data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array( 'message' => $result->get_error_message() )
            );
        }

        wp_send_json_success(
            array( 'message' => __( 'Din profil har uppdaterats!', 'tranas-intranet' ) )
        );
    }
}
