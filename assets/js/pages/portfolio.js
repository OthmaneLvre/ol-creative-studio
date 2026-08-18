/*
|--------------------------------------------------------------------------
| PORTFOLIO — FILTERS
|--------------------------------------------------------------------------
*/

function initPortfolioFilters() {

    const filters = document.querySelector(
        '[data-portfolio-filters]'
    );

    const grid = document.querySelector(
        '[data-portfolio-grid]'
    );

    if (!filters || !grid) {
        return;
    }

    const buttons = filters.querySelectorAll(
        '[data-category]'
    );

    const projects = grid.querySelectorAll(
        '[data-project]'
    );


    const filterProjects = (category) => {

        for (const project of projects) {

            const projectCategory =
                project.dataset.category;

            const shouldDisplay =
                category === 'all' ||
                projectCategory === category;

            project.classList.toggle(
                'is-hidden',
                !shouldDisplay
            );
        }
    };


    for (const button of buttons) {

        button.addEventListener(
            'click',
            () => {

                const category =
                    button.dataset.category;

                for (const currentButton of buttons) {

                    currentButton.classList.toggle(
                        'is-active',
                        currentButton === button
                    );
                }

                filterProjects(category);
            }
        );
    }
}


/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {
        initPortfolioFilters();
    }
);