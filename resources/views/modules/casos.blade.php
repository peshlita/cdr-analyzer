@extends('layouts.app')
@section('title', 'Casos')
@section('page-title', 'Módulo Integración de Casos')
@section('page-subtitle', 'Gestión y correlación de expedientes')

@section('content')
@include('modules._placeholder', [
    'icon'  => 'fas fa-folder-open',
    'color' => '#d97706',
    'name'  => 'CASOS',
    'full'  => 'Integración de Casos',
    'desc'  => 'Sistema centralizado para la gestión de expedientes de investigación. Correlaciona datos de COMINT, OSINT, GEOINT e INCIDENCIA en un único caso, permitiendo una visión 360° de la investigación.',
    'features' => ['Expedientes integrados', 'Correlación multi-módulo', 'Línea de tiempo de eventos', 'Exportación de reportes'],
])
@endsection
