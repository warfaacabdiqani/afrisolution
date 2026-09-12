<header>
@if($logo)<img src="{{ $logo }}" alt="Clinic logo" style="max-width:160px;max-height:80px;object-fit:contain;margin-bottom:12px">@endif
<h1>{{ $profile['name'] }}</h1>
@if(isset($branchName))<div>{{ $branchName }}</div>@endif
@if($documents['show_address'])<div>{{ $profile['address'] }} {{ $profile['city'] }} {{ $profile['country'] }}</div>@endif
@if($documents['show_phone'])<div>{{ $profile['phone'] }}</div>@endif
@if($documents['show_email'])<div>{{ $profile['email'] }}</div>@endif
<small>{{ $documents[$kind.'_header'] ?? ucfirst($kind) }}</small>
</header>
