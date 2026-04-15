<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RR_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'admin_post_rr_settings',     [ $this, 'save_settings' ] );
        add_action( 'admin_post_rr_tag_sperren',  [ $this, 'save_tag_sperren' ] );
        add_action( 'admin_post_rr_tag_freigeben',[ $this, 'save_tag_freigeben' ] );
        add_action( 'admin_post_rr_status',       [ $this, 'save_status' ] );
        add_action( 'admin_post_rr_loeschen',     [ $this, 'save_loeschen' ] );
        add_action( 'admin_notices',         [ $this, 'notices' ] );
    }

    public function menu() {
        add_menu_page(
            'Reservierungen', 'Reservierungen', 'manage_options',
            'rr-reservierungen', [ $this, 'page_reservierungen' ],
            'dashicons-calendar-alt', 30
        );
        add_submenu_page(
            'rr-reservierungen', 'Reservierungen', 'Übersicht',
            'manage_options', 'rr-reservierungen', [ $this, 'page_reservierungen' ]
        );
        add_submenu_page(
            'rr-reservierungen', 'Tage sperren', 'Tage sperren',
            'manage_options', 'rr-gesperrt', [ $this, 'page_gesperrt' ]
        );
        add_submenu_page(
            'rr-reservierungen', 'Einstellungen', 'Einstellungen',
            'manage_options', 'rr-einstellungen', [ $this, 'page_einstellungen' ]
        );
    }

    public function assets( $hook ) {
        if ( strpos( $hook, 'rr-' ) === false ) return;
        wp_enqueue_style(  'rr-admin', RR_PLUGIN_URL . 'assets/css/admin.css', [], RR_VERSION );
        wp_enqueue_script( 'rr-admin', RR_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], RR_VERSION, true );
    }

    public function notices() {
        if ( ! isset( $_GET['rr_msg'] ) ) return;
        $msgs = [
            'bestaetigt' => [ 'success', 'Reservierung wurde bestätigt und Gast informiert.' ],
            'abgelehnt'  => [ 'error',   'Reservierung wurde abgelehnt und Gast informiert.' ],
            'geloescht'  => [ 'warning', 'Reservierung wurde gelöscht.' ],
            'gesperrt'   => [ 'success', 'Tag wurde gesperrt.' ],
            'freigegeben'=> [ 'success', 'Tag wurde freigegeben.' ],
            'gespeichert'=> [ 'success', 'Einstellungen gespeichert.' ],
        ];
        $key = sanitize_key( $_GET['rr_msg'] );
        if ( isset( $msgs[ $key ] ) ) {
            echo '<div class="notice notice-' . $msgs[$key][0] . ' is-dismissible"><p>' . esc_html( $msgs[$key][1] ) . '</p></div>';
        }
    }

    // ---- Status-Aktionen ----

    public function save_status() {
        check_admin_referer( 'rr_status' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff' );

        $id     = (int) $_POST['res_id'];
        $status = sanitize_key( $_POST['neuer_status'] );
        if ( ! in_array( $status, [ 'bestaetigt', 'abgelehnt' ] ) ) wp_die( 'Ungültiger Status' );

        $res = RR_Database::get( $id );
        if ( ! $res ) wp_die( 'Reservierung nicht gefunden' );

        RR_Database::status_setzen( $id, $status );

        if ( $status === 'bestaetigt' ) {
            RR_Mail::gast_bestaetigung( $res );
        } else {
            RR_Mail::gast_ablehnung( $res );
        }

        wp_redirect( admin_url( 'admin.php?page=rr-reservierungen&rr_msg=' . $status ) );
        exit;
    }

    public function save_loeschen() {
        check_admin_referer( 'rr_loeschen' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff' );
        RR_Database::loeschen( (int) $_POST['res_id'] );
        wp_redirect( admin_url( 'admin.php?page=rr-reservierungen&rr_msg=geloescht' ) );
        exit;
    }

    public function save_tag_sperren() {
        check_admin_referer( 'rr_tag_sperren' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff' );
        $von   = sanitize_text_field( $_POST['von']   ?? '' );
        $bis   = sanitize_text_field( $_POST['bis']   ?? $von );
        $grund = sanitize_text_field( $_POST['grund'] ?? '' );
        if ( ! $von ) wp_die( 'Kein Datum angegeben' );
        if ( ! $bis ) $bis = $von;
        RR_Database::tag_sperren( $von, $bis, $grund );
        wp_redirect( admin_url( 'admin.php?page=rr-gesperrt&rr_msg=gesperrt' ) );
        exit;
    }

    public function save_tag_freigeben() {
        check_admin_referer( 'rr_tag_freigeben' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff' );
        RR_Database::tag_entsperren( (int) $_POST['sperr_id'] );
        wp_redirect( admin_url( 'admin.php?page=rr-gesperrt&rr_msg=freigegeben' ) );
        exit;
    }

    public function save_settings() {
        check_admin_referer( 'rr_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Kein Zugriff' );

        update_option( 'rr_slots_start',     sanitize_text_field( $_POST['slots_start'] ) );
        update_option( 'rr_slots_end',       sanitize_text_field( $_POST['slots_end'] ) );
        update_option( 'rr_slots_interval',  (int) $_POST['slots_interval'] );
        update_option( 'rr_max_personen',    (int) $_POST['max_personen'] );
        update_option( 'rr_admin_email',     sanitize_email( $_POST['admin_email'] ) );
        update_option( 'rr_restaurant_name', sanitize_text_field( $_POST['restaurant_name'] ) );
        update_option( 'rr_telefon',          sanitize_text_field( $_POST['rr_telefon'] ?? '' ) );
        update_option( 'rr_datenschutz_url',  esc_url_raw( $_POST['datenschutz_url'] ?? '/datenschutz' ) );

        $wochentage = array_map( 'intval', (array) ( $_POST['wochentage'] ?? [] ) );
        update_option( 'rr_wochentage', $wochentage );

        wp_redirect( admin_url( 'admin.php?page=rr-einstellungen&rr_msg=gespeichert' ) );
        exit;
    }

    // ---- Seiten ----

    public function page_reservierungen() {
        $filter_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
        $reservierungen = RR_Database::alle( $filter_status ? [ 'status' => $filter_status ] : [] );
        $counts = [
            'alle'        => count( RR_Database::alle() ),
            'ausstehend'  => count( RR_Database::alle( [ 'status' => 'ausstehend' ] ) ),
            'bestaetigt'  => count( RR_Database::alle( [ 'status' => 'bestaetigt' ] ) ),
            'abgelehnt'   => count( RR_Database::alle( [ 'status' => 'abgelehnt' ] ) ),
        ];
        $wochentage_namen = [ 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So' ];
        ?>
        <div class="wrap rr-wrap">
            <h1 class="rr-h1">🍽 Reservierungen</h1>

            <div class="rr-tabs">
                <a href="?page=rr-reservierungen" class="rr-tab <?= !$filter_status ? 'active' : '' ?>">Alle <span class="rr-badge"><?= $counts['alle'] ?></span></a>
                <a href="?page=rr-reservierungen&status=ausstehend" class="rr-tab <?= $filter_status==='ausstehend' ? 'active' : '' ?>">Ausstehend <span class="rr-badge rr-badge--warn"><?= $counts['ausstehend'] ?></span></a>
                <a href="?page=rr-reservierungen&status=bestaetigt" class="rr-tab <?= $filter_status==='bestaetigt' ? 'active' : '' ?>">Bestätigt <span class="rr-badge rr-badge--ok"><?= $counts['bestaetigt'] ?></span></a>
                <a href="?page=rr-reservierungen&status=abgelehnt" class="rr-tab <?= $filter_status==='abgelehnt' ? 'active' : '' ?>">Abgelehnt <span class="rr-badge rr-badge--err"><?= $counts['abgelehnt'] ?></span></a>
            </div>

            <?php if ( empty( $reservierungen ) ) : ?>
                <p class="rr-leer">Keine Reservierungen gefunden.</p>
            <?php else : ?>
            <table class="rr-table">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Uhrzeit</th>
                        <th>Name</th>
                        <th>Personen</th>
                        <th>Telefon</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $reservierungen as $r ) :
                    $ts   = strtotime( $r->datum );
                    $wtag = $wochentage_namen[ (int) date( 'N', $ts ) ] ?? '';
                    $datum_fmt = $wtag . ' ' . date( 'd.m.Y', $ts );
                    $status_label = [ 'ausstehend' => '⏳ Ausstehend', 'bestaetigt' => '✅ Bestätigt', 'abgelehnt' => '❌ Abgelehnt' ];
                ?>
                <tr class="rr-row rr-row--<?= esc_attr( $r->status ) ?>" data-id="<?= $r->id ?>">
                    <td><?= esc_html( $datum_fmt ) ?></td>
                    <td><?= esc_html( $r->uhrzeit ) ?> Uhr</td>
                    <td>
                        <strong><?= esc_html( $r->name ) ?></strong>
                        <?php if ( $r->anmerkung ) : ?>
                            <span class="rr-note-icon" title="<?= esc_attr( $r->anmerkung ) ?>">💬</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc_html( $r->personen ) ?></td>
                    <td><a href="tel:<?= esc_attr( $r->telefon ) ?>"><?= esc_html( $r->telefon ) ?></a></td>
                    <td><a href="mailto:<?= esc_attr( $r->email ) ?>"><?= esc_html( $r->email ) ?></a></td>
                    <td><span class="rr-status rr-status--<?= esc_attr( $r->status ) ?>"><?= $status_label[$r->status] ?? $r->status ?></span></td>
                    <td class="rr-actions">
                        <?php if ( $r->status === 'ausstehend' ) : ?>
                        <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline">
                            <?php wp_nonce_field('rr_status') ?>
                            <input type="hidden" name="action"     value="rr_status">
                            <input type="hidden" name="res_id"     value="<?= $r->id ?>">
                            <input type="hidden" name="neuer_status" value="bestaetigt">
                            <button type="submit" class="rr-btn rr-btn--ok">✓ Bestätigen</button>
                        </form>
                        <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline">
                            <?php wp_nonce_field('rr_status') ?>
                            <input type="hidden" name="action"     value="rr_status">
                            <input type="hidden" name="res_id"     value="<?= $r->id ?>">
                            <input type="hidden" name="neuer_status" value="abgelehnt">
                            <button type="submit" class="rr-btn rr-btn--err">✗ Ablehnen</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline" onsubmit="return confirm('Wirklich löschen?')">
                            <?php wp_nonce_field('rr_loeschen') ?>
                            <input type="hidden" name="action" value="rr_loeschen">
                            <input type="hidden" name="res_id" value="<?= $r->id ?>">
                            <button type="submit" class="rr-btn rr-btn--del">🗑</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function page_gesperrt() {
        $gesperrt = RR_Database::gesperrte_tage();
        ?>
        <div class="wrap rr-wrap">
            <h1 class="rr-h1">🔒 Tage sperren</h1>
            <p>Gesperrte Tage sind im Buchungsformular nicht auswählbar (z. B. Urlaub, Betriebsferien, große Gesellschaften).</p>

            <div class="rr-card">
                <h2>Zeitraum sperren</h2>
                <p>Für einen einzelnen Tag tragen Sie das gleiche Datum in beide Felder ein.</p>
                <form method="post" action="<?= admin_url('admin-post.php') ?>">
                    <?php wp_nonce_field('rr_tag_sperren') ?>
                    <input type="hidden" name="action" value="rr_tag_sperren">
                    <table class="form-table">
                        <tr>
                            <th><label for="von">Von</label></th>
                            <td><input type="date" id="von" name="von" required min="<?= date('Y-m-d') ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="bis">Bis</label></th>
                            <td><input type="date" id="bis" name="bis" required min="<?= date('Y-m-d') ?>" class="regular-text">
                            <p class="description">Für einen einzelnen Tag: gleiches Datum wie "Von".</p></td>
                        </tr>
                        <tr>
                            <th><label for="grund">Grund (optional)</label></th>
                            <td><input type="text" id="grund" name="grund" class="regular-text" placeholder="z. B. Betriebsferien, Urlaub, Große Gesellschaft"></td>
                        </tr>
                    </table>
                    <?php submit_button( 'Zeitraum sperren', 'primary' ) ?>
                </form>
            </div>

            <?php if ( $gesperrt ) : ?>
            <div class="rr-card">
                <h2>Gesperrte Tage</h2>
                <table class="rr-table">
                    <thead><tr><th>Von</th><th>Bis</th><th>Tage</th><th>Grund</th><th>Aktion</th></tr></thead>
                    <tbody>
                    <?php
                    foreach ( $gesperrt as $g ) :
                        $ts_von = strtotime( $g->von );
                        $ts_bis = strtotime( $g->bis );
                        $tage   = (int) round( ( $ts_bis - $ts_von ) / 86400 ) + 1;
                    ?>
                    <tr>
                        <td><?= date( 'd.m.Y', $ts_von ) ?></td>
                        <td><?= date( 'd.m.Y', $ts_bis ) ?></td>
                        <td><?= $tage == 1 ? '1 Tag' : $tage . ' Tage' ?></td>
                        <td><?= esc_html( $g->grund ?: '–' ) ?></td>
                        <td>
                            <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline">
                                <?php wp_nonce_field('rr_tag_freigeben') ?>
                                <input type="hidden" name="action"   value="rr_tag_freigeben">
                                <input type="hidden" name="sperr_id" value="<?= $g->id ?>">
                                <button type="submit" class="rr-btn rr-btn--ok">Freigeben</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
                <p class="rr-leer">Keine Tage gesperrt.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function page_einstellungen() {
        $wochentage_alle = [ 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag' ];
        $aktive_wt = (array) get_option( 'rr_wochentage', [ 3, 4, 5, 6 ] );
        ?>
        <div class="wrap rr-wrap">
            <h1 class="rr-h1">⚙️ Einstellungen</h1>
            <div class="rr-card">
                <form method="post" action="<?= admin_url('admin-post.php') ?>">
                    <?php wp_nonce_field('rr_settings') ?>
                    <input type="hidden" name="action" value="rr_settings">
                    <table class="form-table">
                        <tr>
                            <th>Restaurant Name</th>
                            <td><input type="text" name="restaurant_name" value="<?= esc_attr( get_option('rr_restaurant_name') ) ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Admin E-Mail</th>
                            <td><input type="email" name="admin_email" value="<?= esc_attr( get_option('rr_admin_email') ) ?>" class="regular-text">
                            <p class="description">An diese Adresse werden neue Reservierungsanfragen gesendet.</p></td>
                        </tr>
                        <tr>
                            <th>Öffnungszeiten Slots</th>
                            <td>
                                Von <input type="time" name="slots_start" value="<?= esc_attr( get_option('rr_slots_start', '17:00') ) ?>">
                                bis <input type="time" name="slots_end"   value="<?= esc_attr( get_option('rr_slots_end', '21:00') ) ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Slot-Intervall</th>
                            <td>
                                <select name="slots_interval">
                                    <?php foreach ( [ 15, 30, 45, 60 ] as $min ) :
                                        $sel = selected( get_option('rr_slots_interval', 30), $min, false );
                                    ?>
                                    <option value="<?= $min ?>" <?= $sel ?>><?= $min ?> Minuten</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Max. Personen pro Slot</th>
                            <td><input type="number" name="max_personen" value="<?= esc_attr( get_option('rr_max_personen', 20) ) ?>" min="1" max="500" class="small-text">
                            <p class="description">Maximale Gesamtpersonenzahl aller Buchungen in einem Zeitslot.</p></td>
                        </tr>
                        <tr>
                            <th>Buchbare Wochentage</th>
                            <td>
                                <?php foreach ( $wochentage_alle as $num => $name ) : ?>
                                <label style="margin-right:12px">
                                    <input type="checkbox" name="wochentage[]" value="<?= $num ?>" <?= in_array( $num, $aktive_wt ) ? 'checked' : '' ?>>
                                    <?= esc_html( $name ) ?>
                                </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Telefonnummer (für Hinweise)</th>
                            <td><input type="text" name="rr_telefon" value="<?= esc_attr( get_option('rr_telefon', '') ) ?>" class="regular-text" placeholder="z. B. 06334 2056">
                            <p class="description">Wird angezeigt wenn jemand auf einen gesperrten Tag oder den heutigen Tag klickt.</p></td>
                        </tr>
                        <tr>
                            <th>Datenschutz-URL</th>
                            <td><input type="text" name="datenschutz_url" value="<?= esc_attr( get_option('rr_datenschutz_url', '/datenschutz') ) ?>" class="regular-text" placeholder="/datenschutz"></td>
                        </tr>
                    </table>
                    <p class="description" style="margin-left:200px">Shortcode für das Buchungsformular: <code>[restaurant_reservierung]</code></p>
                    <?php submit_button( 'Einstellungen speichern' ) ?>
                </form>
            </div>
        </div>
        <?php
    }
}
