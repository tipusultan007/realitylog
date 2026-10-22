import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/app.css';

import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    initCaptureTabs();
    initConfirmations();
    initAutoSubmit();
    initSearchShortcut();
    initFileDrops();
    initFlashMessages();
    initSuggestionChips();
    initCopyButtons();
    initTooltips();
    initLanguageChoices();

    document.querySelectorAll('[data-auto-grow]').forEach((field) => {
        const grow = () => {
            field.style.height = 'auto';
            field.style.height = `${field.scrollHeight}px`;
        };

        field.addEventListener('input', grow);
        grow();
    });
});

function initTheme() {
    const apply = (theme) => {
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
    };

    apply(document.documentElement.dataset.theme || 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('realitylog-theme', next);
            apply(next);
        });
    });
}

function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const scrim = document.querySelector('[data-sidebar-scrim]');

    if (!sidebar) {
        return;
    }

    const close = () => {
        sidebar.classList.remove('is-open');
        scrim?.classList.remove('is-visible');
        document.body.classList.remove('nav-open');
    };

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
        button.addEventListener('click', () => {
            sidebar.classList.add('is-open');
            scrim?.classList.add('is-visible');
            document.body.classList.add('nav-open');
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    scrim?.addEventListener('click', close);
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}

function initCaptureTabs() {
    const form = document.querySelector('[data-capture-form]');

    if (!form) {
        return;
    }

    const sourceInput = form.querySelector('[name="source_type"]');
    const tabs = form.querySelectorAll('[data-capture-tab]');
    const panels = form.querySelectorAll('[data-capture-panel]');

    const select = (source) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.captureTab === source;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const active = panel.dataset.capturePanel === source;
            panel.hidden = !active;

            panel.querySelectorAll('input, textarea, select').forEach((field) => {
                field.disabled = !active;
            });

            if (!active) {
                panel.querySelectorAll('input[type="file"]').forEach((input) => {
                    input.value = '';
                    input.dispatchEvent(new Event('change'));
                });
            }
        });

        if (sourceInput) {
            sourceInput.value = source;
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => select(tab.dataset.captureTab));
    });

    select(sourceInput?.value || 'text');
}

function initConfirmations() {
    const modalElement = document.getElementById('confirmModal');
    const forms = document.querySelectorAll('form[data-confirm]');

    if (!modalElement || forms.length === 0) {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const title = modalElement.querySelector('[data-confirm-title]');
    const message = modalElement.querySelector('[data-confirm-message]');
    const action = modalElement.querySelector('[data-confirm-action]');
    let pendingForm = null;

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            title.textContent = form.dataset.confirmTitle || 'Are you sure?';
            message.textContent = form.dataset.confirmBody || '';
            action.textContent = form.dataset.confirmLabel || 'Confirm';
            action.className = `btn ${form.dataset.confirmDanger === 'true' ? 'btn-danger' : 'btn-accent'}`;
            modal.show();
        });
    });

    action.addEventListener('click', () => {
        if (pendingForm) {
            pendingForm.dataset.confirmed = 'true';
            pendingForm.requestSubmit();
        }
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        pendingForm = null;
    });
}

function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });
}

function initSearchShortcut() {
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const isTyping = target instanceof HTMLElement
            && (target.matches('input, textarea, select') || target.isContentEditable);

        if (event.key === '/' && !isTyping) {
            const search = document.querySelector('[data-global-search]');
            if (search) {
                event.preventDefault();
                search.focus();
            }
        }
    });
}

function initFileDrops() {
    document.querySelectorAll('[data-file-drop]').forEach((drop) => {
        const input = drop.querySelector('input[type="file"]');
        const fileName = drop.querySelector('[data-file-name]');

        if (!input) {
            return;
        }

        const update = () => {
            if (fileName) {
                fileName.textContent = input.files?.[0]?.name || '';
                fileName.hidden = !input.files?.[0];
            }

            drop.classList.toggle('has-file', Boolean(input.files?.[0]));
        };

        input.addEventListener('change', update);

        ['dragenter', 'dragover'].forEach((name) => {
            drop.addEventListener(name, (event) => {
                event.preventDefault();
                drop.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((name) => {
            drop.addEventListener(name, (event) => {
                event.preventDefault();
                drop.classList.remove('is-dragging');
            });
        });

        drop.addEventListener('drop', (event) => {
            if (event.dataTransfer?.files?.length) {
                input.files = event.dataTransfer.files;
                update();
            }
        });
    });
}

function initFlashMessages() {
    document.querySelectorAll('[data-flash]').forEach((flash) => {
        window.setTimeout(() => {
            flash.classList.add('is-leaving');
            window.setTimeout(() => flash.remove(), 250);
        }, 6000);
    });
}

function initSuggestionChips() {
    document.querySelectorAll('[data-search-suggestion]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const form = document.querySelector('[data-search-form]');
            const input = form?.querySelector('input[name="q"]');

            if (!form || !input) {
                return;
            }

            input.value = chip.dataset.searchSuggestion || chip.textContent.trim();
            form.requestSubmit();
        });
    });
}

function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copy || '';

            try {
                await navigator.clipboard.writeText(text);
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i>';
                window.setTimeout(() => {
                    button.innerHTML = original;
                }, 1600);
            } catch {
                // Clipboard access can be denied; leave the UI unchanged.
            }
        });
    });
}

function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
}

function initLanguageChoices() {
    document.querySelectorAll('.language-selector').forEach((group) => {
        group.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.addEventListener('change', () => {
                group.querySelectorAll('.lang-choice').forEach((choice) => {
                    choice.classList.toggle('is-selected', choice.contains(input) && input.checked);
                });
            });
        });
    });
}
