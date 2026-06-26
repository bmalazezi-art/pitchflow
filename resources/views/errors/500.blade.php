@extends('errors.layout')
@section('code', '500')
@section('title', app()->getLocale() === 'sq' ? 'Diçka shkoi keq' : 'Something went wrong')
@section('message', app()->getLocale() === 'sq' ? 'Nuk mund ta përfundojmë këtë kërkesë tani. Ju lutemi provoni përsëri.' : 'We could not complete this request right now. Please try again.')
