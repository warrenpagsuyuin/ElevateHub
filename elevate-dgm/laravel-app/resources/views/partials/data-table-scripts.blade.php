<script>
(() => {
    const SELECTOR = {
        table: '[data-enhanced-table]',
        clientTable: '[data-client-table]',
        rowCheckbox: '[data-row-checkbox]',
    };

    const debounce = (callback, wait = 300) => {
        let timeout;
        return (...args) => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => callback(...args), wait);
        };
    };

    const normalize = (value) => String(value || '').trim().toLowerCase();
    const columnStorageKey = (tableId) => `elevate:data-table:${tableId}:columns`;

    const csvEscape = (value) => {
        const text = String(value || '').replace(/\s+/g, ' ').trim();
        return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    };

    const downloadCsv = (filename, rows) => {
        const blob = new Blob([rows.map((row) => row.map(csvEscape).join(',')).join('\n')], {
            type: 'text/csv;charset=utf-8;',
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        URL.revokeObjectURL(link.href);
        link.remove();
    };

    const getOrCreateConfirmDialog = () => {
        let backdrop = document.querySelector('[data-table-confirm-backdrop]');

        if (backdrop) {
            return backdrop;
        }

        backdrop = document.createElement('div');
        backdrop.className = 'data-table-modal-backdrop';
        backdrop.dataset.tableConfirmBackdrop = 'true';
        backdrop.innerHTML = `
            <section class="data-table-modal" role="dialog" aria-modal="true" aria-labelledby="data-table-confirm-title" aria-describedby="data-table-confirm-message">
                <h2 id="data-table-confirm-title">Confirm change</h2>
                <p id="data-table-confirm-message" data-table-confirm-message></p>
                <div class="data-table-modal-actions">
                    <button type="button" class="data-table-button" data-table-confirm-cancel>Cancel</button>
                    <button type="button" class="data-table-button primary" data-table-confirm-accept>Confirm</button>
                </div>
            </section>
        `;
        document.body.appendChild(backdrop);
        return backdrop;
    };

    const confirmChange = (message) => new Promise((resolve) => {
        const backdrop = getOrCreateConfirmDialog();
        const messageNode = backdrop.querySelector('[data-table-confirm-message]');
        const cancelButton = backdrop.querySelector('[data-table-confirm-cancel]');
        const acceptButton = backdrop.querySelector('[data-table-confirm-accept]');
        const previousFocus = document.activeElement;

        const cleanup = (answer) => {
            backdrop.classList.remove('open');
            cancelButton.removeEventListener('click', cancel);
            acceptButton.removeEventListener('click', accept);
            backdrop.removeEventListener('click', clickOutside);
            document.removeEventListener('keydown', keydown);

            if (previousFocus && typeof previousFocus.focus === 'function') {
                previousFocus.focus();
            }

            resolve(answer);
        };

        const cancel = () => cleanup(false);
        const accept = () => cleanup(true);
        const clickOutside = (event) => {
            if (event.target === backdrop) {
                cancel();
            }
        };
        const focusableControls = () => Array.from(backdrop.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
            .filter((element) => !element.disabled && element.offsetParent !== null);
        const keydown = (event) => {
            if (event.key === 'Escape') {
                cancel();
                return;
            }

            if (event.key === 'Tab') {
                const controls = focusableControls();

                if (controls.length === 0) {
                    return;
                }

                const first = controls[0];
                const last = controls[controls.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };

        messageNode.textContent = message;
        backdrop.classList.add('open');
        cancelButton.addEventListener('click', cancel);
        acceptButton.addEventListener('click', accept);
        backdrop.addEventListener('click', clickOutside);
        document.addEventListener('keydown', keydown);
        acceptButton.focus();
    });

    const normalizeHiddenColumns = (table, hiddenColumns) => {
        const headers = Array.from(table.querySelectorAll('thead th[data-column]'));
        const hideableColumns = headers
            .filter((header) => !header.dataset.lockedColumn)
            .map((header) => header.dataset.column);
        const requested = hiddenColumns.filter((column) => hideableColumns.includes(column));

        if (hideableColumns.length > 0 && hideableColumns.every((column) => requested.includes(column))) {
            return requested.filter((column) => column !== hideableColumns[0]);
        }

        return requested;
    };

    const setHiddenColumns = (table, hiddenColumns) => {
        const nextHiddenColumns = normalizeHiddenColumns(table, hiddenColumns);
        const hidden = new Set(nextHiddenColumns);
        const headers = Array.from(table.querySelectorAll('thead th[data-column]'));

        headers.forEach((header, headerIndex) => {
            const column = header.dataset.column;
            const shouldHide = hidden.has(column);
            const cellIndex = Array.from(header.parentElement.children).indexOf(header);

            header.hidden = shouldHide;
            table.querySelectorAll('tbody tr').forEach((row) => {
                const cell = row.children[cellIndex];
                if (cell) {
                    cell.hidden = shouldHide;
                }
            });

            const checkbox = document.querySelector(`[data-column-toggle][data-table-id="${table.dataset.tableId}"][value="${column}"]`);
            if (checkbox) {
                checkbox.checked = !shouldHide;
            }

            header.dataset.columnIndex = String(headerIndex);
        });

        return nextHiddenColumns;
    };

    const readHiddenColumns = (table) => {
        return Array.from(table.querySelectorAll('thead th[data-column]'))
            .filter((header) => header.hidden)
            .map((header) => header.dataset.column);
    };

    const initColumnVisibility = () => {
        document.querySelectorAll('[data-column-visibility]').forEach((container) => {
            const table = document.querySelector(container.dataset.target || '');
            if (!table) {
                return;
            }

            const tableId = table.dataset.tableId || container.dataset.tableId || 'default';
            table.dataset.tableId = tableId;
            const headers = Array.from(table.querySelectorAll('thead th[data-column]'))
                .filter((header) => !header.dataset.lockedColumn);
            const storedColumns = JSON.parse(localStorage.getItem(columnStorageKey(tableId)) || '[]');

            container.classList.add('data-table-columns');
            container.innerHTML = `
                <button type="button" class="data-table-button data-table-columns-button" data-column-menu-button aria-haspopup="true" aria-expanded="false">
                    Columns <span aria-hidden="true">v</span>
                </button>
                <div class="data-table-columns-panel" data-column-menu role="menu" hidden></div>
            `;

            const button = container.querySelector('[data-column-menu-button]');
            const panel = container.querySelector('[data-column-menu]');

            headers.forEach((header) => {
                const label = document.createElement('label');
                label.className = 'data-table-column-toggle';
                label.setAttribute('role', 'menuitemcheckbox');
                label.innerHTML = `
                    <input class="data-table-checkbox data-table-column-checkbox" type="checkbox" data-column-toggle data-table-id="${tableId}" value="${header.dataset.column}">
                    <span>${header.dataset.label || header.textContent.trim()}</span>
                `;
                panel.appendChild(label);
            });

            const persistColumns = (hiddenColumns) => {
                localStorage.setItem(columnStorageKey(tableId), JSON.stringify(hiddenColumns));
            };
            const syncToggleState = () => {
                container.querySelectorAll('.data-table-column-toggle').forEach((label) => {
                    const checkbox = label.querySelector('[data-column-toggle]');
                    label.setAttribute('aria-checked', String(Boolean(checkbox?.checked)));
                });
            };
            const closePanel = (restoreFocus = false) => {
                panel.hidden = true;
                button.setAttribute('aria-expanded', 'false');

                if (restoreFocus) {
                    button.focus();
                }
            };
            const openPanel = () => {
                panel.hidden = false;
                button.setAttribute('aria-expanded', 'true');
            };

            persistColumns(setHiddenColumns(table, storedColumns));
            syncToggleState();

            container.querySelectorAll('[data-column-toggle]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const requestedHiddenColumns = Array.from(container.querySelectorAll('[data-column-toggle]'))
                        .filter((input) => !input.checked)
                        .map((input) => input.value);
                    const hiddenColumns = setHiddenColumns(table, requestedHiddenColumns);

                    persistColumns(hiddenColumns);
                    syncToggleState();
                });
            });

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                panel.hidden ? openPanel() : closePanel();
            });

            document.addEventListener('click', (event) => {
                if (!container.contains(event.target)) {
                    closePanel();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !panel.hidden) {
                    closePanel(true);
                }
            });
        });
    };

    const initMenus = () => {
        let activeMenu = null;

        const menuItems = (menu) => Array.from(menu.querySelectorAll('[role="menuitem"]'))
            .filter((item) => !item.disabled && item.offsetParent !== null);

        const positionMenu = (menu) => {
            const button = menu.querySelector('[data-menu-button]');
            const panel = menu.querySelector('.data-table-menu');

            if (!button || !panel) {
                return;
            }

            const buttonRect = button.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const gap = 8;
            const maxLeft = Math.max(gap, window.innerWidth - panelRect.width - gap);
            const left = Math.min(maxLeft, Math.max(gap, buttonRect.right - panelRect.width));
            let top = buttonRect.bottom + gap;

            if (top + panelRect.height > window.innerHeight - gap && buttonRect.top > panelRect.height + gap) {
                top = buttonRect.top - panelRect.height - gap;
            }

            panel.style.left = `${left}px`;
            panel.style.top = `${Math.max(gap, top)}px`;
        };

        const closeMenu = (menu, restoreFocus = false) => {
            const button = menu?.querySelector('[data-menu-button]');
            const panel = menu?.querySelector('.data-table-menu');

            menu?.classList.remove('open');
            button?.setAttribute('aria-expanded', 'false');

            if (panel) {
                panel.style.left = '';
                panel.style.top = '';
            }

            if (activeMenu === menu) {
                activeMenu = null;
            }

            if (restoreFocus) {
                button?.focus();
            }
        };

        const closeAllMenus = (restoreFocus = false) => {
            document.querySelectorAll('.data-table-row-menu.open').forEach((menu) => closeMenu(menu, restoreFocus));
        };

        const openMenu = (menu) => {
            closeAllMenus();
            const button = menu.querySelector('[data-menu-button]');

            menu.classList.add('open');
            button?.setAttribute('aria-expanded', 'true');
            activeMenu = menu;
            positionMenu(menu);
        };

        document.addEventListener('click', (event) => {
            const focusTargetButton = event.target.closest('[data-focus-target]');
            if (focusTargetButton) {
                const target = document.querySelector(focusTargetButton.dataset.focusTarget || '');
                closeMenu(focusTargetButton.closest('.data-table-row-menu'));
                target?.focus();
                return;
            }

            const button = event.target.closest('[data-menu-button]');

            if (!button) {
                if (!event.target.closest('.data-table-row-menu')) {
                    closeAllMenus();
                }
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            const menu = button.closest('.data-table-row-menu');
            const willOpen = !menu.classList.contains('open');

            willOpen ? openMenu(menu) : closeMenu(menu, true);
        });

        document.addEventListener('keydown', (event) => {
            const menu = activeMenu;

            if (!menu) {
                return;
            }

            if (event.key === 'Escape') {
                closeMenu(menu, true);
                return;
            }

            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                return;
            }

            const items = menuItems(menu);
            if (items.length === 0) {
                return;
            }

            event.preventDefault();
            const currentIndex = items.indexOf(document.activeElement);
            let nextIndex = currentIndex;

            if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = items.length - 1;
            } else if (event.key === 'ArrowDown') {
                nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % items.length;
            } else if (event.key === 'ArrowUp') {
                nextIndex = currentIndex < 0 ? items.length - 1 : (currentIndex - 1 + items.length) % items.length;
            }

            items[nextIndex]?.focus();
        });

        window.addEventListener('resize', () => activeMenu && positionMenu(activeMenu));
        window.addEventListener('scroll', () => activeMenu && positionMenu(activeMenu), true);
    };

    const initSearchClear = () => {
        document.querySelectorAll('[data-search-clear]').forEach((button) => {
            const wrapper = button.closest('.data-table-search');
            const input = wrapper?.querySelector('input[type="search"]');

            if (!input) {
                return;
            }

            const sync = () => {
                button.hidden = input.value.trim() === '';
            };

            input.addEventListener('input', sync);
            button.addEventListener('click', () => {
                input.value = '';
                sync();
                input.focus();
                input.form?.querySelector('[name="page"]')?.setAttribute('value', '1');
                input.form?.requestSubmit();
            });
            sync();
        });
    };

    const initDebouncedForms = () => {
        document.querySelectorAll('[data-debounced-submit]').forEach((input) => {
            input.addEventListener('input', debounce(() => {
                input.form?.querySelector('[name="page"]')?.setAttribute('value', '1');
                input.form?.requestSubmit();
            }, Number(input.dataset.debounceDelay || 350)));
        });

        document.querySelectorAll('[data-auto-submit]').forEach((input) => {
            input.addEventListener('change', () => {
                input.form?.querySelector('[name="page"]')?.setAttribute('value', '1');
                input.form?.requestSubmit();
            });
        });
    };

    const updateStatusClass = (select) => {
        const statusClasses = ['status-successful', 'status-pending', 'status-unsuccessful', 'status-active', 'status-new', 'status-inactive'];
        const className = `status-${normalize(select.value).replace(/\s+/g, '-')}`;
        const control = select.closest('[data-status-control]');

        select.classList.remove(...statusClasses);
        select.classList.add(className);

        if (control) {
            control.classList.remove(...statusClasses);
            control.classList.add(className);
        }
    };

    const interpolate = (template, data) => {
        return template.replace(/\{([a-z_]+)\}/gi, (_, key) => data[key] ?? '');
    };

    const markFormSubmitting = (form) => {
        if (!form || form.dataset.submitting === 'true') {
            return;
        }

        form.dataset.submitting = 'true';
        form.classList.add('data-table-loading');
        form.querySelectorAll('button').forEach((control) => {
            control.disabled = true;
        });
    };

    const initConfirmingControls = () => {
        document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return;
                }

                event.preventDefault();
                const ok = await confirmChange(form.dataset.confirmMessage);
                if (ok) {
                    form.dataset.confirmed = 'true';
                    markFormSubmitting(form);
                    form.submit();
                }
            });
        });

        document.querySelectorAll('select[data-confirm-change]').forEach((select) => {
            select.dataset.previousValue = select.value;
            updateStatusClass(select);

            select.addEventListener('change', async () => {
                const previousValue = select.dataset.previousValue || '';
                const nextValue = select.value;
                updateStatusClass(select);

                const message = interpolate(select.dataset.confirmChange, {
                    from: previousValue || 'Unassigned',
                    to: nextValue || 'Unassigned',
                    name: select.dataset.recordName || 'this record',
                });
                const ok = await confirmChange(message);

                if (!ok) {
                    select.value = previousValue;
                    updateStatusClass(select);
                    return;
                }

                select.dataset.previousValue = nextValue;
                markFormSubmitting(select.form);
                select.form?.submit();
            });
        });
    };

    const initFilterLoading = () => {
        document.querySelectorAll('[data-table-filter-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                form.classList.add('data-table-loading');
            });
        });
    };

    const initBulkSelection = () => {
        document.querySelectorAll('[data-bulk-scope]').forEach((scope) => {
            const form = scope.querySelector('[data-bulk-form]');
            const selectionScope = form?.querySelector('[data-selection-scope]');
            const selectedIds = form?.querySelector('[data-selected-ids]');
            const actionInput = form?.querySelector('[data-bulk-action-input]');
            const summaryBar = scope.querySelector('[data-selection-bar]');
            const summary = scope.querySelector('[data-selection-summary]');
            const help = scope.querySelector('[data-selection-help]');
            const selectPage = scope.querySelector('[data-select-page]');
            const selectMatching = scope.querySelector('[data-select-matching]');
            const clearSelection = scope.querySelector('[data-clear-selection]');
            const matchingCount = Number(scope.dataset.matchingCount || 0);
            let selectionMode = 'ids';

            if (!form || !selectionScope || !selectedIds || !summaryBar) {
                return;
            }

            const rowCheckboxes = () => Array.from(scope.querySelectorAll(SELECTOR.rowCheckbox));
            const checkedIds = () => rowCheckboxes().filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
            const selectedCount = () => selectionMode === 'matching' ? matchingCount : checkedIds().length;

            const syncHiddenIds = () => {
                selectedIds.innerHTML = '';
                if (selectionMode === 'matching') {
                    selectionScope.value = 'matching';
                    return;
                }

                selectionScope.value = 'ids';
                checkedIds().forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    selectedIds.appendChild(input);
                });
            };

            const update = () => {
                const count = selectedCount();
                const totalOnPage = rowCheckboxes().length;
                summaryBar.hidden = count === 0;

                if (summary) {
                    const label = scope.dataset.recordLabel || 'records';
                    const singularLabel = scope.dataset.recordSingularLabel || label.replace(/s$/, '');
                    const countLabel = count === 1 ? singularLabel : label;
                    summary.textContent = selectionMode === 'matching'
                        ? `${count} matching ${countLabel} selected`
                        : `${count} ${countLabel} selected`;
                }

                if (help) {
                    help.textContent = selectionMode === 'matching'
                        ? 'Bulk actions will apply to all records matching the current filters.'
                        : 'Bulk actions will apply only to checked rows on this page.';
                }

                if (selectPage) {
                    selectPage.checked = totalOnPage > 0 && checkedIds().length === totalOnPage && selectionMode !== 'matching';
                    selectPage.indeterminate = checkedIds().length > 0 && checkedIds().length < totalOnPage && selectionMode !== 'matching';
                }

                syncHiddenIds();
            };

            rowCheckboxes().forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    selectionMode = 'ids';
                    update();
                });
            });

            selectPage?.addEventListener('change', () => {
                selectionMode = 'ids';
                rowCheckboxes().forEach((checkbox) => {
                    checkbox.checked = selectPage.checked;
                });
                update();
            });

            selectMatching?.addEventListener('click', () => {
                if (scope.dataset.matchingSelection === 'ids') {
                    selectionMode = 'ids';
                    rowCheckboxes().forEach((checkbox) => {
                        checkbox.checked = !checkbox.closest('[data-table-row]')?.hidden;
                    });
                    update();
                    return;
                }

                selectionMode = 'matching';
                rowCheckboxes().forEach((checkbox) => {
                    checkbox.checked = false;
                });
                update();
            });

            clearSelection?.addEventListener('click', () => {
                selectionMode = 'ids';
                rowCheckboxes().forEach((checkbox) => {
                    checkbox.checked = false;
                });
                update();
            });

            scope.querySelectorAll('[data-bulk-action]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (actionInput) {
                        actionInput.value = button.dataset.bulkAction;
                    }
                });
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submitter = event.submitter || document.activeElement;
                const actionLabel = submitter?.dataset?.actionLabel || 'update selected records';
                const count = selectedCount();

                if (count === 0) {
                    await confirmChange('Select at least one row before running a bulk action.');
                    return;
                }

                syncHiddenIds();
                const message = interpolate(submitter?.dataset?.confirmTemplate || form.dataset.confirmTemplate || 'This will {action} for {count} records.', {
                    count,
                    action: actionLabel,
                });
                const ok = await confirmChange(message);

                if (ok) {
                    markFormSubmitting(form);
                    form.submit();
                }
            });

            update();
        });
    };

    const initClientTables = () => {
        document.querySelectorAll(SELECTOR.clientTable).forEach((scope) => {
            const table = scope.querySelector(SELECTOR.table);
            const tbody = table?.querySelector('tbody');
            const rows = Array.from(scope.querySelectorAll('[data-table-row]'));
            const emptyFilterRow = scope.querySelector('[data-empty-filter]');
            const search = scope.querySelector('[data-client-search]');
            const filters = Array.from(scope.querySelectorAll('[data-client-filter]'));
            const pageSize = scope.querySelector('[data-client-page-size]');
            const pagination = scope.querySelector('[data-client-pagination]');
            const countLabel = scope.querySelector('[data-client-count]');
            const exportButton = scope.querySelector('[data-client-export]');
            let sortField = scope.dataset.defaultSort || '';
            let sortDirection = scope.dataset.defaultDirection || 'asc';
            let page = 1;

            if (!table || !tbody) {
                return;
            }

            const headerForField = (field) => table.querySelector(`thead th[data-field="${field}"]`);
            const rowValue = (row, field) => normalize(row.dataset[field] || '');
            const orderedRows = (sourceRows) => {
                if (!sortField) {
                    return sourceRows;
                }

                const header = headerForField(sortField);
                const type = header?.dataset.type || 'text';
                const multiplier = sortDirection === 'desc' ? -1 : 1;

                return [...sourceRows].sort((a, b) => {
                    const aValue = rowValue(a, sortField);
                    const bValue = rowValue(b, sortField);

                    if (type === 'number') {
                        return ((Number(aValue) || 0) - (Number(bValue) || 0)) * multiplier;
                    }

                    return aValue.localeCompare(bValue) * multiplier;
                });
            };

            const filteredRows = () => {
                const query = normalize(search?.value);

                return rows.filter((row) => {
                    const haystack = normalize(row.dataset.search || row.textContent);
                    const matchesSearch = !query || haystack.includes(query);
                    const matchesFilters = filters.every((filter) => {
                        const value = filter.value;
                        return !value || value === 'all' || rowValue(row, filter.dataset.field) === normalize(value);
                    });

                    return matchesSearch && matchesFilters;
                });
            };

            const renderPagination = (total, pageCount) => {
                if (!pagination) {
                    return;
                }

                pagination.innerHTML = '';
                pagination.classList.add('pagination-bar');

                const summary = document.createElement('span');
                summary.className = 'pagination-summary';
                const start = total === 0 ? 0 : ((page - 1) * Number(pageSize?.value || 10)) + 1;
                const end = Math.min(total, page * Number(pageSize?.value || 10));
                summary.textContent = `Showing ${start}-${end} of ${total}`;
                pagination.appendChild(summary);

                const actions = document.createElement('div');
                actions.className = 'pagination-links';

                const pageLinks = [...new Set([1, pageCount, page - 1, page, page + 1])]
                    .filter((pageNumber) => pageNumber >= 1 && pageNumber <= pageCount)
                    .sort((a, b) => a - b);

                const makeButton = (label, targetPage, disabled = false, current = false) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = current ? 'pagination-current' : (disabled ? 'pagination-disabled' : 'pagination-link');
                    button.textContent = label;
                    button.disabled = disabled || current;

                    if (current) {
                        button.setAttribute('aria-current', 'page');
                        button.setAttribute('aria-label', `Page ${label}, current page`);
                    } else if (!disabled) {
                        button.setAttribute('aria-label', label.includes('Previous') ? 'Go to previous page' : (label.includes('Next') ? 'Go to next page' : `Go to page ${label}`));
                        button.addEventListener('click', () => {
                            page = targetPage;
                            render();
                        });
                    }

                    return button;
                };

                actions.appendChild(makeButton('\u2039 Previous', page - 1, page <= 1));

                const mobileStatus = document.createElement('span');
                mobileStatus.className = 'pagination-mobile-status';
                mobileStatus.textContent = `Page ${page} of ${pageCount}`;
                actions.appendChild(mobileStatus);

                let previousPage = null;
                pageLinks.forEach((pageNumber) => {
                    if (previousPage !== null && pageNumber > previousPage + 1) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'pagination-ellipsis';
                        ellipsis.setAttribute('aria-hidden', 'true');
                        ellipsis.textContent = '\u2026';
                        actions.appendChild(ellipsis);
                    }

                    actions.appendChild(makeButton(String(pageNumber), pageNumber, false, pageNumber === page));
                    previousPage = pageNumber;
                });

                actions.appendChild(makeButton('Next \u203A', page + 1, page >= pageCount));
                pagination.appendChild(actions);
            };

            const render = () => {
                const matchingRows = orderedRows(filteredRows());
                const size = Number(pageSize?.value || rows.length || 1);
                const pageCount = Math.max(1, Math.ceil(matchingRows.length / size));
                page = Math.min(page, pageCount);
                const visibleRows = matchingRows.slice((page - 1) * size, page * size);
                const visible = new Set(visibleRows);

                rows.forEach((row) => {
                    row.hidden = !visible.has(row);
                });

                if (emptyFilterRow) {
                    emptyFilterRow.hidden = matchingRows.length > 0 || rows.length === 0;
                }

                if (countLabel) {
                    countLabel.textContent = `${matchingRows.length} of ${rows.length} records`;
                }

                scope.dataset.matchingCount = String(matchingRows.length);
                renderPagination(matchingRows.length, pageCount);
            };

            search?.addEventListener('input', debounce(() => {
                page = 1;
                render();
            }));

            filters.forEach((filter) => {
                filter.addEventListener('change', () => {
                    page = 1;
                    render();
                });
            });

            pageSize?.addEventListener('change', () => {
                page = 1;
                render();
            });

            table.querySelectorAll('[data-client-sort]').forEach((button) => {
                button.addEventListener('click', () => {
                    const field = button.dataset.clientSort;
                    if (sortField === field) {
                        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortField = field;
                        sortDirection = 'asc';
                    }

                    table.querySelectorAll('th[aria-sort]').forEach((header) => header.setAttribute('aria-sort', 'none'));
                    button.closest('th')?.setAttribute('aria-sort', sortDirection === 'asc' ? 'ascending' : 'descending');
                    button.querySelector('.data-table-sort-indicator').textContent = sortDirection === 'asc' ? 'A-Z' : 'Z-A';
                    render();
                });
            });

            exportButton?.addEventListener('click', () => {
                const headers = Array.from(table.querySelectorAll('thead th'))
                    .filter((header) => !header.hidden && !header.dataset.lockedColumn)
                    .map((header) => header.dataset.label || header.textContent.trim());
                const tableRows = orderedRows(filteredRows()).map((row) => {
                    return Array.from(row.children)
                        .filter((cell, index) => !cell.hidden && !table.querySelectorAll('thead th')[index]?.dataset.lockedColumn)
                        .map((cell) => cell.innerText);
                });

                downloadCsv(`${scope.dataset.exportName || 'table'}-${new Date().toISOString().slice(0, 10)}.csv`, [headers, ...tableRows]);
            });

            render();
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initColumnVisibility();
        initMenus();
        initSearchClear();
        initDebouncedForms();
        initConfirmingControls();
        initFilterLoading();
        initBulkSelection();
        initClientTables();
    });
})();
</script>
