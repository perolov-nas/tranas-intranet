<?php
/**
 * Anpassade meta-fält för användare
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för anpassade användarfält
 */
class Tranas_User_Meta_Fields {

    /**
     * Definierade anpassade fält
     *
     * @var array
     */
    private $custom_fields = array();

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->define_fields();
        $this->init_hooks();
    }

    /**
     * Definiera anpassade fält
     */
    private function define_fields() {
        $this->custom_fields = array(
            'tranas_phone' => array(
                'label'       => __( 'Telefonnummer', 'tranas-intranet' ),
                'type'        => 'tel',
                'placeholder' => __( 't.ex. 0140-123 45', 'tranas-intranet' ),
                'section'     => 'contact',
                'icon'        => '📞',
                'sanitize'    => 'sanitize_text_field',
            ),
            'tranas_mobile' => array(
                'label'       => __( 'Mobilnummer', 'tranas-intranet' ),
                'type'        => 'tel',
                'placeholder' => __( 't.ex. 070-123 45 67', 'tranas-intranet' ),
                'section'     => 'contact',
                'icon'        => '📱',
                'sanitize'    => 'sanitize_text_field',
            ),
            'tranas_quick_number' => array(
                'label'       => __( 'Snabbnummer', 'tranas-intranet' ),
                'type'        => 'text',
                'placeholder' => __( 't.ex. 9999', 'tranas-intranet' ),
                'section'     => 'contact',
                'icon'        => '📟',
                'sanitize'    => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Initiera hooks
     */
    private function init_hooks() {
        // Admin - visa fält på användarprofilsidan
        add_action( 'show_user_profile', array( $this, 'render_admin_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'render_admin_fields' ) );

        // Admin - spara fält
        add_action( 'personal_options_update', array( $this, 'save_admin_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_admin_fields' ) );

        // Registrera meta för REST API (om det behövs i framtiden)
        add_action( 'init', array( $this, 'register_meta' ) );
    }

    /**
     * Hämta alla anpassade fält
     *
     * @return array
     */
    public function get_fields() {
        return $this->custom_fields;
    }

    /**
     * Hämta sektioner för anpassade fält
     *
     * @return array
     */
    public function get_sections() {
        return array(
            'contact' => __( 'Kontaktuppgifter', 'tranas-intranet' ),
        );
    }

    /**
     * Registrera meta-fält
     */
    public function register_meta() {
        foreach ( $this->custom_fields as $field_key => $field_data ) {
            register_meta(
                'user',
                $field_key,
                array(
                    'type'              => 'string',
                    'description'       => $field_data['label'],
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => $field_data['sanitize'],
                )
            );
        }
    }

    /**
     * Rendera fält i WordPress admin
     *
     * @param WP_User $user Användarobjekt
     */
    public function render_admin_fields( $user ) {
        $sections = $this->get_sections();
        $grouped_fields = array();

        // Gruppera fält efter sektion
        foreach ( $this->custom_fields as $field_key => $field_data ) {
            $section = isset( $field_data['section'] ) ? $field_data['section'] : 'other';
            if ( ! isset( $grouped_fields[ $section ] ) ) {
                $grouped_fields[ $section ] = array();
            }
            $grouped_fields[ $section ][ $field_key ] = $field_data;
        }

        ?>
        <h2><?php esc_html_e( 'Tranås Intranät - Anpassade fält', 'tranas-intranet' ); ?></h2>
        
        <?php foreach ( $grouped_fields as $section_key => $section_fields ) : ?>
            <?php if ( isset( $sections[ $section_key ] ) ) : ?>
                <h3><?php echo esc_html( $sections[ $section_key ] ); ?></h3>
                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ( $section_fields as $field_key => $field_data ) : ?>
                            <tr>
                                <th>
                                    <label for="<?php echo esc_attr( $field_key ); ?>">
                                        <?php echo esc_html( $field_data['label'] ); ?>
                                    </label>
                                </th>
                                <td>
                                    <input
                                        type="<?php echo esc_attr( $field_data['type'] ); ?>"
                                        name="<?php echo esc_attr( $field_key ); ?>"
                                        id="<?php echo esc_attr( $field_key ); ?>"
                                        value="<?php echo esc_attr( get_user_meta( $user->ID, $field_key, true ) ); ?>"
                                        class="regular-text"
                                        placeholder="<?php echo esc_attr( $field_data['placeholder'] ?? '' ); ?>"
                                    />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php
    }

    /**
     * Spara fält i WordPress admin
     *
     * @param int $user_id Användar-ID
     */
    public function save_admin_fields( $user_id ) {
        // Kontrollera behörighet
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        // Spara varje fält
        foreach ( $this->custom_fields as $field_key => $field_data ) {
            if ( isset( $_POST[ $field_key ] ) ) {
                $sanitize_callback = $field_data['sanitize'];
                $value = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $field_key ] ) );
                update_user_meta( $user_id, $field_key, $value );
            }
        }
    }

    /**
     * Hämta fältvärde för en användare
     *
     * @param int    $user_id   Användar-ID
     * @param string $field_key Fältnyckel
     * @return string
     */
    public function get_field_value( $user_id, $field_key ) {
        return get_user_meta( $user_id, $field_key, true );
    }

    /**
     * Uppdatera fältvärde för en användare
     *
     * @param int    $user_id   Användar-ID
     * @param string $field_key Fältnyckel
     * @param string $value     Värde
     * @return bool
     */
    public function update_field_value( $user_id, $field_key, $value ) {
        if ( ! isset( $this->custom_fields[ $field_key ] ) ) {
            return false;
        }

        $sanitize_callback = $this->custom_fields[ $field_key ]['sanitize'];
        $value = call_user_func( $sanitize_callback, $value );

        return update_user_meta( $user_id, $field_key, $value );
    }
}

