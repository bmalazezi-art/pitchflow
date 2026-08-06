@extends('errors.layout')
@section('code', '403')
@section('title', app()->getLocale() === 'sq' ? 'Qasja u refuzua' : 'Access denied')
@section('message', app()->getLocale() === 'sq' ? 'Nuk keni leje për këtë faqe ose veprim. Ju lutemi kontaktoni pronarin ose administratorin nëse ju duhet qasje.' : 'You don’t have permission for this page or action. Please contact your owner/admin if you need access.')
