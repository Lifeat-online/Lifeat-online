<?php

namespace App\Http\Controllers;

use App\Models\CivicFaultReport;
use Illuminate\Http\Request;

class CivicFaultMapController extends Controller
{
    public function index(Request $request)
    {
        return view('faults.index', [
            'categories' => CivicFaultReport::categories(),
            'severities' => CivicFaultReport::severities(),
            'statuses' => CivicFaultReport::statuses(),
        ]);
    }
}

