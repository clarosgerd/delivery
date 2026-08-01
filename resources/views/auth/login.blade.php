@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="max-w-sm mx-auto mt-16">
    <x-card title="Delivery — acceso">
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-600">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-600">
            </div>
            <x-btn class="w-full">Entrar</x-btn>
        </form>
    </x-card>
</div>
@endsection
