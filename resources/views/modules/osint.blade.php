@extends('layouts.app')
@section('title', 'OSINT')
@section('page-title', 'Módulo OSINT')
@section('page-subtitle', 'Búsqueda e Investigación de Personas')

@section('content')
@include('modules._placeholder', [
    'icon'  => 'fas fa-user-secret',
    'color' => '#7c3aed',
    'name'  => 'OSINT',
    'full'  => 'Open Source Intelligence',
    'desc'  => 'Herramientas para búsqueda e investigación de personas a través de fuentes abiertas: redes sociales, registros públicos, geolocalización y correlación de identidades digitales.',
    'features' => ['Búsqueda en redes sociales', 'Verificación de identidades', 'Análisis de perfiles digitales', 'Correlación de datos públicos'],
])
@endsection
