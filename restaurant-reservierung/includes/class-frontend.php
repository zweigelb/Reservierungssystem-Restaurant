<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RR_Frontend {

    public function __construct() {
        add_shortcode( 'restaurant_reservierung', [ $this, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets() {
        wp_enqueue_style(  'rr-frontend', RR_PLUGIN_URL . 'assets/css/frontend.css', [], RR_VERSION );
        wp_enqueue_script( 'rr-frontend', RR_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], RR_VERSION, true );
        wp_localize_script( 'rr-frontend', 'RR', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'rr_frontend' ),
            'gesperrt'       => $this->gesperrte_tage_mit_grund(),
            'wochentage'     => array_map( 'intval', (array) get_option( 'rr_wochentage', [ 3, 4, 5, 6 ] ) ),
            'slots_start'    => get_option( 'rr_slots_start', '17:00' ),
            'slots_end'      => get_option( 'rr_slots_end', '21:00' ),
            'slots_interval' => (int) get_option( 'rr_slots_interval', 30 ),
            'max_personen'   => (int) get_option( 'rr_max_personen', 20 ),
            'telefon'        => get_option( 'rr_telefon', '' ),
        ] );
    }

    private function gesperrte_tage_mit_grund() {
        $eintraege = RR_Database::gesperrte_tage();
        $result    = [];
        foreach ( $eintraege as $e ) {
            $ts = strtotime( $e->von );
            $te = strtotime( $e->bis );
            while ( $ts <= $te ) {
                $result[ date( 'Y-m-d', $ts ) ] = $e->grund ?: '';
                $ts += 86400;
            }
        }
        return $result;
    }

    public function shortcode() {
        $max_personen = (int) get_option( 'rr_max_personen', 20 );
        ob_start();
        ?>
        <div class="rr-form-wrap" id="rr-form-wrap">
            <div class="rr-form-inner" id="rr-form-container">
                <h2 class="rr-form-title">Tisch reservieren</h2>
                <p class="rr-form-sub">Wählen Sie zunächst Ihren Wunschtermin. Wir bestätigen Ihre Anfrage schnellstmöglich per E-Mail.</p>

                <div id="rr-msg" class="rr-msg" style="display:none"></div>

                <form id="rr-form" novalidate>
                    <?php wp_nonce_field( 'rr_frontend', 'rr_nonce' ) ?>

                    <!-- SCHRITT 1: Personenzahl -->
                    <div class="rr-step">
                        <div class="rr-step-label">1. Anzahl Personen</div>
                        <div class="rr-stepper">
                            <button type="button" class="rr-stepper-btn rr-stepper-minus" id="rr-pers-minus" disabled>−</button>
                            <div class="rr-stepper-display">
                                <span class="rr-stepper-icon">👥</span>
                                <span id="rr-pers-zahl">1</span>
                                <span class="rr-stepper-label">Person insgesamt</span>
                            </div>
                            <button type="button" class="rr-stepper-btn rr-stepper-plus" id="rr-pers-plus">+</button>
                        </div>
                        <div class="rr-stepper-hint">Die Gesamtzahl der Personen für die Buchung.</div>
                        <input type="hidden" id="rr-personen" name="personen" value="1">
                    </div>

                    <!-- SCHRITT 2: Kalender -->
                    <div class="rr-step rr-step--calendar" id="rr-step-calendar">
                        <div class="rr-step-label">2. Datum wählen</div>
                        <input type="hidden" id="rr-datum" name="datum">

                        <div class="rr-calendar" id="rr-calendar">
                            <!-- wird per JS gebaut -->
                            <div class="rr-cal-loading">Bitte zuerst Personenzahl wählen</div>
                        </div>

                        <div class="rr-cal-legende">
                            <span class="rr-l rr-l--frei">Verfügbar</span>
                            <span class="rr-l rr-l--teilweise">Teilweise belegt</span>
                            <span class="rr-l rr-l--voll">Ausgebucht</span>
                            <span class="rr-l rr-l--gesperrt">Nicht verfügbar</span>
                        </div>
                    </div>

                    <!-- SCHRITT 3: Uhrzeit -->
                    <div class="rr-step rr-step--time" id="rr-step-time" style="display:none">
                        <div class="rr-step-label">3. Uhrzeit wählen</div>
                        <div class="rr-selected-date" id="rr-selected-date"></div>
                        <div class="rr-slots" id="rr-slots">
                            <!-- wird per JS gefüllt -->
                        </div>
                        <input type="hidden" id="rr-uhrzeit" name="uhrzeit">
                    </div>

                    <!-- SCHRITT 4: Kontaktdaten -->
                    <div class="rr-step rr-step--contact" id="rr-step-contact" style="display:none">
                        <div class="rr-step-label">4. Ihre Kontaktdaten</div>
                        <div class="rr-grid">
                            <div class="rr-field">
                                <label for="rr-name">Name <span>*</span></label>
                                <input type="text" id="rr-name" name="name" placeholder="Vor- und Nachname" autocomplete="name" required>
                            </div>
                            <div class="rr-field">
                                <label for="rr-email">E-Mail <span>*</span></label>
                                <input type="email" id="rr-email" name="email" placeholder="ihre@email.de" autocomplete="email" required>
                            </div>
                            <div class="rr-field">
                                <label for="rr-telefon">Telefon <span>*</span></label>
                                <input type="tel" id="rr-telefon" name="telefon" placeholder="+49 631 ..." autocomplete="tel" required>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label for="rr-anmerkung">Anmerkungen (optional)</label>
                                <textarea id="rr-anmerkung" name="anmerkung" placeholder="Allergien, besondere Wünsche, Anlässe …" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Zusammenfassung -->
                        <div class="rr-summary" id="rr-summary"></div>

                        <div class="rr-dsgvo">
                            <label>
                                <input type="checkbox" id="rr-dsgvo" required>
                                Ich habe die <a href="<?= esc_url( get_option('rr_datenschutz_url', '/datenschutz') ) ?>" target="_blank">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner Daten zu. <span>*</span>
                            </label>
                        </div>

                        <button type="submit" class="rr-submit" id="rr-submit">
                            <span class="rr-submit-text">Reservierung anfragen</span>
                            <span class="rr-submit-loading" style="display:none">Wird gesendet …</span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
