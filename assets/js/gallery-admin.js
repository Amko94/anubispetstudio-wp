document.addEventListener('click', function (event) {
 var select = event.target.closest('.anubis-gallery-select');
 var remove = event.target.closest('.anubis-gallery-remove');
 if (!select && !remove) { return; }
 var field = (select || remove).closest('.anubis-gallery-field');
 var input = field.querySelector('input');
 var preview = field.querySelector('.anubis-gallery-preview');
 if (remove) { input.value = ''; preview.replaceChildren(); return; }
 var frame = wp.media({ title: select.dataset.title, button: { text: 'Bild verwenden' }, library: { type: 'image' }, multiple: false });
 frame.on('select', function () {
  var attachment = frame.state().get('selection').first().toJSON();
  input.value = attachment.id;
  var image = document.createElement('img');
  image.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
  image.alt = attachment.alt || '';
  image.style.cssText = 'max-width:100%;height:auto';
  preview.replaceChildren(image);
 });
 frame.open();
});
