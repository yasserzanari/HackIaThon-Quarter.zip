/**
 * PédagoLens Landing — Front-end JS
 *
 * - IntersectionObserver pour animations au scroll
 * - Smooth scroll pour les ancres
 * - Counter animation pour les stats
 * - Score bars animation
 * - Nav scroll effect
 * - Parallax orbs
 */
( function () {
    'use strict';

    // =========================================================================
    // 1. INTERSECTION OBSERVER — Fade-in au scroll
    // =========================================================================

    function initScrollAnimations() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '.pl-animate-in' ).forEach( function ( el ) {
                el.classList.add( 'pl-visible' );
            } );
            return;
        }

        var observer = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    entry.target.classList.add( 'pl-visible' );
                    observer.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' } );

        document.querySelectorAll( '.pl-animate-in' ).forEach( function ( el ) {
            observer.observe( el );
        } );
    }

    // =========================================================================
    // 2. COUNTER ANIMATION — Animate numbers from 0 to target
    // =========================================================================

    function animateCounters() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '[data-count-to]' ).forEach( function ( el ) {
                el.textContent = el.getAttribute( 'data-count-to' ) + ( el.getAttribute( 'data-count-suffix' ) || '' );
            } );
            return;
        }

        var counterObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var el = entry.target;
                    var target = parseInt( el.getAttribute( 'data-count-to' ), 10 );
                    var suffix = el.getAttribute( 'data-count-suffix' ) || '';
                    var duration = 2200;
                    var startTime = null;

                    function step( timestamp ) {
                        if ( ! startTime ) startTime = timestamp;
                        var progress = Math.min( ( timestamp - startTime ) / duration, 1 );
                        // Ease out expo for smooth deceleration
                        var eased = 1 - Math.pow( 2, -10 * progress );
                        var current = Math.floor( eased * target );
                        el.textContent = current + suffix;
                        if ( progress < 1 ) {
                            window.requestAnimationFrame( step );
                        } else {
                            el.textContent = target + suffix;
                        }
                    }

                    window.requestAnimationFrame( step );
                    counterObserver.unobserve( el );
                }
            } );
        }, { threshold: 0.3 } );

        document.querySelectorAll( '[data-count-to]' ).forEach( function ( el ) {
            el.textContent = '0';
            counterObserver.observe( el );
        } );
    }

    // =========================================================================
    // 3. SCORE BARS ANIMATION
    // =========================================================================

    function animateScoreBars() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '.pl-score-bar' ).forEach( function ( bar ) {
                bar.classList.add( 'pl-animated' );
            } );
            return;
        }

        var barObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var bars = entry.target.querySelectorAll( '.pl-score-bar' );
                    bars.forEach( function ( bar, index ) {
                        setTimeout( function () {
                            bar.classList.add( 'pl-animated' );
                        }, index * 120 );
                    } );
                    barObserver.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.15 } );

        document.querySelectorAll( '.pl-score-bars' ).forEach( function ( container ) {
            barObserver.observe( container );
        } );
    }

    // =========================================================================
    // 4. SMOOTH SCROLL — Anchor links
    // =========================================================================

    function initSmoothScroll() {
        document.querySelectorAll( 'a[href^="#"]' ).forEach( function ( anchor ) {
            anchor.addEventListener( 'click', function ( e ) {
                var targetId = this.getAttribute( 'href' );
                if ( targetId === '#' ) return;
                var targetEl = document.querySelector( targetId );
                if ( targetEl ) {
                    e.preventDefault();
                    var navHeight = 72;
                    var targetPosition = targetEl.getBoundingClientRect().top + window.pageYOffset - navHeight;
                    window.scrollTo( {
                        top: targetPosition,
                        behavior: 'smooth'
                    } );
                }
            } );
        } );
    }

    // =========================================================================
    // 5. NAV SCROLL EFFECT — Add class on scroll
    // =========================================================================

    function initNavScroll() {
        var nav = document.querySelector( '.pl-landing-nav' );
        if ( ! nav ) return;

        var scrollThreshold = 50;
        var ticking = false;

        function updateNav() {
            if ( window.pageYOffset > scrollThreshold ) {
                nav.classList.add( 'pl-nav-scrolled' );
            } else {
                nav.classList.remove( 'pl-nav-scrolled' );
            }
            ticking = false;
        }

        window.addEventListener( 'scroll', function () {
            if ( ! ticking ) {
                window.requestAnimationFrame( updateNav );
                ticking = true;
            }
        }, { passive: true } );

        // Initial check
        updateNav();
    }

    // =========================================================================
    // 6. PARALLAX ORBS — Subtle mouse-follow effect
    // =========================================================================

    function initParallaxOrbs() {
        var orbs = document.querySelectorAll( '.pl-hero-orb' );
        if ( ! orbs.length ) return;

        // Only on desktop
        if ( window.innerWidth < 768 ) return;

        var ticking = false;

        document.addEventListener( 'mousemove', function ( e ) {
            if ( ticking ) return;
            ticking = true;

            window.requestAnimationFrame( function () {
                var x = ( e.clientX / window.innerWidth - 0.5 ) * 2;
                var y = ( e.clientY / window.innerHeight - 0.5 ) * 2;

                orbs.forEach( function ( orb, i ) {
                    var factor = ( i + 1 ) * 8;
                    orb.style.transform = 'translate(' + ( x * factor ) + 'px, ' + ( y * factor ) + 'px)';
                } );

                ticking = false;
            } );
        }, { passive: true } );
    }

    // =========================================================================
    // 7. STAGGER CHILDREN — Animate children with delay
    // =========================================================================

    function initStaggerAnimations() {
        if ( ! ( 'IntersectionObserver' in window ) ) return;

        var staggerObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var children = entry.target.querySelectorAll( '.pl-animate-in' );
                    children.forEach( function ( child, index ) {
                        child.style.transitionDelay = ( index * 0.1 ) + 's';
                        child.classList.add( 'pl-visible' );
                    } );
                    staggerObserver.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.1 } );

        document.querySelectorAll( '.pl-features-grid, .pl-phase2-grid, .pl-problem-stats' ).forEach( function ( container ) {
            staggerObserver.observe( container );
        } );
    }

    // =========================================================================
    // 8. INIT
    // =========================================================================

    function init() {
        initScrollAnimations();
        animateCounters();
        animateScoreBars();
        initSmoothScroll();
        initNavScroll();
        initParallaxOrbs();
        initStaggerAnimations();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();


// =========================================================================
// 9. LOGIN / REGISTER PAGE (Stitch v2)
// =========================================================================

( function () {
    'use strict';

    function initLoginPage() {
        var loginCard = document.querySelector( '.pl-st-login-page' );
        if ( ! loginCard ) return;

        // -----------------------------------------------------------------
        // Tabs
        // -----------------------------------------------------------------
        var tabs   = loginCard.querySelectorAll( '.pl-st-login-tab' );
        var panels = loginCard.querySelectorAll( '.pl-st-login-panel' );

        tabs.forEach( function ( tab ) {
            tab.addEventListener( 'click', function () {
                var target = this.getAttribute( 'data-tab' );
                tabs.forEach( function ( t ) { t.classList.remove( 'pl-st-login-tab--active' ); } );
                panels.forEach( function ( p ) { p.classList.remove( 'pl-st-login-panel--active' ); } );
                this.classList.add( 'pl-st-login-tab--active' );
                var panel = document.getElementById( 'pl-panel-' + target );
                if ( panel ) panel.classList.add( 'pl-st-login-panel--active' );
            } );
        } );

        // -----------------------------------------------------------------
        // "Créer un compte" link → switch to register tab
        // -----------------------------------------------------------------
        var switchBtns = loginCard.querySelectorAll( '[data-switch-tab]' );
        switchBtns.forEach( function ( btn ) {
            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                var target = this.getAttribute( 'data-switch-tab' );
                tabs.forEach( function ( t ) { t.classList.remove( 'pl-st-login-tab--active' ); } );
                panels.forEach( function ( p ) { p.classList.remove( 'pl-st-login-panel--active' ); } );
                var matchTab = loginCard.querySelector( '.pl-st-login-tab[data-tab="' + target + '"]' );
                if ( matchTab ) matchTab.classList.add( 'pl-st-login-tab--active' );
                var panel = document.getElementById( 'pl-panel-' + target );
                if ( panel ) panel.classList.add( 'pl-st-login-panel--active' );
            } );
        } );

        // -----------------------------------------------------------------
        // Role selection
        // -----------------------------------------------------------------
        var roleCards    = loginCard.querySelectorAll( '.pl-st-role-card' );
        var stepRole     = document.getElementById( 'pl-register-step-role' );
        var stepForm     = document.getElementById( 'pl-register-step-form' );
        var roleInput    = document.getElementById( 'pl-register-role' );
        var backBtn      = loginCard.querySelector( '.pl-register-back' );
        var teacherFields = loginCard.querySelectorAll( '.pl-field-teacher' );
        var studentFields = loginCard.querySelectorAll( '.pl-field-student' );

        roleCards.forEach( function ( card ) {
            card.addEventListener( 'click', function () {
                var role = this.getAttribute( 'data-role' );
                roleInput.value = role;
                stepRole.style.display = 'none';
                stepForm.style.display = 'block';

                teacherFields.forEach( function ( f ) { f.style.display = role === 'teacher' ? 'block' : 'none'; } );
                studentFields.forEach( function ( f ) { f.style.display = role === 'student' ? 'block' : 'none'; } );
            } );
        } );

        if ( backBtn ) {
            backBtn.addEventListener( 'click', function () {
                stepForm.style.display = 'none';
                stepRole.style.display = 'block';
                roleInput.value = '';
            } );
        }

        // -----------------------------------------------------------------
        // Difficulties checkbox → open modal
        // -----------------------------------------------------------------
        var diffCheck = document.getElementById( 'pl-reg-difficulties-check' );
        var diffModal = document.getElementById( 'pl-difficulties-modal' );

        if ( diffCheck && diffModal ) {
            diffCheck.addEventListener( 'change', function () {
                if ( this.checked ) {
                    diffModal.style.display = 'flex';
                }
            } );

            // Close modal
            var closeBtn  = diffModal.querySelector( '.pl-diff-modal-close' );
            var backdrop  = diffModal.querySelector( '.pl-diff-modal-backdrop' );

            function closeModal() {
                diffModal.style.display = 'none';
                // If no difficulties selected, uncheck the trigger
                var anyChecked = diffModal.querySelectorAll( 'input[name="diff[]"]:checked' );
                if ( anyChecked.length === 0 ) {
                    diffCheck.checked = false;
                }
            }

            if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
            if ( backdrop ) backdrop.addEventListener( 'click', closeModal );

            // "Autre" field toggle
            var autreCheckbox = diffModal.querySelector( 'input[value="autre"]' );
            var autreField    = diffModal.querySelector( '.pl-diff-autre-field' );
            if ( autreCheckbox && autreField ) {
                autreCheckbox.addEventListener( 'change', function () {
                    autreField.style.display = this.checked ? 'block' : 'none';
                } );
            }

            // "Plus précisément" button
            var moreBtn  = diffModal.querySelector( '.pl-diff-more-btn' );
            var moreZone = diffModal.querySelector( '.pl-diff-more' );
            if ( moreBtn && moreZone ) {
                moreBtn.addEventListener( 'click', function () {
                    moreZone.style.display = 'block';
                    this.classList.add( 'pl-hidden' );
                } );
            }

            // Save button in modal
            var saveBtn = diffModal.querySelector( '.pl-diff-modal-save' );
            if ( saveBtn ) {
                saveBtn.addEventListener( 'click', function () {
                    diffModal.style.display = 'none';
                } );
            }
        }

        // -----------------------------------------------------------------
        // Helper: show message
        // -----------------------------------------------------------------
        function showMsg( id, text, type ) {
            var el = document.getElementById( id );
            if ( ! el ) return;
            el.className = 'pl-st-login-msg pl-st-login-msg--' + type;
            el.textContent = text;
            el.style.display = 'block';
        }

        // -----------------------------------------------------------------
        // Login form AJAX
        // -----------------------------------------------------------------
        var loginForm = document.getElementById( 'pl-login-form' );
        if ( loginForm ) {
            loginForm.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                var btn   = loginForm.querySelector( '.pl-st-login-submit' );
                var email = loginForm.querySelector( '[name="email"]' ).value.trim();
                var pass  = loginForm.querySelector( '[name="password"]' ).value;
                var nonce = loginForm.querySelector( '[name="_wpnonce"]' ).value;

                if ( ! email || ! pass ) {
                    showMsg( 'pl-login-msg', 'Veuillez remplir tous les champs.', 'error' );
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Connexion…';

                var data = new FormData();
                data.append( 'action', 'pl_login' );
                data.append( '_wpnonce', nonce );
                data.append( 'email', email );
                data.append( 'password', pass );

                fetch( ( window.plFront && plFront.ajaxUrl ) || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    if ( res.success && res.data && res.data.redirect ) {
                        showMsg( 'pl-login-msg', 'Connexion r\u00e9ussie ! Redirection…', 'success' );
                        window.location.href = res.data.redirect;
                    } else {
                        showMsg( 'pl-login-msg', ( res.data && res.data.message ) || 'Erreur de connexion.', 'error' );
                        btn.disabled = false;
                        btn.textContent = 'Se connecter';
                    }
                } )
                .catch( function () {
                    showMsg( 'pl-login-msg', 'Erreur r\u00e9seau.', 'error' );
                    btn.disabled = false;
                    btn.textContent = 'Se connecter';
                } );
            } );
        }

        // -----------------------------------------------------------------
        // Register form AJAX
        // -----------------------------------------------------------------
        var registerForm = document.getElementById( 'pl-register-form' );
        if ( registerForm ) {
            registerForm.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                var btn   = registerForm.querySelector( '.pl-st-login-submit' );
                var nonce = registerForm.querySelector( '[name="_wpnonce"]' ).value;
                var role  = registerForm.querySelector( '[name="role"]' ).value;
                var name  = registerForm.querySelector( '[name="display_name"]' ).value.trim();
                var email = registerForm.querySelector( '[name="email"]' ).value.trim();
                var pass  = registerForm.querySelector( '[name="password"]' ).value;
                var pass2 = registerForm.querySelector( '[name="password_confirm"]' ).value;
                var inst  = registerForm.querySelector( '[name="institute"]' );
                var institute = inst ? inst.value.trim() : '';

                // Client-side validation
                if ( ! name || ! email || ! pass || ! pass2 ) {
                    showMsg( 'pl-register-msg', 'Veuillez remplir tous les champs obligatoires.', 'error' );
                    return;
                }
                if ( pass.length < 6 ) {
                    showMsg( 'pl-register-msg', 'Le mot de passe doit contenir au moins 6 caract\u00e8res.', 'error' );
                    return;
                }
                if ( pass !== pass2 ) {
                    showMsg( 'pl-register-msg', 'Les mots de passe ne correspondent pas.', 'error' );
                    return;
                }

                // Collect difficulties from modal
                var difficulties = [];
                if ( diffModal && role === 'student' ) {
                    var checked = diffModal.querySelectorAll( 'input[name="diff[]"]:checked' );
                    var contextText = '';
                    var contextEl = document.getElementById( 'pl-diff-context' );
                    if ( contextEl ) contextText = contextEl.value.trim();

                    checked.forEach( function ( cb ) {
                        var val = cb.value;
                        if ( val === 'autre' ) {
                            var autreText = document.getElementById( 'pl-diff-autre-text' );
                            difficulties.push( { key: 'autre', text: autreText ? autreText.value.trim() : '', context: contextText } );
                        } else {
                            difficulties.push( val );
                        }
                    } );

                    // If context but no "autre", attach to first or standalone
                    if ( contextText && difficulties.length > 0 && ! difficulties.some( function(d) { return typeof d === 'object'; } ) ) {
                        difficulties.push( { key: 'context', text: contextText, context: contextText } );
                    }
                }

                btn.disabled = true;
                btn.textContent = 'Cr\u00e9ation…';

                var data = new FormData();
                data.append( 'action', 'pl_register' );
                data.append( '_wpnonce', nonce );
                data.append( 'role', role );
                data.append( 'display_name', name );
                data.append( 'email', email );
                data.append( 'password', pass );
                data.append( 'password_confirm', pass2 );
                data.append( 'institute', institute );
                data.append( 'difficulties', JSON.stringify( difficulties ) );

                fetch( ( window.plFront && plFront.ajaxUrl ) || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    if ( res.success && res.data && res.data.redirect ) {
                        showMsg( 'pl-register-msg', 'Compte cr\u00e9\u00e9 ! Redirection…', 'success' );
                        window.location.href = res.data.redirect;
                    } else {
                        showMsg( 'pl-register-msg', ( res.data && res.data.message ) || 'Erreur lors de l\'inscription.', 'error' );
                        btn.disabled = false;
                        btn.textContent = 'Cr\u00e9er mon compte';
                    }
                } )
                .catch( function () {
                    showMsg( 'pl-register-msg', 'Erreur r\u00e9seau.', 'error' );
                    btn.disabled = false;
                    btn.textContent = 'Cr\u00e9er mon compte';
                } );
            } );
        }
    }

    // Init on DOM ready
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initLoginPage );
    } else {
        initLoginPage();
    }

} )();

