@extends('errors::minimal')

@section('title', 'Akses Ditolak')
@section('code', '403')
@section('message', 'Akses Ditolak')
@section('description', $exception->getMessage() ?: 'Anda tidak memiliki izin untuk mengakses halaman ini.')
