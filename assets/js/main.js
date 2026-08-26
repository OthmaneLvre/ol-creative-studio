import { initIntro } from './modules/intro.js';
import { initNavigation } from './modules/navigation.js';
import { initRevealAnimations } from './modules/reveal.js';
import './modules/deferred-css.js';

/* =========================================
   INITIALISATION
   ========================================= */

document.addEventListener('DOMContentLoaded', () => {
    initIntro();
    initNavigation();
    initRevealAnimations();
});