// =========================================================================
// 10. STITCH DESIGN SYSTEM — Animations & Interactions (v2.0)
// =========================================================================

( function () {
    'use strict';

    // Avoid double-init
    if ( window.__plStitchInit ) return;
    window.__plStitchInit = true;

    // =====================================================================
    // UTILS
    // =====================================================================

    var rAF = window.requestAnimationFrame || function ( cb ) { return setTimeout( cb, 16 ); };
    var prefersReducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

    // =====================================================================
    // A. INTERSECTION OBSERVER — Fade-in / Slide-up on sections
    //    Targets: [data-stitch-animate], .stitch-fade-in, .stitch-slide-up
    // =====================================================================

    function initStitchScrollReveal() {
        if ( prefersReducedMotion ) {
            document.querySelectorAll( '[data-stitch-animate], .stitch-fade-in, .stitch-slide-up' ).forEach( function ( el ) {
                el.style.opacity = '1';
                el.style.transform = 'none';
            } );
            return;
        }

        // Inject base styles once
        var styleId = 'stitch-reveal-css';
        if ( ! document.getElementById( styleId ) ) {
            var css = document.createElement( 'style' );
            css.id = styleId;
            css.textContent =
                '[data-stitch-animate], .stitch-fade-in, .stitch-slide-up {' +
                '  opacity: 0; transition: opacity 0.7s cubic-bezier(.22,1,.36,1), transform 0.7s cubic-bezier(.22,1,.36,1); }' +
                '.stitch-slide-up, [data-stitch-animate="slide-up"] { transform: translateY(32px); }' +
                '[data-stitch-animate="fade-in"] { transform: translateY(0); }' +
                '.stitch--visible { opacity: 1 !important; transform: translateY(0) !important; }';
            document.head.appendChild( css );
        }

        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '[data-stitch-animate], .stitch-fade-in, .stitch-slide-up' ).forEach( function ( el ) {
                el.classList.add( 'stitch--visible' );
            } );
            return;
        }

        var revealObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var delay = parseInt( entry.target.getAttribute( 'data-stitch-delay' ), 10 ) || 0;
                    setTimeout( function () {
                        entry.target.classList.add( 'stitch--visible' );
                    }, delay );
                    revealObserver.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' } );

        document.querySelectorAll( '[data-stitch-animate], .stitch-fade-in, .stitch-slide-up' ).forEach( function ( el ) {
            revealObserver.observe( el );
        } );
    }

    // =====================================================================
    // B. SMOOTH SCROLL — Internal anchors (enhanced, coexists with §4)
    // =====================================================================

    function initStitchSmoothScroll() {
        document.addEventListener( 'click', function ( e ) {
            var link = e.target.closest( 'a[href*="#"]' );
            if ( ! link ) return;

            var href = link.getAttribute( 'href' );
            // Only handle same-page anchors
            if ( ! href || href === '#' ) return;
            var hashIndex = href.indexOf( '#' );
            if ( hashIndex === -1 ) return;

            var hash = href.substring( hashIndex );
            // If href has a path part, make sure it matches current page
            var pathPart = href.substring( 0, hashIndex );
            if ( pathPart && pathPart !== window.location.pathname && pathPart !== '.' ) return;

            var target;
            try { target = document.querySelector( hash ); } catch ( ex ) { return; }
            if ( ! target ) return;

            e.preventDefault();

            var nav = document.querySelector( '.pl-landing-nav, header.fixed, header.sticky' );
            var navH = nav ? nav.offsetHeight + 16 : 80;
            var top = target.getBoundingClientRect().top + window.pageYOffset - navH;

            window.scrollTo( { top: top, behavior: prefersReducedMotion ? 'auto' : 'smooth' } );

            // Update URL hash without jump
            if ( history.pushState ) {
                history.pushState( null, '', hash );
            }

            // Close mobile menu if open
            var mobileMenu = document.querySelector( '.stitch-mobile-menu.stitch-mobile-menu--open' );
            if ( mobileMenu ) closeMobileMenu();
        } );
    }

    // =====================================================================
    // C. PARALLAX HERO — Subtle vertical shift on scroll
    // =====================================================================

    function initStitchHeroParallax() {
        var hero = document.querySelector( '.pl-hero, [data-stitch-parallax], .stitch-hero-parallax' );
        if ( ! hero || prefersReducedMotion || window.innerWidth < 768 ) return;

        var parallaxEls = hero.querySelectorAll( '.pl-hero-orb, .stitch-parallax-layer, [data-parallax-speed]' );
        // Also apply a subtle shift to the hero background itself
        var heroInner = hero.querySelector( '.stitch-hero-inner' ) || hero;

        var ticking = false;

        function onScroll() {
            if ( ticking ) return;
            ticking = true;
            rAF( function () {
                var scrollY = window.pageYOffset;
                var heroRect = hero.getBoundingClientRect();
                // Only animate when hero is in viewport
                if ( heroRect.bottom > 0 ) {
                    // Background parallax (slow)
                    heroInner.style.transform = 'translate3d(0,' + ( scrollY * 0.15 ) + 'px,0)';

                    // Individual layers
                    parallaxEls.forEach( function ( el ) {
                        var speed = parseFloat( el.getAttribute( 'data-parallax-speed' ) ) || 0.08;
                        el.style.transform = 'translate3d(0,' + ( scrollY * speed ) + 'px,0)';
                    } );
                }
                ticking = false;
            } );
        }

        window.addEventListener( 'scroll', onScroll, { passive: true } );
    }

    // =====================================================================
    // D. COUNT-UP ANIMATION — Animated counters (Stitch-enhanced)
    //    Targets: [data-stitch-count]
    // =====================================================================

    function initStitchCountUp() {
        var counters = document.querySelectorAll( '[data-stitch-count]' );
        if ( ! counters.length ) return;

        if ( prefersReducedMotion || ! ( 'IntersectionObserver' in window ) ) {
            counters.forEach( function ( el ) {
                el.textContent = el.getAttribute( 'data-stitch-count' ) + ( el.getAttribute( 'data-stitch-suffix' ) || '' );
            } );
            return;
        }

        var countObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( ! entry.isIntersecting ) return;
                var el = entry.target;
                var end = parseFloat( el.getAttribute( 'data-stitch-count' ) );
                var suffix = el.getAttribute( 'data-stitch-suffix' ) || '';
                var prefix = el.getAttribute( 'data-stitch-prefix' ) || '';
                var decimals = ( String( end ).split( '.' )[1] || '' ).length;
                var duration = parseInt( el.getAttribute( 'data-stitch-duration' ), 10 ) || 2000;
                var start = 0;
                var startTime = null;

                function step( ts ) {
                    if ( ! startTime ) startTime = ts;
                    var progress = Math.min( ( ts - startTime ) / duration, 1 );
                    // Ease-out cubic
                    var eased = 1 - Math.pow( 1 - progress, 3 );
                    var current = start + ( end - start ) * eased;
                    el.textContent = prefix + current.toFixed( decimals ) + suffix;
                    if ( progress < 1 ) {
                        rAF( step );
                    } else {
                        el.textContent = prefix + end.toFixed( decimals ) + suffix;
                    }
                }

                rAF( step );
                countObserver.unobserve( el );
            } );
        }, { threshold: 0.4 } );

        counters.forEach( function ( el ) {
            el.textContent = ( el.getAttribute( 'data-stitch-prefix' ) || '' ) + '0' + ( el.getAttribute( 'data-stitch-suffix' ) || '' );
            countObserver.observe( el );
        } );
    }

    // =====================================================================
    // E. MOBILE MENU TOGGLE — Hamburger
    //    Trigger: .stitch-hamburger / [data-stitch-toggle="menu"]
    //    Menu:    .stitch-mobile-menu
    // =====================================================================

    var closeMobileMenu; // hoisted for smooth-scroll access

    function initStitchMobileMenu() {
        var toggle = document.querySelector( '.stitch-hamburger, [data-stitch-toggle="menu"]' );
        var menu   = document.querySelector( '.stitch-mobile-menu' );
        if ( ! toggle || ! menu ) return;

        var isOpen = false;

        // Inject overlay style
        var styleId = 'stitch-mobile-menu-css';
        if ( ! document.getElementById( styleId ) ) {
            var css = document.createElement( 'style' );
            css.id = styleId;
            css.textContent =
                '.stitch-mobile-menu { ' +
                '  position: fixed; top: 0; right: 0; bottom: 0; width: 80vw; max-width: 320px;' +
                '  background: #fff; z-index: 9999; transform: translateX(100%);' +
                '  transition: transform 0.35s cubic-bezier(.22,1,.36,1);' +
                '  box-shadow: -4px 0 24px rgba(0,0,0,0.08); overflow-y: auto; padding: 2rem; }' +
                '.stitch-mobile-menu--open { transform: translateX(0); }' +
                '.stitch-mobile-backdrop { position: fixed; inset: 0; background: rgba(0,35,111,0.25);' +
                '  z-index: 9998; opacity: 0; pointer-events: none;' +
                '  transition: opacity 0.3s ease; }' +
                '.stitch-mobile-backdrop--visible { opacity: 1; pointer-events: auto; }' +
                '.stitch-hamburger-line { display: block; width: 24px; height: 2px;' +
                '  background: currentColor; transition: transform 0.3s ease, opacity 0.3s ease; }' +
                '.stitch-hamburger--active .stitch-hamburger-line:nth-child(1) { transform: translateY(7px) rotate(45deg); }' +
                '.stitch-hamburger--active .stitch-hamburger-line:nth-child(2) { opacity: 0; }' +
                '.stitch-hamburger--active .stitch-hamburger-line:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }';
            document.head.appendChild( css );
        }

        // Create backdrop if missing
        var backdrop = document.querySelector( '.stitch-mobile-backdrop' );
        if ( ! backdrop ) {
            backdrop = document.createElement( 'div' );
            backdrop.className = 'stitch-mobile-backdrop';
            document.body.appendChild( backdrop );
        }

        function open() {
            isOpen = true;
            menu.classList.add( 'stitch-mobile-menu--open' );
            backdrop.classList.add( 'stitch-mobile-backdrop--visible' );
            toggle.classList.add( 'stitch-hamburger--active' );
            toggle.setAttribute( 'aria-expanded', 'true' );
            document.body.style.overflow = 'hidden';
        }

        function close() {
            isOpen = false;
            menu.classList.remove( 'stitch-mobile-menu--open' );
            backdrop.classList.remove( 'stitch-mobile-backdrop--visible' );
            toggle.classList.remove( 'stitch-hamburger--active' );
            toggle.setAttribute( 'aria-expanded', 'false' );
            document.body.style.overflow = '';
        }

        closeMobileMenu = close; // expose for smooth-scroll

        toggle.addEventListener( 'click', function () {
            isOpen ? close() : open();
        } );

        backdrop.addEventListener( 'click', close );

        // Close on Escape
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && isOpen ) close();
        } );
    }

    // =====================================================================
    // F. SCORE BARS — Progressive fill animation (Stitch style)
    //    Targets: [data-stitch-bar] with data-stitch-bar-value="75"
    // =====================================================================

    function initStitchScoreBars() {
        var bars = document.querySelectorAll( '[data-stitch-bar]' );
        if ( ! bars.length ) return;

        // Inject styles
        var styleId = 'stitch-bar-css';
        if ( ! document.getElementById( styleId ) ) {
            var css = document.createElement( 'style' );
            css.id = styleId;
            css.textContent =
                '[data-stitch-bar] .stitch-bar-fill {' +
                '  width: 0; transition: width 1.2s cubic-bezier(.22,1,.36,1); }' +
                '[data-stitch-bar].stitch-bar--animated .stitch-bar-fill {' +
                '  width: var(--stitch-bar-w); }';
            document.head.appendChild( css );
        }

        if ( prefersReducedMotion || ! ( 'IntersectionObserver' in window ) ) {
            bars.forEach( function ( bar ) {
                bar.classList.add( 'stitch-bar--animated' );
                var fill = bar.querySelector( '.stitch-bar-fill' );
                if ( fill ) {
                    var val = bar.getAttribute( 'data-stitch-bar-value' ) || '0';
                    fill.style.setProperty( '--stitch-bar-w', val + '%' );
                    fill.style.width = val + '%';
                }
            } );
            return;
        }

        var barObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( ! entry.isIntersecting ) return;
                var bar = entry.target;
                var fill = bar.querySelector( '.stitch-bar-fill' );
                var val = bar.getAttribute( 'data-stitch-bar-value' ) || '0';
                if ( fill ) {
                    fill.style.setProperty( '--stitch-bar-w', val + '%' );
                }
                // Stagger if inside a group
                var delay = parseInt( bar.getAttribute( 'data-stitch-bar-delay' ), 10 ) || 0;
                setTimeout( function () {
                    bar.classList.add( 'stitch-bar--animated' );
                }, delay );
                barObserver.unobserve( bar );
            } );
        }, { threshold: 0.2 } );

        bars.forEach( function ( bar ) {
            barObserver.observe( bar );
        } );
    }

    // =====================================================================
    // G. GLASS CARD HOVER EFFECTS
    //    Targets: .glass-card, .stitch-glass-card
    //    Effect: tilt + glow on mouse move (desktop only)
    // =====================================================================

    function initStitchGlassHover() {
        if ( prefersReducedMotion || window.innerWidth < 768 ) return;

        var cards = document.querySelectorAll( '.glass-card, .stitch-glass-card' );
        if ( ! cards.length ) return;

        // Inject styles
        var styleId = 'stitch-glass-css';
        if ( ! document.getElementById( styleId ) ) {
            var css = document.createElement( 'style' );
            css.id = styleId;
            css.textContent =
                '.glass-card, .stitch-glass-card {' +
                '  transition: transform 0.25s cubic-bezier(.22,1,.36,1), box-shadow 0.25s ease; }' +
                '.stitch-glass-glow {' +
                '  position: absolute; width: 180px; height: 180px; border-radius: 50%;' +
                '  background: radial-gradient(circle, rgba(112,58,226,0.12) 0%, transparent 70%);' +
                '  pointer-events: none; opacity: 0; transition: opacity 0.3s ease;' +
                '  transform: translate(-50%,-50%); z-index: 0; }';
            document.head.appendChild( css );
        }

        cards.forEach( function ( card ) {
            // Ensure relative positioning
            var pos = window.getComputedStyle( card ).position;
            if ( pos === 'static' ) card.style.position = 'relative';
            card.style.overflow = 'hidden';

            // Create glow element
            var glow = document.createElement( 'div' );
            glow.className = 'stitch-glass-glow';
            card.appendChild( glow );

            card.addEventListener( 'mousemove', function ( e ) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                var cx = rect.width / 2;
                var cy = rect.height / 2;

                // Subtle tilt (max ±3deg)
                var rotateX = ( ( y - cy ) / cy ) * -3;
                var rotateY = ( ( x - cx ) / cx ) * 3;
                card.style.transform = 'perspective(600px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) scale(1.02)';
                card.style.boxShadow = '0 12px 40px rgba(0,35,111,0.12)';

                // Move glow
                glow.style.left = x + 'px';
                glow.style.top = y + 'px';
                glow.style.opacity = '1';
            } );

            card.addEventListener( 'mouseleave', function () {
                card.style.transform = '';
                card.style.boxShadow = '';
                glow.style.opacity = '0';
            } );
        } );
    }

    // =====================================================================
    // H. SIDEBAR TOGGLE — Dashboard pages
    //    Trigger: .stitch-sidebar-toggle / [data-stitch-toggle="sidebar"]
    //    Sidebar: .stitch-sidebar / .pl-dashboard-sidebar
    //    Main:    .stitch-main / .pl-dashboard-main
    // =====================================================================

    function initStitchSidebarToggle() {
        var toggle  = document.querySelector( '.stitch-sidebar-toggle, [data-stitch-toggle="sidebar"]' );
        var sidebar = document.querySelector( '.stitch-sidebar, .pl-dashboard-sidebar' );
        var main    = document.querySelector( '.stitch-main, .pl-dashboard-main' );
        if ( ! sidebar ) return;

        // Inject styles
        var styleId = 'stitch-sidebar-css';
        if ( ! document.getElementById( styleId ) ) {
            var css = document.createElement( 'style' );
            css.id = styleId;
            css.textContent =
                '.stitch-sidebar, .pl-dashboard-sidebar {' +
                '  transition: transform 0.35s cubic-bezier(.22,1,.36,1), width 0.35s cubic-bezier(.22,1,.36,1); }' +
                '.stitch-sidebar--collapsed { transform: translateX(-100%); width: 0 !important;' +
                '  overflow: hidden; padding: 0 !important; opacity: 0; }' +
                '@media (min-width: 768px) {' +
                '  .stitch-sidebar--collapsed { transform: none; width: 64px !important;' +
                '    opacity: 1; padding: 1.5rem 0.5rem !important; }' +
                '  .stitch-sidebar--collapsed .stitch-sidebar-label,' +
                '  .stitch-sidebar--collapsed .pl-sidebar-label { display: none; }' +
                '}' +
                '.stitch-main, .pl-dashboard-main {' +
                '  transition: margin-left 0.35s cubic-bezier(.22,1,.36,1); }';
            document.head.appendChild( css );
        }

        var collapsed = false;

        function toggleSidebar() {
            collapsed = ! collapsed;
            sidebar.classList.toggle( 'stitch-sidebar--collapsed', collapsed );
            if ( toggle ) {
                toggle.setAttribute( 'aria-expanded', String( ! collapsed ) );
            }
        }

        if ( toggle ) {
            toggle.addEventListener( 'click', toggleSidebar );
        }

        // On mobile, close sidebar when clicking outside
        if ( window.innerWidth < 768 ) {
            document.addEventListener( 'click', function ( e ) {
                if ( collapsed ) return;
                if ( ! sidebar.contains( e.target ) && ( ! toggle || ! toggle.contains( e.target ) ) ) {
                    toggleSidebar();
                }
            } );
        }
    }

    // =====================================================================
    // I. STAGGER CHILDREN — Stitch variant
    //    Container: [data-stitch-stagger]
    //    Children get incremental delay
    // =====================================================================

    function initStitchStagger() {
        if ( prefersReducedMotion || ! ( 'IntersectionObserver' in window ) ) return;

        var containers = document.querySelectorAll( '[data-stitch-stagger]' );
        if ( ! containers.length ) return;

        var staggerObs = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( ! entry.isIntersecting ) return;
                var gap = parseInt( entry.target.getAttribute( 'data-stitch-stagger' ), 10 ) || 100;
                var children = entry.target.querySelectorAll( '[data-stitch-animate], .stitch-fade-in, .stitch-slide-up' );
                children.forEach( function ( child, i ) {
                    setTimeout( function () {
                        child.classList.add( 'stitch--visible' );
                    }, i * gap );
                } );
                staggerObs.unobserve( entry.target );
            } );
        }, { threshold: 0.08 } );

        containers.forEach( function ( c ) { staggerObs.observe( c ); } );
    }

    // =====================================================================
    // J. HOVER LIFT — Cards with .stitch-hover-lift
    //    Adds translateY(-8px) + shadow on hover via CSS injection
    // =====================================================================

    function initStitchHoverLift() {
        var styleId = 'stitch-hover-lift-css';
        if ( ! document.getElementById( styleId ) ) {
            var css = document.createElement( 'style' );
            css.id = styleId;
            css.textContent =
                '.stitch-hover-lift {' +
                '  transition: transform 0.4s cubic-bezier(.22,1,.36,1), box-shadow 0.4s ease; }' +
                '.stitch-hover-lift:hover {' +
                '  transform: translateY(-8px);' +
                '  box-shadow: 0 20px 60px rgba(0,35,111,0.10); }';
            document.head.appendChild( css );
        }
    }

    // =====================================================================
    // INIT
    // =====================================================================

    function initStitchHeaderScroll() {
        var header = document.querySelector('.plx-header');
        if (!header) return;
        var onScroll = function() {
            if (window.scrollY > 20) {
                header.classList.add('plx-header--scrolled');
            } else {
                header.classList.remove('plx-header--scrolled');
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    function initStitch() {
        initStitchScrollReveal();
        initStitchSmoothScroll();
        initStitchHeroParallax();
        initStitchCountUp();
        initStitchMobileMenu();
        initStitchScoreBars();
        initStitchGlassHover();
        initStitchSidebarToggle();
        initStitchStagger();
        initStitchHoverLift();
        initStitchHeaderScroll();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initStitch );
    } else {
        initStitch();
    }

} )();

