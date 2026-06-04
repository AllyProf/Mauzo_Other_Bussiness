<script>
(function () {
  const cards = document.querySelectorAll('.business-type-card[data-type]');
  const selected = new Set();
  const hidden = document.getElementById('serviceTemplateTypesHidden');
  const btn = document.getElementById('btnImportServices');
  const form = document.getElementById('serviceTemplateForm');

  function syncHidden() {
    if (!hidden) return;
    hidden.innerHTML = '';
    selected.forEach(function (key) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'template_types[]';
      input.value = key;
      hidden.appendChild(input);
    });
    if (btn) btn.disabled = selected.size === 0;
  }

  cards.forEach(function (card) {
    card.addEventListener('click', function () {
      const key = card.getAttribute('data-type');
      if (selected.has(key)) { selected.delete(key); card.classList.remove('selected'); }
      else { selected.add(key); card.classList.add('selected'); }
      syncHidden();
    });
  });

  if (btn && form) {
    btn.addEventListener('click', function () {
      if (selected.size === 0) return;
      if (confirm('Import selected service templates for this branch?')) form.submit();
    });
  }
})();
</script>
