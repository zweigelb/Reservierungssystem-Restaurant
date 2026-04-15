<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RR_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Reservierungen
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rr_reservierungen (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100) NOT NULL,
            email       VARCHAR(150) NOT NULL,
            telefon     VARCHAR(50)  NOT NULL,
            datum       DATE         NOT NULL,
            uhrzeit     VARCHAR(10)  NOT NULL,
            personen    TINYINT(3)   NOT NULL,
            anmerkung   TEXT,
            status      ENUM('ausstehend','bestaetigt','abgelehnt') NOT NULL DEFAULT 'ausstehend',
            token       VARCHAR(64)  NOT NULL,
            erstellt_am DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token)
        ) $charset;";

        // Gesperrte Tage (von/bis)
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rr_gesperrt (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            von         DATE         NOT NULL,
            bis         DATE         NOT NULL,
            grund       VARCHAR(255),
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );

        // Migration: alte gesperrt-Tabelle (datum-Spalte) auf von/bis upgraden
        global $wpdb;
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}rr_gesperrt" );
        if ( in_array( 'datum', $cols ) && ! in_array( 'von', $cols ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}rr_gesperrt ADD COLUMN von DATE NOT NULL DEFAULT '2000-01-01' AFTER id" );
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}rr_gesperrt ADD COLUMN bis DATE NOT NULL DEFAULT '2000-01-01' AFTER von" );
            $wpdb->query( "UPDATE {$wpdb->prefix}rr_gesperrt SET von = datum, bis = datum" );
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}rr_gesperrt DROP INDEX datum" );
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}rr_gesperrt DROP COLUMN datum" );
        }

        // Default-Einstellungen
        $defaults = [
            'rr_slots_start'    => '17:00',
            'rr_slots_end'      => '21:00',
            'rr_slots_interval' => 15,
            'rr_max_personen'   => 20,
            'rr_wochentage'     => [ 3, 4, 5, 6 ], // Mi=3, Do=4, Fr=5, Sa=6
            'rr_admin_email'    => get_option( 'admin_email' ),
            'rr_restaurant_name'=> get_option( 'blogname' ),
        ];
        foreach ( $defaults as $key => $val ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $val );
            }
        }
    }

    // ---- Reservierungen ----

    public static function insert( $data ) {
        global $wpdb;
        $data['token']       = bin2hex( random_bytes( 32 ) );
        $data['erstellt_am'] = current_time( 'mysql' );
        $wpdb->insert( "{$wpdb->prefix}rr_reservierungen", $data );
        return $wpdb->insert_id ? [ 'id' => $wpdb->insert_id, 'token' => $data['token'] ] : false;
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rr_reservierungen WHERE id = %d", $id
        ) );
    }

    public static function get_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rr_reservierungen WHERE token = %s", sanitize_text_field( $token )
        ) );
    }

    public static function alle( $filter = [] ) {
        global $wpdb;
        $where = '1=1';
        $vals  = [];
        if ( ! empty( $filter['status'] ) ) {
            $where .= ' AND status = %s';
            $vals[] = $filter['status'];
        }
        if ( ! empty( $filter['datum'] ) ) {
            $where .= ' AND datum = %s';
            $vals[] = $filter['datum'];
        }
        $sql = "SELECT * FROM {$wpdb->prefix}rr_reservierungen WHERE $where ORDER BY datum ASC, uhrzeit ASC";
        if ( $vals ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, ...$vals ) );
        }
        return $wpdb->get_results( $sql );
    }

    public static function status_setzen( $id, $status ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}rr_reservierungen",
            [ 'status' => $status ],
            [ 'id'     => (int) $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    public static function loeschen( $id ) {
        global $wpdb;
        return $wpdb->delete( "{$wpdb->prefix}rr_reservierungen", [ 'id' => (int) $id ], [ '%d' ] );
    }

    // Wie viele Personen sind für Datum+Uhrzeit bereits bestätigt oder ausstehend?
    public static function personen_im_slot( $datum, $uhrzeit, $exclude_id = 0 ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(personen),0)
             FROM {$wpdb->prefix}rr_reservierungen
             WHERE datum = %s AND uhrzeit = %s AND status IN ('ausstehend','bestaetigt') AND id != %d",
            $datum, $uhrzeit, (int) $exclude_id
        ) );
    }

    // ---- Gesperrte Tage ----

    public static function tag_sperren( $von, $bis, $grund = '' ) {
        global $wpdb;
        // Sicherstellen von <= bis
        if ( $von > $bis ) { $tmp = $von; $von = $bis; $bis = $tmp; }
        return $wpdb->insert( "{$wpdb->prefix}rr_gesperrt", [
            'von'   => $von,
            'bis'   => $bis,
            'grund' => sanitize_text_field( $grund ),
        ] );
    }

    public static function tag_entsperren( $id ) {
        global $wpdb;
        return $wpdb->delete( "{$wpdb->prefix}rr_gesperrt", [ 'id' => (int) $id ], [ '%d' ] );
    }

    public static function gesperrte_tage() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}rr_gesperrt ORDER BY von ASC" );
    }

    public static function ist_gesperrt( $datum ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rr_gesperrt WHERE %s BETWEEN von AND bis", $datum
        ) );
    }

    // Gibt den Grund zurück wenn Datum gesperrt ist
    public static function gesperrt_grund( $datum ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT grund FROM {$wpdb->prefix}rr_gesperrt WHERE %s BETWEEN von AND bis LIMIT 1", $datum
        ) ) ?? '';
    }
}
