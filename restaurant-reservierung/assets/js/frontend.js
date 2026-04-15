/* Restaurant Reservierung – Frontend JS v3 */
(function($) {
    'use strict';

    var state = {
        personen: 1,
        datum: '',
        uhrzeit: '',
        monatData: {},
        currentJahr: new Date().getFullYear(),
        currentMonat: new Date().getMonth() + 1
    };

    var MONAT_NAMEN = ['Januar','Februar','März','April','Mai','Juni',
                       'Juli','August','September','Oktober','November','Dezember'];
    var TAG_KURZ    = ['Mo','Di','Mi','Do','Fr','Sa','So'];

    // gesperrt ist jetzt ein Objekt: { 'YYYY-MM-DD': 'Grund' }
    var gesperrtObj = RR.gesperrt || {};

    function istGesperrt(datum) {
        return gesperrtObj.hasOwnProperty(datum);
    }
    function gesperrtGrund(datum) {
        return gesperrtObj[datum] || '';
    }

    // ----------------------------------------------------------------
    // SCHRITT 1: Personen-Stepper
    // ----------------------------------------------------------------
    var maxP = parseInt(RR.max_personen) || 20;

    $('#rr-pers-minus').on('click', function() {
        if (state.personen <= 1) return;
        state.personen--;
        aktualisiereStepperUI();
        onPersonenChange();
    });

    $('#rr-pers-plus').on('click', function() {
        if (state.personen >= maxP) return;
        state.personen++;
        aktualisiereStepperUI();
        onPersonenChange();
    });

    function aktualisiereStepperUI() {
        $('#rr-personen').val(state.personen);
        $('#rr-pers-zahl').text(state.personen);
        // Label: Person / Personen
        var label = state.personen === 1 ? 'Person insgesamt' : 'Personen insgesamt';
        $('.rr-stepper-label').text(label);
        // Minus deaktivieren wenn 1
        $('#rr-pers-minus').prop('disabled', state.personen <= 1);
        // Plus deaktivieren wenn max
        $('#rr-pers-plus').prop('disabled', state.personen >= maxP);
    }

    function onPersonenChange() {
        state.datum   = '';
        state.uhrzeit = '';
        $('#rr-datum').val('');
        $('#rr-uhrzeit').val('');
        $('#rr-step-time').hide();
        $('#rr-step-contact').hide();
        state.monatData = {};
        ladeMonat(state.currentJahr, state.currentMonat);
    }

    // Initial Stepper-State setzen
    aktualisiereStepperUI();

    // ----------------------------------------------------------------
    // SCHRITT 2: Kalender
    // ----------------------------------------------------------------
    function ladeMonat(jahr, monat) {
        var key = jahr + '-' + ('0'+monat).slice(-2);
        $('#rr-calendar').html('<div class="rr-cal-loading"><span class="rr-spinner"></span> Verfügbarkeit laden …</div>');

        if (state.monatData[key]) {
            renderKalender(jahr, monat, state.monatData[key]);
            return;
        }

        $.post(RR.ajax_url, {
            action:   'rr_monat',
            nonce:    RR.nonce,
            jahr:     jahr,
            monat:    monat,
            personen: state.personen
        }, function(res) {
            if (res.success) {
                state.monatData[key] = res.data.tage;
                renderKalender(jahr, monat, res.data.tage);
            } else {
                $('#rr-calendar').html('<div class="rr-cal-loading">Fehler beim Laden.</div>');
            }
        });
    }

    function renderKalender(jahr, monat, tage) {
        state.currentJahr  = jahr;
        state.currentMonat = monat;

        var heute      = new Date(); heute.setHours(0,0,0,0);
        var heuteDatum = formatDatum(heute);
        var ersterTag  = new Date(jahr, monat - 1, 1);
        var startWt    = ersterTag.getDay() === 0 ? 6 : ersterTag.getDay() - 1;
        var tageIm     = new Date(jahr, monat, 0).getDate();

        var html = '';

        // Header
        html += '<div class="rr-cal-header">';
        html += '<button type="button" class="rr-cal-prev"' + (kannZurueck(jahr,monat) ? '' : ' disabled') + '>&#8249;</button>';
        html += '<div class="rr-cal-monat">' + MONAT_NAMEN[monat-1] + ' <strong>' + jahr + '</strong></div>';
        html += '<button type="button" class="rr-cal-next">&#8250;</button>';
        html += '</div>';

        html += '<div class="rr-cal-grid">';
        TAG_KURZ.forEach(function(t) {
            html += '<div class="rr-cal-th">' + t + '</div>';
        });

        for (var i = 0; i < startWt; i++) {
            html += '<div class="rr-cal-td rr-cal-td--leer"></div>';
        }

        for (var d = 1; d <= tageIm; d++) {
            var datumStr = String(jahr) + '-' + ('0'+monat).slice(-2) + '-' + ('0'+d).slice(-2);
            var status   = tage[datumStr] || 'inaktiv';
            var isToday  = datumStr === heuteDatum;
            var isSel    = datumStr === state.datum;

            // Heute ist immer gesperrt für Online-Buchung
            var istHeute   = isToday;
            var istGesperrtTag = istGesperrt(datumStr);
            var klickbar   = !istHeute && !istGesperrtTag && (status === 'frei' || status === 'teilweise');
            var klickInfo  = istHeute || (istGesperrtTag && status !== 'inaktiv' && status !== 'vergangen');

            var cls = 'rr-cal-td rr-cal-td--' + status;
            if (istHeute)       cls += ' rr-cal-td--heute';
            if (isSel)          cls += ' rr-cal-td--selected';
            if (klickbar)       cls += ' rr-cal-td--klickbar';
            if (klickInfo)      cls += ' rr-cal-td--info';

            var dot = '';
            if (status === 'frei')      dot = '<span class="rr-dot rr-dot--frei"></span>';
            if (status === 'teilweise') dot = '<span class="rr-dot rr-dot--teilweise"></span>';
            if (status === 'voll')      dot = '<span class="rr-dot rr-dot--voll"></span>';
            if (status === 'gesperrt' || istGesperrtTag) dot = '<span class="rr-dot rr-dot--gesperrt"></span>';

            var attrs = '';
            if (klickbar)  attrs += ' data-datum="' + datumStr + '"';
            if (klickInfo) attrs += ' data-info-datum="' + datumStr + '"';
            // Tooltip für gesperrte Tage
            if (istGesperrtTag) {
                var grund = gesperrtGrund(datumStr);
                attrs += ' data-tooltip="' + (grund ? escAttr(grund) : 'Nicht verfügbar') + '"';
            }

            html += '<div class="' + cls + '"' + attrs + '>';
            html += '<span class="rr-cal-day-num">' + d + '</span>' + dot;
            html += '</div>';
        }
        html += '</div>';

        $('#rr-calendar').html(html);

        // Events
        $('#rr-calendar').off('.rrcal')
            .on('click.rrcal', '.rr-cal-td--klickbar', function() {
                waehleDatum($(this).data('datum'));
            })
            .on('click.rrcal', '.rr-cal-td--heute', function() {
                zeigeInfoHinweis('heute');
            })
            .on('click.rrcal', '.rr-cal-td--info:not(.rr-cal-td--heute)', function() {
                var grund = $(this).data('tooltip') || 'Dieser Tag ist nicht verfügbar.';
                zeigeInfoHinweis('gesperrt', grund);
            })
            .on('click.rrcal', '.rr-cal-prev', function() {
                if (!kannZurueck(state.currentJahr, state.currentMonat)) return;
                var nm = state.currentMonat - 1, nj = state.currentJahr;
                if (nm < 1) { nm = 12; nj--; }
                ladeMonat(nj, nm);
            })
            .on('click.rrcal', '.rr-cal-next', function() {
                var nm = state.currentMonat + 1, nj = state.currentJahr;
                if (nm > 12) { nm = 1; nj++; }
                ladeMonat(nj, nm);
            });

        // Tooltip bei gesperrten Tagen – folgt dem Cursor
        $('#rr-calendar').on('mouseenter.rrcal', '[data-tooltip]', function(e) {
            var text = $(this).data('tooltip');
            if (!text) return;
            $('.rr-tooltip').remove();
            $('body').append($('<div class="rr-tooltip">').text(text));
            positionTooltip($('.rr-tooltip'), e);
        }).on('mousemove.rrcal', '[data-tooltip]', function(e) {
            positionTooltip($('.rr-tooltip'), e);
        }).on('mouseleave.rrcal', '[data-tooltip]', function() {
            $('.rr-tooltip').remove();
        });
    }

    function zeigeInfoHinweis(typ, grund) {
        var $box = $('#rr-cal-info');
        if (!$box.length) {
            $box = $('<div id="rr-cal-info" class="rr-cal-info"></div>');
            $('#rr-calendar').after($box);
        }
        var tel  = RR.telefon || '';
        var telLink = tel ? ' Bitte rufen Sie uns an: <a href="tel:' + tel.replace(/\s/g,'') + '">' + tel + '</a>' : '';

        if (typ === 'heute') {
            $box.html('📞 Für den heutigen Tag sind keine Online-Reservierungen möglich.' + telLink);
        } else {
            $box.html('🔒 ' + (grund || 'Dieser Tag ist nicht verfügbar.') + (tel ? telLink : ''));
        }
        $box.show();
        // Nach 6s automatisch ausblenden
        clearTimeout($box.data('timer'));
        $box.data('timer', setTimeout(function() { $box.fadeOut(); }, 6000));
    }

    function kannZurueck(jahr, monat) {
        var h = new Date();
        return !(jahr === h.getFullYear() && monat === h.getMonth() + 1);
    }

    function waehleDatum(datum) {
        state.datum   = datum;
        state.uhrzeit = '';
        $('#rr-datum').val(datum);
        $('#rr-uhrzeit').val('');
        $('#rr-cal-info').hide();

        var key = state.currentJahr + '-' + ('0'+state.currentMonat).slice(-2);
        if (state.monatData[key]) renderKalender(state.currentJahr, state.currentMonat, state.monatData[key]);

        ladeSlots(datum);
    }

    // ----------------------------------------------------------------
    // SCHRITT 3: Zeitslots
    // ----------------------------------------------------------------
    function ladeSlots(datum) {
        var step = $('#rr-step-time');
        step.show();
        $('#rr-step-contact').hide();

        var dateObj  = new Date(datum + 'T00:00:00');
        var wtNames  = ['So','Mo','Di','Mi','Do','Fr','Sa'];
        var d = ('0'+dateObj.getDate()).slice(-2);
        var m = ('0'+(dateObj.getMonth()+1)).slice(-2);
        $('#rr-selected-date').text(wtNames[dateObj.getDay()] + ', ' + d + '.' + m + '.' + dateObj.getFullYear());
        $('#rr-slots').html('<div class="rr-slots-loading"><span class="rr-spinner"></span></div>');

        $('html,body').animate({ scrollTop: step.offset().top - 80 }, 400);

        $.post(RR.ajax_url, {
            action:   'rr_slots',
            nonce:    RR.nonce,
            datum:    datum,
            personen: state.personen
        }, function(res) {
            if (!res.success) {
                $('#rr-slots').html('<p class="rr-slots-err">Fehler beim Laden.</p>');
                return;
            }
            if (res.data.gesperrt) {
                $('#rr-slots').html('<p class="rr-slots-err">Dieser Tag ist nicht verfügbar.</p>');
                return;
            }

            var html = '';
            res.data.slots.forEach(function(s) {
                if (s.verfuegbar) {
                    var hint = '';
                    if (s.frei <= 3) {
                        hint = '<span class="rr-slot-hint">Nur noch ' + s.frei + ' Platz' + (s.frei > 1 ? '\u00e4tze' : '') + ' frei</span>';
                    } else if (s.frei < maxP * 0.5) {
                        hint = '<span class="rr-slot-hint">Noch ' + s.frei + ' Pl\u00e4tze frei</span>';
                    }
                    html += '<button type="button" class="rr-slot-btn" data-uhrzeit="' + s.uhrzeit + '">' +
                            '<span class="rr-slot-time">' + s.label + '</span>' + hint + '</button>';
                } else {
                    html += '<button type="button" class="rr-slot-btn rr-slot-btn--voll" disabled>' +
                            '<span class="rr-slot-time">' + s.label + '</span>' +
                            '<span class="rr-slot-hint">Ausgebucht</span></button>';
                }
            });
            $('#rr-slots').html(html || '<p class="rr-slots-err">Keine Zeitslots verfügbar.</p>');
        });
    }

    $(document).on('click', '.rr-slot-btn:not([disabled])', function() {
        state.uhrzeit = $(this).data('uhrzeit');
        $('#rr-uhrzeit').val(state.uhrzeit);
        $('.rr-slot-btn').removeClass('active');
        $(this).addClass('active');
        var step = $('#rr-step-contact');
        step.show();
        aktualisiereSummary();
        $('html,body').animate({ scrollTop: step.offset().top - 80 }, 400);
    });

    // ----------------------------------------------------------------
    // SCHRITT 4: Zusammenfassung + Submit
    // ----------------------------------------------------------------
    function aktualisiereSummary() {
        if (!state.datum || !state.uhrzeit) return;
        var dateObj = new Date(state.datum + 'T00:00:00');
        var wtNames = ['So','Mo','Di','Mi','Do','Fr','Sa'];
        var d = ('0'+dateObj.getDate()).slice(-2);
        var m = ('0'+(dateObj.getMonth()+1)).slice(-2);
        var datumDE = wtNames[dateObj.getDay()] + ', ' + d + '.' + m + '.' + dateObj.getFullYear();
        $('#rr-summary').html(
            '<div class="rr-summary-inner">' +
            '<span>📅 ' + datumDE + '</span>' +
            '<span>🕐 ' + state.uhrzeit + ' Uhr</span>' +
            '<span>👥 ' + state.personen + ' Person' + (state.personen > 1 ? 'en' : '') + '</span>' +
            '</div>'
        );
    }

    $('#rr-form').on('submit', function(e) {
        e.preventDefault();
        var fehler = false;
        if (!state.personen || !state.datum || !state.uhrzeit) fehler = true;
        ['name','email','telefon'].forEach(function(f) {
            var el = $('#rr-' + f);
            if (!el.val().trim()) { el.addClass('rr-error'); fehler = true; }
            else el.removeClass('rr-error');
        });
        if (!$('#rr-dsgvo').prop('checked')) fehler = true;
        if (fehler) { showMsg('Bitte füllen Sie alle Pflichtfelder aus.', 'error'); return; }

        var btn = $('#rr-submit');
        btn.prop('disabled', true);
        btn.find('.rr-submit-text').hide();
        btn.find('.rr-submit-loading').show();

        $.post(RR.ajax_url, {
            action:    'rr_buchen',
            nonce:     RR.nonce,
            name:      $('#rr-name').val(),
            email:     $('#rr-email').val(),
            telefon:   $('#rr-telefon').val(),
            personen:  state.personen,
            datum:     state.datum,
            uhrzeit:   state.uhrzeit,
            anmerkung: $('#rr-anmerkung').val()
        }, function(res) {
            btn.prop('disabled', false);
            btn.find('.rr-submit-text').show();
            btn.find('.rr-submit-loading').hide();
            if (res.success) {
                $('#rr-form-container').html(
                    '<div class="rr-success-view"><div class="rr-success-icon">✅</div>' +
                    '<h3>Anfrage eingegangen!</h3><p>' + res.data.message + '</p></div>'
                );
                $('html,body').animate({ scrollTop: $('#rr-form-container').offset().top - 60 }, 400);
            } else {
                showMsg(res.data || 'Unbekannter Fehler.', 'error');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            btn.find('.rr-submit-text').show();
            btn.find('.rr-submit-loading').hide();
            showMsg('Verbindungsfehler. Bitte erneut versuchen.', 'error');
        });
    });

    $('#rr-form input, #rr-form select, #rr-form textarea').on('input change', function() {
        $(this).removeClass('rr-error');
    });

    function showMsg(text, typ) {
        $('#rr-msg').removeClass('rr-msg--success rr-msg--error')
            .addClass('rr-msg--' + typ).text(text).show();
        $('html,body').animate({ scrollTop: $('#rr-msg').offset().top - 80 }, 300);
    }

    function formatDatum(date) {
        return date.getFullYear() + '-' + ('0'+(date.getMonth()+1)).slice(-2) + '-' + ('0'+date.getDate()).slice(-2);
    }

    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function positionTooltip($tip, e) {
        if (!$tip.length) return;
        // clientX/Y für position:fixed (viewport-relativ)
        var x  = e.clientX;
        var y  = e.clientY;
        var tw = $tip.outerWidth()  || 140;
        var th = $tip.outerHeight() || 30;
        var ww = window.innerWidth;
        var top  = y - th - 12;
        var left = x - tw / 2;
        if (left < 8)          left = 8;
        if (left + tw > ww - 8) left = ww - tw - 8;
        if (top < 8)            top  = y + 18;
        $tip.css({ top: top, left: left });
    }

    // Initial laden
    ladeMonat(state.currentJahr, state.currentMonat);

})(jQuery);
