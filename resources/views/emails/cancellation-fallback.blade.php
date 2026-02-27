@extends('emails.layouts.custom')

@section('title', 'Kündigungsbestätigung - ' . $gym->name)

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        K&uuml;ndigungsbest&auml;tigung
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Liebe/r {{ $member->first_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        wir best&auml;tigen hiermit den Erhalt Ihrer K&uuml;ndigung.
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Ihre Mitgliedschaft endet zum <strong style="color: #2d3748;">{{ $membership->cancellation_date->format('d.m.Y') }}</strong>.
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Bis zu diesem Datum k&ouml;nnen Sie selbstverst&auml;ndlich weiterhin alle Einrichtungen nutzen.
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Wir bedauern Ihre Entscheidung und w&uuml;rden uns freuen, Sie vielleicht in Zukunft wieder bei uns begr&uuml;&szlig;en zu d&uuml;rfen.
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sportliche Gr&uuml;&szlig;e<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
