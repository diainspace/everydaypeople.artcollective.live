(() => {
    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', event => {
            const message = button.getAttribute('data-confirm') ?? 'Are you sure?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });

    document.querySelectorAll('input[type="file"][name$="Photo_upload"]').forEach(input => {
        if (!(input instanceof HTMLInputElement)) return;
        const preview = document.querySelector(`[data-upload-preview="${input.name}"]`);
        const alt = document.querySelector(`[data-upload-alt="${input.name}"]`);
        const path = document.querySelector(`[data-photo-path="${input.name}"]`);
        const clear = document.querySelector(`[data-photo-clear="${input.name}"]`);
        let previewUrl = '';
        input.addEventListener('change', () => {
            if (!(preview instanceof HTMLImageElement)) return;
            if (previewUrl !== '') URL.revokeObjectURL(previewUrl);
            const file = input.files?.[0];
            if (!file) return;
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            if (alt instanceof HTMLInputElement) alt.required = true;
            if (path instanceof HTMLInputElement) path.readOnly = true;
            if (clear instanceof HTMLButtonElement) {
                clear.hidden = false;
                clear.textContent = 'Clear selected photo';
            }
        });
        if (path instanceof HTMLInputElement) {
            path.addEventListener('input', () => {
                if (input.files?.length) return;
                input.disabled = path.value.trim() !== '';
            });
        }
        if (clear instanceof HTMLButtonElement) {
            clear.addEventListener('click', () => {
                if (previewUrl !== '') URL.revokeObjectURL(previewUrl);
                previewUrl = '';
                input.value = '';
                input.disabled = false;
                if (path instanceof HTMLInputElement) {
                    path.value = '';
                    path.readOnly = false;
                }
                if (preview instanceof HTMLImageElement) {
                    preview.removeAttribute('src');
                    preview.hidden = true;
                }
                if (alt instanceof HTMLInputElement) alt.required = false;
                clear.hidden = true;
                clear.textContent = 'Clear current photo';
            });
        }
    });

    const input = document.querySelector('input[name="catName"]');
    const list = document.querySelector('#category-options');
    const description = document.querySelector('#selected-category-description');
    if (!(input instanceof HTMLInputElement) || !(list instanceof HTMLDataListElement) || !(description instanceof HTMLElement)) return;

    const categories = new Map(
        Array.from(list.options, option => [option.value, option.dataset.description ?? '']),
    );
    const updateDescription = () => {
        const name = input.value.trim();
        if (name === '') description.textContent = 'No category selected.';
        else if (categories.has(name)) description.textContent = categories.get(name) || 'This category has no description.';
        else description.textContent = 'Choose a category from Manage Categories.';
    };

    input.addEventListener('input', updateDescription);
    input.addEventListener('change', updateDescription);
    updateDescription();
})();
