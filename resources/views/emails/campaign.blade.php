@extends('emails.layout')

@section('email-title'){{ $emailSubject }}@endsection

@section('content')
{!! $emailBody !!}
@endsection
