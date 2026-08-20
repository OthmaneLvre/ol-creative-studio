document.addEventListener('DOMContentLoaded', () => {

    document.documentElement.classList.add('admin-js-ready');

    initDeleteConfirmations();
    initTagFields();
    initProjectSlug();

});


/* =========================================================
   DELETE CONFIRMATIONS
   ========================================================= */

function initDeleteConfirmations() {

    const deleteForms = document.querySelectorAll(
        '.admin-delete-form'
    );

    for (const form of deleteForms) {

        form.addEventListener('submit', (event) => {

            const label =
                form.dataset.projectTitle ||
                form.dataset.reviewName ||
                'cet élément';

            const confirmed = window.confirm(
                `Supprimer définitivement « ${label} » ?`
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    }

}

/* =========================================================
   TAG FIELDS
   ========================================================= */

function initTagFields() {

    const fields = document.querySelectorAll(
        '[data-tag-field]'
    );

    for (const field of fields) {

        const input =
            field.querySelector('[data-tag-input]');

        const addButton =
            field.querySelector('[data-tag-add]');

        const list =
            field.querySelector('[data-tag-list]');

        const hidden =
            field.querySelector('[data-tag-hidden]');

        if (
            !input ||
            !addButton ||
            !list ||
            !hidden
        ) {
            continue;
        }

        let tags = parseTags(hidden.value);

        renderTags(
            tags,
            list,
            hidden
        );


        addButton.addEventListener(
            'click',
            () => {

                tags = addTag(
                    tags,
                    input.value
                );

                input.value = '';

                renderTags(
                    tags,
                    list,
                    hidden
                );

                input.focus();
            }
        );


        input.addEventListener(
            'keydown',
            (event) => {

                if (
                    event.key !== 'Enter' &&
                    event.key !== ','
                ) {
                    return;
                }

                event.preventDefault();

                tags = addTag(
                    tags,
                    input.value
                );

                input.value = '';

                renderTags(
                    tags,
                    list,
                    hidden
                );
            }
        );


        list.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '[data-tag-remove]'
                    );

                if (!button) {
                    return;
                }

                const index =
                    Number(
                        button.dataset.tagRemove
                    );

                if (
                    Number.isNaN(index) ||
                    !tags[index]
                ) {
                    return;
                }

                tags.splice(index, 1);

                renderTags(
                    tags,
                    list,
                    hidden
                );
            }
        );

    }

}


function parseTags(value) {

    if (!value) {
        return [];
    }

    try {

        const parsed = JSON.parse(value);

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter(
                (item) =>
                    typeof item === 'string'
            )
            .map(
                (item) => item.trim()
            )
            .filter(Boolean);

    } catch {

        return [];

    }

}


function addTag(tags, value) {

    const cleanValue =
        value.trim();

    if (!cleanValue) {
        return tags;
    }

    const alreadyExists =
        tags.some(
            (tag) =>
                tag.toLowerCase()
                === cleanValue.toLowerCase()
        );

    if (alreadyExists) {
        return tags;
    }

    return [
        ...tags,
        cleanValue
    ];

}


function renderTags(
    tags,
    list,
    hidden
) {

    list.innerHTML = '';

    tags.forEach(
        (tag, index) => {

            const element =
                document.createElement('span');

            element.className =
                'admin-tag';

            const text =
                document.createElement('span');

            text.textContent = tag;

            const button =
                document.createElement('button');

            button.type = 'button';

            button.dataset.tagRemove =
                String(index);

            button.setAttribute(
                'aria-label',
                `Supprimer ${tag}`
            );

            button.textContent = '×';

            element.append(
                text,
                button
            );

            list.appendChild(element);
        }
    );

    hidden.value =
        JSON.stringify(tags);

}


/* =========================================================
   PROJECT SLUG
   ========================================================= */

function initProjectSlug() {

    const titleInput =
        document.querySelector(
            '[data-project-title]'
        );

    const slugInput =
        document.querySelector(
            '[data-project-slug]'
        );

    if (
        !titleInput ||
        !slugInput
    ) {
        return;
    }

    let slugEditedManually =
        slugInput.value.trim() !== '';


    slugInput.addEventListener(
        'input',
        () => {

            slugEditedManually =
                slugInput.value.trim() !== '';
        }
    );


    titleInput.addEventListener(
        'input',
        () => {

            if (slugEditedManually) {
                return;
            }

            slugInput.value =
                slugify(titleInput.value);
        }
    );

}


function slugify(value) {

    return value
        .normalize('NFD')
        .replace(
            /[\u0300-\u036f]/g,
            ''
        )
        .toLowerCase()
        .trim()
        .replace(
            /[^a-z0-9]+/g,
            '-'
        )
        .replace(
            /^-+|-+$/g,
            ''
        );

}
