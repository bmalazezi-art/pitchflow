<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
    public function showView(){
        return view("/");
    }
}
