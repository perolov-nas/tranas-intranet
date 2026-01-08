/**
 * Tranås Intranät - Bakgrundsbild-uppladdning
 * Drag-och-släpp samt filuppladdning
 *
 * @package Tranas_Intranet
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('tranas-cover-image-form');
        
        if (!form) {
            return;
        }

        // Förhindra att event-listeners läggs till flera gånger
        if (form.getAttribute('data-tf-initialized') === 'true') {
            return;
        }
        form.setAttribute('data-tf-initialized', 'true');

        const dropzone = document.getElementById('tranas-cover-dropzone');
        const fileInput = document.getElementById('tranas-cover-file-input');
        const selectBtn = document.getElementById('tranas-cover-select-btn');
        const removeBtn = document.getElementById('tranas-cover-remove-btn');
        const preview = document.getElementById('tranas-cover-preview');
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
                    showMessage('error', tranasIntranet.strings.coverInvalidType || 'Endast bildfiler är tillåtna.');
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
                showMessage('error', tranasIntranet.strings.coverInvalidType || 'Ogiltig filtyp. Endast JPG, PNG, GIF och WebP är tillåtna.');
                return;
            }

            // Validera filstorlek (5 MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                showMessage('error', tranasIntranet.strings.coverTooLarge || 'Filen är för stor. Max storlek är 5 MB.');
                return;
            }

            // Visa laddningsläge
            dropzone.classList.add('is-uploading');
            clearMessages();

            // Skapa FormData
            const formData = new FormData();
            formData.append('action', 'tranas_upload_cover_image');
            formData.append('nonce', tranasIntranet.coverNonce);
            formData.append('cover_image', file);

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

                    // Uppdatera hero-elementet direkt
                    updateHeroBackground(data.data.image_url);

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
         * Ta bort bakgrundsbild via AJAX
         */
        function removeImage() {
            if (!confirm(tranasIntranet.strings.coverConfirmRemove || 'Är du säker på att du vill ta bort bakgrundsbilden?')) {
                return;
            }

            dropzone.classList.add('is-uploading');
            clearMessages();

            const formData = new FormData();
            formData.append('action', 'tranas_remove_cover_image');
            formData.append('nonce', tranasIntranet.coverNonce);

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
                    const currentRemoveBtn = document.getElementById('tranas-cover-remove-btn');
                    if (currentRemoveBtn) {
                        currentRemoveBtn.remove();
                    }

                    // Ta bort bakgrundsbilden från hero-elementet
                    updateHeroBackground(null);

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
            var btn = document.getElementById('tranas-cover-remove-btn');
            
            if (!btn) {
                btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'tranas-cover-remove-btn';
                btn.className = 'tf-submit tranas-image-upload__btn tranas-image-upload__btn--remove';
                
                // Skapa SVG-ikon
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('width', '20');
                svg.setAttribute('height', '20');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2');
                svg.setAttribute('stroke-linecap', 'round');
                svg.setAttribute('stroke-linejoin', 'round');
                
                var polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                polyline.setAttribute('points', '3 6 5 6 21 6');
                svg.appendChild(polyline);
                
                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2');
                svg.appendChild(path);
                
                var line1 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line1.setAttribute('x1', '10');
                line1.setAttribute('y1', '11');
                line1.setAttribute('x2', '10');
                line1.setAttribute('y2', '17');
                svg.appendChild(line1);
                
                var line2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line2.setAttribute('x1', '14');
                line2.setAttribute('y1', '11');
                line2.setAttribute('x2', '14');
                line2.setAttribute('y2', '17');
                svg.appendChild(line2);
                
                btn.appendChild(svg);
                
                // Lägg till text
                var textNode = document.createTextNode(' ' + (tranasIntranet.strings.coverRemove || 'Ta bort bild'));
                btn.appendChild(textNode);
                
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeImage();
                });

                var actionsContainer = form.querySelector('.tranas-image-upload__actions');
                if (actionsContainer) {
                    actionsContainer.appendChild(btn);
                }
            }

            btn.dataset.attachmentId = attachmentId;
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

        /**
         * Uppdatera hero-elementets bakgrundsbild
         * @param {string|null} imageUrl - Bild-URL eller null för fallback
         */
        function updateHeroBackground(imageUrl) {
            var heroElement = document.querySelector('.user-hero');
            
            if (!heroElement) {
                return;
            }

            // Använd fallback om ingen bild angavs
            var finalUrl = imageUrl || tranasIntranet.coverFallbackUrl;

            heroElement.style.backgroundImage = 'url(' + finalUrl + ')';

            // Uppdatera även <style>-taggen i head
            var styleTag = document.getElementById('tranas-user-hero-bg');
            
            if (!styleTag) {
                styleTag = document.createElement('style');
                styleTag.id = 'tranas-user-hero-bg';
                document.head.appendChild(styleTag);
            }
            styleTag.textContent = '.user-hero { background-image: url(' + finalUrl + ') !important; }';
        }
    });
})();

