<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;

class ModuleController extends Controller
{
    public function osint()
    {
        AuditLogger::log('view_module', 'Acceso al módulo OSINT', 'osint');
        return view('modules.osint');
    }

    public function incidencia()
    {
        AuditLogger::log('view_module', 'Acceso al módulo Incidencia', 'incidencia');
        return view('modules.incidencia');
    }

    public function geoint()
    {
        AuditLogger::log('view_module', 'Acceso al módulo GEOINT', 'geoint');
        return view('modules.geoint');
    }

    public function casos()
    {
        AuditLogger::log('view_module', 'Acceso al módulo Casos', 'casos');
        return view('modules.casos');
    }
}
