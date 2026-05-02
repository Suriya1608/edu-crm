@extends('emails.layout')

@section('email-title', $emailSubject)

@section('content')
    {!! $emailBody !!}
@endsection
