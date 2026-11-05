@extends('errors::minimal')

@section('title', 'Acceso no permitido')
@section('code', '403')
@section('message', __($exception->getMessage() ?: 'This action is unauthorized.'))
