@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('eyebrow', 'Service error')
@section('title', 'TouchNRelief could not complete this request')
@section('message', 'The technical details were kept private and recorded for troubleshooting.')
@section('guidance', 'Try again later. If the problem continues, share the reference ID below with the administrator.')
