document.addEventListener(
    'DOMContentLoaded',
    () => {
        const stylesheet =
            document.querySelector(
                'link[data-deferred-styles]'
            );

        if (!stylesheet) {
            return;
        }

        stylesheet.media = 'all';
    }
);