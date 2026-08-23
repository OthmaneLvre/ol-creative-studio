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
| SERVICES — FAQ
|--------------------------------------------------------------------------
*/

function initFaq() {

    const faq =
        document.querySelector(
            '[data-faq]'
        );

    if (!faq) {
        return;
    }

    const triggers =
        faq.querySelectorAll(
            '[data-faq-trigger]'
        );


    const closeItem = (
        trigger,
        answer
    ) => {

        trigger.setAttribute(
            'aria-expanded',
            'false'
        );

        trigger
            .closest(
                '.services-faq__item'
            )
            ?.classList.remove(
                'is-open'
            );

        answer.style.height =
            `${answer.scrollHeight}px`;

        requestAnimationFrame(() => {
            answer.style.height = '0px';
            answer.style.opacity = '0';
        });

        const handleTransitionEnd = () => {
            answer.hidden = true;
            answer.style.height = '';
            answer.style.opacity = '';

            answer.removeEventListener(
                'transitionend',
                handleTransitionEnd
            );
        };

        answer.addEventListener(
            'transitionend',
            handleTransitionEnd
        );
    };


    const openItem = (
        trigger,
        answer
    ) => {

        answer.hidden = false;

        answer.style.height = '0px';
        answer.style.opacity = '0';

        trigger.setAttribute(
            'aria-expanded',
            'true'
        );

        trigger
            .closest(
                '.services-faq__item'
            )
            ?.classList.add(
                'is-open'
            );

        requestAnimationFrame(() => {
            answer.style.height =
                `${answer.scrollHeight}px`;

            answer.style.opacity = '1';
        });

        const handleTransitionEnd = () => {
            answer.style.height = 'auto';

            answer.removeEventListener(
                'transitionend',
                handleTransitionEnd
            );
        };

        answer.addEventListener(
            'transitionend',
            handleTransitionEnd
        );
    };


    for (const trigger of triggers) {

        trigger.addEventListener(
            'click',
            () => {

                const answerId =
                    trigger.getAttribute(
                        'aria-controls'
                    );

                if (!answerId) {
                    return;
                }

                const answer =
                    document.getElementById(
                        answerId
                    );

                if (!answer) {
                    return;
                }

                const isOpen =
                    trigger.getAttribute(
                        'aria-expanded'
                    ) === 'true';


                /*
                |--------------------------------------------------------------------------
                | Fermer les autres
                |--------------------------------------------------------------------------
                */

                for (
                    const otherTrigger
                    of triggers
                ) {

                    if (
                        otherTrigger
                        === trigger
                    ) {
                        continue;
                    }

                    if (
                        otherTrigger.getAttribute(
                            'aria-expanded'
                        ) !== 'true'
                    ) {
                        continue;
                    }

                    const otherAnswerId =
                        otherTrigger.getAttribute(
                            'aria-controls'
                        );

                    if (!otherAnswerId) {
                        continue;
                    }

                    const otherAnswer =
                        document.getElementById(
                            otherAnswerId
                        );

                    if (otherAnswer) {
                        closeItem(
                            otherTrigger,
                            otherAnswer
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Item courant
                |--------------------------------------------------------------------------
                */

                if (isOpen) {
                    closeItem(
                        trigger,
                        answer
                    );
                } else {
                    openItem(
                        trigger,
                        answer
                    );
                }
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
        initProcessTimeline();
        initFaq();
    }
);