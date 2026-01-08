/**
 * Tranås Intranät - Favoriter
 *
 * @package Tranas_Intranet
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initFavoriteButtons();
    });

    /**
     * Initiera favorit-knappar
     */
    function initFavoriteButtons() {
        const buttons = document.querySelectorAll('.tranas-favorite-button');

        if (!buttons.length) {
            return;
        }

        buttons.forEach(function(button) {
            // Förhindra att event-listeners läggs till flera gånger
            if (button.getAttribute('data-tf-initialized') === 'true') {
                return;
            }
            button.setAttribute('data-tf-initialized', 'true');

            button.addEventListener('click', handleFavoriteClick);
        });
    }

    /**
     * Hantera klick på favorit-knapp
     *
     * @param {Event} e Click-event
     */
    function handleFavoriteClick(e) {
        e.preventDefault();

        const button = e.currentTarget;
        const postId = button.getAttribute('data-post-id');
        const nonce = button.getAttribute('data-nonce');

        if (!postId || !nonce) {
            console.error('Favorit-knapp saknar post-id eller nonce');
            return;
        }

        // Förhindra dubbla klick
        if (button.getAttribute('data-loading') === 'true') {
            return;
        }
        button.setAttribute('data-loading', 'true');
        button.classList.add('tranas-favorite-button--loading');

        // Skapa FormData
        const formData = new FormData();
        formData.append('action', 'tranas_toggle_favorite');
        formData.append('post_id', postId);
        formData.append('nonce', nonce);

        fetch(tranasIntranet.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Uppdatera knappens utseende
                updateButtonState(button, data.data.is_favorite);

                // Uppdatera alla räknare på sidan
                updateAllCounters(data.data.count);

                // Uppdatera alla knappar med samma post-id
                updateAllButtonsForPost(postId, data.data.is_favorite);

                // Om borttagen från favoriter och knappen är i en lista, ta bort list-item
                if (!data.data.is_favorite) {
                    removeFromFavoritesList(button, postId);
                }
            } else {
                console.error('Fel vid favorit-toggle:', data.data.message);
                showToast(data.data.message, 'error');
            }
        })
        .catch(function(error) {
            console.error('Nätverksfel vid favorit-toggle:', error);
            showToast(tranasIntranet.strings.error, 'error');
        })
        .finally(function() {
            button.removeAttribute('data-loading');
            button.classList.remove('tranas-favorite-button--loading');
        });
    }

    /**
     * Uppdatera knappens state
     *
     * @param {HTMLButtonElement} button      Knappen
     * @param {boolean}           isFavorite  Om det är en favorit
     */
    function updateButtonState(button, isFavorite) {
        // Lägg till animation
        button.classList.add('tranas-favorite-button--animating');
        setTimeout(function() {
            button.classList.remove('tranas-favorite-button--animating');
        }, 300);

        if (isFavorite) {
            button.classList.add('tranas-favorite-button--active');
            button.setAttribute('aria-pressed', 'true');
            button.setAttribute('aria-label', tranasIntranet.strings.favoriteRemove || 'Ta bort från favoriter');
            button.setAttribute('title', tranasIntranet.strings.favoriteRemove || 'Ta bort från favoriter');
        } else {
            button.classList.remove('tranas-favorite-button--active');
            button.setAttribute('aria-pressed', 'false');
            button.setAttribute('aria-label', tranasIntranet.strings.favoriteAdd || 'Lägg till i favoriter');
            button.setAttribute('title', tranasIntranet.strings.favoriteAdd || 'Lägg till i favoriter');
        }
    }

    /**
     * Uppdatera alla knappar med samma post-id
     *
     * @param {string}  postId     Post-ID
     * @param {boolean} isFavorite Om det är en favorit
     */
    function updateAllButtonsForPost(postId, isFavorite) {
        const buttons = document.querySelectorAll('.tranas-favorite-button[data-post-id="' + postId + '"]');
        buttons.forEach(function(btn) {
            updateButtonState(btn, isFavorite);
        });
    }

    /**
     * Uppdatera alla räknare på sidan
     *
     * @param {number} count Antal favoriter
     */
    function updateAllCounters(count) {
        const counters = document.querySelectorAll('.tranas-favorites-count');

        counters.forEach(function(counter) {
            const numberEl = counter.querySelector('.tranas-favorites-count__number');
            if (numberEl) {
                numberEl.textContent = count;
                numberEl.setAttribute('data-count', count);
            }

            // Uppdatera aria-label
            const label = count === 1 ? count + ' favorit' : count + ' favoriter';
            counter.setAttribute('aria-label', label);

            // Hantera synlighet baserat på show_zero
            if (count === 0) {
                counter.classList.add('tranas-favorites-count--hidden');
            } else {
                counter.classList.remove('tranas-favorites-count--hidden');
            }
        });
    }

    /**
     * Ta bort post från favoritlistan med animering
     *
     * @param {HTMLButtonElement} button Knappen som klickades
     * @param {string}            postId Post-ID
     */
    function removeFromFavoritesList(button, postId) {
        // Kolla om knappen är i en favorit-lista
        const listItem = button.closest('.tranas-favorites-list__item');
        if (!listItem) {
            return;
        }

        const list = listItem.closest('.tranas-favorites-list');
        const itemsList = list ? list.querySelector('.tranas-favorites-list__items') : null;

        // Animera borttagning
        listItem.classList.add('tranas-favorites-list__item--removing');

        // Ta bort efter animering
        setTimeout(function() {
            listItem.remove();

            // Kolla om listan är tom
            if (itemsList && itemsList.children.length === 0) {
                // Visa tomt meddelande
                const emptyMessage = document.createElement('p');
                emptyMessage.className = 'tranas-favorites-list__empty-message';
                emptyMessage.textContent = tranasIntranet.strings.favoritesEmpty || 'Du har inga sparade favoriter ännu.';

                list.classList.add('tranas-favorites-list--empty');
                itemsList.remove();
                list.appendChild(emptyMessage);
            }
        }, 300);
    }

    /**
     * Visa toast-meddelande (om tillgängligt)
     *
     * @param {string} message Meddelande
     * @param {string} type    Typ (success, error)
     */
    function showToast(message, type) {
        // Om det finns en global toast-funktion, använd den
        if (typeof window.tranasShowToast === 'function') {
            window.tranasShowToast(message, type);
            return;
        }

        // Annars logga bara
        if (type === 'error') {
            console.error(message);
        }
    }
})();

