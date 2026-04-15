<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RR_Mail {

    private static function headers() {
        $name  = get_option( 'rr_restaurant_name', get_option( 'blogname' ) );
        $email = get_option( 'rr_admin_email', get_option( 'admin_email' ) );
        return [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$name} <{$email}>",
        ];
    }

    private static function wrap( $inhalt, $titel ) {
        $name = get_option( 'rr_restaurant_name', get_option( 'blogname' ) );
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
            .wrap{max-width:560px;margin:30px auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
            .head{background:#1a1a2e;padding:28px 32px;color:#fff}
            .head h1{margin:0;font-size:20px;font-weight:600;letter-spacing:.5px}
            .head p{margin:4px 0 0;font-size:13px;opacity:.7}
            .body{padding:28px 32px;color:#333;line-height:1.6}
            .box{background:#f8f8f8;border-left:4px solid #e8c547;padding:16px 20px;border-radius:0 4px 4px 0;margin:18px 0}
            .box table{border-collapse:collapse;width:100%}
            .box td{padding:5px 0;font-size:14px}
            .box td:first-child{color:#666;width:130px;font-size:13px}
            .btn{display:inline-block;padding:12px 28px;background:#e8c547;color:#1a1a2e;text-decoration:none;border-radius:4px;font-weight:700;font-size:14px;margin-top:8px}
            .footer{background:#f0f0f0;padding:14px 32px;font-size:12px;color:#999;text-align:center}
        </style></head><body>
        <div class="wrap">
            <div class="head"><h1>' . esc_html( $titel ) . '</h1><p>' . esc_html( $name ) . '</p></div>
            <div class="body">' . $inhalt . '</div>
            <div class="footer">Diese E-Mail wurde automatisch generiert. Bitte nicht direkt antworten.</div>
        </div></body></html>';
    }

    private static function buchungs_tabelle( $res ) {
        $wochentage = [ 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag' ];
        $ts   = strtotime( $res->datum );
        $wtag = $wochentage[ (int) date( 'N', $ts ) ] ?? '';
        $datum_fmt = $wtag . ', ' . date( 'd.m.Y', $ts );

        $rows = [
            'Name'        => esc_html( $res->name ),
            'Datum'       => esc_html( $datum_fmt ),
            'Uhrzeit'     => esc_html( $res->uhrzeit ) . ' Uhr',
            'Personen'    => esc_html( $res->personen ),
            'Telefon'     => esc_html( $res->telefon ),
            'E-Mail'      => esc_html( $res->email ),
        ];
        if ( ! empty( $res->anmerkung ) ) {
            $rows['Anmerkung'] = esc_html( $res->anmerkung );
        }

        $html = '<table>';
        foreach ( $rows as $label => $val ) {
            $html .= "<tr><td>{$label}:</td><td><strong>{$val}</strong></td></tr>";
        }
        $html .= '</table>';
        return '<div class="box">' . $html . '</div>';
    }

    // Admin: neue Reservierungsanfrage
    public static function admin_neue_anfrage( $res ) {
        $admin_email = get_option( 'rr_admin_email', get_option( 'admin_email' ) );
        $url         = admin_url( 'admin.php?page=rr-reservierungen' );

        $inhalt = '<p>Es ist eine neue Reservierungsanfrage eingegangen:</p>'
                . self::buchungs_tabelle( $res )
                . '<p><a class="btn" href="' . esc_url( $url ) . '">Jetzt bearbeiten</a></p>';

        wp_mail(
            $admin_email,
            'Neue Reservierungsanfrage – ' . $res->name,
            self::wrap( $inhalt, 'Neue Reservierungsanfrage' ),
            self::headers()
        );
    }

    // Gast: Eingangsbestätigung (ausstehend)
    public static function gast_eingang( $res ) {
        $inhalt = '<p>Hallo <strong>' . esc_html( $res->name ) . '</strong>,</p>
        <p>vielen Dank für Ihre Reservierungsanfrage! Wir haben diese erhalten und werden sie so schnell wie möglich bearbeiten. Sie erhalten eine gesonderte Bestätigung per E-Mail.</p>'
                . self::buchungs_tabelle( $res )
                . '<p>Bei Fragen erreichen Sie uns unter: <a href="mailto:' . esc_attr( get_option( 'rr_admin_email' ) ) . '">' . esc_html( get_option( 'rr_admin_email' ) ) . '</a></p>';

        wp_mail(
            $res->email,
            'Ihre Reservierungsanfrage ist eingegangen',
            self::wrap( $inhalt, 'Anfrage eingegangen' ),
            self::headers()
        );
    }

    // Gast: Bestätigung
    public static function gast_bestaetigung( $res ) {
        $tel      = get_option( 'rr_telefon', '' );
        $tel_link = $tel ? ' Sie erreichen uns telefonisch unter <a href="tel:' . esc_attr( preg_replace('/\s+/','',$tel) ) . '"><strong>' . esc_html( $tel ) . '</strong></a>.' : '';

        $inhalt = '<p>Hallo <strong>' . esc_html( $res->name ) . '</strong>,</p>
        <p>wir freuen uns, Ihnen mitteilen zu können, dass Ihre Reservierung <strong>bestätigt</strong> wurde. Wir freuen uns auf Ihren Besuch!</p>'
                . self::buchungs_tabelle( $res )
                . '<div style="background:#f9f6ee;border-left:4px solid rgb(197,171,107);padding:14px 18px;border-radius:0 6px 6px 0;margin:20px 0;font-size:14px;line-height:1.7;color:#444;">
            Sollte sich an der Personenzahl noch etwas ändern, bitten wir Sie, uns kurz Bescheid zu geben – auch wenn es sehr kurzfristig ist. Ein kurzer Anruf bei uns genügt.' . $tel_link . ' Damit helfen Sie uns sehr bei unserer Planung und sorgen gleichzeitig dafür, dass wir auch künftig – anders als manche Restaurants – auf eine No-Show-Gebühr verzichten können.
        </div>'
                . '<p>Bei Fragen erreichen Sie uns unter: <a href="mailto:' . esc_attr( get_option( 'rr_admin_email' ) ) . '">' . esc_html( get_option( 'rr_admin_email' ) ) . '</a></p>';

        wp_mail(
            $res->email,
            'Ihre Reservierung wurde bestätigt',
            self::wrap( $inhalt, 'Reservierung bestätigt ✓' ),
            self::headers()
        );
    }

    // Gast: Ablehnung
    public static function gast_ablehnung( $res ) {
        $inhalt = '<p>Hallo <strong>' . esc_html( $res->name ) . '</strong>,</p>
        <p>leider müssen wir Ihnen mitteilen, dass wir Ihre Reservierungsanfrage für den <strong>' . esc_html( date( 'd.m.Y', strtotime( $res->datum ) ) ) . ' um ' . esc_html( $res->uhrzeit ) . ' Uhr</strong> nicht bestätigen können.</p>
        <p>Dies kann z. B. daran liegen, dass der gewünschte Zeitslot inzwischen ausgebucht ist oder das Datum für uns nicht verfügbar ist.</p>
        <p>Wir bitten um Ihr Verständnis und würden uns freuen, Sie zu einem anderen Termin begrüßen zu dürfen. Bitte kontaktieren Sie uns direkt: <a href="mailto:' . esc_attr( get_option( 'rr_admin_email' ) ) . '">' . esc_html( get_option( 'rr_admin_email' ) ) . '</a></p>';

        wp_mail(
            $res->email,
            'Ihre Reservierungsanfrage – Leider kein freier Tisch',
            self::wrap( $inhalt, 'Reservierungsanfrage' ),
            self::headers()
        );
    }
}