// =========================================================================
// 11. EXTENDED INTERACTIONS — Fade-in, Tabs, Modals, Toasts, AJAX helpers
//     (v2.2.0 — completes Stitch design system interactions)
// =========================================================================

(function ($) {
    'use strict';

    // Avoid double-init
    if (window.__plExtendedInit) return;
    window.__plExtendedInit = true;

    // =====================================================================
    // A. INTERSECTION OBSERVER — .pl-fade-in support
    //    Adds visibility class when elements scroll into view.
    //    Works alongside existing .pl-animate-in from section 1.
    // =====================================================================

    function initFadeIn() {
        var els = document.querySelectorAll('.pl-fade-in');
        if (!els.length) return;

        // Inject CSS once
        var styleId = 'pl-fade-in-css';
        if (!document.getElementById(styleId)) {
            var css = document.createElement('style');
            css.id = styleId;
            css.textContent =
                '.pl-fade-in {' +
                '  opacity: 0; transform: translateY(24px);' +
                '  transition: opacity 0.6s cubic-bezier(.22,1,.36,1), transform 0.6s cubic-bezier(.22,1,.36,1); }' +
                '.pl-fade-in.pl-fade-in--visible {' +
                '  opacity: 1; transform: translateY(0); }';
            document.head.appendChild(css);
        }

        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReduced || !('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('pl-fade-in--visible'); });
            return;
        }

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var delay = parseInt(entry.target.getAttribute('data-fade-delay'), 10) || 0;
                    setTimeout(function () {
                        entry.target.classList.add('pl-fade-in--visible');
                    }, delay);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        els.forEach(function (el) { obs.observe(el); });
    }

    // =====================================================================
    // B. STAGGER ANIMATION — Cards lists
    //    Container: [data-pl-stagger] or .pl-stagger-cards
    //    Children: .pl-fade-in inside the container get incremental delay
    // =====================================================================

    function initStaggerCards() {
        var containers = document.querySelectorAll('[data-pl-stagger], .pl-stagger-cards');
        if (!containers.length || !('IntersectionObserver' in window)) return;

        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) return;

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var gap = parseInt(entry.target.getAttribute('data-pl-stagger'), 10) || 120;
                var children = entry.target.querySelectorAll('.pl-fade-in');
                children.forEach(function (child, i) {
                    setTimeout(function () {
                        child.classList.add('pl-fade-in--visible');
                    }, i * gap);
                });
                obs.unobserve(entry.target);
            });
        }, { threshold: 0.08 });

        containers.forEach(function (c) { obs.observe(c); });
    }

    // =====================================================================
    // C. TABS SWITCHING — Generic tab component
    //    Trigger: [data-pl-tab]  (value = panel id suffix)
    //    Panel:   [data-pl-tab-panel]
    //    Group:   closest [data-pl-tabs] or .pl-tabs
    // =====================================================================

    function initTabs() {
        document.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-pl-tab]');
            if (!tab) return;

            var group = tab.closest('[data-pl-tabs], .pl-tabs');
            if (!group) return;

            var target = tab.getAttribute('data-pl-tab');

            // Deactivate all tabs in group
            group.querySelectorAll('[data-pl-tab]').forEach(function (t) {
                t.classList.remove('pl-tab--active');
                t.setAttribute('aria-selected', 'false');
            });

            // Hide all panels in group
            group.querySelectorAll('[data-pl-tab-panel]').forEach(function (p) {
                p.classList.remove('pl-tab-panel--active');
                p.style.display = 'none';
            });

            // Activate clicked tab
            tab.classList.add('pl-tab--active');
            tab.setAttribute('aria-selected', 'true');

            // Show target panel
            var panel = group.querySelector('[data-pl-tab-panel="' + target + '"]');
            if (panel) {
                panel.classList.add('pl-tab-panel--active');
                panel.style.display = '';
            }
        });
    }

    // =====================================================================
    // D. MODAL OPEN / CLOSE — Generic modal component
    //    Open:  [data-pl-modal-open="modalId"]
    //    Close: [data-pl-modal-close] or .pl-modal-backdrop click
    //    Modal: .pl-modal#modalId or [data-pl-modal="modalId"]
    // =====================================================================

    function initModals() {
        // Inject base styles
        var styleId = 'pl-modal-css';
        if (!document.getElementById(styleId)) {
            var css = document.createElement('style');
            css.id = styleId;
            css.textContent =
                '.pl-modal { display: none; position: fixed; inset: 0; z-index: 10000;' +
                '  align-items: center; justify-content: center; }' +
                '.pl-modal--open { display: flex; }' +
                '.pl-modal-backdrop { position: absolute; inset: 0; background: rgba(0,35,111,0.3);' +
                '  backdrop-filter: blur(4px); }' +
                '.pl-modal-content { position: relative; z-index: 1; background: #fff;' +
                '  border-radius: 1.5rem; padding: 2rem; max-width: 560px; width: 90%;' +
                '  max-height: 85vh; overflow-y: auto;' +
                '  box-shadow: 0 20px 60px rgba(0,35,111,0.15);' +
                '  animation: plModalIn 0.3s cubic-bezier(.22,1,.36,1); }' +
                '@keyframes plModalIn {' +
                '  from { opacity: 0; transform: translateY(16px) scale(0.97); }' +
                '  to { opacity: 1; transform: translateY(0) scale(1); } }';
            document.head.appendChild(css);
        }

        // Open
        document.addEventListener('click', function (e) {
            var opener = e.target.closest('[data-pl-modal-open]');
            if (opener) {
                e.preventDefault();
                var id = opener.getAttribute('data-pl-modal-open');
                var modal = document.getElementById(id) || document.querySelector('[data-pl-modal="' + id + '"]');
                if (modal) {
                    modal.classList.add('pl-modal--open');
                    document.body.style.overflow = 'hidden';
                }
                return;
            }

            // Close via button
            var closer = e.target.closest('[data-pl-modal-close]');
            if (closer) {
                var modal = closer.closest('.pl-modal');
                if (modal) {
                    modal.classList.remove('pl-modal--open');
                    document.body.style.overflow = '';
                }
                return;
            }

            // Close via backdrop click
            if (e.target.classList.contains('pl-modal-backdrop')) {
                var modal = e.target.closest('.pl-modal');
                if (modal) {
                    modal.classList.remove('pl-modal--open');
                    document.body.style.overflow = '';
                }
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var open = document.querySelector('.pl-modal--open');
                if (open) {
                    open.classList.remove('pl-modal--open');
                    document.body.style.overflow = '';
                }
            }
        });
    }

    // =====================================================================
    // E. TOAST NOTIFICATIONS — Success / Error / Info
    //    API: window.plToast(message, type, duration)
    //    type: 'success' | 'error' | 'info'  (default: 'info')
    // =====================================================================

    function initToasts() {
        // Inject styles
        var styleId = 'pl-toast-css';
        if (!document.getElementById(styleId)) {
            var css = document.createElement('style');
            css.id = styleId;
            css.textContent =
                '.pl-toast-container { position: fixed; top: 24px; right: 24px; z-index: 11000;' +
                '  display: flex; flex-direction: column; gap: 10px; pointer-events: none; }' +
                '.pl-toast { pointer-events: auto; display: flex; align-items: center; gap: 10px;' +
                '  padding: 14px 20px; border-radius: 12px; font-size: 14px; font-weight: 600;' +
                '  color: #fff; min-width: 280px; max-width: 420px;' +
                '  box-shadow: 0 8px 32px rgba(0,0,0,0.12);' +
                '  animation: plToastIn 0.35s cubic-bezier(.22,1,.36,1); }' +
                '.pl-toast--success { background: #059669; }' +
                '.pl-toast--error   { background: #dc2626; }' +
                '.pl-toast--info    { background: #00236f; }' +
                '.pl-toast--exit { animation: plToastOut 0.3s ease forwards; }' +
                '@keyframes plToastIn {' +
                '  from { opacity: 0; transform: translateX(40px); }' +
                '  to   { opacity: 1; transform: translateX(0); } }' +
                '@keyframes plToastOut {' +
                '  from { opacity: 1; transform: translateX(0); }' +
                '  to   { opacity: 0; transform: translateX(40px); } }';
            document.head.appendChild(css);
        }

        // Create container
        var container = document.querySelector('.pl-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'pl-toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        var icons = {
            success: '✓',
            error: '✕',
            info: 'ℹ'
        };

        /**
         * Show a toast notification.
         * @param {string} message
         * @param {string} [type='info']  — 'success' | 'error' | 'info'
         * @param {number} [duration=4000] — ms before auto-dismiss
         */
        window.plToast = function (message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;

            var toast = document.createElement('div');
            toast.className = 'pl-toast pl-toast--' + type;
            toast.setAttribute('role', 'status');
            toast.innerHTML = '<span>' + (icons[type] || '') + '</span><span>' + message + '</span>';
            container.appendChild(toast);

            // Auto-dismiss
            var timer = setTimeout(function () { dismiss(); }, duration);

            // Click to dismiss
            toast.addEventListener('click', function () {
                clearTimeout(timer);
                dismiss();
            });

            function dismiss() {
                toast.classList.add('pl-toast--exit');
                setTimeout(function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 300);
            }
        };

        // PlToast compatibility shim (capital P, .success/.error/.info methods)
        window.PlToast = {
            success: function(msg, dur) { window.plToast(msg, 'success', dur); },
            error:   function(msg, dur) { window.plToast(msg, 'error',   dur); },
            info:    function(msg, dur) { window.plToast(msg, 'info',    dur); }
        };
    }

    // =====================================================================
    // F. AJAX HELPER — plAjax(action, data) → Promise
    //    Wraps jQuery.post with nonce from plFront.nonces
    //    Also provides button loading state management.
    // =====================================================================

    function initAjaxHelper() {
        var frontData = window.plFront || {};
        var ajaxUrl = frontData.ajaxUrl || '/wp-admin/admin-ajax.php';
        var nonces = frontData.nonces || {};

        /**
         * Send an AJAX request to WordPress.
         *
         * @param {string} action  — WP AJAX action name (e.g. 'pl_analyze')
         * @param {Object} [data]  — Additional data to send
         * @returns {jQuery.Deferred|Promise}
         */
        window.plAjax = function (action, data) {
            data = data || {};
            data.action = action;

            // Auto-attach nonce: try action-specific, then generic patterns
            if (!data._wpnonce && !data.nonce) {
                // Try matching nonce key from action name (e.g. pl_dashboard_xxx → nonces.dashboard)
                var parts = action.replace(/^pl_/, '').split('_');
                var nonceKey = parts[0]; // first segment after pl_
                if (nonces[nonceKey]) {
                    data._wpnonce = nonces[nonceKey];
                } else if (nonces.settings) {
                    // Fallback to settings nonce
                    data._wpnonce = nonces.settings;
                }
            }

            if ($ && $.post) {
                return $.post(ajaxUrl, data);
            }

            // Fallback to fetch if jQuery not available
            var formData = new FormData();
            Object.keys(data).forEach(function (key) {
                formData.append(key, typeof data[key] === 'object' ? JSON.stringify(data[key]) : data[key]);
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (r) { return r.json(); });
        };

        /**
         * Set a button to loading state.
         * @param {HTMLElement|jQuery} btn
         * @param {string} [loadingText] — text to show while loading
         */
        window.plBtnLoading = function (btn, loadingText) {
            var el = btn instanceof $ ? btn[0] : btn;
            if (!el) return;
            el._plOrigText = el.textContent;
            el._plOrigHTML = el.innerHTML;
            el.disabled = true;
            el.classList.add('pl-btn--loading');
            el.innerHTML = '<span class="pl-spinner"></span> ' + (loadingText || frontData.i18n && frontData.i18n.sending || 'Chargement…');
        };

        /**
         * Reset a button from loading state.
         * @param {HTMLElement|jQuery} btn
         * @param {string} [text] — override text (otherwise restores original)
         */
        window.plBtnReset = function (btn, text) {
            var el = btn instanceof $ ? btn[0] : btn;
            if (!el) return;
            el.disabled = false;
            el.classList.remove('pl-btn--loading');
            if (text) {
                el.textContent = text;
            } else if (el._plOrigHTML) {
                el.innerHTML = el._plOrigHTML;
            }
        };

        // Inject spinner CSS
        var styleId = 'pl-btn-loading-css';
        if (!document.getElementById(styleId)) {
            var css = document.createElement('style');
            css.id = styleId;
            css.textContent =
                '.pl-btn--loading { opacity: 0.7; cursor: wait; }' +
                '.pl-spinner { display: inline-block; width: 14px; height: 14px;' +
                '  border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;' +
                '  border-radius: 50%; animation: plSpin 0.6s linear infinite;' +
                '  vertical-align: middle; }' +
                '@keyframes plSpin { to { transform: rotate(360deg); } }';
            document.head.appendChild(css);
        }
    }

    // =====================================================================
    // G. SCORE BAR — Dynamic color based on score value
    //    Targets: .pl-score-bar[data-score] or [data-pl-score-color]
    //    Colors: ≥80 green, ≥60 blue, ≥40 yellow, ≥20 orange, <20 red
    // =====================================================================

    function initScoreBarColors() {
        var bars = document.querySelectorAll('.pl-score-bar[data-score], [data-pl-score-color]');
        if (!bars.length) return;

        var colorMap = [
            { min: 80, color: '#059669', cls: 'pl-score--green' },
            { min: 60, color: '#2563eb', cls: 'pl-score--blue' },
            { min: 40, color: '#d97706', cls: 'pl-score--yellow' },
            { min: 20, color: '#ea580c', cls: 'pl-score--orange' },
            { min: 0,  color: '#dc2626', cls: 'pl-score--red' }
        ];

        function getScoreColor(score) {
            for (var i = 0; i < colorMap.length; i++) {
                if (score >= colorMap[i].min) return colorMap[i];
            }
            return colorMap[colorMap.length - 1];
        }

        bars.forEach(function (bar) {
            var score = parseInt(bar.getAttribute('data-score') || bar.getAttribute('data-pl-score-color'), 10);
            if (isNaN(score)) return;

            var info = getScoreColor(score);
            var fill = bar.querySelector('.pl-score-bar-fill, .stitch-bar-fill, .pl-bar-fill');

            if (fill) {
                fill.style.backgroundColor = info.color;
                fill.style.width = score + '%';
            }

            bar.classList.add(info.cls);
        });

        // Also handle bars animated by IntersectionObserver (section 3)
        // Re-apply color after animation triggers
        if ('IntersectionObserver' in window) {
            var colorObs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var container = entry.target;
                    container.querySelectorAll('.pl-score-bar[data-score]').forEach(function (bar) {
                        var score = parseInt(bar.getAttribute('data-score'), 10);
                        if (isNaN(score)) return;
                        var info = getScoreColor(score);
                        var fill = bar.querySelector('.pl-score-bar-fill, .stitch-bar-fill, .pl-bar-fill');
                        if (fill) fill.style.backgroundColor = info.color;
                        bar.classList.add(info.cls);
                    });
                    colorObs.unobserve(container);
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.pl-score-bars').forEach(function (c) {
                colorObs.observe(c);
            });
        }
    }

    // =====================================================================
    // INIT
    // =====================================================================

    function initExtended() {
        initFadeIn();
        initStaggerCards();
        initTabs();
        initModals();
        initToasts();
        initAjaxHelper();
        initScoreBarColors();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExtended);
    } else {
        initExtended();
    }

})(window.jQuery || window.$ || null);


