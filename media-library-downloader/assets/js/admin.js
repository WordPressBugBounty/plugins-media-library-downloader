document.addEventListener('DOMContentLoaded', function () {
    let body = document.body;
    if (!body.classList.contains('upload-php')) { return; }

    /**
     * Get current view
     */
    let viewList = document.querySelector('.table-view-list');
    if (viewList !== null) {
        let bulkActionTop = document.querySelector('#bulk-action-selector-top');
        let bulkActionBottom = document.querySelector('#bulk-action-selector-bottom');

        /**
         * Add option on Top
         */
        const option1 = document.createElement("option");
        option1.value = "mld-download-files"
        option1.innerText = mld_i18n.download_files;
        bulkActionTop.appendChild(option1);

        /**
         * Add option on Bottom
         */
        const option2 = document.createElement("option");
        option2.value = "mld-download-files"
        option2.innerText = mld_i18n.download_files;
        bulkActionBottom.appendChild(option2);

        // Ajax Call
        let doAction = document.querySelector('#doaction');
        doAction.addEventListener('click', function (e) {
            if ((bulkActionTop.value || bulkActionBottom.value) !== 'mld-download-files') {
                return;
            }

            e.preventDefault();

            let checkedFiles = document.querySelectorAll('#the-list input:checked');
            if (!checkedFiles || checkedFiles.length === 0) {
                alert(mld_i18n.no_files_selected);
                return;
            }

            let selection = new Array();
            for (let index = 0; index < checkedFiles.length; index++) {
                const element = checkedFiles[index];
                if (element && element.value) {
                    selection.push(element.value);
                }
            }

            if (selection.length === 0) {
                alert(mld_i18n.no_files_selected);
                return;
            }

            jQuery.post({
                url: admin.ajax_url,
                data: {
                    action: "download_files",
                    ids: selection,
                },
                success: function (response) {
                    if (response.success) {
                        window.location = response.data;
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert(mld_i18n.download_error);
                }
            });
        })
    } else {

        setTimeout(() => {
            let bulkSelect = document.querySelector('.media-toolbar .select-mode-toggle-button');
            
            if (bulkSelect !== null) {
                bulkSelect.setAttribute('aria-selected', 'false');
                bulkSelect.addEventListener('click', function (e) {
                    let existingDownloadButton = document.querySelector('#mld-download');

                    // Remove old button
                    if (e.target.classList.contains('large-button')){
                        existingDownloadButton.remove();
                    }
                    if (existingDownloadButton === null) {
                        let downloadButton = document.createElement('button');
                        downloadButton.id = 'mld-download'
                        downloadButton.type = 'button';
                        downloadButton.classList = 'button media-button button-primary button-large delete-selected-button';
                        downloadButton.innerText = 'Télécharger';
                        document.querySelector('.media-toolbar-secondary').insertBefore(downloadButton, document.querySelector('.delete-selected-button'))
                    }
                })
            }
        }, 200);

        // Ajax Call
        window.addEventListener('click', function(e){
            let clickedElementID = e.target.id;
            if (clickedElementID !== 'mld-download'){
                return;
            }
            let filesToDownload = new Array();
            let imagesSelected = document.querySelectorAll('.attachments-wrapper .attachments .attachment[aria-checked="true"]');
            if (imagesSelected.length > 0){
                for (let index = 0; index < imagesSelected.length; index++) {
                    const element = imagesSelected[index];
                    let dataID = element.dataset.id;
                    filesToDownload.push(dataID);
                }
                jQuery.post({
                    url: admin.ajax_url,
                    data: {
                        action: "download_files",
                        ids: filesToDownload,
                    },
                    success: function (response) {
                        window.location = response.data;
                    },
                });
            }
        })
    }
})