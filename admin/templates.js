(() => {
  'use strict';
  // Adds one "example value" field per {{n}} variable, as Meta requires for review.
  document.querySelectorAll('[data-template-body]').forEach((body) => {
    const box = body.form?.querySelector('[data-examples]');
    if (!box) return;
    const sync = () => {
      const found = [...body.value.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map((m) => Number(m[1]));
      const count = found.length ? Math.max(...found) : 0;
      const current = [...box.querySelectorAll('input')].map((i) => i.value);
      box.replaceChildren();
      for (let i = 0; i < count; i += 1) {
        const label = document.createElement('label');
        label.textContent = `Example for {{${i + 1}}}`;
        const input = document.createElement('input');
        input.name = 'examples[]';
        input.required = true;
        input.value = current[i] || '';
        label.append(input);
        box.append(label);
      }
    };
    body.addEventListener('input', sync);
    sync();
  });
})();
