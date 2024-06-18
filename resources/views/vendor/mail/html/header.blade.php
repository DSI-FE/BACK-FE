@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
@else
    @if (trim($slot) === config('app.nickname'))
        <img src="{{config('app.url')}}/api/images/general/logo.png" class="logo-lg" alt="DGEHM Logo">
    @else
        <img src="{{config('app.url')}}/api/images/general/logo.png" class="logo-lg" alt="DGEHM Logo">
        <!-- {{ $slot }} -->
    @endif
@endif
</a>
</td>
</tr>
