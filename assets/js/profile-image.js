/**
 * Tranås Intranät - Profilbild-uppladdning
 * Drag-och-släpp samt filuppladdning
 *
 * @package Tranas_Intranet
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('tranas-profile-image-form');
        
        if (!form) {
            return;
        }

        // Förhindra att event-listeners läggs till flera gånger
        if (form.getAttribute('data-tf-initialized') === 'true') {
            return;
        }
        form.setAttribute('data-tf-initialized', 'true');

        const dropzone = document.getElementById('tranas-profile-image-dropzone');
        const fileInput = document.getElementById('tranas-profile-image-input');
        const selectBtn = document.getElementById('tranas-profile-image-select-btn');
        let removeBtn = document.getElementById('tranas-profile-image-remove-btn');
        const preview = document.getElementById('tranas-profile-image-preview');
        const messageContainer = form.querySelector('.tranas-image-upload__messages');

        // Klick på dropzone eller select-knappen öppnar fildialogen
        dropzone.addEventListener('click', function(e) {
            if (e.target !== removeBtn && !removeBtn?.contains(e.target)) {
                fileInput.click();
            }
        });

        selectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });

        // Tangentbordsnavigering för dropzone
        dropzone.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });

        // Filinput change-event
        fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files[0]) {
                uploadFile(fileInput.files[0]);
            }
        });

        // Räknare för att hantera drag över barn-element utan blinkande
        let dragCounter = 0;

        // Förhindra standard drag-beteende på hela sidan
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
            document.body.addEventListener(eventName, function(e) {
                e.preventDefault();
            });
        });

        // Dragenter - öka räknaren
        dropzone.addEventListener('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dragCounter++;
            dropzone.classList.add('is-dragover');
        });

        // Dragover - behåll stilen
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });

        // Dragleave - minska räknaren, ta bara bort stilen om räknaren är 0
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dragCounter--;
            if (dragCounter === 0) {
                dropzone.classList.remove('is-dragover');
            }
        });

        // Hantera drop
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Återställ räknaren och ta bort stilen
            dragCounter = 0;
            dropzone.classList.remove('is-dragover');

            const dt = e.dataTransfer;
            const files = dt.files;

            if (files && files[0]) {
                // Validera att det är en bild
                if (!files[0].type.startsWith('image/')) {
                    showMessage('error', tranasIntranet.strings.profileInvalidType || 'Endast bildfiler är tillåtna.');
                    return;
                }
                uploadFile(files[0]);
            }
        });

        // Ta bort-knappen
        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                removeImage();
            });
        }

        /**
         * Ladda upp fil via AJAX
         */
        function uploadFile(file) {
            // Validera filtyp
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showMessage('error', tranasIntranet.strings.profileInvalidType || 'Ogiltig filtyp. Endast JPG, PNG, GIF och WebP är tillåtna.');
                return;
            }

            // Validera filstorlek (2 MB)
            const maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                showMessage('error', tranasIntranet.strings.profileTooLarge || 'Filen är för stor. Max storlek är 2 MB.');
                return;
            }

            // Visa laddningsläge
            dropzone.classList.add('is-uploading');
            clearMessages();

            // Skapa FormData
            const formData = new FormData();
            formData.append('action', 'tranas_upload_profile_image');
            formData.append('nonce', tranasIntranet.profileImageNonce);
            formData.append('profile_image', file);

            fetch(tranasIntranet.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                dropzone.classList.remove('is-uploading');

                if (data.success) {
                    // Uppdatera förhandsvisningen
                    preview.style.backgroundImage = 'url(' + data.data.image_url + ')';
                    preview.classList.add('has-image');

                    // Lägg till/uppdatera ta bort-knappen
                    updateRemoveButton(data.data.attachment_id);

                    // Uppdatera alla avatarer på sidan
                    updateAvatarsOnPage(data.data.thumbnail_url);

                    showMessage('success', data.data.message);
                } else {
                    showMessage('error', data.data.message);
                }
            })
            .catch(function(error) {
                console.error('Uppladdningsfel:', error);
                dropzone.classList.remove('is-uploading');
                showMessage('error', tranasIntranet.strings.error);
            });
        }

        /**
         * Ta bort profilbild via AJAX
         */
        function removeImage() {
            if (!confirm(tranasIntranet.strings.profileConfirmRemove || 'Är du säker på att du vill ta bort din profilbild?')) {
                return;
            }

            dropzone.classList.add('is-uploading');
            clearMessages();

            const formData = new FormData();
            formData.append('action', 'tranas_remove_profile_image');
            formData.append('nonce', tranasIntranet.profileImageNonce);

            fetch(tranasIntranet.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                dropzone.classList.remove('is-uploading');

                if (data.success) {
                    // Ta bort förhandsvisningen
                    preview.style.backgroundImage = '';
                    preview.classList.remove('has-image');

                    // Ta bort ta bort-knappen
                    const currentRemoveBtn = document.getElementById('tranas-profile-image-remove-btn');
                    if (currentRemoveBtn) {
                        currentRemoveBtn.remove();
                        removeBtn = null;
                    }

                    // Återställ alla avatarer till Gravatar
                    if (data.data.gravatar_url) {
                        updateAvatarsOnPage(data.data.gravatar_url);
                    }

                    showMessage('success', data.data.message);
                } else {
                    showMessage('error', data.data.message);
                }
            })
            .catch(function(error) {
                console.error('Borttagningsfel:', error);
                dropzone.classList.remove('is-uploading');
                showMessage('error', tranasIntranet.strings.error);
            });
        }

        /**
         * Uppdatera eller skapa ta bort-knappen
         */
        function updateRemoveButton(attachmentId) {
            let btn = document.getElementById('tranas-profile-image-remove-btn');
            
            if (!btn) {
                btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'tranas-profile-image-remove-btn';
                btn.className = 'tf-submit tranas-image-upload__btn tranas-image-upload__btn--remove';
                
                // Skapa SVG-ikon
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('width', '18');
                svg.setAttribute('height', '18');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2');
                svg.setAttribute('stroke-linecap', 'round');
                svg.setAttribute('stroke-linejoin', 'round');
                
                const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                polyline.setAttribute('points', '3 6 5 6 21 6');
                svg.appendChild(polyline);
                
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2');
                svg.appendChild(path);
                
                btn.appendChild(svg);
                
                // Lägg till text
                const textNode = document.createTextNode(' ' + (tranasIntranet.strings.profileRemove || 'Ta bort'));
                btn.appendChild(textNode);
                
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeImage();
                });

                const actionsContainer = form.querySelector('.tranas-image-upload__actions');
                if (actionsContainer) {
                    actionsContainer.appendChild(btn);
                }

                removeBtn = btn;
            }

            btn.dataset.attachmentId = attachmentId;
        }

        /**
         * Uppdatera alla avatarer/profilbilder på sidan (för direkt feedback)
         * Stödjer flera vanliga selektorer och både img src och background-image
         */
        function updateAvatarsOnPage(imageUrl) {
            // Selektorer för img-element med profilbilder
            const imgSelectors = [
                '.avatar',                          // WordPress standard
                '.wp-block-avatar img',             // Gutenberg avatar-block
                '.author-avatar img',               // Vanligt i teman
                '.user-avatar img',                 // Vanligt i teman
                '.profile-image img',               // Generellt
                '.profile-avatar img',              // Generellt
                '.user-profile-image',              // Specifik klass
                '[data-tranas-profile-image]',      // Attribut för att markera profilbilder
                '.tranas-profile-image',            // Vår egen klass
                '.site-header .avatar',             // Sidhuvud avatar
                '.header .avatar',                  // Header avatar
                '.nav .avatar',                     // Navigation avatar
                '.menu .avatar',                    // Menu avatar
                '.user-menu img',                   // Användarmeny
                '.profile-dropdown img',            // Dropdown
                '.account-avatar img'               // Konto-avatar
            ];

            // Selektorer för element med background-image
            const bgSelectors = [
                '.avatar-bg',
                '.profile-image-bg',
                '.user-avatar-bg',
                '[data-tranas-profile-image-bg]'
            ];

            // Uppdatera alla img-element
            const imgElements = document.querySelectorAll(imgSelectors.join(', '));
            imgElements.forEach(function(img) {
                if (img.tagName === 'IMG') {
                    img.src = imageUrl;
                    // Uppdatera också srcset om det finns
                    if (img.srcset) {
                        img.srcset = imageUrl;
                    }
                }
            });

            // Uppdatera alla element med background-image
            const bgElements = document.querySelectorAll(bgSelectors.join(', '));
            bgElements.forEach(function(el) {
                el.style.backgroundImage = 'url(' + imageUrl + ')';
            });

            // Dispatcha ett custom event så andra scripts kan reagera
            document.dispatchEvent(new CustomEvent('tranasProfileImageUpdated', {
                detail: { imageUrl: imageUrl }
            }));
        }

        /**
         * Visa meddelande
         */
        function showMessage(type, message) {
            clearMessages();

            const msgEl = document.createElement('div');
            msgEl.className = 'tf-message tf-message--' + type;
            msgEl.setAttribute('role', type === 'error' ? 'alert' : 'status');
            msgEl.textContent = message;
            
            messageContainer.appendChild(msgEl);
            
            // Scrolla till meddelandet
            msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        /**
         * Rensa meddelanden
         */
        function clearMessages() {
            while (messageContainer.firstChild) {
                messageContainer.removeChild(messageContainer.firstChild);
            }
        }
    });
})();

