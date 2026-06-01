@if(session('partial_cancel_prompt'))
@php $partialCancelPrompt = session('partial_cancel_prompt'); @endphp
<form id="partialCancelForm" action="{{ route('receivings.cancel', $partialCancelPrompt['receiving_id']) }}" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="partial_ok" value="1">
</form>
@endif
