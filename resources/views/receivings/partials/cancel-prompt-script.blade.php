@if(session('partial_cancel_prompt'))
@php $partialCancelPrompt = session('partial_cancel_prompt'); @endphp
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        const items = @json($partialCancelPrompt['items']);

        const formatQty = (qty) => {
            const value = parseFloat(qty);
            return Number.isInteger(value) ? value.toString() : value.toFixed(2);
        };

        const lines = items.map((item) => {
            return `<li><strong>${item.name}</strong>: reverse ${formatQty(item.reversible)} of ${formatQty(item.added)} (${formatQty(item.not_reversible)} already sold)</li>`;
        }).join('');

        Swal.fire({
            title: 'Some stock was already sold',
            html: `<p>Receiving <strong>{{ $partialCancelPrompt['reference_no'] }}</strong> cannot be fully reversed:</p><ul style="text-align:left; margin: 1rem 0;">${lines}</ul><p>Cancel anyway and remove only the stock still available?</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, cancel receiving',
            cancelButtonText: 'No, keep it'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('partialCancelForm').submit();
            }
        });
    });
</script>
@endif
