@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('eyebrow', 'Request unavailable')
@section('title', 'We could not open this request')
@section('message', 'The request could not be completed from this address or account.')
@section('guidance', 'Return to TouchNRelief home and continue from the appropriate page.')
