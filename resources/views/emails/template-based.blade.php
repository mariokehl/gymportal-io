@extends('emails.layouts.custom')

@section('title', $gym->name . ' - E-Mail')

@section('content')
    {!! $renderedContent !!}
@endsection