// =========================================================================
// SETTINGS PAGE — AJAX save + tone buttons + cancel
// =========================================================================

( function () {
    'use strict';

    function initSettingsPage() {
        var form = document.getElementById( 'pl-settings-form' );
        if ( ! form ) return;

        var msgEl     = document.getElementById( 'pl-settings-msg' );
        var cancelBtn = document.getElementById( 'pl-settings-cancel' );
        var saveBtn   = form.querySelector( '.pl-st-settings-btn-save' );

        // Store initial form state for cancel
        var initialState = new FormData( form );

        // ── Tone buttons ──
        var toneBtns  = form.querySelectorAll( '.pl-st-tone-btn' );
        var toneInput = document.getElementById( 'pl-ai-tone' );

        toneBtns.forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                toneBtns.forEach( function ( b ) { b.classList.remove( 'pl-st-tone-btn--active' ); } );
                this.classList.add( 'pl-st-tone-btn--active' );
                if ( toneInput ) toneInput.value = this.getAttribute( 'data-tone' );
            } );
        } );

        // ── Show message helper ──
        function showMsg( text, type ) {
            if ( ! msgEl ) return;
            msgEl.className = 'pl-st-settings-msg ' + ( type === 'ok' ? 'pl-msg-ok' : 'pl-msg-err' );
            msgEl.textContent = text;
            msgEl.style.display = 'block';
            msgEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
            if ( type === 'ok' ) {
                setTimeout( function () { msgEl.style.display = 'none'; }, 4000 );
            }
        }

        // ── Cancel button ──
        if ( cancelBtn ) {
            cancelBtn.addEventListener( 'click', function () {
                window.location.reload();
            } );
        }

        // ── Form submit via AJAX ──
        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();

            saveBtn.disabled = true;
            saveBtn.textContent = 'Enregistrement…';
            if ( msgEl ) msgEl.style.display = 'none';

            var data = new FormData( form );
            data.append( 'action', 'pl_save_settings' );

            var ajaxUrl = ( window.plFront && plFront.ajaxUrl ) || '/wp-admin/admin-ajax.php';

            fetch( ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( res ) {
                if ( res.success ) {
                    showMsg( res.data.message || 'Paramètres enregistrés.', 'ok' );
                } else {
                    showMsg( ( res.data && res.data.message ) || 'Erreur lors de la sauvegarde.', 'err' );
                }
                saveBtn.disabled = false;
                saveBtn.textContent = 'Sauvegarder les paramètres';
            } )
            .catch( function () {
                showMsg( 'Erreur réseau.', 'err' );
                saveBtn.disabled = false;
                saveBtn.textContent = 'Sauvegarder les paramètres';
            } );
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initSettingsPage );
    } else {
        initSettingsPage();
    }

} )();

