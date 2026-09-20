const THEME_KEY = 'veloraplus-theme';

const getPreferredTheme = () => {
    const saved = window.localStorage.getItem(THEME_KEY);

    if (saved === 'dark' || saved === 'light') {
        return saved;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = (theme) => {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

    document.querySelectorAll('[data-theme-icon]').forEach((element) => {
        const name = isDark ? 'sun' : 'moon';
        element.hidden = element.dataset.themeIcon !== name;
    });

    document.querySelectorAll('[data-theme-label]').forEach((element) => {
        element.textContent = isDark ? element.dataset.darkLabel : element.dataset.lightLabel;
    });
};

const toggleTheme = () => {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    window.localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
};

const setupTheme = () => {
    applyTheme(getPreferredTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', toggleTheme);
    });

    window.addEventListener('storage', (event) => {
        if (event.key === THEME_KEY) {
            applyTheme(getPreferredTheme());
        }
    });
};

const setupCommandPalette = () => {
    const palette = document.querySelector('[data-command-palette]');
    const input = document.querySelector('[data-command-input]');
    const items = [...document.querySelectorAll('[data-command-item]')];
    const trigger = document.querySelector('[data-command-trigger]');

    if (!palette || !input || !trigger) {
        return;
    }

    let activeIndex = 0;

    const visibleItems = () => items.filter((item) => !item.hidden);

    const setActive = (index) => {
        const visible = visibleItems();

        if (!visible.length) {
            activeIndex = 0;
            return;
        }

        activeIndex = (index + visible.length) % visible.length;

        visible.forEach((item, itemIndex) => {
            item.dataset.active = itemIndex === activeIndex ? 'true' : 'false';
        });
    };

    const close = () => {
        palette.hidden = true;
        document.body.classList.remove('overflow-hidden');
    };

    const open = () => {
        palette.hidden = false;
        document.body.classList.add('overflow-hidden');
        input.value = '';
        items.forEach((item) => {
            item.hidden = false;
            item.dataset.active = 'false';
        });
        setActive(0);
        window.requestAnimationFrame(() => input.focus());
    };

    const filter = () => {
        const query = input.value.trim().toLowerCase();

        items.forEach((item) => {
            item.hidden = query !== '' && !item.dataset.search.includes(query);
        });

        setActive(0);
    };

    trigger.addEventListener('click', open);

    palette.addEventListener('click', (event) => {
        if (event.target === palette || event.target.closest('[data-command-close]')) {
            close();
        }
    });

    input.addEventListener('input', filter);

    input.addEventListener('keydown', (event) => {
        const visible = visibleItems();

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex - 1);
        }

        if (event.key === 'Enter' && visible[activeIndex]) {
            event.preventDefault();
            visible[activeIndex].click();
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    });

    items.forEach((item) => {
        item.addEventListener('mouseenter', () => {
            setActive(visibleItems().indexOf(item));
        });
    });

    window.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();

            if (palette.hidden) {
                open();
            } else {
                close();
            }
        }

        if (event.key === 'Escape' && !palette.hidden) {
            close();
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupTheme();
    setupCommandPalette();
});
