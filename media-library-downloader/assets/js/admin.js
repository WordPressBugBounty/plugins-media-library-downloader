document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    if (!body.classList.contains('upload-php')) {
        return;
    }

    class MLDDownloadManager {
        constructor() {
            this.isDownloading = false;
            this.listObserver = null;
            this.init();
        }

        isListView() {
            if (body.classList.contains('mode-list')) {
                return true;
            }

            if (document.querySelector('form#posts-filter .wp-list-table')) {
                return true;
            }

            const mode = new URLSearchParams(window.location.search).get('mode');
            if (mode === 'list') {
                return true;
            }

            return document.querySelector('.wp-list-table.upload') !== null
                || document.querySelector('.table-view-list') !== null;
        }

        isGridView() {
            if (document.getElementById('wp-media-grid')) {
                return true;
            }

            const mode = new URLSearchParams(window.location.search).get('mode');
            return mode === 'grid' || mode === null;
        }

        init() {
            this.bindViewSwitchers();

            if (this.isListView()) {
                this.initListView();
            } else if (this.isGridView()) {
                this.initGridView();
            }

            this.runPendingDownload();
        }

        bindViewSwitchers() {
            document.querySelectorAll('#view-switch-list a, #view-switch-grid a').forEach((link) => {
                link.addEventListener('click', () => {
                    window.setTimeout(() => {
                        if (this.listObserver) {
                            this.listObserver.disconnect();
                            this.listObserver = null;
                        }

                        if (this.isListView()) {
                            this.initListView();
                        } else if (this.isGridView()) {
                            this.initGridView();
                        }
                    }, 300);
                });
            });
        }

        runPendingDownload() {
            if (typeof mld_pending === 'undefined' || !Array.isArray(mld_pending.ids) || !mld_pending.ids.length) {
                return;
            }

            this.downloadFiles(mld_pending.ids);
        }

        initListView() {
            this.addBulkActions();
            this.addListViewToolbarButton();
            this.handleBulkDownload();
            this.addIndividualDownloadButtons();
            this.watchListChanges();
            this.showListViewHint();
        }

        getSelectedListIds() {
            const checkedFiles = document.querySelectorAll('#the-list input[type="checkbox"][name="media[]"]:checked');

            if (!checkedFiles.length) {
                return [];
            }

            const selection = [];
            checkedFiles.forEach((element) => {
                if (element && element.value) {
                    selection.push(element.value);
                }
            });

            return selection;
        }

        addBulkActions() {
            const bulkActionTop = document.querySelector('#bulk-action-selector-top');
            const bulkActionBottom = document.querySelector('#bulk-action-selector-bottom');

            [bulkActionTop, bulkActionBottom].forEach((selector) => {
                if (!selector) {
                    return;
                }

                if (selector.querySelector('option[value="mld-download-files"]')) {
                    return;
                }

                const option = document.createElement('option');
                option.value = 'mld-download-files';
                option.innerText = mld_i18n.download_files;
                selector.appendChild(option);
            });
        }

        addListViewToolbarButton() {
            if (document.getElementById('mld-list-download-btn')) {
                return;
            }

            const bulkActions = document.querySelector('.tablenav.top .bulkactions');
            if (!bulkActions) {
                return;
            }

            const downloadButton = document.createElement('button');
            downloadButton.id = 'mld-list-download-btn';
            downloadButton.type = 'button';
            downloadButton.className = 'button button-primary';
            downloadButton.style.marginLeft = '8px';
            downloadButton.textContent = mld_i18n.download_files;
            downloadButton.setAttribute('aria-label', mld_i18n.download_files);

            downloadButton.addEventListener('click', () => {
                const selection = this.getSelectedListIds();
                if (!selection.length) {
                    this.showMessage(mld_i18n.no_files_selected, 'error');
                    return;
                }

                this.downloadFiles(selection);
            });

            bulkActions.insertAdjacentElement('afterend', downloadButton);
        }

        handleBulkDownload() {
            const doAction = document.querySelector('#doaction');
            const doAction2 = document.querySelector('#doaction2');

            if (doAction && !doAction.dataset.mldBound) {
                doAction.dataset.mldBound = '1';
                doAction.addEventListener('click', (e) => this.processBulkAction(e, 'top'));
            }

            if (doAction2 && !doAction2.dataset.mldBound) {
                doAction2.dataset.mldBound = '1';
                doAction2.addEventListener('click', (e) => this.processBulkAction(e, 'bottom'));
            }
        }

        processBulkAction(e, position) {
            const selector = position === 'top'
                ? document.querySelector('#bulk-action-selector-top')
                : document.querySelector('#bulk-action-selector-bottom');

            if (!selector || selector.value !== 'mld-download-files') {
                return;
            }

            e.preventDefault();

            const selection = this.getSelectedListIds();
            if (!selection.length) {
                this.showMessage(mld_i18n.no_files_selected, 'error');
                return;
            }

            this.downloadFiles(selection);
        }

        addIndividualDownloadButtons() {
            const rows = document.querySelectorAll('#the-list tr');

            rows.forEach((row) => {
                if (row.classList.contains('mld-processed')) {
                    return;
                }

                row.classList.add('mld-processed');

                const attachmentId = this.getAttachmentId(row);
                if (!attachmentId) {
                    return;
                }

                let rowActions = row.querySelector('.row-actions');
                if (!rowActions) {
                    const titleColumn = row.querySelector('.column-title, .title');
                    if (!titleColumn) {
                        return;
                    }

                    rowActions = document.createElement('div');
                    rowActions.className = 'row-actions';
                    titleColumn.appendChild(rowActions);
                }

                if (rowActions.querySelector('.mld-download')) {
                    return;
                }

                const downloadAction = document.createElement('span');
                downloadAction.className = 'mld-download';
                downloadAction.innerHTML = '<a href="#" class="mld-single-download" data-attachment-id="' + attachmentId + '">' + mld_i18n.download_single + '</a>';

                const downloadLink = downloadAction.querySelector('.mld-single-download');
                downloadLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.downloadFiles([attachmentId]);
                });

                if (rowActions.children.length) {
                    downloadAction.insertAdjacentHTML('afterbegin', ' | ');
                }

                rowActions.appendChild(downloadAction);
            });
        }

        watchListChanges() {
            const listTable = document.querySelector('#the-list');
            if (!listTable) {
                return;
            }

            if (this.listObserver) {
                this.listObserver.disconnect();
            }

            this.listObserver = new MutationObserver(() => {
                this.addBulkActions();
                this.addListViewToolbarButton();
                this.handleBulkDownload();
                this.addIndividualDownloadButtons();
            });

            this.listObserver.observe(listTable, {
                childList: true,
                subtree: true,
            });
        }

        showListViewHint() {
            if (window.localStorage.getItem('mld_list_hint_dismissed') === '1') {
                return;
            }

            const toolbar = document.querySelector('.tablenav.top');
            if (!toolbar || document.getElementById('mld-list-hint')) {
                return;
            }

            const hint = document.createElement('div');
            hint.id = 'mld-list-hint';
            hint.className = 'notice notice-info inline';
            hint.style.margin = '8px 0';
            hint.innerHTML = '<p>' + mld_i18n.list_view_hint + ' <button type="button" class="button-link" id="mld-dismiss-hint">' + mld_i18n.dismiss_hint + '</button></p>';
            toolbar.parentNode.insertBefore(hint, toolbar);

            document.getElementById('mld-dismiss-hint').addEventListener('click', () => {
                window.localStorage.setItem('mld_list_hint_dismissed', '1');
                hint.remove();
            });
        }

        getAttachmentId(row) {
            const checkbox = row.querySelector('input[type="checkbox"][name="media[]"]');
            return checkbox ? checkbox.value : null;
        }

        initGridView() {
            this.watchForGridChanges();
            this.addGridDownloadButtons();
        }

        watchForGridChanges() {
            const observer = new MutationObserver(() => {
                this.addGridDownloadButtons();
            });

            const attachmentsWrapper = document.querySelector('.attachments-wrapper');
            if (attachmentsWrapper) {
                observer.observe(attachmentsWrapper, {
                    childList: true,
                    subtree: true,
                });
            }

            setTimeout(() => {
                const bulkSelect = document.querySelector('.media-toolbar .select-mode-toggle-button');
                if (bulkSelect && !bulkSelect.dataset.mldBound) {
                    bulkSelect.dataset.mldBound = '1';
                    bulkSelect.addEventListener('click', () => {
                        setTimeout(() => this.addBulkDownloadButton(), 100);
                    });
                }
            }, 200);
        }

        addBulkDownloadButton() {
            const existingBtn = document.querySelector('#mld-download');
            if (existingBtn) {
                existingBtn.remove();
            }

            const toolbar = document.querySelector('.media-toolbar-secondary');
            const deleteBtn = document.querySelector('.delete-selected-button');

            if (!toolbar || !deleteBtn) {
                return;
            }

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
                selectedAttachments.forEach((element) => {
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

        addGridDownloadButtons() {
            const attachments = document.querySelectorAll('.attachment:not(.mld-processed)');

            attachments.forEach((attachment) => {
                attachment.classList.add('mld-processed');

                const attachmentId = attachment.dataset.id;
                if (!attachmentId) {
                    return;
                }

                const downloadOverlay = document.createElement('div');
                downloadOverlay.className = 'mld-download-overlay';
                downloadOverlay.style.cssText = 'position:absolute;top:5px;right:5px;background:rgba(0,0,0,0.7);border-radius:3px;padding:2px;opacity:0;transition:opacity 0.2s;z-index:10;';

                const downloadBtn = document.createElement('button');
                downloadBtn.className = 'button button-small mld-grid-download';
                downloadBtn.type = 'button';
                downloadBtn.innerHTML = '📥';
                downloadBtn.title = mld_i18n.download_single;
                downloadBtn.style.cssText = 'background:#0073aa;color:white;border:none;padding:4px 6px;font-size:12px;cursor:pointer;';

                downloadBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.downloadFiles([attachmentId]);
                });

                downloadOverlay.appendChild(downloadBtn);
                attachment.style.position = 'relative';
                attachment.appendChild(downloadOverlay);

                attachment.addEventListener('mouseenter', () => {
                    downloadOverlay.style.opacity = '1';
                });
                attachment.addEventListener('mouseleave', () => {
                    downloadOverlay.style.opacity = '0';
                });
            });
        }

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
                    action: 'download_files',
                    ids: attachmentIds,
                    nonce: admin.nonce,
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
                },
            });
        }

        handleDownloadResponse(response) {
            if (response.success && response.data) {
                const data = response.data;

                if (data.single) {
                    window.open(data.url, '_blank');
                    this.showMessage('Downloaded: ' + data.filename, 'success');
                } else {
                    window.location = data.url;
                    this.showMessage('Prepared ZIP with ' + data.file_count + ' files', 'success');
                }
            } else {
                this.showMessage(response.data || mld_i18n.download_error, 'error');
            }
        }

        handleDownloadError(xhr, status, error) {
            let errorMessage = mld_i18n.download_error;

            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data;
            } else if (error) {
                errorMessage = error;
            }

            this.showMessage(errorMessage, 'error');
        }

        showProgress(show) {
            let progressEl = document.querySelector('#mld-progress');

            if (show) {
                if (!progressEl) {
                    progressEl = document.createElement('div');
                    progressEl.id = 'mld-progress';
                    progressEl.style.cssText = 'position:fixed;top:32px;right:20px;background:#0073aa;color:white;padding:10px 15px;border-radius:4px;z-index:100000;font-size:14px;';
                    progressEl.innerHTML = '📥 ' + mld_i18n.preparing_download;
                    document.body.appendChild(progressEl);
                }
            } else if (progressEl) {
                progressEl.remove();
            }
        }

        showMessage(message, type = 'info') {
            const existingMessage = document.querySelector('#mld-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            const messageEl = document.createElement('div');
            messageEl.id = 'mld-message';

            let bgColor = '#0073aa';
            if (type === 'error') {
                bgColor = '#dc3232';
            }
            if (type === 'success') {
                bgColor = '#46b450';
            }

            messageEl.style.cssText = 'position:fixed;top:32px;right:20px;background:' + bgColor + ';color:white;padding:10px 15px;border-radius:4px;z-index:100000;font-size:14px;max-width:300px;';
            messageEl.textContent = message;
            document.body.appendChild(messageEl);

            setTimeout(() => {
                if (messageEl && messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 5000);
        }
    }

    new MLDDownloadManager();
});