/* ===== TASK 16: Course CRUD AJAX ===== */
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        // Helper: toggle submit button loading state
        function setBtnLoading(btn, loading) {
            if (!btn) return;
            var btnText = btn.querySelector('.pl-btn-text');
            var btnLoader = btn.querySelector('.pl-btn-loader');
            btn.disabled = loading;
            if (btnText) btnText.style.display = loading ? 'none' : '';
            if (btnLoader) btnLoader.style.display = loading ? 'inline-flex' : 'none';
        }

        // Helper: AJAX post
        function plAjaxPost(action, fd, onSuccess, onError) {
            fd.append('action', action);
            fd.append('nonce', (window.plFront && plFront.nonce) || '');
            fetch((window.plFront && plFront.ajaxurl) || '/wp-admin/admin-ajax.php', {
                method: 'POST', body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    if (onSuccess) onSuccess(res.data);
                } else {
                    if (onError) onError(res.data);
                }
            })
            .catch(function() {
                if (typeof plToast === 'function') plToast('Erreur réseau', 'error');
            });
        }

        // ── Create course form ──
        var createForm = document.getElementById('pl-create-course-form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = createForm.querySelector('.pl-btn-submit');
                setBtnLoading(btn, true);
                var fd = new FormData(createForm);
                plAjaxPost('pl_create_course_front', fd,
                    function(data) {
                        setBtnLoading(btn, false);
                        if (typeof plToast === 'function') plToast(data.message, 'success');
                        setTimeout(function() { location.reload(); }, 800);
                    },
                    function(data) {
                        setBtnLoading(btn, false);
                        if (typeof plToast === 'function') plToast(data.message || 'Erreur', 'error');
                    }
                );
            });
        }

        // ── Edit course form ──
        var editForm = document.getElementById('pl-edit-course-form');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = editForm.querySelector('.pl-btn-submit');
                setBtnLoading(btn, true);
                var fd = new FormData(editForm);
                plAjaxPost('pl_update_course_front', fd,
                    function(data) {
                        setBtnLoading(btn, false);
                        if (typeof plToast === 'function') plToast(data.message, 'success');
                        setTimeout(function() { location.reload(); }, 800);
                    },
                    function(data) {
                        setBtnLoading(btn, false);
                        if (typeof plToast === 'function') plToast(data.message || 'Erreur', 'error');
                    }
                );
            });
        }

        // ── Delete course buttons ──
        document.addEventListener('click', function(e) {
            var delBtn = e.target.closest('.pl-course-card-delete-btn');
            if (!delBtn) return;
            e.preventDefault();
            var courseId = delBtn.getAttribute('data-course-id');
            var courseTitle = delBtn.getAttribute('data-course-title') || 'ce cours';
            if (!confirm('Supprimer "' + courseTitle + '" et tous ses projets ? Cette action est irréversible.')) return;

            var fd = new FormData();
            fd.append('course_id', courseId);
            plAjaxPost('pl_delete_course_front', fd,
                function(data) {
                    if (typeof plToast === 'function') plToast(data.message, 'success');
                    setTimeout(function() { location.reload(); }, 800);
                },
                function(data) {
                    if (typeof plToast === 'function') plToast(data.message || 'Erreur', 'error');
                }
            );
        });

        // ── Edit button: populate edit modal ──
        document.addEventListener('click', function(e) {
            var editBtn = e.target.closest('.pl-course-card-edit-btn');
            if (!editBtn) return;
            e.preventDefault();
            var modal = document.querySelector('[data-pl-modal="edit-course"]');
            if (!modal) return;
            document.getElementById('pl-edit-course-id').value = editBtn.getAttribute('data-course-id') || '';
            document.getElementById('pl-edit-course-title').value = editBtn.getAttribute('data-course-title') || '';
            document.getElementById('pl-edit-course-code').value = editBtn.getAttribute('data-course-code') || '';
            document.getElementById('pl-edit-course-session').value = editBtn.getAttribute('data-course-session') || '';
            document.getElementById('pl-edit-course-desc').value = editBtn.getAttribute('data-course-desc') || '';
            document.getElementById('pl-edit-course-type').value = editBtn.getAttribute('data-course-type') || 'magistral';
            modal.classList.add('pl-modal--open');
            document.body.style.overflow = 'hidden';
        });
    });
})();

