@extends('layouts.app')
@section('title', 'Incidencia Delictiva')
@section('page-title', 'Módulo Incidencia Delictiva')
@section('page-subtitle', 'Análisis y estadísticas de delincuencia')

@section('content')
@include('modules._placeholder', [
    'icon'  => 'fas fa-exclamation-triangle',
    'color' => '#dc2626',
    'name'  => 'INCIDENCIA',
    'full'  => 'Incidencia Delictiva',
    'desc'  => 'Sistema de registro, análisis y visualización de incidencias delictivas. Mapas de calor, tendencias temporales, correlación geográfica y reportes estadísticos para la toma de decisiones.',
    'features' => ['Mapas de calor delictivo', 'Tendencias y estadísticas', 'Reportes automáticos', 'Alertas por zona'],
])
@endsection
