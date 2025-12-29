/**
 * Tranås Intranät - Användarprofilformulär
 * Följer samma struktur som tranas-forms
 *
 * @package Tranas_Intranet
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('tranas-user-profile-form');

        if (form) {
            // Förhindra att event-listeners läggs till flera gånger
            if (form.getAttribute('data-tf-initialized') === 'true') {
                return;
            }
            form.setAttribute('data-tf-initialized', 'true');
            
            form.addEventListener('submit', handleSubmit);

            // Uppdatera visningsnamnsalternativ när namn ändras
            const nameFields = ['first_name', 'last_name', 'nickname'];
            nameFields.forEach(function(fieldName) {
                const field = form.querySelector('[name="' + fieldName + '"]');
                if (field) {
                    field.addEventListener('change', updateDisplayNameOptions);
                }
            });
        }
    });

    function handleSubmit(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const form = e.target;

        // Förhindra dubbla inskick
        if (form.getAttribute('data-submitting') === 'true') {
            return;
        }
        form.setAttribute('data-submitting', 'true');

        const submitBtn = form.querySelector('.tf-submit');
        const submitText = submitBtn.querySelector('.tf-submit-text');
        const submitLoading = submitBtn.querySelector('.tf-submit-loading');
        const messageContainer = form.querySelector('.tf-message-container');

        // Rensa tidigare meddelanden och felmarkeringar
        messageContainer.innerHTML = '';
        form.querySelectorAll('[aria-invalid]').forEach(function(el) {
            el.removeAttribute('aria-invalid');
        });

        // Validera formuläret
        if (!form.checkValidity()) {
            form.reportValidity();
            form.removeAttribute('data-submitting');
            return;
        }

        // Visa laddningsläge
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');
        if (submitText) submitText.style.display = 'none';
        if (submitLoading) submitLoading.style.display = 'inline';

        // Samla formulärdata
        const formData = new FormData(form);
        formData.append('action', 'tranas_update_user_profile');
        formData.append('nonce', tranasIntranet.nonce);

        fetch(tranasIntranet.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Skapa meddelande
            const msgEl = document.createElement('div');
            msgEl.className = 'tf-message ' + (data.success ? 'tf-message--success' : 'tf-message--error');
            msgEl.setAttribute('role', 'status');
            msgEl.innerHTML = data.data.message;

            // Lägg till i containern
            messageContainer.appendChild(msgEl);

            if (data.success) {
                // Flytta fokus till meddelandet för skärmläsare
                msgEl.setAttribute('tabindex', '-1');
                msgEl.focus();
            } else {
                // Markera första fältet med fel om möjligt
                const firstInput = form.querySelector('input:not([type="hidden"]), textarea, select');
                if (firstInput) {
                    firstInput.setAttribute('aria-invalid', 'true');
                    firstInput.focus();
                }
            }

            // Återställ knappen och tillåt nya inskick
            resetButton(submitBtn, submitText, submitLoading);
            form.removeAttribute('data-submitting');

            // Scrolla till meddelandet
            msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(function(error) {
            console.error('Profiluppdateringsfel:', error);

            const msgEl = document.createElement('div');
            msgEl.className = 'tf-message tf-message--error';
            msgEl.setAttribute('role', 'alert');
            msgEl.textContent = tranasIntranet.strings.error;
            messageContainer.appendChild(msgEl);

            resetButton(submitBtn, submitText, submitLoading);
            form.removeAttribute('data-submitting');
        });
    }

    function resetButton(btn, textEl, loadingEl) {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        if (textEl) textEl.style.display = 'inline';
        if (loadingEl) loadingEl.style.display = 'none';
    }

    /**
     * Uppdatera visningsnamnsalternativ baserat på aktuella värden
     */
    function updateDisplayNameOptions() {
        const form = document.getElementById('tranas-user-profile-form');
        if (!form) return;

        const firstName = (form.querySelector('[name="first_name"]')?.value || '').trim();
        const lastName = (form.querySelector('[name="last_name"]')?.value || '').trim();
        const nickname = (form.querySelector('[name="nickname"]')?.value || '').trim();
        const displayNameSelect = form.querySelector('[name="display_name"]');
        
        if (!displayNameSelect) return;

        const currentValue = displayNameSelect.value;

        // Hämta användarnamn från första alternativet (det är alltid användarnamnet)
        const userLogin = displayNameSelect.querySelector('option:first-child')?.value || '';

        // Bygg upp nya alternativ
        const options = new Map();
        if (userLogin) options.set(userLogin, userLogin);
        if (firstName) options.set(firstName, firstName);
        if (lastName) options.set(lastName, lastName);
        
        if (firstName && lastName) {
            options.set(firstName + ' ' + lastName, firstName + ' ' + lastName);
            options.set(lastName + ' ' + firstName, lastName + ' ' + firstName);
        }
        
        if (nickname && !options.has(nickname)) {
            options.set(nickname, nickname);
        }

        // Uppdatera select-elementet
        displayNameSelect.innerHTML = '';
        options.forEach(function(label, value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            if (value === currentValue) {
                option.selected = true;
            }
            displayNameSelect.appendChild(option);
        });

        // Om det tidigare valda värdet inte längre finns, välj det första
        if (!options.has(currentValue) && displayNameSelect.options.length > 0) {
            displayNameSelect.options[0].selected = true;
        }
    }
})();
