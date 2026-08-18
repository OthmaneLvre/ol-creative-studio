/*
|--------------------------------------------------------------------------
| SERVICES — PROCESS TIMELINE
|--------------------------------------------------------------------------
*/

function initProcessTimeline() {

    const timeline = document.querySelector(
        '[data-process-timeline]'
    );

    if (!timeline) {
        return;
    }

    const progress = timeline.querySelector(
        '[data-process-progress]'
    );

    const steps = timeline.querySelectorAll(
        '.services-process__step'
    );

    if (!progress || !steps.length) {
        return;
    }

    const mobileMediaQuery = window.matchMedia(
        '(max-width: 768px)'
    );


    /*
    |--------------------------------------------------------------------------
    | ACTIVATE STEP
    |--------------------------------------------------------------------------
    */

    const activateStep = (activeIndex) => {

        let index = 0;

        for (const step of steps) {

            step.classList.toggle(
                'is-active',
                index === activeIndex
            );

            index += 1;
        }

        const progressValue =
            steps.length > 1
                ? activeIndex / (steps.length - 1)
                : 1;

        progress.style.transform =
            mobileMediaQuery.matches
                ? `scaleY(${progressValue})`
                : `scaleX(${progressValue})`;
    };


    /*
    |--------------------------------------------------------------------------
    | INTERACTIONS
    |--------------------------------------------------------------------------
    */

    let index = 0;

    for (const step of steps) {

        const stepIndex = index;


        /*
        |--------------------------------------------------------------------------
        | DESKTOP — HOVER
        |--------------------------------------------------------------------------
        */

        step.addEventListener(
            'mouseenter',
            () => {

                if (mobileMediaQuery.matches) {
                    return;
                }

                activateStep(stepIndex);
            }
        );


        /*
        |--------------------------------------------------------------------------
        | ACCESSIBILITY — KEYBOARD
        |--------------------------------------------------------------------------
        */

        step.addEventListener(
            'focusin',
            () => {
                activateStep(stepIndex);
            }
        );


        /*
        |--------------------------------------------------------------------------
        | MOBILE — TAP
        |--------------------------------------------------------------------------
        */

        step.addEventListener(
            'click',
            () => {

                if (!mobileMediaQuery.matches) {
                    return;
                }

                activateStep(stepIndex);
            }
        );

        index += 1;
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE
    |--------------------------------------------------------------------------
    */

    const handleBreakpointChange = () => {
        activateStep(0);
    };

    mobileMediaQuery.addEventListener(
        'change',
        handleBreakpointChange
    );


    /*
    |--------------------------------------------------------------------------
    | INITIAL STATE
    |--------------------------------------------------------------------------
    */

    activateStep(0);
}


/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {
        initProcessTimeline();
    }
);