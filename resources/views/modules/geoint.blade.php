@extends('layouts.app')
@section('title', 'GEOINT')
@section('page-title', 'Módulo GEOINT')
@section('page-subtitle', 'Inteligencia Geoespacial y Monitoreo GPS')

@section('content')
@include('modules._placeholder', [
    'icon'  => 'fas fa-satellite',
    'color' => '#059669',
    'name'  => 'GEOINT',
    'full'  => 'Geospatial Intelligence',
    'desc'  => 'Plataforma de monitoreo GPS en tiempo real, trazado de rutas, zonas de exclusión y análisis de patrones de movimiento. Integración con dispositivos de rastreo y cámaras de vigilancia.',
    'features' => ['Monitoreo GPS en tiempo real', 'Trazado de rutas históricas', 'Geocercas y alertas', 'Análisis de patrones'],
])
@endsection
