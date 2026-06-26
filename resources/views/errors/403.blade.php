@extends('errors.layout')
@section('code', '403')
@section('title', app()->getLocale() === 'sq' ? 'Qasja u refuzua' : 'Access denied')
@section('message', app()->getLocale() === 'sq' ? 'Nuk keni leje për të hapur këtë faqe.' : 'You do not have permission to open this page.')
