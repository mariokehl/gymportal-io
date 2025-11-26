<?php

return [
    'page_title' => 'Mitglieder',

    'status' => [
        'active'    => 'Aktiv',
        'pending'   => 'Ausstehend',
        'cancelled' => 'Storniert',
        'paused'    => 'Pausiert',
        'expired'   => 'Abgelaufen',
        'inactive'  => 'Inaktiv',
        'overdue'   => 'Überfällig',
    ],

    'filters' => [
        'all_status' => 'Alle Status',
        'active'     => 'Aktiv',
        'inactive'   => 'Inaktiv',
        'paused'     => 'Pausiert',
        'pending'    => 'Ausstehend',
        'overdue'    => 'Überfällig',
    ],

    'table' => [
        'recent_members_title' => 'Zuletzt angelegte Mitglieder',
        'headers' => [
            'name'          => 'Name',
            'member_number' => 'Mitgliedsnummer',
            'membership'    => 'Mitgliedschaft',
            'status'        => 'Status',
            'last_visit'    => 'Letzter Besuch',
            'contract_end'  => 'Vertragsende',
            'actions'       => 'Aktionen',
        ],
        'empty'               => 'Keine Mitglieder vorhanden.',
        'never_checked_in'    => 'Noch nie',
        'no_members_title'    => 'Keine Mitglieder gefunden',
        'no_members_filtered' => 'Keine Mitglieder entsprechen den aktuellen Filtern.',
        'no_members_default'  => 'Beginnen Sie mit dem Hinzufügen Ihres ersten Mitglieds.',
    ],

    'search' => [
        'member_placeholder' => 'Mitglieder durchsuchen...',
    ],

    'actions' => [
        'new_member'   => 'Neues Mitglied',
        'new_contract' => 'Neuer Vertrag',
        'show'         => 'Anzeigen',
        'edit'         => 'Bearbeiten',
        'delete'       => 'Löschen',
    ],

    'delete_tooltip' => [
        'title' => 'Löschen nicht möglich',
        'hint'  => 'Tipp: Mitglied muss erst inaktiviert werden',
    ],

    'delete_modal' => [
        'title'                => 'Mitglied löschen',
        'status_inactive'      => 'Status: Inaktiv',
        'no_active_memberships'=> 'Keine aktiven Mitgliedschaften',
        'no_open_payments'     => 'Keine offenen Zahlungen',
        'warning_text'         => 'Diese Aktion kann nicht rückgängig gemacht werden. Alle Daten des Mitglieds werden unwiderruflich gelöscht.',
        'confirm_button'       => 'Endgültig löschen',
        'deleting'             => 'Wird gelöscht...',
        'cancel_button'        => 'Abbrechen',
    ],

    'pagination' => [
        'item_label' => 'Mitglieder',
    ],
];
