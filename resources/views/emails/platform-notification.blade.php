@php $platform = platform_settings('platform_name', 'Mauzo Link'); @endphp
@if($recipientName)
<p>Hello {{ $recipientName }},</p>
@endif
<p style="white-space:pre-wrap">{{ $bodyMessage }}</p>
<p style="color:#666;font-size:12px;margin-top:24px;">{{ $platform }}</p>
