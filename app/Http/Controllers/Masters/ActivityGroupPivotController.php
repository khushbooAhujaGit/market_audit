<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityGroupPivotController extends Controller
{
    //
    function __construct()
    {
        return $this->middleware("auth");
    }
}