/* ===== TASK 17: Project Creation AJAX ===== */
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        // Open project modal and set course_id
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.pl-btn-create-project');
            if (!btn) return;
            e.preventDefault();
            var modal = document.querySelector('[data-pl-modal="create-project"]');
            if (!modal) return;
            var courseIdInput = document.getElementById('pl-project-course-id');
            if (courseIdInput) courseIdInput.value = btn.getAttribute('data-course-id') || '';
            var courseLabel = document.getElementById('pl-project-course-label');
            if (courseLabel) courseLabel.textContent = btn.getAttribute('data-course-title') || '';
            modal.classList.add('pl-modal--open');
            document.body.style.overflow = 'hidden';
        });

        // File input display
        var fileInput = document.getElementById('pl-project-files');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var list = document.getElementById('pl-project-file-list');
                if (!list) return;
                var names = [];
                for (var i = 0; i < fileInput.files.length; i++) {
                    names.push(fileInput.files[i].name);
                }
                list.textContent = names.length ? '📎 ' + names.join(', ') : '';
            });
        }

        // Submit project form
        var form = document.getElementById('pl-create-project-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = form.querySelector('.pl-btn-submit');
                var btnText = btn.querySelector('.pl-btn-text');
                var btnLoader = btn.querySelector('.pl-btn-loader');
                btn.disabled = true;
                if (btnText) btnText.style.display = 'none';
                if (btnLoader) btnLoader.style.display = 'inline-flex';

                var fd = new FormData(form);
                fd.append('action', 'pl_create_project_front');
                fd.append('nonce', (window.plFront && plFront.nonce) || '');

                fetch((window.plFront && plFront.ajaxurl) || '/wp-admin/admin-ajax.php', {
                    method: 'POST', body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    btn.disabled = false;
                    if (btnText) btnText.style.display = '';
                    if (btnLoader) btnLoader.style.display = 'none';
                    if (res.success) {
                        if (window.PlToast) PlToast.success(res.data.message);
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        if (window.PlToast) PlToast.error(res.data.message || 'Erreur');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    if (btnText) btnText.style.display = '';
                    if (btnLoader) btnLoader.style.display = 'none';
                    if (window.PlToast) PlToast.error('Erreur réseau');
                });
            });
        }
    });
})();


