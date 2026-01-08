/**
 * Tranås Intranät - Nyhetsflödes-inställningar
 *
 * @package Tranas_Intranet
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initNewsPreferencesForm();
    });

    /**
     * Initiera nyhetsflödes-inställningsformulär
     */
    function initNewsPreferencesForm() {
        const form = document.getElementById('tranas-news-preferences-form');

        if (!form) {
            return;
        }

        // Förhindra att event-listeners läggs till flera gånger
        if (form.getAttribute('data-tf-initialized') === 'true') {
            return;
        }
        form.setAttribute('data-tf-initialized', 'true');

        // Formulär-submit
        form.addEventListener('submit', handleSubmit);

        // Markera/avmarkera alla-knappar
        const selectAllBtn = form.querySelector('.tranas-preferences__select-all');
        const deselectAllBtn = form.querySelector('.tranas-preferences__deselect-all');

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                toggleAllCheckboxes(form, true);
            });
        }

        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                toggleAllCheckboxes(form, false);
            });
        }
    }

    /**
     * Markera eller avmarkera alla checkboxar
     *
     * @param {HTMLFormElement} form    Formuläret
     * @param {boolean}         checked Om checkboxarna ska markeras eller inte
     */
    function toggleAllCheckboxes(form, checked) {
        const checkboxes = form.querySelectorAll('.tranas-preferences__checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = checked;
        });
    }

    /**
     * Hantera formulärinskickning
     *
     * @param {Event} e Submit-event
     */
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
        const submitText = submitBtn ? submitBtn.querySelector('.tf-submit-text') : null;
        const submitLoading = submitBtn ? submitBtn.querySelector('.tf-submit-loading') : null;
        const messageContainer = form.querySelector('.tf-message-container');

        // Rensa tidigare meddelanden
        if (messageContainer) {
            messageContainer.innerHTML = '';
        }

        // Visa laddningsläge
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
        }
        if (submitText) submitText.style.display = 'none';
        if (submitLoading) submitLoading.style.display = 'inline';

        // Samla formulärdata
        const formData = new FormData(form);
        formData.append('action', 'tranas_save_news_preferences');
        
        // Hämta nonce från formulärets dolda fält
        const nonceField = form.querySelector('[name="tranas_news_nonce"]');
        if (nonceField) {
            formData.append('nonce', nonceField.value);
        }

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
            if (messageContainer) {
                messageContainer.appendChild(msgEl);
            }

            if (data.success) {
                // Flytta fokus till meddelandet för skärmläsare
                msgEl.setAttribute('tabindex', '-1');
                msgEl.focus();
            }

            // Återställ knappen och tillåt nya inskick
            resetButton(submitBtn, submitText, submitLoading);
            form.removeAttribute('data-submitting');

            // Scrolla till meddelandet
            if (msgEl) {
                msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        })
        .catch(function(error) {
            console.error('Fel vid sparning av nyhetsflödes-inställningar:', error);

            const msgEl = document.createElement('div');
            msgEl.className = 'tf-message tf-message--error';
            msgEl.setAttribute('role', 'alert');
            msgEl.textContent = tranasIntranet.strings.error;
            
            if (messageContainer) {
                messageContainer.appendChild(msgEl);
            }

            resetButton(submitBtn, submitText, submitLoading);
            form.removeAttribute('data-submitting');
        });
    }

    /**
     * Återställ submit-knappen
     *
     * @param {HTMLButtonElement} btn       Knappen
     * @param {HTMLElement}       textEl    Text-elementet
     * @param {HTMLElement}       loadingEl Laddnings-elementet
     */
    function resetButton(btn, textEl, loadingEl) {
        if (btn) {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
        }
        if (textEl) textEl.style.display = 'inline';
        if (loadingEl) loadingEl.style.display = 'none';
    }
})();

