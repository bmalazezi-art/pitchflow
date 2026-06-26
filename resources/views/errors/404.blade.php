@extends('errors.layout')
@section('code', '404')
@section('title', app()->getLocale() === 'sq' ? 'Faqja nuk u gjet' : 'Page not found')
@section('message', app()->getLocale() === 'sq' ? 'Faqja që kërkuat nuk ekziston ose është zhvendosur.' : 'The page you requested does not exist or has moved.')