/* ============================================================
   TASK 37 — Workbench Course Selector Accordion
   ============================================================ */
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            var header = e.target.closest('.pl-wb-selector-course-header');
            if (!header) return;
            // Don't toggle if clicking a link inside
            if (e.target.closest('a')) return;

            var course = header.closest('.pl-wb-selector-course');
            if (!course) return;

            var seances = course.querySelector('.pl-wb-selector-seances');
            if (!seances) return;

            var isOpen = course.classList.contains('pl-wb-course-open');

            // Close all others
            document.querySelectorAll('.pl-wb-selector-course.pl-wb-course-open').forEach(function(c) {
                c.classList.remove('pl-wb-course-open');
                var s = c.querySelector('.pl-wb-selector-seances');
                if (s) s.style.display = 'none';
            });

            // Toggle current
            if (!isOpen) {
                course.classList.add('pl-wb-course-open');
                seances.style.display = '';
            }
        });
    });
})();


/* ============================================================
   TASK 36 — Séances Modal WOW
   ============================================================ */
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {

        var typeIcons = {
            magistral: '🎓', exercice: '📝',
            travail_equipe: '👥', evaluation: '📋'
        };
        var typeLabels = {
            magistral: 'Magistral', exercice: 'Exercice',
            travail_equipe: "Travail d'équipe", evaluation: 'Évaluation'
        };

        function openSeancesModal(card) {
            var courseId    = card.getAttribute('data-course-id');
            var courseTitle = card.getAttribute('data-course-title') || 'Cours';
            var courseType  = card.getAttribute('data-course-type') || 'magistral';
            var projects    = [];
            try { projects = JSON.parse(card.getAttribute('data-course-projects') || '[]'); } catch(e) {}

            // Populate header
            var iconEl  = document.getElementById('pl-seances-modal-icon');
            var titleEl = document.getElementById('pl-seances-modal-title');
            var badgeEl = document.getElementById('pl-seances-modal-badge');
            var bodyEl  = document.getElementById('pl-seances-modal-body');
            var createBtn = document.getElementById('pl-seances-modal-create-btn');

            if (iconEl)  iconEl.textContent = typeIcons[courseType] || '📚';
            if (titleEl) titleEl.textContent = courseTitle;
            if (badgeEl) badgeEl.textContent = projects.length + ' séance(s)';

            // Populate body
            if (bodyEl) {
                if (projects.length === 0) {
                    bodyEl.innerHTML =
                        '<div class="pl-seances-empty">' +
                        '  <div class="pl-seances-empty-icon">📭</div>' +
                        '  <h3>Aucune séance</h3>' +
                        '  <p>Créez votre première séance pour ce cours.</p>' +
                        '</div>';
                } else {
                    var html = '<div class="pl-seances-grid">';
                    projects.forEach(function(p) {
                        var icon = typeIcons[p.type] || '📄';
                        var label = typeLabels[p.type] || p.type;
                        html +=
                            '<a href="' + p.url + '" class="pl-seance-card">' +
                            '  <div class="pl-seance-card-top">' +
                            '    <span class="pl-seance-card-icon">' + icon + '</span>' +
                            '    <h4>' + escHtml(p.title) + '</h4>' +
                            '  </div>' +
                            '  <span class="pl-badge pl-type-' + p.type + '">' + escHtml(label) + '</span>' +
                            '  <span class="pl-seance-card-arrow">→</span>' +
                            '</a>';
                    });
                    html += '</div>';
                    bodyEl.innerHTML = html;
                }
            }

            // Wire create button
            if (createBtn) {
                createBtn.onclick = function() {
                    // Close seances modal
                    var seancesModal = document.querySelector('[data-pl-modal="view-seances"]');
                    if (seancesModal) seancesModal.classList.remove('pl-modal--open');
                    // Open create-project modal with course info
                    var projModal = document.querySelector('[data-pl-modal="create-project"]');
                    if (projModal) {
                        var cid = document.getElementById('pl-project-course-id');
                        if (cid) cid.value = courseId;
                        var clbl = document.getElementById('pl-project-course-label');
                        if (clbl) clbl.textContent = courseTitle;
                        projModal.classList.add('pl-modal--open');
                    }
                };
            }

            // Open modal
            var modal = document.querySelector('[data-pl-modal="view-seances"]');
            if (modal) {
                modal.classList.add('pl-modal--open');
                document.body.style.overflow = 'hidden';
            }
        }

        function escHtml(str) {
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        // Click on "Voir les séances" button
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.pl-btn-open-seances');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var card = btn.closest('.pl-course-card-front');
                if (card) openSeancesModal(card);
                return;
            }

            // Click on course card itself (but not on action buttons)
            var card = e.target.closest('.pl-course-card-clickable');
            if (card) {
                // Don't trigger if clicking edit, delete, history, or create-project buttons
                if (e.target.closest('.pl-course-card-edit-btn') ||
                    e.target.closest('.pl-course-card-delete-btn') ||
                    e.target.closest('.pl-btn-create-project') ||
                    e.target.closest('.pl-wb-btn-outline') ||
                    e.target.closest('a[href]')) {
                    return;
                }
                e.preventDefault();
                openSeancesModal(card);
            }
        });
    });
})();


