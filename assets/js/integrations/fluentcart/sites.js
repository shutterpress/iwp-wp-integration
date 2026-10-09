document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('.fcip-fc-setup, .fcip-fc-action')) return;
    event.preventDefault();
    const button = event.submitter || form.querySelector('button[type="submit"]');
    const message = form.querySelector('.fcip-fc-message');
    button.disabled = true;
    try {
        const body = new FormData(form);
        if (button.name) body.set(button.name, button.value);
        const response = await fetch(form.action, {
            method: 'POST', credentials: 'same-origin', body
        });
        const result = await response.json();
        message.textContent = result.data.message;
        if (result.success) {
            window.location.reload();
        } else {
            button.disabled = false;
        }
    } catch (error) {
        // The request may have reached the server. Refresh to check its state
        // before allowing another submission.
        window.location.reload();
    }
});
