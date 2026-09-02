<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>Employee Time Clock</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen bg-gray-100">

    <main class="min-h-screen w-full px-4 py-8 sm:px-6 lg:px-8">
        <livewire:attendance.employee-time-clock />
    </main>

    @livewireScripts

</body>

</html>
