import { initNavigation } from './modules/navigation.js';
import { initRevealAnimations } from './modules/reveal.js';

/* =========================================
   INITIALISATION
   ========================================= */

document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initRevealAnimations();
});

