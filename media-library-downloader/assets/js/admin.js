document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    if (!body.classList.contains('upload-php')) { 
        return; 
    }

    /**
     * Download Manager Class
     */
    class MLDDownloadManager {
        constructor() {
            this.isDownloading = false;
            this.init();
        }

        init() {
            const viewList = document.querySelector('.table-view-list');
            if (viewList !== null) {
                this.initListView();
            } else {
                this.initGridView();
            }
        }

        /**
         * Initialize List View
         */
        initListView() {
            this.addBulkActions();
            this.handleBulkDownload();
            this.addIndividualDownloadButtons();
        }

        /**
         * Add bulk action options
         */
        addBulkActions() {
            const bulkActionTop = document.querySelector('#bulk-action-selector-top');
            const bulkActionBottom = document.querySelector('#bulk-action-selector-bottom');

            if (!bulkActionTop || !bulkActionBottom) return;

            // Add option to top selector
            const option1 = document.createElement("option");
            option1.value = "mld-download-files";
            option1.innerText = mld_i18n.download_files;
            bulkActionTop.appendChild(option1);

            // Add option to bottom selector
            const option2 = document.createElement("option");
            option2.value = "mld-download-files";
            option2.innerText = mld_i18n.download_files;
            bulkActionBottom.appendChild(option2);
        }

        /**
         * Handle bulk download action
         */
        handleBulkDownload() {
            const doAction = document.querySelector('#doaction');
            const doAction2 = document.querySelector('#doaction2');
            
            if (doAction) {
                doAction.addEventListener('click', (e) => this.processBulkAction(e, 'top'));
            }
            if (doAction2) {
                doAction2.addEventListener('click', (e) => this.processBulkAction(e, 'bottom'));
            }
        }

        /**
         * Process bulk action
         */
        processBulkAction(e, position) {
            const selector = position === 'top' ? 
                document.querySelector('#bulk-action-selector-top') : 
                document.querySelector('#bulk-action-selector-bottom');

            if (!selector || selector.value !== 'mld-download-files') {
                return;
            }

            e.preventDefault();

            const checkedFiles = document.querySelectorAll('#the-list input:checked');
            if (!checkedFiles || checkedFiles.length === 0) {
                this.showMessage(mld_i18n.no_files_selected, 'error');
                return;
            }

            const selection = [];
            for (const element of checkedFiles) {
                if (element && element.value) {
                    selection.push(element.value);
                }
            }

            if (selection.length === 0) {
                this.showMessage(mld_i18n.no_files_selected, 'error');
                return;
            }

            this.downloadFiles(selection);
        }

        /**
         * Add individual download buttons to list view
         */
        addIndividualDownloadButtons() {
            const rows = document.querySelectorAll('#the-list tr');
            
            rows.forEach(row => {
                const titleCell = row.querySelector('.title');
                if (!titleCell) return;

                const attachmentId = this.getAttachmentId(row);
                if (!attachmentId) return;

                // Create download button
                const downloadBtn = document.createElement('button');
                downloadBtn.className = 'button button-small mld-single-download';
                downloadBtn.type = 'button';
                downloadBtn.innerHTML = '📥 ' + mld_i18n.download_single;
                downloadBtn.dataset.attachmentId = attachmentId;
                downloadBtn.style.marginLeft = '10px';
                downloadBtn.setAttribute('aria-label', mld_i18n.download_single + ' ' + (titleCell.querySelector('.title')?.textContent || 'file'));
                downloadBtn.setAttribute('title', mld_i18n.download_single);

                // Add click handler
                downloadBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.downloadFiles([attachmentId]);
                });

                // Add keyboard support
                downloadBtn.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.downloadFiles([attachmentId]);
                    }
                });

                // Insert button
                const rowActions = titleCell.querySelector('.row-actions');
                if (rowActions) {
                    const downloadAction = document.createElement('span');
                    downloadAction.className = 'mld-download';
                    downloadAction.appendChild(downloadBtn);
                    rowActions.appendChild(downloadAction);
                }
            });
        }

        /**
         * Get attachment ID from row
         */
        getAttachmentId(row) {
            const checkbox = row.querySelector('input[type="checkbox"]');
            return checkbox ? checkbox.value : null;
        }

        /**
         * Initialize Grid View
         */
        initGridView() {
            this.watchForGridChanges();
            this.addGridDownloadButtons();
        }

        /**
         * Watch for grid view changes
         */
        watchForGridChanges() {
            const observer = new MutationObserver(() => {
                this.addGridDownloadButtons();
            });

            const attachmentsWrapper = document.querySelector('.attachments-wrapper');
            if (attachmentsWrapper) {
                observer.observe(attachmentsWrapper, {
                    childList: true,
                    subtree: true
                });
            }

            // Also watch for select mode toggle
            setTimeout(() => {
                const bulkSelect = document.querySelector('.media-toolbar .select-mode-toggle-button');
                if (bulkSelect) {
                    bulkSelect.addEventListener('click', () => {
                        setTimeout(() => this.addBulkDownloadButton(), 100);
                    });
                }
            }, 200);
        }

        /**
         * Add bulk download button for grid view
         */
        addBulkDownloadButton() {
            // Remove existing button
            const existingBtn = document.querySelector('#mld-download');
            if (existingBtn) {
                existingBtn.remove();
            }

            const toolbar = document.querySelector('.media-toolbar-secondary');
            const deleteBtn = document.querySelector('.delete-selected-button');
            
            if (!toolbar || !deleteBtn) return;

            const downloadButton = document.createElement('button');
            downloadButton.id = 'mld-download';
            downloadButton.type = 'button';
            downloadButton.className = 'button media-button button-primary button-large';
            downloadButton.innerHTML = mld_i18n.download_files;
            downloadButton.setAttribute('aria-label', mld_i18n.download_files);
            downloadButton.setAttribute('title', mld_i18n.download_files);
            
            downloadButton.addEventListener('click', () => {
                const selectedAttachments = document.querySelectorAll('.attachments-wrapper .attachments .attachment[aria-checked="true"]');
                
                if (selectedAttachments.length === 0) {
                    this.showMessage(mld_i18n.no_files_selected, 'error');
                    return;
                }

                const filesToDownload = [];
                selectedAttachments.forEach(element => {
                    const dataID = element.dataset.id;
                    if (dataID) {
                        filesToDownload.push(dataID);
                    }
                });

                if (filesToDownload.length > 0) {
                    this.downloadFiles(filesToDownload);
                }
            });

            toolbar.insertBefore(downloadButton, deleteBtn);
        }

        /**
         * Add individual download buttons to grid view
         */
        addGridDownloadButtons() {
            const attachments = document.querySelectorAll('.attachment:not(.mld-processed)');
            
            attachments.forEach(attachment => {
                attachment.classList.add('mld-processed');
                
                const attachmentId = attachment.dataset.id;
                if (!attachmentId) return;

                // Create download overlay
                const downloadOverlay = document.createElement('div');
                downloadOverlay.className = 'mld-download-overlay';
                downloadOverlay.style.cssText = `
                    position: absolute;
                    top: 5px;
                    right: 5px;
                    background: rgba(0,0,0,0.7);
                    border-radius: 3px;
                    padding: 2px;
                    opacity: 0;
                    transition: opacity 0.2s;
                    z-index: 10;
                `;

                const downloadBtn = document.createElement('button');
                downloadBtn.className = 'button button-small mld-grid-download';
                downloadBtn.type = 'button';
                downloadBtn.innerHTML = '📥';
                downloadBtn.title = mld_i18n.download_single;
                downloadBtn.style.cssText = `
                    background: #0073aa;
                    color: white;
                    border: none;
                    padding: 4px 6px;
                    font-size: 12px;
                    cursor: pointer;
                `;

                downloadBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.downloadFiles([attachmentId]);
                });

                downloadOverlay.appendChild(downloadBtn);
                attachment.style.position = 'relative';
                attachment.appendChild(downloadOverlay);

                // Show/hide on hover
                attachment.addEventListener('mouseenter', () => {
                    downloadOverlay.style.opacity = '1';
                });
                attachment.addEventListener('mouseleave', () => {
                    downloadOverlay.style.opacity = '0';
                });
            });
        }

        /**
         * Download files via AJAX
         */
        downloadFiles(attachmentIds) {
            if (this.isDownloading) {
                this.showMessage(mld_i18n.preparing_download, 'info');
                return;
            }

            this.isDownloading = true;
            this.showProgress(true);

            jQuery.post({
                url: admin.ajax_url,
                data: {
                    action: "download_files",
                    ids: attachmentIds,
                    nonce: admin.nonce
                },
                success: (response) => {
                    this.handleDownloadResponse(response);
                },
                error: (xhr, status, error) => {
                    this.handleDownloadError(xhr, status, error);
                },
                complete: () => {
                    this.isDownloading = false;
                    this.showProgress(false);
                }
            });
        }

        /**
         * Handle successful download response
         */
        handleDownloadResponse(response) {
            if (response.success && response.data) {
                const data = response.data;
                
                if (data.single) {
                    // Direct download for single files
                    window.open(data.url, '_blank');
                    this.showMessage(`Downloaded: ${data.filename}`, 'success');
                } else {
                    // ZIP download for multiple files
                    window.location = data.url;
                    this.showMessage(`Prepared ZIP with ${data.file_count} files`, 'success');
                }
            } else {
                this.showMessage(response.data || mld_i18n.download_error, 'error');
            }
        }

        /**
         * Handle download error
         */
        handleDownloadError(xhr, status, error) {
            let errorMessage = mld_i18n.download_error;
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data;
            } else if (error) {
                errorMessage = error;
            }
            
            this.showMessage(errorMessage, 'error');
        }

        /**
         * Show progress indicator
         */
        showProgress(show) {
            let progressEl = document.querySelector('#mld-progress');
            
            if (show) {
                if (!progressEl) {
                    progressEl = document.createElement('div');
                    progressEl.id = 'mld-progress';
                    progressEl.style.cssText = `
                        position: fixed;
                        top: 32px;
                        right: 20px;
                        background: #0073aa;
                        color: white;
                        padding: 10px 15px;
                        border-radius: 4px;
                        z-index: 100000;
                        font-size: 14px;
                    `;
                    progressEl.innerHTML = '📥 ' + mld_i18n.preparing_download;
                    document.body.appendChild(progressEl);
                }
            } else {
                if (progressEl) {
                    progressEl.remove();
                }
            }
        }

        /**
         * Show message to user
         */
        showMessage(message, type = 'info') {
            // Remove existing message
            const existingMessage = document.querySelector('#mld-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            const messageEl = document.createElement('div');
            messageEl.id = 'mld-message';
            
            let bgColor = '#0073aa';
            if (type === 'error') bgColor = '#dc3232';
            if (type === 'success') bgColor = '#46b450';
            
            messageEl.style.cssText = `
                position: fixed;
                top: 32px;
                right: 20px;
                background: ${bgColor};
                color: white;
                padding: 10px 15px;
                border-radius: 4px;
                z-index: 100000;
                font-size: 14px;
                max-width: 300px;
            `;
            messageEl.textContent = message;
            document.body.appendChild(messageEl);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (messageEl && messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 5000);
        }
    }

    // Initialize the download manager
    new MLDDownloadManager();
});