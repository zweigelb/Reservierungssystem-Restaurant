<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RR_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_rr_buchen',          [ $this, 'buchen' ] );
        add_action( 'wp_ajax_nopriv_rr_buchen',   [ $this, 'buchen' ] );
        add_action( 'wp_ajax_rr_slots',           [ $this, 'slots' ] );
        add_action( 'wp_ajax_nopriv_rr_slots',    [ $this, 'slots' ] );
        add_action( 'wp_ajax_rr_monat',           [ $this, 'monat' ] );
        add_action( 'wp_ajax_nopriv_rr_monat',    [ $this, 'monat' ] );
    }

    // Verfügbarkeit aller Tage eines Monats
    public function monat() {
        check_ajax_referer( 'rr_frontend', 'nonce' );

        $jahr     = (int) ( $_POST['jahr']     ?? date('Y') );
        $monat    = (int) ( $_POST['monat']    ?? date('m') );
        $personen = (int) ( $_POST['personen'] ?? 1 );

        if ( $monat < 1 || $monat > 12 ) wp_send_json_error('Ungültiger Monat');

        $start    = get_option( 'rr_slots_start', '17:00' );
        $end      = get_option( 'rr_slots_end', '21:00' );
        $interval = (int) get_option( 'rr_slots_interval', 30 );
        $max      = (int) get_option( 'rr_max_personen', 20 );
        $erlaubte_wt = array_map( 'intval', (array) get_option( 'rr_wochentage', [3,4,5,6] ) );

        // Anzahl Slots pro Tag
        $slots_pro_tag = 0;
        $ts = strtotime("2000-01-01 $start");
        $te = strtotime("2000-01-01 $end");
        while ( $ts < $te ) { $slots_pro_tag++; $ts += $interval * 60; }

        $tage_im_monat = (int) date('t', mktime(0,0,0,$monat,1,$jahr));
        $heute = strtotime( date('Y-m-d') );
        $tage  = [];

        for ( $t = 1; $t <= $tage_im_monat; $t++ ) {
            $datum    = sprintf('%04d-%02d-%02d', $jahr, $monat, $t);
            $ts_tag   = strtotime($datum);
            $wt_js    = (int) date('N', $ts_tag); // 1=Mo..7=So

            $status = 'inaktiv'; // nicht buchbarer Wochentag

            if ( in_array( $wt_js, $erlaubte_wt ) ) {
                if ( $datum === date('Y-m-d') ) {
                    $status = 'heute';
                } elseif ( $ts_tag < $heute ) {
                    $status = 'vergangen';
                } elseif ( RR_Database::ist_gesperrt( $datum ) ) {
                    $status = 'gesperrt';
                } else {
                    // Pruefe alle Slots
                    $freie_slots = 0;
                    $ts_slot = strtotime("$datum $start");
                    $te_slot = strtotime("$datum $end");
                    while ( $ts_slot < $te_slot ) {
                        $uhrzeit = date('H:i', $ts_slot);
                        $belegt  = RR_Database::personen_im_slot( $datum, $uhrzeit );
                        if ( ($max - $belegt) >= $personen ) $freie_slots++;
                        $ts_slot += $interval * 60;
                    }
                    if ( $freie_slots === 0 ) {
                        $status = 'voll';
                    } elseif ( $freie_slots < $slots_pro_tag ) {
                        $status = 'teilweise';
                    } else {
                        $status = 'frei';
                    }
                }
            }

            $tage[$datum] = $status;
        }

        wp_send_json_success( [ 'tage' => $tage ] );
    }

    // Gibt verfügbare Slots für ein Datum zurück
    public function slots() {
        check_ajax_referer( 'rr_frontend', 'nonce' );

        $datum    = sanitize_text_field( $_POST['datum'] ?? '' );
        $personen = (int) ( $_POST['personen'] ?? 1 );

        if ( ! $datum || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
            wp_send_json_error( 'Ungültiges Datum' );
        }

        if ( RR_Database::ist_gesperrt( $datum ) ) {
            wp_send_json_success( [ 'slots' => [], 'gesperrt' => true ] );
        }

        $start    = get_option( 'rr_slots_start', '17:00' );
        $end      = get_option( 'rr_slots_end', '21:00' );
        $interval = (int) get_option( 'rr_slots_interval', 30 );
        $max      = (int) get_option( 'rr_max_personen', 20 );

        $slots  = [];
        $ts     = strtotime( $datum . ' ' . $start );
        $ts_end = strtotime( $datum . ' ' . $end );

        while ( $ts < $ts_end ) {
            $uhrzeit  = date( 'H:i', $ts );
            $belegt   = RR_Database::personen_im_slot( $datum, $uhrzeit );
            $frei     = $max - $belegt;
            $verfuegbar = $frei >= $personen;

            $slots[] = [
                'uhrzeit'    => $uhrzeit,
                'label'      => $uhrzeit . ' Uhr',
                'frei'       => $frei,
                'verfuegbar' => $verfuegbar,
            ];
            $ts += $interval * 60;
        }

        wp_send_json_success( [ 'slots' => $slots, 'gesperrt' => false ] );
    }

    // Buchung speichern
    public function buchen() {
        check_ajax_referer( 'rr_frontend', 'nonce' );

        $name     = sanitize_text_field( $_POST['name']     ?? '' );
        $email    = sanitize_email(      $_POST['email']    ?? '' );
        $telefon  = sanitize_text_field( $_POST['telefon']  ?? '' );
        $datum    = sanitize_text_field( $_POST['datum']    ?? '' );
        $uhrzeit  = sanitize_text_field( $_POST['uhrzeit']  ?? '' );
        $personen = (int)               ($_POST['personen'] ?? 0  );
        $anmerkung= sanitize_textarea_field( $_POST['anmerkung'] ?? '' );

        // Validierung
        $fehler = [];
        if ( ! $name )                          $fehler[] = 'Bitte geben Sie Ihren Namen ein.';
        if ( ! is_email( $email ) )             $fehler[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        if ( ! $telefon )                       $fehler[] = 'Bitte geben Sie Ihre Telefonnummer ein.';
        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum ) ) $fehler[] = 'Bitte wählen Sie ein gültiges Datum.';
        if ( ! preg_match('/^\d{2}:\d{2}$/', $uhrzeit ) )     $fehler[] = 'Bitte wählen Sie eine Uhrzeit.';
        if ( $personen < 1 || $personen > 500 ) $fehler[] = 'Bitte wählen Sie die Personenzahl.';

        if ( $fehler ) {
            wp_send_json_error( implode( ' ', $fehler ) );
        }

        // Heute nicht buchbar
        if ( $datum === date('Y-m-d') ) {
            wp_send_json_error( 'Für den heutigen Tag sind keine Online-Reservierungen möglich. Bitte rufen Sie uns an.' );
        }

        // Wochentag prüfen
        $erlaubte_wt = array_map( 'intval', (array) get_option( 'rr_wochentage', [ 3, 4, 5, 6 ] ) );
        $wochentag   = (int) date( 'N', strtotime( $datum ) );
        if ( ! in_array( $wochentag, $erlaubte_wt ) ) {
            wp_send_json_error( 'Dieses Datum ist leider nicht buchbar.' );
        }

        // Gesperrt?
        if ( RR_Database::ist_gesperrt( $datum ) ) {
            wp_send_json_error( 'Dieser Tag ist leider nicht verfügbar (z. B. wegen einer Veranstaltung oder Betriebsferien).' );
        }

        // Kapazität prüfen
        $max    = (int) get_option( 'rr_max_personen', 20 );
        $belegt = RR_Database::personen_im_slot( $datum, $uhrzeit );
        if ( $belegt + $personen > $max ) {
            wp_send_json_error( 'Für diesen Zeitslot stehen leider nicht mehr genügend Plätze zur Verfügung. Bitte wählen Sie eine andere Uhrzeit.' );
        }

        // Speichern
        $result = RR_Database::insert( [
            'name'      => $name,
            'email'     => $email,
            'telefon'   => $telefon,
            'datum'     => $datum,
            'uhrzeit'   => $uhrzeit,
            'personen'  => $personen,
            'anmerkung' => $anmerkung,
            'status'    => 'ausstehend',
        ] );

        if ( ! $result ) {
            wp_send_json_error( 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut oder kontaktieren Sie uns telefonisch.' );
        }

        $res = RR_Database::get( $result['id'] );

        // E-Mails senden
        RR_Mail::gast_eingang( $res );
        RR_Mail::admin_neue_anfrage( $res );

        wp_send_json_success( [
            'message' => 'Vielen Dank, ' . esc_html( $name ) . '! Ihre Reservierungsanfrage ist eingegangen. Sie erhalten in Kürze eine Eingangsbestätigung per E-Mail. Sobald wir Ihre Anfrage geprüft haben, erhalten Sie eine separate Bestätigung.',
        ] );
    }
}