/* ============================================================
   Agent IA Léa — Chat Interface JS
   ============================================================ */
(function() {
    'use strict';

    function initLeaChat() {
        var messagesEl = document.getElementById('pl-lea-messages');
        var inputEl    = document.getElementById('pl-lea-input');
        var sendBtn    = document.getElementById('pl-lea-send');
        var profileSelect  = document.getElementById('pl-lea-profile-select');
        var activeProfileEl = document.getElementById('pl-lea-active-profile');
        var toggleBtn  = document.getElementById('pl-lea-toggle-analytics');
        var analyticsPanel = document.getElementById('pl-lea-analytics');

        if (!messagesEl || !inputEl || !sendBtn) return;

        var currentProfile = profileSelect ? profileSelect.options[profileSelect.selectedIndex].text : 'Visuel-Spatial';
        var currentSlug    = profileSelect ? profileSelect.value : 'visuel-spatial';
        var isTyping = false;

        // Profile responses based on student type
        var profileResponses = {
            'visuel-spatial': {
                style: 'Je comprends mieux avec des schémas et des images.',
                greeting: 'un étudiant visuel-spatial',
                traits: 'J\'ai besoin de voir les concepts pour les comprendre. Les diagrammes, les cartes mentales et les couleurs m\'aident beaucoup.'
            },
            'auditif-verbal': {
                style: 'J\'apprends mieux en écoutant et en discutant.',
                greeting: 'un étudiant auditif-verbal',
                traits: 'Les explications orales et les discussions de groupe sont mes meilleurs outils d\'apprentissage.'
            },
            'kinesthesique': {
                style: 'J\'ai besoin de pratiquer pour comprendre.',
                greeting: 'un étudiant kinesthésique',
                traits: 'Les exercices pratiques, les manipulations et les mises en situation concrètes sont essentiels pour moi.'
            },
            'tdah': {
                style: 'J\'ai du mal à rester concentré longtemps.',
                greeting: 'un étudiant avec TDAH',
                traits: 'J\'ai besoin de contenu court, structuré et interactif. Les longues lectures me perdent rapidement.'
            },
            'allophone': {
                style: 'Le français n\'est pas ma langue maternelle.',
                greeting: 'un étudiant allophone',
                traits: 'J\'ai parfois du mal avec le vocabulaire technique. Des définitions simples et du contexte m\'aident beaucoup.'
            },
            'anxieux': {
                style: 'Le stress d\'évaluation me bloque souvent.',
                greeting: 'un étudiant anxieux',
                traits: 'J\'ai besoin de renforcement positif et de consignes très claires pour me sentir en confiance.'
            }
        };

        // Toggle analytics panel (mobile)
        if (toggleBtn && analyticsPanel) {
            toggleBtn.addEventListener('click', function() {
                analyticsPanel.classList.toggle('open');
            });
        }

        // Profile selection via dropdown
        if (profileSelect) {
            profileSelect.addEventListener('change', function() {
                currentProfile = profileSelect.options[profileSelect.selectedIndex].text;
                currentSlug = profileSelect.value;
                if (activeProfileEl) {
                    activeProfileEl.textContent = 'Profil : ' + currentProfile;
                }
                addMessage('bot', 'Profil chang\u00e9 ! Je suis maintenant ' + (profileResponses[currentSlug] ? profileResponses[currentSlug].greeting : currentProfile) + '. ' + (profileResponses[currentSlug] ? profileResponses[currentSlug].traits : '') + ' Posez-moi une question !');
            });
        }

        // Send message
        function sendMessage() {
            var text = inputEl.value.trim();
            if (!text || isTyping) return;

            addMessage('user', text);
            inputEl.value = '';
            inputEl.style.height = 'auto';

            // Show typing indicator
            isTyping = true;
            sendBtn.disabled = true;
            var typingEl = document.createElement('div');
            typingEl.className = 'pl-lea-msg pl-lea-msg--typing';
            typingEl.innerHTML = '<div class="pl-lea-typing-dots"><span></span><span></span><span></span></div>';
            messagesEl.appendChild(typingEl);
            messagesEl.scrollTop = messagesEl.scrollHeight;

            // Simulate AI response (mock mode)
            var delay = 1000 + Math.random() * 2000;
            setTimeout(function() {
                messagesEl.removeChild(typingEl);
                isTyping = false;
                sendBtn.disabled = false;

                var response = generateResponse(text, currentSlug);
                addMessage('bot', response);
            }, delay);
        }

        sendBtn.addEventListener('click', sendMessage);
        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Auto-resize textarea
        inputEl.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 96) + 'px';
        });

        function addMessage(type, text) {
            var msg = document.createElement('div');
            msg.className = 'pl-lea-msg pl-lea-msg--' + type;
            msg.innerHTML = text;
            messagesEl.appendChild(msg);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function generateResponse(question, slug) {
            var profile = profileResponses[slug] || profileResponses['visuel-spatial'];
            var q = question.toLowerCase();

            // Context-aware mock responses
            if (q.indexOf('comprend') !== -1 || q.indexOf('compris') !== -1 || q.indexOf('clair') !== -1) {
                return '<strong>Réaction (' + currentProfile + ') :</strong> ' + profile.style + ' Pour ce concept, j\'aurais besoin que vous me l\'expliquiez différemment. Peut-être avec un exemple concret ?';
            }
            if (q.indexOf('exercice') !== -1 || q.indexOf('pratique') !== -1 || q.indexOf('exemple') !== -1) {
                return '<strong>Réaction (' + currentProfile + ') :</strong> Les exercices pratiques m\'aident beaucoup ! ' + profile.style + ' Est-ce que vous pourriez me donner un cas concret à résoudre étape par étape ?';
            }
            if (q.indexOf('examen') !== -1 || q.indexOf('évaluation') !== -1 || q.indexOf('test') !== -1 || q.indexOf('note') !== -1) {
                return '<strong>Réaction (' + currentProfile + ') :</strong> Quand je pense à l\'évaluation... ' + profile.style + ' J\'aimerais savoir exactement ce qui sera évalué et comment me préparer efficacement.';
            }
            if (q.indexOf('cours') !== -1 || q.indexOf('matière') !== -1 || q.indexOf('sujet') !== -1) {
                return '<strong>Réaction (' + currentProfile + ') :</strong> Pour bien suivre ce cours, ' + profile.style.toLowerCase() + ' Pourriez-vous me donner un aperçu de la structure du cours ?';
            }
            if (q.indexOf('aide') !== -1 || q.indexOf('difficulté') !== -1 || q.indexOf('problème') !== -1 || q.indexOf('bloqué') !== -1) {
                return '<strong>Réaction (' + currentProfile + ') :</strong> Je rencontre parfois des difficultés parce que ' + profile.style.toLowerCase() + ' Quand je suis bloqué, j\'ai besoin qu\'on me guide pas à pas sans me donner directement la réponse.';
            }

            // Default response
            return '<strong>Réaction (' + currentProfile + ') :</strong> ' + profile.style + ' Concernant votre question, je dirais que j\'ai besoin de plus de contexte pour bien comprendre. Pourriez-vous reformuler ou me donner un exemple ?';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLeaChat);
    } else {
        initLeaChat();
    }
})();
